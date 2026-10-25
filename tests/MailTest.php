<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Core\Auth\Role;
use Sesam\Core\Mail\Mailer;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Tests\Support\IntegrationTestCase;

/**
 * E-mails : mot de passe oublié (lien qui fonctionne), alerte après changement, lien envoyé par l'équipe,
 * bienvenue à l'inscription. Pilote « log » pendant les tests (MAIL_DRIVER_OVERRIDE) : rien ne part réellement.
 */
final class MailTest extends IntegrationTestCase
{
    public function testForgottenPasswordSendsAWorkingLinkThenASecurityNotice(): void
    {
        $kernel = $this->kernel();
        $mailer = Mailer::current();
        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertSame('log', $mailer->driver());
        $email = $this->user([Role::TENANT_OWNER], $this->tenant(Sector::BOUTIQUE));

        // Adresse inconnue : même message, aucun e-mail (on ne révèle pas quels comptes existent).
        $kernel->handle($this->post('/mot-de-passe', ['_csrf' => $this->csrf($kernel, '/mot-de-passe'), 'email' => 'inconnu-p20@example.com']));
        $this->assertSame([], $mailer->outbox());

        $kernel->handle($this->post('/mot-de-passe', ['_csrf' => $this->csrf($kernel, '/mot-de-passe'), 'email' => $email]));
        $this->assertCount(1, $mailer->outbox());
        $sent = $mailer->outbox()[0];
        $this->assertSame($email, $sent['to']);
        $this->assertSame('Réinitialiser votre mot de passe SESAM', $sent['subject']);
        $this->assertSame(1, preg_match('#/mot-de-passe/reinitialiser/([a-f0-9]{64})#', $sent['text'], $match));

        // Le lien reçu fonctionne, puis une alerte confirme le changement.
        $reset = $kernel->handle($this->post('/mot-de-passe/reinitialiser/' . $match[1], [
            '_csrf' => $this->csrf($kernel, '/mot-de-passe/reinitialiser/' . $match[1]),
            'password' => 'NouveauPass-2026!',
            'password_confirmation' => 'NouveauPass-2026!',
        ]));
        $this->assertSame(302, $reset->status());
        $this->assertSame('Votre mot de passe SESAM a été modifié', $mailer->outbox()[1]['subject'] ?? null);
        $login = $kernel->handle($this->post('/connexion', ['_csrf' => $this->csrf($kernel, '/connexion'), 'email' => $email, 'password' => 'NouveauPass-2026!']));
        $this->assertSame('/espace', $login->headers()['Location'] ?? '');
    }

    public function testTeamCanEmailAResetLinkAndSignupGetsAWelcome(): void
    {
        $kernel = $this->kernel();
        $mailer = Mailer::current();
        $this->assertInstanceOf(Mailer::class, $mailer);

        // L'équipe SESAM crée un lien pour un compte : il part aussi par e-mail.
        $target = $this->user([Role::SUPPORT], null);
        $targetId = $this->userId($target);
        $this->loginAs($kernel, [Role::SUPER_ADMIN], null);
        $this->submit($kernel, '/utilisateurs/' . $targetId . '/lien-reinitialisation', [], '/utilisateurs/' . $targetId);
        $this->assertSame($target, $mailer->outbox()[0]['to'] ?? null);
        $page = html_entity_decode($kernel->handle(new Request('GET', '/utilisateurs/' . $targetId))->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('envoyé par e-mail à ' . $target, $page);
        $this->logout($kernel);

        // Inscription depuis le site : e-mail de bienvenue.
        $email = 'p20-mail-' . bin2hex(random_bytes(3)) . '@example.com';
        $this->submit($kernel, '/commencer', [
            'sector' => Sector::BOUTIQUE, 'full_name' => 'Aïssatou p20', 'business_name' => 'Boutique mail p20', 'phone' => '771234567',
            'email' => $email, 'password' => 'SignupPass-2026', 'password_confirm' => 'SignupPass-2026', 'terms' => '1',
        ], '/commencer');
        $this->track((int) $this->value('SELECT tenant_id FROM users WHERE email = ?', [$email]), $email);
        $welcome = array_values(array_filter($mailer->outbox(), static fn (array $mail): bool => $mail['to'] === $email));
        $this->assertCount(1, $welcome);
        $this->assertStringContainsString('Boutique mail p20', $welcome[0]['subject']);
        $this->assertStringContainsString('/connexion', $welcome[0]['text']);
    }
}
