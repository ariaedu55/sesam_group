<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Logger\Logger;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\Tenant;
use Sesam\Models\TenantSubscription;
use Sesam\Models\User;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\TenantSubscriptionRepository;

final class TenantConsoleService extends Service
{
    public const SOON_DAYS = 7;
    private const MAX_AMOUNT = 100_000_000;

    private TenantRepository $tenants;
    private TenantSubscriptionRepository $subscriptions;
    private ?\PDO $pdo = null;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        private readonly array $database,
        private readonly ?Logger $logger = null,
    ) {
    }

    private function connect(): \PDO
    {
        if ($this->pdo === null) {
            $this->pdo = Connection::make($this->database);
            $this->tenants = new TenantRepository($this->pdo);
            $this->subscriptions = new TenantSubscriptionRepository($this->pdo);
        }

        return $this->pdo;
    }

    /**
     * @param array<string, mixed> $query
     * @return array{tenants: list<Tenant>, overview: array<string, mixed>, filters: array{q: string, sector: string, status: string, subscription: string}, today: string}
     */
    public function overview(User $actor, array $query): array
    {
        $this->assertCan($actor, Permission::TENANTS_VIEW);
        $this->connect();
        $filters = $this->filters($query);
        $today = gmdate('Y-m-d');
        if (!$actor->can(Permission::SUBSCRIPTIONS_VIEW)) {
            $filters['subscription'] = '';
        }

        return [
            'tenants' => $this->subscriptions->console($filters, $today),
            'overview' => $this->subscriptions->overview($today, $this->addDays($today, self::SOON_DAYS)),
            'filters' => $filters,
            'today' => $today,
        ];
    }

    /**
     * @return array{tenant: Tenant, subscription: ?TenantSubscription, state: string, owners: list<array{id: int, full_name: string, email: string, is_active: bool}>, userCount: int, events: list<array<string, mixed>>, today: string}
     */
    public function detail(User $actor, int $id): array
    {
        $this->assertCan($actor, Permission::TENANTS_VIEW);
        $this->connect();
        $tenant = $this->requireTenant($id);
        $today = gmdate('Y-m-d');
        $canSee = $actor->can(Permission::SUBSCRIPTIONS_VIEW);
        $subscription = $canSee ? $this->subscriptions->find($id) : null;

        return [
            'tenant' => $tenant,
            'subscription' => $subscription,
            'state' => $subscription?->state($today) ?? 'none',
            'owners' => $this->subscriptions->owners($id),
            'userCount' => $this->subscriptions->countUsers($id),
            'events' => $canSee ? $this->subscriptions->events($id) : [],
            'today' => $today,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function apply(User $actor, int $id, array $input): string
    {
        $this->assertCan($actor, Permission::SUBSCRIPTIONS_MANAGE);
        $pdo = $this->connect();
        $this->requireTenant($id);
        $action = is_string($input['action'] ?? null) ? $input['action'] : '';
        $note = $this->note($input);
        $today = gmdate('Y-m-d');

        $pdo->beginTransaction();
        try {
            $current = $this->subscriptions->find($id);
            $state = $current?->state($today) ?? 'none';
            $message = match ($action) {
                'activate' => $this->activate($actor, $id, $current, $state, $input, $note, $today),
                'renew' => $this->renew($actor, $id, $current, $state, $note, $today),
                'suspend' => $this->suspend($actor, $id, $current, $state, $note),
                default => throw new ValidationException(['action' => 'Action inconnue.']),
            };
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        $this->logger?->info('subscription tenant=' . $id . ' action=' . $action . ' actor=' . $actor->id());

        return $message;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function activate(User $actor, int $id, ?TenantSubscription $current, string $state, array $input, string $note, string $today): string
    {
        if ($state === 'active') {
            throw new ValidationException(['action' => 'L\'abonnement est déjà actif. Utilisez le renouvellement.']);
        }
        $start = $this->date($input['period_start'] ?? null, 'period_start');
        $amount = $this->amount($input['monthly_amount'] ?? null) ?? $current?->monthlyAmount();
        $end = $this->periodEnd($start);
        $this->subscriptions->save($id, TenantSubscription::ACTIVE, $amount, $start, $end);
        $this->tenants->updateStatus($id, 'active');
        $this->subscriptions->addEvent($id, 'activate', $current?->status(), TenantSubscription::ACTIVE, $end, $note, $actor->id());

        return 'L\'abonnement est actif jusqu\'au ' . $this->frenchDate($end) . '. Le commerce peut se connecter.';
    }

    private function renew(User $actor, int $id, ?TenantSubscription $current, string $state, string $note, string $today): string
    {
        if ($current === null || !in_array($state, ['active', 'overdue'], true)) {
            throw new ValidationException(['action' => 'Seul un abonnement actif ou échu peut être renouvelé.']);
        }
        $previousEnd = $current->periodEnd();
        if ($previousEnd !== null && $previousEnd >= $today) {
            $start = $current->periodStart() ?? $today;
            $end = $this->periodEnd($this->addDays($previousEnd, 1));
        } else {
            $start = $today;
            $end = $this->periodEnd($today);
        }
        $this->subscriptions->save($id, TenantSubscription::ACTIVE, $current->monthlyAmount(), $start, $end);
        $this->tenants->updateStatus($id, 'active');
        $this->subscriptions->addEvent($id, 'renew', $current->status(), TenantSubscription::ACTIVE, $end, $note, $actor->id());

        return 'L\'abonnement est renouvelé jusqu\'au ' . $this->frenchDate($end) . '.';
    }

    private function suspend(User $actor, int $id, ?TenantSubscription $current, string $state, string $note): string
    {
        if ($current === null || !in_array($state, ['active', 'overdue'], true)) {
            throw new ValidationException(['action' => 'Seul un abonnement actif ou échu peut être suspendu.']);
        }
        $this->subscriptions->save($id, TenantSubscription::SUSPENDED, $current->monthlyAmount(), $current->periodStart(), $current->periodEnd());
        $this->tenants->updateStatus($id, 'suspended');
        $this->subscriptions->addEvent($id, 'suspend', $current->status(), TenantSubscription::SUSPENDED, $current->periodEnd(), $note, $actor->id());

        return 'L\'abonnement est suspendu. Le commerce ne peut plus se connecter et sa page publique est fermée.';
    }

    /**
     * @param array<string, mixed> $query
     * @return array{q: string, sector: string, status: string, subscription: string}
     */
    private function filters(array $query): array
    {
        $text = static fn (string $key): string => is_string($query[$key] ?? null) ? trim($query[$key]) : '';
        $q = mb_substr($text('q'), 0, 80);
        $sector = $text('secteur');
        $status = $text('etat');
        $subscription = $text('abonnement');

        return [
            'q' => $q,
            'sector' => in_array($sector, Sector::ALL, true) ? $sector : '',
            'status' => in_array($status, ['active', 'suspended'], true) ? $status : '',
            'subscription' => array_key_exists($subscription, TenantSubscription::LABELS) ? $subscription : '',
            'source' => $text('source') === 'site' ? 'site' : '',
        ];
    }

    private function requireTenant(int $id): Tenant
    {
        $tenant = $this->tenants->findById($id);
        if ($tenant === null) {
            throw new HttpException(404, 'Commerce introuvable.');
        }

        return $tenant;
    }

    private function date(mixed $value, string $field): string
    {
        $raw = is_string($value) ? trim($value) : '';
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new \DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $raw) {
            throw new ValidationException([$field => 'Indiquez une date valide.']);
        }

        return $raw;
    }

    private function amount(mixed $value): ?int
    {
        $raw = is_string($value) ? str_replace([' ', "\u{202F}", "\u{00A0}"], '', trim($value)) : '';
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{1,9}$/', $raw) !== 1 || (int) $raw > self::MAX_AMOUNT) {
            throw new ValidationException(['monthly_amount' => 'Indiquez un montant entier en FCFA.']);
        }

        return (int) $raw;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function note(array $input): string
    {
        $note = is_string($input['note'] ?? null) ? trim($input['note']) : '';
        if (mb_strlen($note) > 180) {
            throw new ValidationException(['note' => 'La note ne doit pas dépasser 180 caractères.']);
        }

        return $note;
    }

    private function periodEnd(string $start): string
    {
        $date = new \DateTimeImmutable($start, new \DateTimeZone('UTC'));
        $next = $date->modify('first day of next month');
        $day = min((int) $date->format('j'), (int) $next->format('t'));

        return $next->setDate((int) $next->format('Y'), (int) $next->format('n'), $day)->modify('-1 day')->format('Y-m-d');
    }

    private function addDays(string $date, int $days): string
    {
        return (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->modify('+' . $days . ' days')->format('Y-m-d');
    }

    private function frenchDate(string $date): string
    {
        return (new \DateTimeImmutable($date))->format('d/m/Y');
    }

    private function assertCan(User $actor, string $permission): void
    {
        if (!$actor->can($permission)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
    }
}
