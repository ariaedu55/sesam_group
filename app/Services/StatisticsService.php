<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Boutique\OrderStatus;
use Sesam\Core\Crm\ClientActivityKind;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Payment\PaymentStatus;
use Sesam\Core\Reservation\AppointmentStatus;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Models\Tenant;
use Sesam\Models\User;
use Sesam\Repositories\LoyerLeaseRepository;
use Sesam\Repositories\StatisticsRepository;

final class StatisticsService extends Service
{
    public const DEFAULT_PERIOD = '30j';

    /** @var array<string, array{days: int, months: int, label: string}> */
    public const PERIODS = [
        '30j' => ['days' => 30, 'months' => 6, 'label' => '30 jours'],
        '90j' => ['days' => 90, 'months' => 6, 'label' => '90 jours'],
        '12m' => ['days' => 365, 'months' => 12, 'label' => '12 mois'],
    ];

    private const TOP_LIMIT = 5;

    /** @var array<int, string> */
    private const MONTHS = [
        1 => 'janv.', 2 => 'févr.', 3 => 'mars', 4 => 'avr.', 5 => 'mai', 6 => 'juin',
        7 => 'juil.', 8 => 'août', 9 => 'sept.', 10 => 'oct.', 11 => 'nov.', 12 => 'déc.',
    ];

    /** @var array<string, string> */
    private const ACTIVITY_LABELS = [
        ClientActivityKind::CREATED => 'Fiches clients créées',
        ClientActivityKind::UPDATED => 'Fiches mises à jour',
        ClientActivityKind::NOTE_ADDED => 'Notes ajoutées',
        ClientActivityKind::TAG_ADDED => 'Étiquettes posées',
        ClientActivityKind::TAG_REMOVED => 'Étiquettes retirées',
        ClientActivityKind::ARCHIVED => 'Clients archivés',
        ClientActivityKind::RESTORED => 'Clients restaurés',
    ];

    private ?\PDO $pdo = null;
    private ?StatisticsRepository $stats = null;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        private readonly array $database,
        private readonly TenantContext $context,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function report(User $actor, string $period): array
    {
        $tenant = $this->requireTenant($actor);
        $period = array_key_exists($period, self::PERIODS) ? $period : self::DEFAULT_PERIOD;
        $config = self::PERIODS[$period];
        $stats = $this->repository();
        $tenantId = $tenant->id();

        $today = new \DateTimeImmutable(gmdate('Y-m-d'), new \DateTimeZone('UTC'));
        $end = $today->modify('+1 day');
        $start = $today->modify('-' . ($config['days'] - 1) . ' days');
        $previousStart = $start->modify('-' . $config['days'] . ' days');
        $from = $start->format('Y-m-d 00:00:00');
        $to = $end->format('Y-m-d 00:00:00');

        $revenue = $stats->revenue($tenantId, $from, $to);
        $previous = $stats->revenue($tenantId, $previousStart->format('Y-m-d 00:00:00'), $from);
        $statusCounts = $stats->paymentStatusCounts($tenantId, $from, $to);
        $initiated = array_sum($statusCounts);
        $succeeded = $statusCounts[PaymentStatus::SUCCESS] ?? 0;
        $clients = $stats->clientCounts($tenantId, $from, $to) + $stats->payingClients($tenantId, $from, $to);

        return [
            'period' => [
                'key' => $period,
                'label' => $config['label'],
                'from' => $start->format('Y-m-d'),
                'to' => $today->format('Y-m-d'),
            ],
            'periods' => array_map(static fn (array $item): string => $item['label'], self::PERIODS),
            'sector' => $tenant->sector(),
            'revenue' => [
                'amount' => $revenue['amount'],
                'count' => $revenue['count'],
                'previous' => $previous['amount'],
                'delta' => $previous['amount'] > 0
                    ? (int) round(($revenue['amount'] - $previous['amount']) * 100 / $previous['amount'])
                    : null,
                'average' => $revenue['count'] > 0 ? intdiv($revenue['amount'], $revenue['count']) : 0,
            ],
            'payments' => [
                'initiated' => $initiated,
                'succeeded' => $succeeded,
                'rate' => $this->rate($succeeded, $initiated),
            ],
            'unpaid' => $this->unpaid($tenant, $today->format('Y-m-d')),
            'clients' => $clients,
            'trend' => $this->trend($tenantId, $today, $config['months'], $to),
            'paymentMix' => $this->bars([
                PaymentStatus::SUCCESS => PaymentStatus::label(PaymentStatus::SUCCESS),
                PaymentStatus::PENDING => PaymentStatus::label(PaymentStatus::PENDING),
                PaymentStatus::FAILED => PaymentStatus::label(PaymentStatus::FAILED),
                PaymentStatus::CANCELLED => PaymentStatus::label(PaymentStatus::CANCELLED),
            ], $statusCounts),
            'activity' => $this->bars(self::ACTIVITY_LABELS, $stats->activityByKind($tenantId, $from, $to), true),
            'module' => $this->module($tenant, $from, $to, $start->format('Y-m-d'), $end->format('Y-m-d')),
        ];
    }

    /**
     * @return array{label: string, amount: int, count: int, hint: string}
     */
    private function unpaid(Tenant $tenant, string $today): array
    {
        $module = match ($tenant->sector()) {
            Sector::LOYER => 'loyer',
            default => null,
        };
        if ($module !== null) {
            $overdue = $this->repository()->overdue($module, $tenant->id(), $today);
            $unit = 'échéance';

            return [
                'label' => 'Impayés en retard',
                'amount' => $overdue['amount'],
                'count' => $overdue['count'],
                'hint' => $this->plural($overdue['count'], $unit . ' en retard', $unit . 's en retard'),
            ];
        }
        $pending = $this->repository()->pendingPayments($tenant->id());

        return [
            'label' => 'Paiements en attente',
            'amount' => $pending['amount'],
            'count' => $pending['count'],
            'hint' => $this->plural($pending['count'], 'paiement non réglé', 'paiements non réglés'),
        ];
    }

    /**
     * @return list<array{month: string, label: string, year: string, short: string, full: string, amount: int, count: int, ratio: int}>
     */
    private function trend(int $tenantId, \DateTimeImmutable $today, int $months, string $to): array
    {
        $first = $today->modify('first day of this month')->modify('-' . ($months - 1) . ' months');
        $totals = $this->repository()->monthlyRevenue($tenantId, $first->format('Y-m-d 00:00:00'), $to);
        $rows = [];
        for ($i = 0; $i < $months; ++$i) {
            $month = $first->modify('+' . $i . ' months');
            $key = $month->format('Y-m');
            $label = self::MONTHS[(int) $month->format('n')] ?? $key;
            $rows[] = [
                'month' => $key,
                'label' => $label,
                'year' => $i === 0 || $month->format('n') === '1' ? $month->format('y') : '',
                'short' => mb_strtoupper(mb_substr($label, 0, 1)),
                'full' => $label . ' ' . $month->format('Y'),
                'amount' => $totals[$key]['amount'] ?? 0,
                'count' => $totals[$key]['count'] ?? 0,
                'ratio' => 0,
            ];
        }
        $max = max(array_column($rows, 'amount') ?: [0]);
        foreach ($rows as $index => $row) {
            $rows[$index]['ratio'] = $max > 0 ? (int) round($row['amount'] * 100 / $max) : 0;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function module(Tenant $tenant, string $from, string $to, string $fromDate, string $toDate): array
    {
        $stats = $this->repository();
        $tenantId = $tenant->id();

        return match ($tenant->sector()) {
            Sector::BOUTIQUE => $this->boutiqueModule($stats, $tenantId, $from, $to),
            Sector::LOYER => $this->loyerModule($tenantId, $fromDate, $toDate),
            default => $this->reservationModule($stats, $tenantId, $from, $to),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function reservationModule(StatisticsRepository $stats, int $tenantId, string $from, string $to): array
    {
        $counts = $stats->appointmentStatusCounts($tenantId, $from, $to);
        $total = array_sum($counts);
        $confirmed = ($counts[AppointmentStatus::CONFIRMED] ?? 0) + ($counts[AppointmentStatus::COMPLETED] ?? 0);
        $cancelled = $counts[AppointmentStatus::CANCELLED] ?? 0;

        return [
            'title' => 'Rendez-vous',
            'link' => ['href' => '/reservation/rendez-vous', 'label' => 'Voir les rendez-vous', 'permission' => Permission::RESERVATION_VIEW],
            'kpis' => [
                ['label' => 'Rendez-vous', 'value' => $total, 'format' => 'number', 'hint' => 'prévus sur la période'],
                ['label' => 'Taux de confirmation', 'value' => $this->rate($confirmed, $total), 'format' => 'percent', 'hint' => $this->plural($confirmed, 'confirmé ou terminé', 'confirmés ou terminés')],
                ['label' => 'Terminés', 'value' => $counts[AppointmentStatus::COMPLETED] ?? 0, 'format' => 'number', 'hint' => 'prestations réalisées'],
                ['label' => 'Annulations', 'value' => $this->rate($cancelled, $total), 'format' => 'percent', 'hint' => $this->plural($cancelled, 'rendez-vous annulé', 'rendez-vous annulés')],
            ],
            'breakdown' => [
                'title' => 'Rendez-vous par statut',
                'rows' => $this->bars([
                    AppointmentStatus::PENDING => AppointmentStatus::label(AppointmentStatus::PENDING),
                    AppointmentStatus::CONFIRMED => AppointmentStatus::label(AppointmentStatus::CONFIRMED),
                    AppointmentStatus::COMPLETED => AppointmentStatus::label(AppointmentStatus::COMPLETED),
                    AppointmentStatus::CANCELLED => AppointmentStatus::label(AppointmentStatus::CANCELLED),
                ], $counts),
            ],
            'top' => [
                'title' => 'Prestations les plus demandées',
                'unit' => 'rdv',
                'amountHint' => 'au tarif',
                'rows' => $this->ranked($stats->topServices($tenantId, $from, $to, self::TOP_LIMIT)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function boutiqueModule(StatisticsRepository $stats, int $tenantId, string $from, string $to): array
    {
        $counts = $stats->orderStatusCounts($tenantId, $from, $to);
        $total = array_sum($counts);
        $cancelled = $counts[OrderStatus::CANCELLED] ?? 0;
        $sold = ($counts[OrderStatus::CONFIRMED] ?? 0) + ($counts[OrderStatus::DELIVERED] ?? 0);
        $labels = [];
        foreach (OrderStatus::ALL as $status) {
            $labels[$status] = OrderStatus::label($status);
        }

        return [
            'title' => 'Boutique',
            'link' => ['href' => '/boutique/commandes', 'label' => 'Voir les commandes', 'permission' => Permission::BOUTIQUE_VIEW],
            'kpis' => [
                ['label' => 'Commandes', 'value' => $total, 'format' => 'number', 'hint' => 'reçues sur la période'],
                ['label' => 'Ventes', 'value' => $stats->orderValue($tenantId, $from, $to), 'format' => 'money', 'hint' => 'commandes confirmées ou livrées'],
                ['label' => 'Taux de confirmation', 'value' => $this->rate($sold, $total), 'format' => 'percent', 'hint' => $this->plural($sold, 'commande vendue', 'commandes vendues')],
                ['label' => 'Annulations', 'value' => $this->rate($cancelled, $total), 'format' => 'percent', 'hint' => $this->plural($cancelled, 'commande annulée', 'commandes annulées')],
            ],
            'breakdown' => ['title' => 'Commandes par étape', 'rows' => $this->bars($labels, $counts)],
            'top' => [
                'title' => 'Produits les plus vendus',
                'unit' => 'pièces',
                'amountHint' => 'vendu',
                'rows' => $this->ranked($stats->topProducts($tenantId, $from, $to, self::TOP_LIMIT)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loyerModule(int $tenantId, string $fromDate, string $toDate): array
    {
        $collection = $this->repository()->dueCollection('loyer', $tenantId, $fromDate, $toDate);
        $unpaid = $collection['count'] - $collection['paid'];

        return [
            'title' => 'Loyers',
            'link' => ['href' => '/immo/loyers', 'label' => 'Voir les loyers', 'permission' => Permission::LOYER_VIEW],
            'kpis' => [
                ['label' => 'Loyers attendus', 'value' => $collection['expected'], 'format' => 'money', 'hint' => $this->plural($collection['count'], 'échéance arrivée à terme', 'échéances arrivées à terme')],
                ['label' => 'Encaissé', 'value' => $collection['collected'], 'format' => 'money', 'hint' => $this->plural($collection['paid'], 'échéance réglée', 'échéances réglées')],
                ['label' => 'Taux de recouvrement', 'value' => $this->rate($collection['collected'], $collection['expected']), 'format' => 'percent', 'hint' => 'montant encaissé sur attendu'],
                ['label' => 'Contrats actifs', 'value' => (new LoyerLeaseRepository($this->connect()))->countActive($tenantId), 'format' => 'number', 'hint' => 'à ce jour'],
            ],
            'breakdown' => [
                'title' => 'Échéances de la période',
                'rows' => $this->bars(['paid' => 'Réglées', 'unpaid' => 'Non réglées'], ['paid' => $collection['paid'], 'unpaid' => $unpaid]),
            ],
            'top' => null,
        ];
    }

    /**
     * @param array<string, string> $labels
     * @param array<string, int> $counts
     * @return list<array{key: string, label: string, count: int, ratio: int}>
     */
    private function bars(array $labels, array $counts, bool $skipEmpty = false): array
    {
        $max = max([0, ...array_values($counts)]);
        $rows = [];
        foreach ($labels as $key => $label) {
            $count = $counts[$key] ?? 0;
            if ($skipEmpty && $count === 0) {
                continue;
            }
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'ratio' => $max > 0 ? (int) round($count * 100 / $max) : 0,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array{label: string, count: int, amount: int}> $rows
     * @return list<array{label: string, count: int, amount: int, ratio: int}>
     */
    private function ranked(array $rows): array
    {
        $max = max([0, ...array_column($rows, 'count')]);

        return array_map(
            static fn (array $row): array => $row + ['ratio' => $max > 0 ? (int) round($row['count'] * 100 / $max) : 0],
            $rows
        );
    }

    private function rate(int $part, int $whole): ?int
    {
        return $whole > 0 ? (int) round($part * 100 / $whole) : null;
    }

    private function plural(int $count, string $singular, string $plural): string
    {
        return number_format($count, 0, ',', ' ') . ' ' . ($count > 1 ? $plural : $singular);
    }

    private function requireTenant(User $actor): Tenant
    {
        $tenant = $this->context->tenant();
        if ($actor->tenantId() === null || !$tenant instanceof Tenant || $tenant->id() !== $actor->tenantId()) {
            throw new HttpException(403, 'Les statistiques sont propres à chaque commerce.');
        }
        if (!$actor->can(Permission::STATS_VIEW)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }

        return $tenant;
    }

    private function repository(): StatisticsRepository
    {
        return $this->stats ??= new StatisticsRepository($this->connect());
    }

    private function connect(): \PDO
    {
        return $this->pdo ??= Connection::make($this->database);
    }
}
