<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Core\Auth\Role;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Services\WelcomeService;
use Sesam\Tests\Support\IntegrationTestCase;

/**
 * Popup de bienvenue après la connexion : visite guidée la première fois, « bon retour » ensuite,
 * version console pour l'équipe SESAM. Une seule fois par connexion.
 */
final class WelcomeTest extends IntegrationTestCase
{
    public function testFirstLoginShowsTheGuidedTourOnlyOnce(): void
    {
        $kernel = $this->kernel();
        $salon = $this->tenant(Sector::RESERVATION);
        $this->loginAs($kernel, [Role::TENANT_OWNER], $salon);

        $first = html_entity_decode($kernel->handle(new Request('GET', '/espace'))->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('data-welcome', $first);
        $this->assertStringContainsString('wl-first', $first);
        $this->assertStringContainsString('3 gestes pour bien démarrer', $first);
        $this->assertStringContainsString('/reservation/prestations', $first);
        $this->assertStringContainsString('welcome.js', $first);

        $again = $kernel->handle(new Request('GET', '/espace'))->content();
        $this->assertStringNotContainsString('data-welcome', $again, 'Une seule fois par connexion.');
    }

    public function testReturningShopOwnerSeesTodaysFigures(): void
    {
        $kernel = $this->kernel();
        $shop = $this->tenant(Sector::BOUTIQUE);
        $email = $this->user([Role::TENANT_OWNER], $shop);
        $this->pdo()->prepare('UPDATE users SET last_login_at = ? WHERE email = ?')->execute([gmdate('Y-m-d H:i:s', strtotime('-2 days')), $email]);
        $this->insert('boutique_orders', ['tenant_id' => $shop, 'order_number' => 'P20-1', 'status' => 'new', 'source' => 'whatsapp', 'customer_name' => 'Client p20', 'total_minor' => 5000]);
        $this->insert('boutique_orders', ['tenant_id' => $shop, 'order_number' => 'P20-2', 'status' => 'new', 'source' => 'whatsapp', 'customer_name' => 'Client p20', 'total_minor' => 7000]);

        $kernel->handle($this->post('/connexion', ['_csrf' => $this->csrf($kernel, '/connexion'), 'email' => $email, 'password' => self::PASSWORD]));
        $page = html_entity_decode($kernel->handle(new Request('GET', '/espace'))->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('wl-back', $page);
        $this->assertStringContainsString('Quel plaisir de vous revoir', $page);
        $this->assertStringContainsString('data-count="2"', $page, 'Deux commandes à traiter.');
        $this->assertStringContainsString('commandes à traiter', $page);
    }

    public function testTeamGetsTheConsoleVersion(): void
    {
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::ADMIN], null);
        $page = html_entity_decode($kernel->handle(new Request('GET', '/espace'))->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('wl-team', $page);
        $this->assertStringContainsString('Ouvrir la console', $page);
        $this->assertStringContainsString('essais finissent sous 3 j', $page);
    }

    public function testServicePagesShowTheirDedicatedPopupAfterTwoSeconds(): void
    {
        $kernel = $this->kernel();
        $pages = [
            '/services/reservation' => ['service-reservation', 'reservation', 'Créer mon salon', '/t/demo-reservation'],
            '/services/boutique' => ['service-boutique', 'boutique', 'Ouvrir ma boutique', '/t/demo-boutique'],
            '/services/immo' => ['service-immo', 'immo', 'Essayer Sesam Immo', '/t/demo-loyer'],
            '/services/agence' => ['service-agence', 'agence', 'Demander mon devis', '/devis'],
        ];
        foreach ($pages as $path => [$key, $variant, $cta, $link]) {
            $html = html_entity_decode($kernel->handle(new Request('GET', $path))->content(), ENT_QUOTES, 'UTF-8');
            $this->assertStringContainsString('data-variant="' . $variant . '"', $html, $path);
            $this->assertStringContainsString('data-once="' . $key . '"', $html, $path);
            $this->assertStringContainsString('data-delay="2000"', $html, $path);
            $this->assertStringContainsString('data-once-storage="session"', $html, $path);
            $this->assertStringContainsString($cta, $html, $path);
            $this->assertStringContainsString($link . '"', $html, $path);
            $this->assertSame(1, substr_count($html, 'data-welcome '), $path . ' : un seul popup');
        }
        $contact = $kernel->handle(new Request('GET', '/contact'))->content();
        $this->assertStringNotContainsString('data-welcome', $contact, 'Pas de popup sur le formulaire de contact.');
    }

    public function testHomeDrawsAPopupAtRandomAmongSevenVariants(): void
    {
        $kernel = $this->kernel();
        $seen = [];
        for ($i = 0; $i < 60 && count($seen) < 4; $i++) {
            $html = $kernel->handle(new Request('GET', '/'))->content();
            $this->assertSame(1, preg_match('/data-variant="([a-z]+)"/', $html, $match));
            $this->assertContains($match[1], ['welcome', 'offer', 'demo', 'reservation', 'boutique', 'immo', 'agence']);
            $this->assertStringContainsString('data-once="accueil"', $html);
            $seen[$match[1]] = true;
        }
        $this->assertGreaterThanOrEqual(4, count($seen), 'Les visiteurs ne voient pas toujours le même popup.');
    }

    public function testGreetingFollowsTheTimeOfDay(): void
    {
        $this->kernel();
        $service = WelcomeService::current();
        $this->assertInstanceOf(WelcomeService::class, $service);
        $this->assertSame('Aïssatou', WelcomeService::firstName('aïssatou diop'));
        $user = new \Sesam\Models\User(['id' => 1, 'full_name' => 'Moussa Ba', 'tenant_id' => null]);
        $this->assertSame('Bonjour', $service->payload([], $user, null, 0, '2026-10-27 08:00:00')['greeting']);
        $this->assertSame('Bon après-midi', $service->payload([], $user, null, 0, '2026-10-27 14:00:00')['greeting']);
        $night = $service->payload([], $user, null, 0, '2026-10-27 21:30:00');
        $this->assertSame('Bonsoir', $night['greeting']);
        $this->assertSame('night', $night['moment']);
        $this->assertSame('Mardi 27 octobre', $night['date']);
    }
}
