<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Tests\Support\IntegrationTestCase;

/**
 * Inscription et connexion des commerces par numéro de téléphone (e-mail facultatif),
 * par e-mail (numéro toujours demandé), et pages qui proposent les deux modes.
 */
final class PhoneSignupLoginTest extends IntegrationTestCase
{
    private function randomPhone(): string
    {
        return '+221 77' . random_int(1000000, 9999999);
    }

    public function testPagesOfferPhoneEmailAndModalForms(): void
    {
        $kernel = $this->kernel();
        $signup = html_entity_decode($kernel->handle(new Request('GET', '/commencer'))->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Continuer avec mon numéro de téléphone', $signup);
        $this->assertStringContainsString('Continuer avec mon e-mail', $signup);
        $this->assertStringContainsString('data-cm-modal="signup"', $signup);
        $this->assertStringContainsString('intlTelInputWithUtils.min.js', $signup, 'Le sélecteur d\'indicatif est chargé.');

        $login = html_entity_decode($kernel->handle(new Request('GET', '/connexion'))->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Continuer avec mon numéro de téléphone', $login);
        $this->assertStringContainsString('Continuer avec mon e-mail', $login);
        $this->assertStringContainsString('data-login-modal', $login);
    }

    public function testPhoneSignupWithoutEmailThenLoginWithPhone(): void
    {
        $kernel = $this->kernel();
        $phone = $this->randomPhone();
        $digits = preg_replace('/\D+/', '', $phone);
        $response = $this->submit($kernel, '/commencer', [
            'mode' => 'phone', 'sector' => Sector::RESERVATION, 'full_name' => 'Awa Téléphone', 'business_name' => 'Salon Téléphone p20',
            'phone' => $phone, 'password' => 'PhonePass-2026!', 'password_confirm' => 'PhonePass-2026!', 'terms' => '1',
        ], '/commencer');
        $this->assertSame(302, $response->status());
        $userId = (int) $this->value('SELECT id FROM users WHERE phone = ?', [$digits]);
        $this->assertGreaterThan(0, $userId, 'Le compte est créé avec le numéro international.');
        $this->track((int) $this->value('SELECT tenant_id FROM users WHERE id = ?', [$userId]));
        $this->assertNull($this->value('SELECT email FROM users WHERE id = ?', [$userId]), 'L\'e-mail est facultatif.');
        $this->logout($kernel);

        // Le même numéro ne peut pas créer un second compte.
        $again = $this->submit($kernel, '/commencer', [
            'mode' => 'phone', 'sector' => Sector::BOUTIQUE, 'full_name' => 'Autre', 'business_name' => 'Autre commerce',
            'phone' => $phone, 'password' => 'PhonePass-2026!', 'password_confirm' => 'PhonePass-2026!', 'terms' => '1',
        ], '/commencer');
        $this->assertSame(422, $again->status());
        $this->assertStringContainsString('Un compte existe déjà avec ce numéro', html_entity_decode($again->content(), ENT_QUOTES, 'UTF-8'));

        // Connexion par téléphone : mauvais mot de passe refusé, bon mot de passe accepté (format libre du numéro).
        $bad = $kernel->handle($this->post('/connexion', ['_csrf' => $this->csrf($kernel, '/connexion'), 'mode' => 'phone', 'phone' => $phone, 'password' => 'Mauvais-2026!!']));
        $this->assertSame(422, $bad->status());
        $this->assertStringContainsString('Numéro ou mot de passe incorrect.', html_entity_decode($bad->content(), ENT_QUOTES, 'UTF-8'));

        $ok = $kernel->handle($this->post('/connexion', ['_csrf' => $this->csrf($kernel, '/connexion'), 'mode' => 'phone', 'phone' => '00' . $digits, 'password' => 'PhonePass-2026!']));
        $this->assertSame(302, $ok->status());
        $this->assertSame('/espace', $ok->headers()['Location'] ?? '');
    }

    public function testEmailModeStillRequiresEmailAndPhone(): void
    {
        $kernel = $this->kernel();
        $base = [
            'mode' => 'email', 'sector' => Sector::BOUTIQUE, 'full_name' => 'Fatou E-mail', 'business_name' => 'Boutique E-mail p20',
            'password' => 'EmailPass-2026!', 'password_confirm' => 'EmailPass-2026!', 'terms' => '1',
        ];
        $noEmail = $this->submit($kernel, '/commencer', $base + ['phone' => $this->randomPhone(), 'email' => ''], '/commencer');
        $this->assertSame(422, $noEmail->status());
        $this->assertStringContainsString('E-mail invalide.', html_entity_decode($noEmail->content(), ENT_QUOTES, 'UTF-8'));

        $noPhone = $this->submit($kernel, '/commencer', $base + ['phone' => '', 'email' => 'p20-mail-' . bin2hex(random_bytes(3)) . '@example.com'], '/commencer');
        $this->assertSame(422, $noPhone->status());
        $this->assertStringContainsString('Numéro invalide', html_entity_decode($noPhone->content(), ENT_QUOTES, 'UTF-8'));

        $noBusiness = $this->submit($kernel, '/commencer', ['business_name' => ''] + $base + ['phone' => $this->randomPhone(), 'email' => 'p20-mail-' . bin2hex(random_bytes(3)) . '@example.com'], '/commencer');
        $this->assertSame(422, $noBusiness->status());
        $this->assertStringContainsString('Écrivez le nom de votre commerce.', html_entity_decode($noBusiness->content(), ENT_QUOTES, 'UTF-8'));
    }
}
