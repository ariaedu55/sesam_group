<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Validation\ValidationException;
use Sesam\Core\Validation\Validator;
use Sesam\Models\User;
use Sesam\Repositories\AuthAttemptRepository;
use Sesam\Repositories\PasswordResetRepository;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\UserRepository;

final class AuthService extends Service
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;
    private const RESET_TTL_SECONDS = 3600;
    private const SESSION_USER = 'auth_user_id';

    private readonly UserRepository $users;
    private readonly PasswordResetRepository $resets;
    private readonly AuthAttemptRepository $attempts;
    private readonly TenantRepository $tenants;
    private ?\Sesam\Core\Mail\Mailer $mailer = null;
    private string $appUrl = '';

    /** E-mails (mot de passe oublié, confirmation de changement) : branché par le Bootstrap. */
    public function useMailer(\Sesam\Core\Mail\Mailer $mailer, string $appUrl): void
    {
        $this->mailer = $mailer;
        $this->appUrl = rtrim($appUrl, '/');
    }

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        array $database,
        private readonly Session $session,
        private readonly Validator $validator = new Validator(),
    ) {
        $pdo = Connection::make($database);
        $this->users = new UserRepository($pdo);
        $this->resets = new PasswordResetRepository($pdo);
        $this->attempts = new AuthAttemptRepository($pdo);
        $this->tenants = new TenantRepository($pdo);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function login(array $input, string $ip): void
    {
        $data = $this->validator->validate($input, [
            'email' => 'required|email|max:190',
            'password' => 'required|string|max:255',
        ]);
        $this->guardRate('login', $ip);

        $email = strtolower((string) $data['email']);
        $user = $this->users->findByEmail($email);
        $hash = $user !== null ? $user->passwordHash() : $this->dummyHash();
        $valid = password_verify((string) $data['password'], $hash);

        if ($user === null || !$valid || !$user->isActive() || !$this->tenantAllowsLogin($user)) {
            $this->attempts->record('login', $ip);
            $this->alertIfBlocked($user, $email, $ip);
            throw new ValidationException([
                'email' => 'E-mail ou mot de passe incorrect.',
            ]);
        }

        $this->session->regenerate();
        $this->session->forget(Csrf::SESSION_KEY);
        $this->session->put(self::SESSION_USER, $user->id());
        $this->users->touchLastLogin($user->id());
    }

    /**
     * Ouvre la session d'un compte qui vient d'être créé (inscription en libre-service).
     */
    public function startSessionFor(int $userId): void
    {
        $this->session->regenerate();
        $this->session->forget(Csrf::SESSION_KEY);
        $this->session->put(self::SESSION_USER, $userId);
        $this->users->touchLastLogin($userId);
    }

    public function logout(): void
    {
        $this->session->invalidate();
    }

    /**
     * @param array<string, mixed> $input
     */
    public function requestReset(array $input, string $ip): void
    {
        $data = $this->validator->validate($input, [
            'email' => 'required|email|max:190',
        ]);
        $this->guardRate('reset', $ip);

        $user = $this->users->findByEmail(strtolower((string) $data['email']));
        $this->attempts->record('reset', $ip);
        if ($user === null || !$user->isActive()) {
            return;
        }

        $this->sendResetLink($user, $this->createResetToken($user->id()));
    }

    /**
     * Envoie le lien de réinitialisation par e-mail. Renvoie false si l'e-mail n'a pas pu partir
     * (jamais d'erreur visible pour le visiteur : on ne révèle pas si le compte existe).
     */
    public function sendResetLink(User $user, string $token): bool
    {
        if ($this->mailer === null) {
            return false;
        }
        try {
            $this->mailer->sendTemplate(
                $user->email(),
                'Réinitialiser votre mot de passe SESAM',
                'Bonjour ' . $user->fullName() . ',',
                [
                    'Vous avez demandé à changer le mot de passe de votre compte SESAM.',
                    'Cliquez sur le bouton ci-dessous pour en choisir un nouveau. Le lien est valable une heure et ne fonctionne qu\'une fois.',
                ],
                'Choisir un nouveau mot de passe',
                $this->appUrl . '/mot-de-passe/reinitialiser/' . $token,
                'Vous n\'êtes pas à l\'origine de cette demande ? Ignorez simplement cet e-mail : votre mot de passe actuel reste valable.',
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function issueResetToken(int $userId): string
    {
        $user = $this->users->findById($userId);
        if ($user === null || !$user->isActive()) {
            throw new HttpException(404, 'Utilisateur introuvable.');
        }

        return $this->createResetToken($user->id());
    }

    /**
     * @param array<string, mixed> $input
     */
    public function resetPassword(string $token, array $input): void
    {
        $data = $this->validator->validate($input, [
            'password' => 'required|string|min:12|max:255',
            'password_confirmation' => 'required|string|max:255',
        ]);
        if ((string) $data['password'] !== (string) $data['password_confirmation']) {
            throw new ValidationException([
                'password_confirmation' => 'Les deux mots de passe doivent être identiques.',
            ]);
        }

        $row = $this->resets->findValid($this->hashToken($token));
        if ($row === null) {
            throw new ValidationException([
                'password' => 'Ce lien de réinitialisation est invalide ou expiré.',
            ]);
        }

        $this->users->updatePassword($row['user_id'], password_hash((string) $data['password'], PASSWORD_DEFAULT));
        $this->resets->markUsed($row['id']);
        $this->resets->expireUnusedForUser($row['user_id']);
        $this->session->invalidate();

        // Alerte de sécurité : le titulaire du compte apprend que son mot de passe a changé.
        $changed = $this->users->findById($row['user_id']);
        if ($changed !== null && $this->mailer !== null) {
            try {
                $this->mailer->sendTemplate(
                    $changed->email(),
                    'Votre mot de passe SESAM a été modifié',
                    'Mot de passe modifié',
                    [
                        'Bonjour ' . $changed->fullName() . ', le mot de passe de votre compte SESAM vient d\'être changé le ' . gmdate('d/m/Y à H:i') . ' (heure de Dakar).',
                        'Si c\'est bien vous, il n\'y a rien à faire.',
                    ],
                    'Se connecter',
                    $this->appUrl . '/connexion',
                    'Ce n\'est pas vous ? Demandez tout de suite un nouveau lien depuis « Mot de passe oublié » et prévenez l\'équipe SESAM.',
                );
            } catch (\Throwable) {
                // L'alerte est un plus : le changement de mot de passe reste valide.
            }
        }
    }

    public function user(): ?User
    {
        $id = $this->session->get(self::SESSION_USER);
        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            return null;
        }

        $user = $this->users->findById((int) $id);
        if ($user === null || !$user->isActive()) {
            $this->session->forget(self::SESSION_USER);

            return null;
        }

        return $user;
    }

    public function requireUser(): User
    {
        $user = $this->user();
        if ($user === null) {
            throw new HttpException(401, 'Connectez-vous pour continuer.');
        }

        return $user;
    }

    public function requirePermission(string $permission): User
    {
        $user = $this->requireUser();
        if (!$user->can($permission)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }

        return $user;
    }

    private function tenantAllowsLogin(User $user): bool
    {
        $tenantId = $user->tenantId();
        if ($tenantId === null) {
            return true;
        }
        $tenant = $this->tenants->findById($tenantId);

        return $tenant !== null && $tenant->isActive();
    }

    private function createResetToken(int $userId): string
    {
        $this->resets->expireUnusedForUser($userId);
        $token = bin2hex(random_bytes(32));
        $expires = gmdate('Y-m-d H:i:s', time() + self::RESET_TTL_SECONDS);
        $this->resets->insert($userId, $this->hashToken($token), $expires);

        return $token;
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Cinquième échec en 15 minutes sur un compte de l'équipe SESAM : la connexion est bloquée, l'équipe est prévenue
     * (une fois par jour et par adresse). Les comptes des commerces ne déclenchent rien (mots de passe oubliés).
     */
    private function alertIfBlocked(?User $user, string $email, string $ip): void
    {
        if ($user === null || $user->tenantId() !== null || $this->attempts->countRecent('login', $ip, self::WINDOW_MINUTES) !== self::MAX_ATTEMPTS) {
            return;
        }
        \Sesam\Services\Notifier::staff(
            'sesam.security',
            'Connexion bloquée sur un compte de l\'équipe',
            self::MAX_ATTEMPTS . ' mots de passe incorrects en ' . self::WINDOW_MINUTES . ' min pour ' . $email . ' depuis l\'adresse ' . $ip . '. Connexions bloquées 15 minutes. Si ce n\'est pas vous, changez le mot de passe.',
            '/utilisateurs/' . $user->id(),
            'danger',
            'security-login-' . $user->id() . '-' . $ip . '-' . gmdate('Y-m-d'),
        );
    }

    private function guardRate(string $action, string $ip): void
    {
        if ($this->attempts->countRecent($action, $ip, self::WINDOW_MINUTES) >= self::MAX_ATTEMPTS) {
            throw new ValidationException([
                'email' => 'Trop de tentatives. Réessayez dans quinze minutes.',
            ]);
        }
    }

    private function dummyHash(): string
    {
        static $hash = null;
        $hash ??= password_hash('invalid-password', PASSWORD_DEFAULT);

        return $hash;
    }
}
