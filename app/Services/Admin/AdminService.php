<?php

declare(strict_types=1);

namespace Sesam\Services\Admin;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\Tenant;
use Sesam\Models\TenantSubscription;
use Sesam\Models\User;
use Sesam\Repositories\AdminRepository;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\TenantSubscriptionRepository;
use Sesam\Services\Service;

/**
 * Console SESAM : tableau de bord, revenus, encaissements d'abonnement, notes et tarifs.
 */
final class AdminService extends Service
{
    public const METHODS = ['wave' => 'Wave', 'orange-money' => 'Orange Money', 'especes' => 'Espèces', 'virement' => 'Virement', 'cheque' => 'Chèque'];
    private const MONTHS = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

    private readonly \PDO $pdo;
    private readonly AdminRepository $admin;
    private readonly TenantRepository $tenants;
    private readonly TenantSubscriptionRepository $subscriptions;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(array $database)
    {
        $this->pdo = Connection::make($database);
        $this->admin = new AdminRepository($this->pdo);
        $this->tenants = new TenantRepository($this->pdo);
        $this->subscriptions = new TenantSubscriptionRepository($this->pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $actor): array
    {
        $this->assertStaff($actor);
        $today = gmdate('Y-m-d');
        $monthStart = gmdate('Y-m-01');
        $nextMonth = gmdate('Y-m-01', (int) strtotime('first day of next month'));
        $prevMonth = gmdate('Y-m-01', (int) strtotime('first day of last month'));
        $seeMoney = $actor->can(Permission::REVENUE_VIEW);
        $seeTenants = $actor->can(Permission::TENANTS_VIEW);
        $seeAgency = $actor->can(Permission::AGENCY_VIEW);
        $board = ['seeMoney' => $seeMoney, 'seeTenants' => $seeTenants, 'seeAgency' => $seeAgency];

        if ($seeTenants) {
            $board['overview'] = $this->subscriptions->overview($today, $this->addDays($today, 7));
            $board['recentTenants'] = $this->admin->recentTenants(5);
            $board['newTenants'] = $this->admin->countTenantsSince($monthStart . ' 00:00:00');
            $trialStats = $this->admin->statistics($monthStart, $nextMonth, $today);
            $board['trialRunning'] = $trialStats['trial_running'];
            $board['trialEnding'] = $trialStats['trial_ending'];
            $board['selfSignups'] = array_sum(array_map(static fn (array $m): int => (int) ($m['site'] ?? 0), $trialStats['signups']));
        }
        if ($seeMoney) {
            $series = $this->series(12);
            $current = $series[count($series) - 1] ?? ['saas' => 0, 'agency' => 0];
            $previous = $series[count($series) - 2] ?? ['saas' => 0, 'agency' => 0];
            $board['mrr'] = $this->admin->mrr($today);
            $board['series'] = $series;
            $board['month'] = $current['saas'] + $current['agency'];
            $board['monthPrevious'] = $previous['saas'] + $previous['agency'];
            $board['year'] = array_sum(array_map(static fn (array $m): int => $m['saas'] + $m['agency'], $series));
            $board['unpaid'] = $this->admin->unpaidInvoices($today);
            $board['due'] = $this->admin->dueSubscriptions($today, $this->addDays($today, 7));
            $board['dueAmount'] = array_sum(array_map(static fn (array $d): int => $d['overdue'] ? $d['amount'] : 0, $board['due']));
            $board['lastPayments'] = array_slice($this->admin->payments($prevMonth, $nextMonth, 8), 0, 6);
        }
        if ($seeAgency) {
            $board['agency'] = $this->admin->agencyPulse($this->addDays($today, -30) . ' 00:00:00');
            $board['requests'] = $this->admin->newRequests(5);
        }

        return $board;
    }

    /**
     * @return array<string, mixed>
     */
    public function revenue(User $actor, int $year, string $month): array
    {
        $this->assertCan($actor, Permission::REVENUE_VIEW);
        $year = $year >= 2020 && $year <= 2100 ? $year : (int) gmdate('Y');
        $byMonth = $this->admin->revenueByMonth($year . '-01-01', ($year + 1) . '-01-01');
        $rows = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = sprintf('%04d-%02d', $year, $m);
            $rows[$key] = ['label' => self::MONTHS[$m], 'saas' => $byMonth[$key]['saas'] ?? 0, 'agency' => $byMonth[$key]['agency'] ?? 0];
        }
        $from = $year . '-01-01';
        $to = ($year + 1) . '-01-01';
        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1 && str_starts_with($month, (string) $year)) {
            $from = $month . '-01';
            $to = gmdate('Y-m-01', (int) strtotime($from . ' +1 month'));
        } else {
            $month = '';
        }
        $today = gmdate('Y-m-d');

        return [
            'year' => $year,
            'month' => $month,
            'rows' => $rows,
            'totalSaas' => array_sum(array_column($rows, 'saas')),
            'totalAgency' => array_sum(array_column($rows, 'agency')),
            'payments' => $this->admin->payments($from, $to),
            'mrr' => $this->admin->mrr($today),
            'unpaid' => $this->admin->unpaidInvoices($today),
            'due' => $this->admin->dueSubscriptions($today, $this->addDays($today, 30)),
        ];
    }

    /**
     * Statistiques de pilotage sur 12 mois glissants : inscriptions, essais et conversion, revenus par module, agence.
     *
     * @return array<string, mixed>
     */
    public function statistics(User $actor): array
    {
        $this->assertCan($actor, Permission::REVENUE_VIEW);
        $today = gmdate('Y-m-d');
        $from = gmdate('Y-m-01', (int) strtotime('first day of -11 months'));
        $to = gmdate('Y-m-01', (int) strtotime('first day of next month'));
        $stats = $this->admin->statistics($from, $to, $today);
        $mrr = $this->admin->mrr($today);
        $series = $this->series(12);
        foreach ($series as &$month) {
            $month['site'] = $stats['signups'][$month['key']]['site'] ?? 0;
            $month['console'] = $stats['signups'][$month['key']]['console'] ?? 0;
        }
        unset($month);
        $quotes = ['sent' => 0, 'accepted' => 0, 'refused' => 0, 'draft' => 0, 'accepted_amount' => 0, 'total_amount' => 0];
        foreach ($stats['quotes'] as $row) {
            $quotes[(string) $row['status']] = (int) $row['n'];
            $quotes['total_amount'] += (int) $row['amount'];
            if ($row['status'] === 'accepted') {
                $quotes['accepted_amount'] = (int) $row['amount'];
            }
        }
        $decided = $quotes['accepted'] + $quotes['refused'];

        return $stats + [
            'mrr' => $mrr,
            'arpu' => $mrr['count'] > 0 ? intdiv($mrr['total'], $mrr['count']) : 0,
            'series' => $series,
            'conversion' => $stats['trials'] > 0 ? (int) round($stats['converted'] * 100 / $stats['trials']) : 0,
            'quoteStats' => $quotes + ['win_rate' => $decided > 0 ? (int) round($quotes['accepted'] * 100 / $decided) : 0],
            'yearSaas' => array_sum(array_column($series, 'saas')),
            'yearAgency' => array_sum(array_column($series, 'agency')),
        ];
    }

    /**
     * Export CSV (séparateur « ; », lisible par Excel en français).
     */
    public function revenueCsv(User $actor, int $year, string $month): string
    {
        $data = $this->revenue($actor, $year, $month);
        $lines = ["\u{FEFF}Date;Source;Libellé;Moyen;Référence;Montant (FCFA)"];
        foreach ($data['payments'] as $payment) {
            $lines[] = implode(';', array_map(static fn (string $v): string => '"' . str_replace('"', '""', $v) . '"', [
                $payment['date'],
                $payment['source'] === 'saas' ? 'Abonnement' : 'Agence',
                $payment['label'],
                self::METHODS[$payment['method']] ?? $payment['method'],
                $payment['reference'],
                (string) $payment['amount'],
            ]));
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @return array{payments: list<array<string, mixed>>, paidTotal: int, notes: list<array<string, string>>, usage: array<string, mixed>, defaultAmount: int}
     */
    public function tenantExtras(User $actor, Tenant $tenant, ?TenantSubscription $subscription): array
    {
        $canSee = $actor->can(Permission::SUBSCRIPTIONS_VIEW);
        $prices = $this->admin->prices();

        return [
            'payments' => $canSee ? $this->admin->tenantPayments($tenant->id()) : [],
            'paidTotal' => $canSee ? $this->admin->tenantPaidTotal($tenant->id()) : 0,
            'notes' => $this->admin->tenantNotes($tenant->id()),
            'usage' => $this->admin->usage($tenant->id(), $tenant->sector(), $this->addDays(gmdate('Y-m-d'), -30) . ' 00:00:00'),
            'defaultAmount' => $subscription?->monthlyAmount() ?? ($prices[$tenant->sector()]['monthly_amount'] ?? 0),
        ];
    }

    /**
     * Enregistre un paiement d'abonnement et prolonge l'accès d'autant de mois. Réactive un commerce suspendu ou sans abonnement.
     *
     * @param array<string, mixed> $input
     */
    public function recordPayment(User $actor, int $tenantId, array $input): string
    {
        $this->assertCan($actor, Permission::SUBSCRIPTIONS_MANAGE);
        $tenant = $this->tenants->findById($tenantId);
        if ($tenant === null) {
            throw new HttpException(404, 'Commerce introuvable.');
        }
        $errors = [];
        $amount = (int) preg_replace('/\D+/', '', (string) ($input['amount'] ?? ''));
        $months = (int) ($input['months'] ?? 1);
        $paidOn = trim((string) ($input['paid_on'] ?? ''));
        $method = (string) ($input['method'] ?? '');
        $reference = mb_substr(trim((string) ($input['reference'] ?? '')), 0, 80);
        $note = trim((string) ($input['note'] ?? ''));
        if ($amount < 1 || $amount > 100_000_000) {
            $errors['amount'] = 'Indiquez le montant encaissé.';
        }
        if ($months < 1 || $months > 12) {
            $errors['months'] = 'Entre 1 et 12 mois.';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidOn) !== 1 || $paidOn > gmdate('Y-m-d')) {
            $errors['paid_on'] = 'Date de paiement invalide (pas dans le futur).';
        }
        if (!isset(self::METHODS[$method])) {
            $errors['method'] = 'Choisissez le moyen de paiement.';
        }
        if (mb_strlen($note) > 180) {
            $errors['note'] = 'Note trop longue (180 caractères max.).';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $today = gmdate('Y-m-d');
        $this->pdo->beginTransaction();
        try {
            $current = $this->subscriptions->find($tenantId);
            $state = $current?->state($today) ?? 'none';
            if ($state === 'active' && $current?->periodEnd() !== null) {
                $start = $current->periodStart() ?? $today;
                $end = $this->addMonths($this->addDays((string) $current->periodEnd(), 1), $months);
            } else {
                $start = $paidOn < $today && $state === 'none' ? $paidOn : $today;
                $end = $this->addMonths($start, $months);
            }
            $monthly = $current?->monthlyAmount() ?? intdiv($amount, $months);
            $this->subscriptions->save($tenantId, TenantSubscription::ACTIVE, $monthly, $start, $end);
            $this->tenants->updateStatus($tenantId, 'active');
            $label = number_format($amount, 0, ',', ' ') . ' FCFA par ' . self::METHODS[$method] . ($note !== '' ? ' — ' . $note : '');
            $this->subscriptions->addEvent($tenantId, $state === 'active' || $state === 'overdue' ? 'renew' : 'activate', $current?->status(), TenantSubscription::ACTIVE, $end, mb_substr('Paiement ' . $label, 0, 180), $actor->id());
            $this->admin->insertPayment($tenantId, [
                'amount' => $amount,
                'months' => $months,
                'paid_on' => $paidOn,
                'method' => $method,
                'reference' => $reference,
                'period_end' => $end,
                'note' => $note,
                'actor_user_id' => $actor->id(),
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        \Sesam\Services\Notifier::staff(
            'sesam.payment',
            'Paiement encaissé : ' . $tenant->displayName(),
            number_format($amount, 0, ',', ' ') . ' FCFA enregistrés par ' . $actor->fullName() . ' · actif jusqu\'au ' . gmdate('d/m/Y', (int) strtotime($end . ' UTC')),
            '/commerces/' . $tenantId,
            'success',
            null,
            $actor->id(),
        );
        \Sesam\Services\Notifier::tenant(
            $tenantId,
            'subscription',
            'Paiement d\'abonnement reçu ✓',
            number_format($amount, 0, ',', ' ') . ' FCFA enregistrés par SESAM. Votre espace est actif jusqu\'au ' . gmdate('d/m/Y', (int) strtotime($end . ' UTC')) . '.',
            '/abonnement',
            'success',
        );

        return 'Paiement de ' . number_format($amount, 0, ',', ' ') . ' FCFA enregistré. Accès prolongé jusqu\'au ' . gmdate('d/m/Y', (int) strtotime($end . ' UTC')) . '.';
    }

    public function addNote(User $actor, int $tenantId, string $body): void
    {
        $this->assertCan($actor, Permission::TENANTS_VIEW);
        if ($this->tenants->findById($tenantId) === null) {
            throw new HttpException(404, 'Commerce introuvable.');
        }
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 1000) {
            throw new ValidationException(['note_body' => 'Écrivez une note (1 000 caractères max.).']);
        }
        $this->admin->insertNote($tenantId, $body, $actor->id());
    }

    /**
     * @return array<string, array{label: string, monthly_amount: int, setup_fee: int}>
     */
    public function prices(User $actor): array
    {
        $this->assertCan($actor, Permission::TENANTS_VIEW);
        $stored = $this->admin->prices();
        $prices = [];
        foreach (Sector::LABELS as $sector => $label) {
            $prices[$sector] = ['label' => $label] + ($stored[$sector] ?? ['monthly_amount' => 0, 'setup_fee' => 0]);
        }

        return $prices;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function savePrices(User $actor, array $input): void
    {
        $this->assertCan($actor, Permission::SUBSCRIPTIONS_MANAGE);
        $errors = [];
        $clean = [];
        foreach (array_keys(Sector::LABELS) as $sector) {
            $monthly = (int) preg_replace('/\D+/', '', (string) ($input[$sector . '_monthly'] ?? '0'));
            $setup = (int) preg_replace('/\D+/', '', (string) ($input[$sector . '_setup'] ?? '0'));
            if ($monthly > 10_000_000 || $setup > 100_000_000) {
                $errors[$sector . '_monthly'] = 'Montant trop élevé.';
            }
            $clean[$sector] = [$monthly, $setup];
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        foreach ($clean as $sector => [$monthly, $setup]) {
            $this->admin->savePrice($sector, $monthly, $setup);
        }
    }

    /**
     * Encaissements des N derniers mois, du plus ancien au plus récent.
     *
     * @return list<array{key: string, label: string, saas: int, agency: int}>
     */
    private function series(int $months): array
    {
        $first = gmdate('Y-m-01', (int) strtotime('first day of -' . ($months - 1) . ' months'));
        $byMonth = $this->admin->revenueByMonth($first, gmdate('Y-m-01', (int) strtotime('first day of next month')));
        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $ts = (int) strtotime($first . ' +' . $i . ' months');
            $key = gmdate('Y-m', $ts);
            $series[] = ['key' => $key, 'label' => self::MONTHS[(int) gmdate('n', $ts)], 'saas' => $byMonth[$key]['saas'] ?? 0, 'agency' => $byMonth[$key]['agency'] ?? 0];
        }

        return $series;
    }

    /** Fin de période : même jour N mois plus tard, moins un jour. */
    private function addMonths(string $start, int $months): string
    {
        $date = new \DateTimeImmutable($start, new \DateTimeZone('UTC'));
        $target = $date->modify('first day of +' . $months . ' months');
        $day = min((int) $date->format('j'), (int) $target->format('t'));

        return $target->setDate((int) $target->format('Y'), (int) $target->format('n'), $day)->modify('-1 day')->format('Y-m-d');
    }

    private function addDays(string $date, int $days): string
    {
        return (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
    }

    private function assertStaff(User $actor): void
    {
        if ($actor->tenantId() !== null) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
    }

    private function assertCan(User $actor, string $permission): void
    {
        $this->assertStaff($actor);
        if (!$actor->can($permission)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
    }
}
