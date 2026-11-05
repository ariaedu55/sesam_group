<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Core\Auth\Role;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\Slug;
use Sesam\Tests\Support\IntegrationTestCase;

/**
 * Retours de test du 4 novembre : lien public modifiable (l'ancien redirige), page « Envoyer » sans jargon technique.
 */
final class TesterFeedbackTest extends IntegrationTestCase
{
    public function testSlugIsBuiltFromAnyName(): void
    {
        $this->assertSame('boutique-eclat-de-dakar', Slug::from('  Boutique Éclat de Dakar ! '));
        $this->assertSame('chez-awa-co', Slug::from('Chez Awa & Co'));
        $this->assertSame('', Slug::from('!!!'));
    }

    public function testOwnerChangesThePublicLinkAndOldLinksRedirect(): void
    {
        $tenantId = $this->tenant(Sector::BOUTIQUE);
        $oldSlug = $this->slug($tenantId);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);

        $settings = html_entity_decode($this->get($kernel, '/boutique/parametres')->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Changer le lien', $settings);

        $wanted = 'Ma Belle Boutique ' . bin2hex(random_bytes(3));
        $response = $this->submit($kernel, '/lien-public', ['slug' => $wanted], '/boutique/parametres');
        $this->assertSame('/boutique/parametres#lien', $response->headers()['Location'] ?? '');
        $newSlug = Slug::from($wanted);
        $this->assertSame($newSlug, $this->slug($tenantId));

        // Les liens déjà partagés redirigent vers le nouveau, page et paramètres compris.
        $old = $kernel->handle(new Request('GET', '/t/' . $oldSlug . '/produit/7', ['ref' => 'wa']));
        $this->assertSame(301, $old->status());
        $this->assertSame('/t/' . $newSlug . '/produit/7?ref=wa', $old->headers()['Location'] ?? '');
        $this->assertSame(200, $kernel->handle(new Request('GET', '/t/' . $newSlug))->status());

        // Un autre commerce ne peut prendre ni le nouveau lien ni l'ancien (qui redirige encore).
        $this->logout($kernel);
        $otherId = $this->tenant(Sector::BOUTIQUE);
        $this->loginAs($kernel, [Role::TENANT_OWNER], $otherId);
        $otherSlug = $this->slug($otherId);
        foreach ([$newSlug, $oldSlug] as $taken) {
            $this->submit($kernel, '/lien-public', ['slug' => $taken], '/boutique/parametres');
            $this->assertSame($otherSlug, $this->slug($otherId), 'Lien déjà pris : ' . $taken);
        }
        $this->submit($kernel, '/lien-public', ['slug' => '!!'], '/boutique/parametres');
        $this->assertSame($otherSlug, $this->slug($otherId), 'Lien trop court refusé.');
        $this->logout($kernel);

        // Un employé sans accès aux réglages ne peut pas changer le lien.
        $this->loginAs($kernel, [Role::STAFF], $tenantId);
        $this->assertSame(403, $this->submit($kernel, '/lien-public', ['slug' => 'pirate-' . bin2hex(random_bytes(2))], '/profil')->status());
        $this->assertSame($newSlug, $this->slug($tenantId));
    }

    public function testSendPageSpeaksPlainFrench(): void
    {
        $tenantId = $this->tenant(Sector::BOUTIQUE);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $page = html_entity_decode($this->get($kernel, '/notifications/envoyer')->content(), ENT_QUOTES, 'UTF-8');
        foreach (['NOTIFICATIONS_MODE', '{{name}}', '{{amount}}', 'Interne (boîte équipe)'] as $jargon) {
            $this->assertStringNotContainsString($jargon, $page, $jargon);
        }
        $this->assertStringContainsString('Comment envoyer le message', $page);
        $this->assertStringContainsString('Envoyer le message', $page);
    }
}
