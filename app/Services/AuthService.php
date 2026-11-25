<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Auth\GoogleIdentityClient;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\Sector;
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
    public const SESSION_WELCOME = 'welcome_popup';
    private const SESSION_GOOGLE_PENDING = 'google_oauth_pending';
    /** Activité ouverte par un compte qui en a plusieurs (Immo, Réservation, Boutique). */
    private const SESSION_ACTIVE_TENANT = 'active_tenant_id';
    /** Le compte a choisi son activité sur « Mes activités » depuis sa connexion. */
    private const SESSION_ACTIVITY_CHOSEN = 'activity_chosen';

    private readonly UserRepository $users;
    private readonly PasswordResetRepository $resets;
    private readonly AuthAttemptRepository $attempts;
    private readonly TenantRepository $tenants;
    private readonly \Sesam\Repositories\TenantAccessRepository $access;
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
        private readonly ?GoogleIdentityClient $google = null,
    ) {
        $pdo = Connection::make($database);
        $this->users = new UserRepository($pdo);
        $this->resets = new PasswordResetRepository($pdo);
        $this->attempts = new AuthAttemptRepository($pdo);
        $this->tenants = new TenantRepository($pdo);
        $this->access = new \Sesam\Repositories\TenantAccessRepository($pdo);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function login(array $input, string $ip): void
    {
        // Connexion par numéro de téléphone (comptes inscrits par téléphone) ou par e-mail.
        $byPhone = ($input['mode'] ?? '') === 'phone';
        $field = $byPhone ? 'phone' : 'email';
        $data = $this->validator->validate($input, [
            $field => $byPhone ? 'required|string|max:40' : 'required|email|max:190',
            'password' => 'required|string|max:255',
        ]);
        $this->guardRate('login', $ip);

        $password = (string) $data['password'];
        if ($byPhone) {
            $identifier = \Sesam\Core\Boutique\WhatsApp::normalize((string) $data['phone']);
            $user = null;
            // Un même numéro peut appartenir à plusieurs comptes (équipe d'un commerce) : le mot de passe les départage.
            foreach ($this->users->findAllByPhone($identifier) as $candidate) {
                if ($user === null || password_verify($password, $candidate->passwordHash())) {
                    $user = $candidate;
                }
            }
        } else {
            $identifier = strtolower((string) $data['email']);
            $user = $this->users->findByEmail($identifier);
        }
        $hash = $user !== null ? $user->passwordHash() : $this->dummyHash();
        $valid = password_verify($password, $hash);
        $wrong = $byPhone ? 'Numéro ou mot de passe incorrect.' : 'E-mail ou mot de passe incorrect.';

        if ($user === null || !$valid || !$user->isActive()) {
            $this->attempts->record('login', $ip);
            $this->alertIfBlocked($user, $identifier, $ip);
            throw new ValidationException([$field => $wrong]);
        }
        if (!$this->tenantAllowsLogin($user)) {
            $this->attempts->record('login', $ip);
            throw new ValidationException([
                $field => 'L\'accès de ce commerce est suspendu. Contactez SESAM ou utilisez « Mot de passe oublié » si vous devez vous reconnecter après réactivation.',
            ]);
        }

        $this->session->regenerate();
        $this->session->forget(Csrf::SESSION_KEY);
        $this->session->put(self::SESSION_USER, $user->id());
        $this->resetActivity();
        // Popup de bienvenue à l'arrivée dans l'espace : découverte la première fois, « bon retour » ensuite.
        $this->session->put(self::SESSION_WELCOME, ['first' => $user->lastLoginAt() === null]);
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
        $this->resetActivity();
        $this->session->put(self::SESSION_WELCOME, ['first' => true]);
        $this->users->touchLastLogin($userId);
    }

    public function logout(): void
    {
        $this->session->invalidate();
    }

    public function googleEnabled(): bool
    {
        return $this->google !== null && $this->google->isConfigured();
    }

    /**
     * @return array<string, string>
     */
    public function googleWebConfig(): array
    {
        return $this->googleEnabled() ? ($this->google?->webConfig() ?? []) : [];
    }

    /**
     * @return '/espace'|'/commencer'|string
     */
    public function completeGoogleIdToken(string $idToken, string $sector, string $ip): string
    {
        if (!$this->googleEnabled()) {
            throw new HttpException(503, 'La connexion Google n\'est pas encore configurée.');
        }
        if (trim($idToken) === '') {
            throw new ValidationException(['email' => 'Connexion Google incomplète. Réessayez.']);
        }
        $this->guardRate('google', $ip);
        $google = $this->google;
        if ($google === null) {
            throw new HttpException(503, 'La connexion Google n\'est pas encore configurée.');
        }
        $profile = $google->profileFromIdToken($idToken);
        $user = $this->users->findByGoogleSub($profile->sub) ?? $this->users->findByEmail($profile->email);
        if ($user !== null) {
            if (!$user->isActive()) {
                $this->attempts->record('google', $ip);
                throw new ValidationException(['email' => 'E-mail ou mot de passe incorrect.']);
            }
            if (!$this->tenantAllowsLogin($user)) {
                $this->attempts->record('google', $ip);
                throw new ValidationException([
                    'email' => 'L\'accès de ce commerce est suspendu. Contactez SESAM ou utilisez « Mot de passe oublié » si vous devez vous reconnecter après réactivation.',
                ]);
            }
            if ($user->googleSub() === null) {
                $this->users->linkGoogleSub($user->id(), $profile->sub);
            }
            $this->session->forget(self::SESSION_GOOGLE_PENDING);
            $this->startSessionForExisting($user);

            return '/espace';
        }

        $this->session->put(self::SESSION_GOOGLE_PENDING, [
            'sub' => $profile->sub,
            'email' => $profile->email,
            'name' => $profile->name,
        ]);
        $commencer = '/commencer';
        if (is_string($sector) && in_array($sector, Sector::ALL, true)) {
            $commencer .= '?service=' . rawurlencode($sector);
        }

        return $commencer;
    }

    /**
     * @return array{sub: string, email: string, name: string}|null
     */
    public function pendingGoogle(): ?array
    {
        $pending = $this->session->get(self::SESSION_GOOGLE_PENDING);
        if (!is_array($pending)) {
            return null;
        }
        $sub = trim((string) ($pending['sub'] ?? ''));
        $email = strtolower(trim((string) ($pending['email'] ?? '')));
        $name = trim((string) ($pending['name'] ?? ''));
        if ($sub === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return ['sub' => $sub, 'email' => $email, 'name' => $name];
    }

    public function clearPendingGoogle(): void
    {
        $this->session->forget(self::SESSION_GOOGLE_PENDING);
    }

    private function startSessionForExisting(\Sesam\Models\User $user): void
    {
        $this->session->regenerate();
        $this->session->forget(Csrf::SESSION_KEY);
        $this->session->put(self::SESSION_USER, $user->id());
        $this->resetActivity();
        $this->session->put(self::SESSION_WELCOME, ['first' => $user->lastLoginAt() === null]);
        $this->users->touchLastLogin($user->id());
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
        // Même flux pour l'équipe SESAM (sans commerce) et les comptes rattachés à un commerce.
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
                    'Vous avez demandé à changer le mot de passe de votre compte SESAM (équipe interne ou espace commerce).',
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

        return $this->withActiveActivity($user);
    }

    /**
     * Toutes les activités du compte connecté (activité principale en premier), y compris celles suspendues.
     *
     * @return list<\Sesam\Models\Tenant>
     */
    public function activities(User $user): array
    {
        if ($user->homeTenantId() === null) {
            return [];
        }
        $tenants = [];
        foreach ($this->access->tenantIdsFor($user->id(), $user->homeTenantId()) as $id) {
            $tenant = $this->tenants->findById($id);
            if ($tenant !== null) {
                $tenants[] = $tenant;
            }
        }

        return $tenants;
    }

    /**
     * Business du même groupe que le compte n'a pas le droit d'ouvrir : affichés grisés sur « Mes activités ».
     *
     * @return list<\Sesam\Models\Tenant>
     */
    public function lockedActivities(User $user): array
    {
        $home = $user->homeTenantId();
        if ($home === null) {
            return [];
        }
        $mine = $this->access->tenantIdsFor($user->id(), $home);
        $locked = [];
        foreach ($this->access->groupTenantIds($home) as $id) {
            if (!in_array($id, $mine, true)) {
                $tenant = $this->tenants->findById($id);
                if ($tenant !== null) {
                    $locked[] = $tenant;
                }
            }
        }

        return $locked;
    }

    public function activityCount(User $user): int
    {
        return $user->homeTenantId() === null ? 0 : count($this->access->tenantIdsFor($user->id(), $user->homeTenantId()));
    }

    public function activityChosen(): bool
    {
        return $this->session->get(self::SESSION_ACTIVITY_CHOSEN) === true;
    }

    /**
     * Ouvre une des activités du compte : toute l'application (menus, données, abonnement) suit ce choix.
     */
    public function switchActivity(User $user, int $tenantId): \Sesam\Models\Tenant
    {
        $home = $user->homeTenantId();
        if ($home === null || !in_array($tenantId, $this->access->tenantIdsFor($user->id(), $home), true)) {
            throw new HttpException(403, 'Cette activité n\'est pas rattachée à votre compte.');
        }
        $tenant = $this->tenants->findById($tenantId);
        if ($tenant === null || !$tenant->isActive()) {
            throw new HttpException(403, 'L\'accès à cette activité est suspendu. Contactez SESAM.');
        }
        if ($tenantId === $home) {
            $this->session->forget(self::SESSION_ACTIVE_TENANT);
        } else {
            $this->session->put(self::SESSION_ACTIVE_TENANT, $tenantId);
        }
        $this->session->put(self::SESSION_ACTIVITY_CHOSEN, true);

        return $tenant;
    }

    /** Retour à l'activité principale ; « Mes activités » s'affichera de nouveau. */
    public function resetActivity(): void
    {
        $this->session->forget(self::SESSION_ACTIVE_TENANT);
        $this->session->forget(self::SESSION_ACTIVITY_CHOSEN);
    }

    /**
     * Le compte agit pour l'activité choisie : son tenant_id devient celui de cette activité
     * (l'activité principale reste connue dans home_tenant_id).
     */
    private function withActiveActivity(User $user): User
    {
        $home = $user->tenantId();
        if ($home === null) {
            return $user;
        }
        $user = $user->with(['home_tenant_id' => $home]);
        $active = $this->session->get(self::SESSION_ACTIVE_TENANT);
        if (!is_int($active) || $active === $home) {
            return $user;
        }
        if (!$this->access->has($user->id(), $active)) {
            $this->session->forget(self::SESSION_ACTIVE_TENANT);

            return $user;
        }

        return $user->with(['tenant_id' => $active]);
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
        // Un compte à plusieurs activités se connecte tant qu'au moins une d'elles est ouverte.
        foreach ($this->access->tenantIdsFor($user->id(), $tenantId) as $id) {
            $tenant = $this->tenants->findById($id);
            if ($tenant !== null && $tenant->isActive()) {
                return true;
            }
        }

        return false;
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
