<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Bootstrap;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Core\Request\Request;

final class PublicSiteTest extends TestCase
{
    public function testPublicPagesRespond(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));

        foreach (['/a-propos', '/services', '/offres', '/portfolio', '/contact', '/devis', '/commencer'] as $path) {
            $response = $kernel->handle(new Request('GET', $path));
            $this->assertSame(200, $response->status(), $path);
        }

        $services = $kernel->handle(new Request('GET', '/services'));
        $html = html_entity_decode($services->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('page-services', $html);
        $this->assertStringContainsString('Sesam Digital — Agence', $html);
        $this->assertStringContainsString('Sesam Réservation', $html);
        $this->assertStringContainsString('Sesam Boutique', $html);
        $this->assertStringContainsString('Sesam Immo', $html);
        $this->assertStringNotContainsString('Sesam Loyer', $html);
        $this->assertStringNotContainsString('Sesam Inscription', $html);
        $this->assertStringNotContainsString('Sesam Garde', $html);
        $this->assertStringNotContainsString('Sesam Commande', $html);
        $this->assertStringContainsString('Trois outils, un abonnement mensuel', $html);
        $this->assertStringContainsString('Inclus dans chaque outil SaaS', $html);
        $this->assertStringContainsString('Wave', $html);
        $this->assertStringContainsString('Orange Money', $html);
        $this->assertStringContainsString('WhatsApp', $html);
        $this->assertStringContainsString('à partir de 200 000 FCFA', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringNotContainsString('Espace cloisonné', $html);
        $this->assertStringNotContainsString('clinique', $html);
        $this->assertStringNotContainsString('QR code', $html);
        $this->assertStringNotContainsString('data-service="portail"', $html);
        $this->assertStringContainsString('class="mk-phone"', $html);
        $this->assertStringContainsString('class="mk-browser"', $html);

        $this->assertStringContainsString('200', $kernel->handle(new Request('GET', '/offres'))->content());
        $this->assertStringContainsString('Aucun projet publié', $kernel->handle(new Request('GET', '/portfolio'))->content());
        $this->assertStringNotContainsString('dépôt', $kernel->handle(new Request('GET', '/portfolio'))->content());

        $offers = html_entity_decode($kernel->handle(new Request('GET', '/offres'))->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Site vitrine simple, présentation de l\'activité', $offers);
        $this->assertStringContainsString('à partir de 200 000 FCFA', $offers);
        $this->assertStringContainsString('à partir de 450 000 FCFA', $offers);
        $this->assertStringNotContainsString('dans le plan', $offers);
        $this->assertStringContainsString('page-sheet', $offers);

        $about = html_entity_decode($kernel->handle(new Request('GET', '/a-propos'))->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Dakar', $about);
        $this->assertStringContainsString('New York', $about);
        $this->assertStringContainsString('page-sheet', $about);

        $contact = html_entity_decode($kernel->handle(new Request('GET', '/contact'))->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringNotContainsString('mailto:', $contact);
        $this->assertStringContainsString('Commencer gratuitement', $contact);
        $this->assertStringContainsString('+1 (917) 569-6871', $contact);
        $this->assertStringContainsString('+221 71 097 45 45', $contact);
        $this->assertStringContainsString('183 West Foire', $contact);
        $this->assertStringContainsString('contact-map', $contact);
        $this->assertStringContainsString('google.com/maps', $contact);
        $this->assertStringContainsString('cdn.jotfor.ms/agent/embedjs', $contact);

        $home = html_entity_decode($kernel->handle(new Request('GET', '/'))->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('cdn.jotfor.ms/agent/embedjs', $home);
        foreach (['/services', '/offres', '/a-propos', '/portfolio', '/devis', '/commencer', '/connexion'] as $publicPath) {
            $page = $kernel->handle(new Request('GET', $publicPath))->content();
            $this->assertStringContainsString('cdn.jotfor.ms/agent/embedjs', $page, $publicPath);
        }

        $quote = html_entity_decode($kernel->handle(new Request('GET', '/devis'))->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Propriétaire ou immeuble', $quote);
        $this->assertStringContainsString('Boutique ou vente en ligne', $quote);
        $this->assertStringNotContainsString('École', $quote);
        $this->assertStringNotContainsString('Pharmacie', $quote);
        $this->assertStringNotContainsString('clinique', strtolower($quote));
        // Le devis est réservé aux sites web : type de site, pages, fonctions, budget, délai.
        foreach (['Votre site web', 'Type de site', 'Pages souhaitées', 'Fonctions utiles', 'Budget envisagé', 'Nom de domaine'] as $needle) {
            $this->assertStringContainsString($needle, $quote);
        }

        $signup = html_entity_decode($kernel->handle(new Request('GET', '/commencer'))->content(), ENT_QUOTES, 'UTF-8');
        foreach (['1 mois offert', 'Sesam Réservation', 'Sesam Boutique', 'Sesam Immo', 'Continuer avec mon numéro de téléphone', 'Continuer avec mon e-mail', 'Numéro de téléphone (WhatsApp)', 'Mot de passe', 'Se connecter', 'data-cm-signup-form', 'data-g-step', 'data-g-next', 'Continuer'] as $needle) {
            $this->assertStringContainsString($needle, $signup);
        }
    }

    public function testEachServiceHasItsOwnPage(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $expected = [
            'agence' => ['Sesam Digital — Agence', 'data-scene="agence"', 'Deux chemins', 'Site vitrine simple', 'mk-browser'],
            'reservation' => ['Sesam Réservation', 'data-scene="reservation"', 'Rendez-vous confirmé', 'De votre côté', 'Prestation'],
            'boutique' => ['Vendez en ligne', 'Commander sur WhatsApp', 'Suivi du stock', 'Gestion des commandes', 'Fichier clients', 'Plus besoin d\'un site complet', 'Essayer gratuitement 1 mois', '/commencer?service=boutique'],
            'immo' => ['Sesam Immo', 'data-scene="immo"', 'De votre côté', 'Paiements partiels', '/commencer?service=loyer'],
        ];

        foreach ($expected as $key => $needles) {
            $response = $kernel->handle(new Request('GET', '/services/' . $key));
            $this->assertSame(200, $response->status(), $key);
            $html = html_entity_decode($response->content(), ENT_QUOTES, 'UTF-8');
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $html, $key . ' / ' . $needle);
            }
            $this->assertStringNotContainsString('clinique', $html);
        }

        $this->assertSame(404, $kernel->handle(new Request('GET', '/services/portail'))->status());
        $this->assertSame(404, $kernel->handle(new Request('GET', '/services/garde'))->status());
        $this->assertSame(404, $kernel->handle(new Request('GET', '/services/inscription'))->status());
        $old = $kernel->handle(new Request('GET', '/services/commande'));
        $this->assertSame(301, $old->status());
        $this->assertSame('/services/boutique', $old->headers()['Location'] ?? '');
        $loyer = $kernel->handle(new Request('GET', '/services/loyer'));
        $this->assertSame(301, $loyer->status());
        $this->assertSame('/services/immo', $loyer->headers()['Location'] ?? '');
        // Seule la page Sites web propose un devis.
        $agency = $kernel->handle(new Request('GET', '/services/agence'))->content();
        $this->assertStringContainsString('Demander un devis pour mon site', html_entity_decode($agency, ENT_QUOTES, 'UTF-8'));
    }

    public function testQuoteRejectsAPostWithoutCsrfToken(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $response = $kernel->handle(new Request('POST', '/devis', [], [
            'full_name' => 'Awa Diop',
            'email' => 'awa@example.com',
            'phone' => '770000000',
            'activity' => 'autre',
            'message' => 'Besoin d\'un site.',
        ]));

        $this->assertSame(419, $response->status());
    }

    public function testQuoteRejectsInvalidDataAndStoresAValidRequest(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $form = $kernel->handle(new Request('GET', '/devis'));
        preg_match('/name="_csrf" value="([^"]+)"/', $form->content(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        $invalid = $kernel->handle(new Request('POST', '/devis', [], [
            '_csrf' => $matches[1],
            'full_name' => '',
            'email' => 'pas-un-email',
            'phone' => '',
            'activity' => 'clinique',
            'message' => '',
        ]));
        $this->assertSame(422, $invalid->status());
        $this->assertStringContainsString('obligatoire', $invalid->content());

        $email = 'phase3+' . bin2hex(random_bytes(4)) . '@example.com';
        $valid = $kernel->handle(new Request('POST', '/devis', [], [
            '_csrf' => $matches[1],
            'full_name' => 'Awa Diop',
            'email' => $email,
            'phone' => '770000000',
            'activity' => 'salon-couture',
            'message' => 'Refonte du site du salon.',
            'site_state' => 'refonte',
            'site_type' => 'vitrine',
            'pages' => ['Accueil', 'Contact', 'Pas une page'],
            'features' => ['Bouton WhatsApp'],
            'budget' => '200-450k',
            'deadline' => '1-mois',
        ], [], [], false, '192.0.2.30'));
        $this->assertSame(200, $valid->status());
        $this->assertStringContainsString('Demande envoyée', $valid->content());

        Env::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
        /** @var array{db: array{host: string, port: string, name: string, user: string, password: string}} $config */
        $config = require dirname(__DIR__) . '/config/app.php';
        $pdo = Connection::make($config['db']);
        $stored = $pdo->prepare('SELECT site_type, site_state, pages, features, budget FROM contact_requests WHERE email = :email');
        $stored->execute(['email' => $email]);
        $this->assertSame(['site_type' => 'vitrine', 'site_state' => 'refonte', 'pages' => 'Accueil, Contact', 'features' => 'Bouton WhatsApp', 'budget' => '200-450k'], $stored->fetch(\PDO::FETCH_ASSOC));
        $statement = $pdo->prepare('DELETE FROM contact_requests WHERE email = :email');
        $statement->execute(['email' => $email]);
        $this->assertSame(1, $statement->rowCount());
        $pdo->prepare("DELETE FROM auth_attempts WHERE action = 'quote' AND ip = '192.0.2.30'")->execute();
    }
}
