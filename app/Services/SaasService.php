<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Auth\Role;
use Sesam\Core\Boutique\Money;
use Sesam\Core\Boutique\WhatsApp;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Payment\PaymentStatus;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\Payment;
use Sesam\Models\TenantSubscription;
use Sesam\Models\User;
use Sesam\Repositories\AuthAttemptRepository;
use Sesam\Repositories\RoleRepository;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\TenantSubscriptionRepository;
use Sesam\Repositories\UserRepository;

/**
 * SaaS en libre-service : inscription avec essai gratuit, état de l'abonnement d'un commerce,
 * page « Abonnement » et réabonnement payé en ligne (NabooPay live ou simulation).
 */
final class SaasService extends Service
{
    public const TRIAL_DAYS = [15 => '15 jours', 30 => '1 mois'];
    public const SIGNUP_TRIAL_DAYS = 30;
    /** Jours après l'échéance pendant lesquels l'espace reste ouvert avant de n'afficher que la page Abonnement. */
    public const GRACE_DAYS = 5;
    public const RENEW_MONTHS = [1 => '1 mois', 3 => '3 mois', 6 => '6 mois', 12 => '1 an'];
    /** Formules longues réglables dans la console (proposée ou non, % de réduction). */
    public const PLAN_MONTHS = [3, 6, 12];
    public const RENEW_METHODS = ['wave' => 'Wave', 'orange-money' => 'Orange Money'];
    private const SIGNUP_RATE_MAX = 5;
    /** En minutes (countRecent compte en minutes) : 5 inscriptions par heure et par connexion. */
    private const SIGNUP_RATE_WINDOW = 60;

    private readonly \PDO $pdo;
    private readonly TenantRepository $tenants;
    private readonly UserRepository $users;
    private readonly RoleRepository $roles;
    private readonly TenantSubscriptionRepository $subscriptions;
    private readonly AuthAttemptRepository $attempts;
    private ?PaymentService $payments = null;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(array $database)
    {
        $this->pdo = Connection::make($database);
        $this->tenants = new TenantRepository($this->pdo);
        $this->users = new UserRepository($this->pdo);
        $this->roles = new RoleRepository($this->pdo);
        $this->subscriptions = new TenantSubscriptionRepository($this->pdo);
        $this->attempts = new AuthAttemptRepository($this->pdo);
    }

    // ================================================================ Essai gratuit (réglage de la console)

    public function trialEnabled(): bool
    {
        return (new \Sesam\Repositories\PlatformSettingsRepository($this->pdo))->get('trial_enabled', '1') === '1';
    }

    /**
     * @return array{enabled: bool, updated_at: ?string, updated_by_name: ?string}
     */
    public function trialSetting(): array
    {
        $details = (new \Sesam\Repositories\PlatformSettingsRepository($this->pdo))->details('trial_enabled', '1');

        return ['enabled' => $details['value'] === '1', 'updated_at' => $details['updated_at'], 'updated_by_name' => $details['updated_by_name']];
    }

    /**
     * Active ou coupe l'essai gratuit d'un mois pour les prochaines inscriptions (les essais en cours continuent).
     */
    public function setTrialEnabled(User $actor, bool $enabled): string
    {
        if ($actor->tenantId() !== null || !$actor->can(Permission::SUBSCRIPTIONS_MANAGE)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
        (new \Sesam\Repositories\PlatformSettingsRepository($this->pdo))->set('trial_enabled', $enabled ? '1' : '0', $actor->id());
        Notifier::staff(
            'sesam.security',
            $enabled ? 'Essai gratuit réactivé' : 'Essai gratuit désactivé',
            ($enabled ? 'Les nouvelles inscriptions profitent de nouveau d\'un mois offert.' : 'Les nouvelles inscriptions doivent payer leur premier mois pour ouvrir leur espace.') . ' Modifié par ' . $actor->fullName() . '.',
            '/tarifs',
            'info',
            null,
            $actor->id(),
        );

        return $enabled
            ? 'Essai gratuit activé : chaque nouvelle inscription profite d\'un mois offert.'
            : 'Essai gratuit désactivé : les nouveaux inscrits paient leur premier mois pour ouvrir leur espace. Les essais en cours ne changent pas.';
    }

    /**
     * Offre affichée sur la page « Commencer » : essai gratuit ou non, et tarifs de chaque module.
     *
     * @return array{trial: bool, prices: array<string, array{monthly: int, setup: int}>}
     */
    public function publicOffer(): array
    {
        $prices = [];
        try {
            foreach ($this->pdo->query('SELECT sector, monthly_amount, setup_fee FROM saas_prices') ?: [] as $row) {
                $prices[(string) $row['sector']] = ['monthly' => (int) $row['monthly_amount'], 'setup' => (int) $row['setup_fee']];
            }
        } catch (\PDOException) {
            $prices = [];
        }

        return ['trial' => $this->trialEnabled(), 'prices' => $prices];
    }

    public function usePayments(PaymentService $payments): void
    {
        $this->payments = $payments;
    }

    public function isLivePayments(): bool
    {
        return $this->payments?->isLiveGateway() ?? false;
    }

    // ================================================================ Inscription

    /**
     * Crée le commerce, le compte du responsable et un essai gratuit d'un mois. Renvoie l'id du compte créé.
     *
     * @param array<string, mixed> $input
     */
    public function signup(array $input, string $ip): int
    {
        $text = static fn (string $key, int $max): string => mb_substr(trim((string) ($input[$key] ?? '')), 0, $max);
        $name = $text('full_name', 120);
        $business = $text('business_name', 120);
        $phone = $text('phone', 40);
        $email = strtolower($text('email', 190));
        $password = (string) ($input['password'] ?? '');
        $sector = (string) ($input['sector'] ?? '');
        $googleSub = trim((string) ($input['google_sub'] ?? ''));
        // Trois façons de s'inscrire : téléphone (l'identifiant est le numéro), e-mail, ou Google.
        // Le numéro WhatsApp est obligatoire dans les trois cas ; l'e-mail seulement en mode e-mail.
        $mode = $googleSub !== '' ? 'google' : ((string) ($input['mode'] ?? '') === 'phone' ? 'phone' : 'email');
        $errors = [];
        if (mb_strlen($name) < 2) {
            $errors['full_name'] = 'Écrivez votre nom.';
        }
        if (mb_strlen($business) < 2) {
            $errors['business_name'] = 'Écrivez le nom de votre commerce.';
        }
        $normalized = WhatsApp::normalize($phone);
        if (!WhatsApp::isValid($normalized)) {
            $errors['phone'] = 'Numéro invalide. Choisissez l\'indicatif du pays puis le numéro, par exemple 77 123 45 67.';
        } elseif ($this->users->findAllByPhone($normalized) !== []) {
            $errors['phone'] = 'Un compte existe déjà avec ce numéro. Connectez-vous.';
        }
        if (!in_array($sector, Sector::ALL, true)) {
            $errors['sector'] = 'Choisissez votre service.';
        }
        if ($email !== '' || $mode === 'email') {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors['email'] = 'E-mail invalide.';
            } elseif ($this->users->findByEmail($email) !== null) {
                $errors['email'] = 'Un compte existe déjà avec cet e-mail. Connectez-vous.';
            }
        }
        if ($googleSub === '') {
            if (mb_strlen($password) < 12) {
                $errors['password'] = '12 caractères minimum.';
            } elseif ($password !== (string) ($input['password_confirm'] ?? '')) {
                $errors['password_confirm'] = 'Les deux mots de passe ne sont pas identiques.';
            }
        }
        if (empty($input['terms'])) {
            $errors['terms'] = 'Acceptez les conditions pour créer votre compte.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        if ($this->attempts->countRecent('signup', $ip, self::SIGNUP_RATE_WINDOW) >= self::SIGNUP_RATE_MAX) {
            throw new HttpException(429, 'Trop d\'inscriptions depuis cette connexion. Réessayez dans une heure.');
        }
        $this->attempts->record('signup', $ip);
        $display = $business !== '' ? $business : $name;

        $this->pdo->beginTransaction();
        try {
            $tenantId = $this->tenants->insert([
                'slug' => $this->uniqueSlug($display),
                'name' => $display,
                'sector' => $sector,
                'status' => 'active',
                'display_name' => $display,
            ]);
            $this->pdo->prepare("UPDATE tenants SET phone = ?, signup_source = 'site' WHERE id = ?")->execute([$normalized, $tenantId]);
            $userId = $this->users->insert([
                'tenant_id' => $tenantId,
                'full_name' => $name,
                'email' => $email,
                'google_sub' => $googleSub !== '' ? $googleSub : null,
                'password_hash' => password_hash(
                    $googleSub !== '' ? bin2hex(random_bytes(32)) : $password,
                    PASSWORD_DEFAULT
                ),
                'is_active' => 1,
            ]);
            $this->pdo->prepare('UPDATE users SET phone = ? WHERE id = ?')->execute([$normalized, $userId]);
            $this->roles->replaceUserRoles($userId, $this->roles->idsForSlugs([Role::TENANT_OWNER]));
            $this->prepareModule($tenantId, $sector, $display, $normalized, $email);
            $withTrial = $this->trialEnabled();
            if ($withTrial) {
                $this->startTrial($tenantId, self::SIGNUP_TRIAL_DAYS, null, 'Inscription depuis le site');
            } else {
                // Essai gratuit désactivé dans la console : l'espace s'ouvre au paiement du premier mois.
                $this->subscriptions->save($tenantId, TenantSubscription::PENDING, $this->monthlyPrice($tenantId), gmdate('Y-m-d'), null);
                $this->subscriptions->addEvent($tenantId, 'pending', null, TenantSubscription::PENDING, null, 'Inscription depuis le site, sans essai : activation au premier paiement', null);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
        // E-mail de bienvenue (jamais bloquant : l'inscription est faite même si l'e-mail ne part pas).
        try {
            if ($email === '') {
                throw new \RuntimeException('Inscription par téléphone : pas d\'e-mail de bienvenue.');
            }
            \Sesam\Core\Mail\Mailer::current()?->sendTemplate(
                $email,
                'Bienvenue sur SESAM — votre espace ' . $display . ' est prêt',
                'Bienvenue ' . $name . ' !',
                [
                    'Votre espace Sesam ' . Sector::label($sector) . ' « ' . $display . ' » est créé. ' . ($withTrial
                        ? 'Vous profitez d\'un mois d\'essai gratuit, avec toutes les fonctions.'
                        : 'Il s\'ouvre dès le paiement de votre premier mois (' . Money::format($this->monthlyPrice($tenantId)) . '), par Wave ou Orange Money, depuis la page Abonnement.'),
                    $googleSub !== ''
                        ? 'Connectez-vous ensuite avec Google (le même compte) ou via « Mot de passe oublié » si vous préférez un mot de passe. L\'équipe SESAM vous appelle très vite pour vous aider à démarrer.'
                        : 'Connectez-vous avec cette adresse e-mail et le mot de passe choisi à l\'inscription. L\'équipe SESAM vous appelle très vite pour vous aider à démarrer.',
                ],
                'Ouvrir mon espace',
                rtrim(\Sesam\Core\Env::get('APP_URL', ''), '/') . '/connexion',
                'Une question ? Répondez simplement à cet e-mail.',
            );
        } catch (\Throwable) {
            // Rien : l'équipe est prévenue de l'inscription de toute façon.
        }
        Notifier::staff(
            'sesam.signup',
            'Nouvelle inscription : ' . $display,
            'Sesam ' . Sector::label($sector) . ' · ' . $name . ' · ' . WhatsApp::display($normalized) . ($withTrial ? ' · essai d\'un mois' : ' · activation en attente de paiement'),
            '/commerces/' . $tenantId,
            'success',
        );

        return $userId;
    }

    /**
     * Essai gratuit : accès complet pendant N jours, puis la page Abonnement invite à payer.
     */
    public function startTrial(int $tenantId, int $days, ?int $actorId, string $note): void
    {
        $start = gmdate('Y-m-d');
        $end = gmdate('Y-m-d', (int) strtotime('+' . max(1, $days) . ' days'));
        $current = $this->subscriptions->find($tenantId);
        $this->subscriptions->save($tenantId, TenantSubscription::TRIAL, $this->monthlyPrice($tenantId), $start, $end);
        $this->subscriptions->addEvent($tenantId, 'trial', $current?->status(), TenantSubscription::TRIAL, $end, mb_substr('Essai gratuit de ' . $days . ' jours — ' . $note, 0, 180), $actorId);
    }

    /**
     * Prolonge un essai en cours (à partir de sa fin) ou en ouvre un nouveau. Depuis la console SESAM.
     */
    public function extendTrial(User $actor, int $tenantId, int $days): string
    {
        if ($actor->tenantId() !== null || !$actor->can(Permission::SUBSCRIPTIONS_MANAGE)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation de faire cette action.');
        }
        if (!isset(self::TRIAL_DAYS[$days])) {
            throw new ValidationException(['trial_days' => 'Choisissez 15 jours ou 1 mois.']);
        }
        if ($this->tenants->findById($tenantId) === null) {
            throw new HttpException(404, 'Commerce introuvable.');
        }
        $current = $this->subscriptions->find($tenantId);
        $today = gmdate('Y-m-d');
        if ($current !== null && $current->status() === TenantSubscription::TRIAL) {
            $from = max($today, (string) $current->periodEnd());
            $end = gmdate('Y-m-d', (int) strtotime($from . ' +' . $days . ' days'));
            $this->subscriptions->save($tenantId, TenantSubscription::TRIAL, $current->monthlyAmount(), $current->periodStart(), $end);
            $this->subscriptions->addEvent($tenantId, 'trial', TenantSubscription::TRIAL, TenantSubscription::TRIAL, $end, 'Essai prolongé de ' . self::TRIAL_DAYS[$days], $actor->id());
        } else {
            $this->startTrial($tenantId, $days, $actor->id(), 'Ouvert depuis la console');
            $end = gmdate('Y-m-d', (int) strtotime('+' . $days . ' days'));
        }
        $this->tenants->updateStatus($tenantId, 'active');

        return 'Essai gratuit jusqu\'au ' . gmdate('d/m/Y', (int) strtotime($end . ' UTC')) . '.';
    }

    private function prepareModule(int $tenantId, string $sector, string $name, string $phone, string $email): void
    {
        if ($sector === Sector::BOUTIQUE) {
            $this->pdo->prepare('INSERT IGNORE INTO boutique_settings (tenant_id, whatsapp_phone, created_at, updated_at) VALUES (?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute([$tenantId, $phone]);
        } elseif ($sector === Sector::RESERVATION) {
            $this->pdo->prepare('INSERT IGNORE INTO reservation_settings (tenant_id, whatsapp_phone, created_at, updated_at) VALUES (?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute([$tenantId, $phone]);
        } elseif ($sector === Sector::LOYER) {
            $this->pdo->prepare('INSERT IGNORE INTO immo_settings (tenant_id, display_name, whatsapp_phone, email, created_at, updated_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute([$tenantId, $name, $phone, $email]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        // Accents retirés sans dépendre d'iconv (qui écrit « 'e » pour « é » sous Windows).
        $plain = strtr($name, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ÿ' => 'y', 'ñ' => 'n',
            'À' => 'a', 'Â' => 'a', 'Ç' => 'c', 'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Î' => 'i', 'Ô' => 'o', 'Ù' => 'u', 'Û' => 'u', 'œ' => 'oe', 'Œ' => 'oe', 'æ' => 'ae']);
        $base = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $plain), '-'));
        $base = $base !== '' ? substr($base, 0, 60) : 'commerce';
        $slug = $base;
        for ($i = 2; $this->tenants->findBySlug($slug) !== null; $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    /**
     * Frais de mise en place (tarifs de la console) : dus une seule fois, à l'activation d'un compte inscrit sans essai.
     */
    public function setupFeeDue(int $tenantId, ?TenantSubscription $subscription = null): int
    {
        $subscription ??= $this->subscriptions->find($tenantId);
        if ($subscription?->status() !== TenantSubscription::PENDING) {
            return 0;
        }
        $statement = $this->pdo->prepare('SELECT p.setup_fee FROM tenants t LEFT JOIN saas_prices p ON p.sector = t.sector WHERE t.id = ?');
        $statement->execute([$tenantId]);

        return (int) ($statement->fetchColumn() ?: 0);
    }

    /**
     * Tarif en vigueur dans la console SESAM pour le module du commerce : il s'applique à chaque souscription
     * et à chaque renouvellement (le montant mémorisé dans l'abonnement ne sert que si la console n'a pas de tarif).
     */
    private function monthlyPrice(int $tenantId, ?TenantSubscription $subscription = null): int
    {
        $price = (int) ($this->sectorPrice($tenantId)['monthly_amount'] ?? 0);

        return $price > 0 ? $price : (int) ($subscription?->monthlyAmount() ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function sectorPrice(int $tenantId): array
    {
        try {
            $statement = $this->pdo->prepare('SELECT p.* FROM tenants t JOIN saas_prices p ON p.sector = t.sector WHERE t.id = ?');
            $statement->execute([$tenantId]);
            $row = $statement->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            $row = false;
        }

        return is_array($row) ? $row : [];
    }

    /** Montant d'une formule : mensualité × mois, moins la réduction (arrondi au franc). */
    public static function planAmount(int $monthly, int $months, int $discount): int
    {
        return (int) round($monthly * $months * (100 - max(0, min(90, $discount))) / 100);
    }

    /**
     * Formules proposées au commerce : 1 mois toujours, puis 3 / 6 / 12 mois si la console les propose.
     *
     * @return array<int, array{months: int, label: string, discount: int, base: int, amount: int, per_month: int, saving: int}>
     */
    public function plans(int $tenantId, ?TenantSubscription $subscription = null): array
    {
        $row = $this->sectorPrice($tenantId);
        $monthly = $this->monthlyPrice($tenantId, $subscription);
        $plans = [];
        foreach (self::RENEW_MONTHS as $months => $label) {
            $discount = 0;
            if ($months > 1) {
                if ((int) ($row['plan_' . $months . '_enabled'] ?? 1) !== 1) {
                    continue;
                }
                $discount = (int) ($row['plan_' . $months . '_discount'] ?? 0);
            }
            $amount = self::planAmount($monthly, $months, $discount);
            $plans[$months] = [
                'months' => $months,
                'label' => $label,
                'discount' => $discount,
                'base' => $monthly * $months,
                'amount' => $amount,
                'per_month' => (int) round($amount / $months),
                'saving' => $monthly * $months - $amount,
            ];
        }

        return $plans;
    }

    // ================================================================ État de l'accès

    /**
     * @return array{state: string, label: string, period_end: ?string, days_left: ?int, blocked: bool, banner: string, tone: string}
     */
    public function access(int $tenantId, ?string $today = null): array
    {
        $today ??= gmdate('Y-m-d');
        $subscription = $this->subscriptions->find($tenantId);
        $state = $subscription?->state($today) ?? 'none';
        $end = $subscription?->periodEnd();
        $daysLeft = $end !== null ? (int) floor((strtotime($end . ' UTC') - strtotime($today . ' UTC')) / 86400) : null;
        $blocked = $state === 'trial_over' || $state === 'pending' || ($state === 'overdue' && $daysLeft !== null && $daysLeft < -self::GRACE_DAYS);
        [$banner, $tone] = match (true) {
            $state === 'trial' => ['Essai gratuit : ' . max(0, (int) $daysLeft) . ' jour(s) restant(s).', $daysLeft !== null && $daysLeft <= 7 ? 'warning' : 'info'],
            $state === 'trial_over' => ['Votre essai gratuit est terminé. Abonnez-vous pour retrouver votre espace.', 'danger'],
            $state === 'pending' => ['Bienvenue ! Réglez votre premier mois pour ouvrir votre espace.', 'info'],
            $state === 'overdue' && $blocked => ['Votre abonnement est échu. Réglez-le pour retrouver votre espace.', 'danger'],
            $state === 'overdue' => ['Votre abonnement est échu depuis le ' . gmdate('d/m/Y', (int) strtotime((string) $end . ' UTC')) . '. Pensez à le renouveler.', 'warning'],
            $state === 'active' && $daysLeft !== null && $daysLeft <= 5 => ['Votre abonnement se termine dans ' . $daysLeft . ' jour(s).', 'warning'],
            default => ['', ''],
        };

        return [
            'state' => $state,
            'label' => TenantSubscription::label($state),
            'period_end' => $end,
            'days_left' => $daysLeft,
            'blocked' => $blocked,
            'banner' => $banner,
            'tone' => $tone,
        ];
    }

    // ================================================================ Page Abonnement du commerce

    /**
     * @return array<string, mixed>
     */
    public function page(User $actor): array
    {
        $tenantId = $this->tenantOf($actor);
        $subscription = $this->subscriptions->find($tenantId);
        $tenant = $this->tenants->findById($tenantId);
        $price = $this->monthlyPrice($tenantId, $subscription);
        $payments =$this->pdo->prepare('SELECT amount, months, paid_on, method, channel, reference, period_end FROM tenant_subscription_payments WHERE tenant_id = ? ORDER BY paid_on DESC, id DESC LIMIT 24');
        $payments->execute([$tenantId]);

        return [
            'access' => $this->access($tenantId),
            'sector' => $tenant?->sectorLabel() ?? '',
            'price' => $price,
            'periodStart' => $subscription?->periodStart(),
            'payments' => $payments->fetchAll(\PDO::FETCH_ASSOC),
            'canPay' => $actor->can(Permission::SUBSCRIPTION_PAY),
            'setupFee' => $this->setupFeeDue($tenantId, $subscription),
            'months' => self::RENEW_MONTHS,
            'plans' => array_map(
                fn (array $plan): array => $plan + ['new_end' => $this->renewedEnd($subscription, $plan['months'])],
                $this->plans($tenantId, $subscription)
            ),
            'methods' => self::RENEW_METHODS,
            'live' => $this->isLivePayments(),
        ];
    }

    /**
     * Prépare l'écran de paiement simulé (aucun débit) : montant et nouvelle date de fin.
     *
     * @return array<string, mixed>
     */
    public function checkout(User $actor, int $months, string $method): array
    {
        $tenantId = $this->tenantOf($actor);
        $this->assertCanPay($actor);
        if (!isset(self::RENEW_MONTHS[$months])) {
            throw new ValidationException(['months' => 'Choisissez une durée proposée.']);
        }
        if (!isset(self::RENEW_METHODS[$method])) {
            throw new ValidationException(['method' => 'Choisissez Wave ou Orange Money.']);
        }
        $subscription = $this->subscriptions->find($tenantId);
        $price = $this->monthlyPrice($tenantId, $subscription);
        if ($price < 1) {
            throw new ValidationException(['months' => 'Le tarif de votre module n\'est pas encore défini. Contactez SESAM.']);
        }

        $plan = $this->plans($tenantId, $subscription)[$months] ?? null;
        if ($plan === null) {
            throw new ValidationException(['months' => 'Cette formule n\'est plus proposée. Choisissez une autre durée.']);
        }

        $setupFee = $this->setupFeeDue($tenantId, $subscription);

        return [
            'months' => $months,
            'method' => $method,
            'methodLabel' => self::RENEW_METHODS[$method],
            'planLabel' => $plan['label'],
            'discount' => $plan['discount'],
            'saving' => $plan['saving'],
            'planAmount' => $plan['amount'],
            'amount' => $plan['amount'] + $setupFee,
            'setupFee' => $setupFee,
            'newEnd' => $this->renewedEnd($subscription, $months),
        ];
    }

    /**
     * Démarre un paiement NabooPay réel pour l'abonnement (clé API présente).
     *
     * @return array{payment: Payment, checkout_url: string}
     */
    public function startOnlineCheckout(User $actor, int $months, string $method): array
    {
        if ($this->payments === null || !$this->payments->isLiveGateway()) {
            throw new HttpException(503, 'Paiement NabooPay live non configuré.');
        }
        $checkout = $this->checkout($actor, $months, $method);
        $nabooMethod = $method === 'orange-money' ? 'orange_money' : 'wave';

        return $this->payments->initiateSubscription(
            $this->tenantOf($actor),
            (int) $checkout['amount'],
            'Abonnement SESAM · ' . $checkout['planLabel'] . ($checkout['discount'] > 0 ? ' (-' . $checkout['discount'] . ' %)' : ''),
            [
                'purpose' => 'subscription',
                'months' => $months,
                'method' => $method,
                'actor_user_id' => $actor->id(),
                'setup_fee' => (int) $checkout['setupFee'],
                'discount' => (int) $checkout['discount'],
            ],
            [$nabooMethod],
        );
    }

    /**
     * Au retour navigateur après NabooPay : vérifie le statut côté API (le webhook reste la source principale).
     */
    public function finalizeOnlineCheckout(User $actor, int $paymentId): string
    {
        if ($this->payments === null) {
            throw new HttpException(503, 'Paiement NabooPay non configuré.');
        }
        $tenantId = $this->tenantOf($actor);
        $this->assertCanPay($actor);
        $payment = $this->payments->syncFromProvider($tenantId, $paymentId);
        if ($payment->tenantId() !== $tenantId) {
            throw new HttpException(404, 'Paiement introuvable.');
        }
        if ($payment->status() === PaymentStatus::SUCCESS) {
            $end = $this->subscriptions->find($tenantId)?->periodEnd();

            return 'Paiement de ' . Money::format($payment->amountMinor()) . ' confirmé'
                . ($end !== null ? '. Votre abonnement est actif jusqu\'au ' . gmdate('d/m/Y', (int) strtotime($end . ' UTC')) . '.' : '.');
        }
        if ($payment->status() === PaymentStatus::CANCELLED || $payment->status() === PaymentStatus::FAILED) {
            return 'Le paiement n\'a pas abouti. Aucun débit SESAM n\'a été enregistré ; vous pouvez réessayer.';
        }

        return 'Paiement en cours de confirmation. Si vous avez payé, l\'abonnement s\'activera dès la notification NabooPay (webhook).';
    }

    /**
     * Appliqué après confirmation serveur (webhook ou GET transaction) — idempotent via la référence paiement.
     *
     * @param array{purpose: string, months: int, method: string, actor_user_id: int} $meta
     */
    public function applyPaidOnlineRenewal(Payment $payment, array $meta): void
    {
        $tenantId = $payment->tenantId();
        $months = (int) $meta['months'];
        $method = (string) $meta['method'];
        if (!isset(self::RENEW_MONTHS[$months]) || !isset(self::RENEW_METHODS[$method])) {
            return;
        }
        $exists = $this->pdo->prepare('SELECT id FROM tenant_subscription_payments WHERE tenant_id = ? AND reference = ? LIMIT 1');
        $exists->execute([$tenantId, $payment->reference()]);
        if ($exists->fetchColumn() !== false) {
            return;
        }

        $today = gmdate('Y-m-d');
        $subscription = $this->subscriptions->find($tenantId);
        $state = $subscription?->state($today) ?? 'none';
        $start = $state === 'active' ? ($subscription?->periodStart() ?? $today) : $today;
        $end = $this->renewedEnd($subscription, $months);
        $amount = $payment->amountMinor();
        $setupFee = max(0, (int) ($meta['setup_fee'] ?? 0));
        $monthly = intdiv(max(0, $amount - $setupFee), $months);
        $actorId = (int) $meta['actor_user_id'];
        $actorId = $actorId > 0 ? $actorId : null;
        $this->pdo->beginTransaction();
        try {
            $this->subscriptions->save($tenantId, TenantSubscription::ACTIVE, $monthly, $start, $end);
            $this->tenants->updateStatus($tenantId, 'active');
            $this->subscriptions->addEvent(
                $tenantId,
                $state === 'active' || $state === 'overdue' ? 'renew' : 'activate',
                $subscription?->status(),
                TenantSubscription::ACTIVE,
                $end,
                'Réabonnement en ligne : ' . Money::format($amount) . ' par ' . self::RENEW_METHODS[$method] . ' (NabooPay)',
                $actorId,
            );
            $this->pdo->prepare(
                "INSERT INTO tenant_subscription_payments (tenant_id, amount, months, paid_on, method, channel, reference, period_end, note, actor_user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, 'en-ligne', ?, ?, ?, ?, UTC_TIMESTAMP())"
            )->execute([
                $tenantId,
                $amount,
                $months,
                $today,
                $method,
                $payment->reference(),
                $end,
                'Payé via NabooPay · ' . ($payment->externalId() ?? $payment->reference()),
                $actorId,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $tenant = $this->tenants->findById($tenantId);
        Notifier::staff(
            'sesam.payment',
            'Abonnement payé : ' . ($tenant?->displayName() ?? 'commerce'),
            Money::format($amount) . ' pour ' . $months . ' mois par ' . self::RENEW_METHODS[$method] . ' · actif jusqu\'au ' . gmdate('d/m/Y', (int) strtotime($end . ' UTC')),
            '/commerces/' . $tenantId,
            'success',
        );
    }

    /**
     * Paiement simulé confirmé : l'abonnement est prolongé (ou démarre après l'essai) et l'encaissement apparaît dans la console SESAM.
     */
    public function confirmRenewal(User $actor, int $months, string $method): string
    {
        if ($this->isLivePayments()) {
            throw new HttpException(403, 'En mode NabooPay live, la confirmation se fait via NabooPay (pas de simulation).');
        }
        $checkout = $this->checkout($actor, $months, $method);
        $tenantId = $this->tenantOf($actor);
        $today = gmdate('Y-m-d');
        $subscription = $this->subscriptions->find($tenantId);
        $state = $subscription?->state($today) ?? 'none';
        $start = $state === 'active' ? ($subscription?->periodStart() ?? $today) : $today;
        $end = $checkout['newEnd'];
        $reference = 'SIM-' . strtoupper(bin2hex(random_bytes(4)));
        $this->pdo->beginTransaction();
        try {
            $this->subscriptions->save($tenantId, TenantSubscription::ACTIVE, intdiv($checkout['planAmount'], $months), $start, $end);
            $this->tenants->updateStatus($tenantId, 'active');
            $this->subscriptions->addEvent($tenantId, $state === 'active' || $state === 'overdue' ? 'renew' : 'activate', $subscription?->status(), TenantSubscription::ACTIVE, $end,
                'Réabonnement en ligne : ' . Money::format($checkout['amount']) . ' par ' . $checkout['methodLabel'] . ' (simulation)', $actor->id());
            $this->pdo->prepare(
                "INSERT INTO tenant_subscription_payments (tenant_id, amount, months, paid_on, method, channel, reference, period_end, note, actor_user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, 'en-ligne', ?, ?, ?, ?, UTC_TIMESTAMP())"
            )->execute([$tenantId, $checkout['amount'], $months, $today, $method, $reference, $end, 'Payé depuis l\'espace commerce (simulation NabooPay)', $actor->id()]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $tenant = $this->tenants->findById($tenantId);
        Notifier::staff(
            'sesam.payment',
            'Abonnement payé : ' . ($tenant?->displayName() ?? 'commerce'),
            Money::format($checkout['amount']) . ' pour ' . $months . ' mois par ' . $checkout['methodLabel'] . ' · actif jusqu\'au ' . gmdate('d/m/Y', (int) strtotime($end . ' UTC')),
            '/commerces/' . $tenantId,
            'success',
        );
        Notifier::tenant(
            $tenantId,
            'subscription',
            'Abonnement renouvelé ✓',
            Money::format($checkout['amount']) . ' reçu pour ' . $months . ' mois. Votre espace est actif jusqu\'au ' . gmdate('d/m/Y', (int) strtotime($end . ' UTC')) . '.',
            '/abonnement',
            'success',
        );

        return 'Paiement de ' . Money::format($checkout['amount']) . ' reçu (' . $reference . '). Votre abonnement est actif jusqu\'au ' . gmdate('d/m/Y', (int) strtotime($end . ' UTC')) . '.';
    }

    private function renewedEnd(?TenantSubscription $subscription, int $months): string
    {
        $today = gmdate('Y-m-d');
        $state = $subscription?->state($today) ?? 'none';
        // Un abonnement en cours se prolonge à la suite ; un essai ou un abonnement échu repart d'aujourd'hui.
        $from = $state === 'active' && $subscription?->periodEnd() !== null
            ? (new \DateTimeImmutable((string) $subscription->periodEnd()))->modify('+1 day')
            : new \DateTimeImmutable($today);
        $target = $from->modify('first day of +' . $months . ' months');
        $day = min((int) $from->format('j'), (int) $target->format('t'));

        return $target->setDate((int) $target->format('Y'), (int) $target->format('n'), $day)->modify('-1 day')->format('Y-m-d');
    }

    private function tenantOf(User $actor): int
    {
        $tenantId = $actor->tenantId();
        if ($tenantId === null) {
            throw new HttpException(403, 'Réservé aux espaces des commerces.');
        }

        return $tenantId;
    }

    private function assertCanPay(User $actor): void
    {
        if (!$actor->can(Permission::SUBSCRIPTION_PAY)) {
            throw new HttpException(403, 'Seul le responsable du commerce peut régler l\'abonnement.');
        }
    }
}
