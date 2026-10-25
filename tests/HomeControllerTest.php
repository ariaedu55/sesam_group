<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Controllers\HomeController;
use Sesam\Core\Bootstrap;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\ViewRenderer;

final class HomeControllerTest extends TestCase
{
    public function testIndexRendersTheFoundationPage(): void
    {
        $controller = new HomeController(new ViewRenderer(dirname(__DIR__) . '/app/Views'));
        $response = $controller->index(new Request('GET', '/'));
        $html = html_entity_decode($response->content(), ENT_QUOTES, 'UTF-8');

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('<html lang="fr">', $html);
        $this->assertStringContainsString('assets/css/site.css', $html);
        $this->assertStringNotContainsString('public-glass.css', $html);
        $this->assertStringContainsString('Le digital', $html);
        $this->assertStringContainsString('qui fait vendre', $html);
        $this->assertStringContainsString("L'innovation à portée de clic", $html);
        $this->assertStringContainsString('Réservation, Boutique en ligne ou Immo', $html);
        $this->assertStringContainsString('Dakar', $html);
        $this->assertStringContainsString('New York', $html);
        $this->assertStringContainsString('Wave', $html);
        $this->assertStringContainsString('Orange Money', $html);
        $this->assertStringContainsString('Commander sur WhatsApp', $html);
        $this->assertStringContainsString('à partir de 200 000 FCFA', $html);
        $this->assertStringContainsString('Votre boutique en ligne, sans créer de site', $html);
        $this->assertStringContainsString('Gestion locative : biens, locataires, loyers et relances', $html);
        $this->assertStringContainsString('Sites web et applications conçus pour faire vendre', $html);
        foreach (['agence', 'reservation', 'boutique', 'immo'] as $key) {
            $this->assertStringContainsString('data-service="' . $key . '"', $html);
            $this->assertStringContainsString('/services/' . $key, $html);
        }
        foreach (['Sesam Garde', 'Sesam Inscription', 'Sesam Commande', '/services/garde', '/services/inscription', 'Écoles', 'Pharmacies'] as $removed) {
            $this->assertStringNotContainsString($removed, $html);
        }
        // Mot qui tourne dans le titre : lisible aussi par les lecteurs d'écran.
        $this->assertStringContainsString('hx-rot', $html);
        $this->assertStringContainsString('votre boutique, vos réservations, vos loyers et votre site.', $html);
        $this->assertStringNotContainsString('mailto:', $html);

        preg_match('/<header class="site-header">[\s\S]*?<\/header>/', $html, $headerMatch);
        $headerHtml = $headerMatch[0] ?? '';
        $this->assertStringContainsString('sh-bar', $headerHtml);
        $this->assertStringContainsString('sesam-logo-360.jpg', $headerHtml);
        $this->assertStringContainsString('aria-label="SESAM, accueil"', $headerHtml);
        $this->assertStringContainsString('>Sites web</span>', $headerHtml);
        $this->assertStringContainsString('>Boutique</span>', $headerHtml);
        $this->assertStringContainsString('>Contact</span>', $headerHtml);
        $this->assertStringNotContainsString('>Garde</span>', $headerHtml);
        $this->assertStringNotContainsString('>Inscription</span>', $headerHtml);
        // « Commencer » remplace « Demander un devis » dans la barre ; le bouton menu reste tout à droite.
        $this->assertMatchesRegularExpression('/sh-cta[\s\S]*?data-nav-toggle/', $headerHtml);
        $this->assertStringContainsString('/commencer', $headerHtml);
        $this->assertStringContainsString('>Commencer</a>', $headerHtml);
        $this->assertStringContainsString('/connexion', $headerHtml);
        $this->assertStringNotContainsString('Demander un devis', $headerHtml);
        $this->assertStringNotContainsString('href="/devis"', $html);
        $this->assertStringContainsString('site-footer', $html);
        $this->assertStringContainsString('footer-pages', $html);

        // Identité plate : aucun dégradé et aucune bordure colorée dans la feuille du site.
        $css = (string) file_get_contents(dirname(__DIR__) . '/public/assets/css/site.css');
        $this->assertStringNotContainsString('gradient(', $css);
        $this->assertDoesNotMatchRegularExpression('/border[\w-]*\s*:[^;]*(#1f4dff|#d4f35a|var\(--(blue|lime|lilac|coral|mint|sun)\))/', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
        foreach (['sesam.css', 'app-glass.css', 'boutique.css', 'boutique-admin.css', 'immo.css', 'console.css', 'mobile-app.css', 'print-doc.css'] as $sheet) {
            $this->assertStringNotContainsString('gradient(', (string) file_get_contents(dirname(__DIR__) . '/public/assets/css/' . $sheet), $sheet);
        }
    }

    public function testKernelHomeAddsSecurityHeaders(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $response = $kernel->handle(new Request('GET', '/'));

        $this->assertSame(200, $response->status());
        $this->assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
        $this->assertSame('DENY', $response->headers()['X-Frame-Options']);
    }
}
