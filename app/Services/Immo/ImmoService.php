<?php

declare(strict_types=1);

namespace Sesam\Services\Immo;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Boutique\Money;
use Sesam\Core\Boutique\WhatsApp;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Immo\ImmoCatalog;
use Sesam\Core\Immo\ImmoLedger;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\User;
use Sesam\Services\MessagingService;
use Sesam\Services\PaymentService;
use Sesam\Services\Service;

/**
 * Sesam Immo : biens et logements, locataires, baux, loyers (paiements partiels, quittances),
 * relances automatiques et manuelles, alertes, interventions et réglages de l'agence.
 */
final class ImmoService extends Service
{
    private const SETTING_DEFAULTS = [
        'display_name' => '',
        'whatsapp_phone' => '',
        'email' => '',
        'address' => '',
        'receipt_footer' => '',
        'signature_name' => '',
        'default_due_day' => 5,
        'grace_days' => 3,
        'remind_before_days' => 3,
        'remind_on_due' => 1,
        'remind_after_days' => '3,7,15',
        'auto_reminders' => 1,
        'reminder_message' => '',
        'allow_partial' => 1,
        'min_partial_minor' => 5000,
        'lease_end_alert_days' => 60,
    ];

    private readonly \PDO $pdo;
    private readonly ImmoLedger $ledger;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        array $database,
        private readonly TenantContext $context,
        private readonly ?PaymentService $payments = null,
        private readonly ?MessagingService $messaging = null,
    ) {
        $this->pdo = Connection::make($database);
        $this->ledger = new ImmoLedger($this->pdo);
    }

    // ================================================================ Réglages

    /**
     * @return array<string, mixed>
     */
    public function settingsFor(int $tenantId): array
    {
        $row = $this->row('SELECT * FROM immo_settings WHERE tenant_id = ?', [$tenantId]);
        $settings = self::SETTING_DEFAULTS;
        foreach ($settings as $key => $default) {
            if ($row !== null && array_key_exists($key, $row) && $row[$key] !== null) {
                $settings[$key] = is_int($default) ? (int) $row[$key] : (string) $row[$key];
            }
        }
        if ($settings['display_name'] === '') {
            $settings['display_name'] = $this->context->tenant()?->displayName() ?? '';
        }
        if ($settings['reminder_message'] === '') {
            $settings['reminder_message'] = ImmoCatalog::DEFAULT_MESSAGE;
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(User $actor): array
    {
        return $this->settingsFor($this->tenantId($actor, Permission::LOYER_VIEW));
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveSettings(User $actor, array $input): void
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_SETTINGS);
        $errors = [];
        $text = static fn (string $key, int $max): string => mb_substr(trim((string) ($input[$key] ?? '')), 0, $max);
        $int = static fn (string $key): int => (int) preg_replace('/\D+/', '', (string) ($input[$key] ?? '0'));
        $phoneRaw = $text('whatsapp_phone', 40);
        $phone = $phoneRaw === '' ? '' : WhatsApp::normalize($phoneRaw);
        if ($phoneRaw !== '' && !WhatsApp::isValid($phone)) {
            $errors['whatsapp_phone'] = 'Numéro WhatsApp invalide. Exemple : 77 123 45 67.';
        }
        $email = $text('email', 190);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'E-mail invalide.';
        }
        $dueDay = $int('default_due_day');
        if ($dueDay < 1 || $dueDay > 28) {
            $errors['default_due_day'] = 'Entre le 1er et le 28 du mois.';
        }
        $after = array_values(array_unique(array_filter(
            array_map('intval', preg_split('/[^\d]+/', (string) ($input['remind_after_days'] ?? '')) ?: []),
            static fn (int $d): bool => $d > 0 && $d <= 90,
        )));
        sort($after);
        $message = $text('reminder_message', 600);
        if ($message !== '' && !str_contains($message, '{reste}') && !str_contains($message, '{montant}')) {
            $errors['reminder_message'] = 'Le message doit contenir {reste} ou {montant} pour indiquer la somme due.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $data = [
            'display_name' => $text('display_name', 120),
            'whatsapp_phone' => $phone,
            'email' => $email,
            'address' => $text('address', 255),
            'receipt_footer' => $text('receipt_footer', 500),
            'signature_name' => $text('signature_name', 120),
            'default_due_day' => $dueDay,
            'grace_days' => min(30, $int('grace_days')),
            'remind_before_days' => min(30, $int('remind_before_days')),
            'remind_on_due' => empty($input['remind_on_due']) ? 0 : 1,
            'remind_after_days' => implode(',', array_slice($after, 0, 5)),
            'auto_reminders' => empty($input['auto_reminders']) ? 0 : 1,
            'reminder_message' => $message,
            'allow_partial' => empty($input['allow_partial']) ? 0 : 1,
            'min_partial_minor' => min(10_000_000, $int('min_partial_minor')),
            'lease_end_alert_days' => max(7, min(365, $int('lease_end_alert_days'))),
        ];
        $columns = array_keys($data);
        $this->pdo->prepare(
            'INSERT INTO immo_settings (tenant_id, ' . implode(', ', $columns) . ', created_at, updated_at)
             VALUES (?, ' . implode(', ', array_fill(0, count($columns), '?')) . ', UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(static fn (string $c): string => $c . ' = VALUES(' . $c . ')', $columns)) . ', updated_at = UTC_TIMESTAMP()'
        )->execute(array_merge([$tenantId], array_values($data)));
    }

    // ================================================================ Échéancier

    /**
     * Crée les échéances manquantes de chaque bail actif, jusqu'au mois prochain (ou la fin du bail).
     */
    public function ensureSchedule(int $tenantId, ?string $today = null): int
    {
        $today ??= gmdate('Y-m-d');
        $utc = new \DateTimeZone('UTC');
        $horizon = (new \DateTimeImmutable($today, $utc))->modify('first day of next month');
        // On ne remonte jamais avant la dernière échéance connue : un bail repris en cours de route n'invente pas d'impayés passés.
        $leases = $this->rows(
            "SELECT l.id, l.rent_minor, l.charges_minor, l.due_day, l.ends_on,
                    COALESCE(DATE_ADD(DATE_FORMAT(MAX(d.due_date), '%Y-%m-01'), INTERVAL 1 MONTH), l.starts_on) AS starts_on
             FROM loyer_leases l LEFT JOIN loyer_rent_dues d ON d.lease_id = l.id
             WHERE l.tenant_id = ? AND l.status = 'active' GROUP BY l.id",
            [$tenantId]
        );
        $insert = $this->pdo->prepare(
            "INSERT INTO loyer_rent_dues (tenant_id, lease_id, due_date, amount_minor, rent_part_minor, charges_part_minor, paid_minor, status, created_at, updated_at)
             SELECT ?, ?, ?, ?, ?, ?, 0, 'pending', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM loyer_rent_dues WHERE lease_id = ? AND DATE_FORMAT(due_date, '%Y-%m') = ?)"
        );
        $created = 0;
        foreach ($leases as $lease) {
            $month = (new \DateTimeImmutable((string) $lease['starts_on'], $utc))->modify('first day of this month');
            $end = $lease['ends_on'] !== null ? new \DateTimeImmutable((string) $lease['ends_on'], $utc) : null;
            $guard = 0;
            while ($month <= $horizon && $guard++ < 240) {
                $day = min((int) $lease['due_day'], (int) $month->format('t'));
                $dueDate = $month->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                if ($end !== null && $dueDate > $end->format('Y-m-d')) {
                    break;
                }
                // Le mois d'entrée ne compte que si le bail commence avant l'échéance du mois.
                if ($dueDate >= (string) $lease['starts_on'] || $month->format('Y-m') !== substr((string) $lease['starts_on'], 0, 7)) {
                    $rent = (int) $lease['rent_minor'];
                    $charges = (int) $lease['charges_minor'];
                    $insert->execute([$tenantId, (int) $lease['id'], $dueDate, $rent + $charges, $rent, $charges, (int) $lease['id'], $month->format('Y-m')]);
                    $created += $insert->rowCount();
                }
                $month = $month->modify('+1 month');
            }
        }

        return $created;
    }

    // ================================================================ Tableau de bord et alertes

    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $actor): array
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_VIEW);
        $this->ensureSchedule($tenantId);
        $settings = $this->settingsFor($tenantId);
        $sent = $settings['auto_reminders'] === 1 ? $this->runReminders($tenantId) : 0;
        $today = gmdate('Y-m-d');
        $monthStart = gmdate('Y-m-01');
        $nextMonth = gmdate('Y-m-01', (int) strtotime('first day of next month'));

        $expected = $this->row(
            "SELECT COALESCE(SUM(amount_minor), 0) AS expected, COALESCE(SUM(paid_minor), 0) AS paid FROM loyer_rent_dues
             WHERE tenant_id = ? AND due_date >= ? AND due_date < ? AND status <> 'cancelled'",
            [$tenantId, $monthStart, $nextMonth]
        ) ?? ['expected' => 0, 'paid' => 0];
        $collected = (int) $this->value('SELECT COALESCE(SUM(amount_minor), 0) FROM immo_payments WHERE tenant_id = ? AND paid_on >= ? AND paid_on < ?', [$tenantId, $monthStart, $nextMonth]);
        $arrears = $this->row(
            "SELECT COUNT(*) AS n, COALESCE(SUM(amount_minor - paid_minor), 0) AS amount FROM loyer_rent_dues d
             WHERE d.tenant_id = ? AND d.status IN ('pending', 'partial') AND DATE_ADD(d.due_date, INTERVAL ? DAY) < ?",
            [$tenantId, (int) $settings['grace_days'], $today]
        ) ?? ['n' => 0, 'amount' => 0];
        $units = $this->row(
            "SELECT COUNT(*) AS total,
                    SUM(EXISTS (SELECT 1 FROM loyer_leases l WHERE l.unit_id = u.id AND l.status = 'active')) AS occupied
             FROM loyer_units u WHERE u.tenant_id = ? AND u.is_active = 1",
            [$tenantId]
        ) ?? ['total' => 0, 'occupied' => 0];
        $total = (int) $units['total'];
        $occupied = (int) $units['occupied'];

        return [
            'settings' => $settings,
            'remindersSent' => $sent,
            'kpis' => [
                'expected' => (int) $expected['expected'],
                'paid' => (int) $expected['paid'],
                'collected' => $collected,
                'rate' => (int) $expected['expected'] > 0 ? (int) round((int) $expected['paid'] * 100 / (int) $expected['expected']) : 0,
                'arrears_count' => (int) $arrears['n'],
                'arrears_amount' => (int) $arrears['amount'],
                'units' => $total,
                'occupied' => $occupied,
                'vacant' => $total - $occupied,
                'occupancy' => $total > 0 ? (int) round($occupied * 100 / $total) : 0,
                'leases' => (int) $this->value("SELECT COUNT(*) FROM loyer_leases WHERE tenant_id = ? AND status = 'active'", [$tenantId]),
            ],
            'alerts' => $this->alerts($tenantId, $settings, $today),
            'upcoming' => $this->dueRows($tenantId, "d.status IN ('pending', 'partial') AND d.due_date BETWEEN ? AND ?", [$today, gmdate('Y-m-d', (int) strtotime('+10 days'))], 'd.due_date ASC', 8, $settings),
            'recentPayments' => $this->rows(
                'SELECT p.id, p.amount_minor, p.method, p.paid_on, p.receipt_number, d.due_date, c.full_name, u.label AS unit_label
                 FROM immo_payments p JOIN loyer_rent_dues d ON d.id = p.due_id JOIN loyer_leases l ON l.id = p.lease_id
                 JOIN clients c ON c.id = l.client_id JOIN loyer_units u ON u.id = l.unit_id
                 WHERE p.tenant_id = ? ORDER BY p.paid_on DESC, p.id DESC LIMIT 6',
                [$tenantId]
            ),
            'months' => $this->collectionByMonth($tenantId, 6),
        ];
    }

    /**
     * Ce qui demande une action : retards, échéances proches, paiements partiels, baux qui se terminent,
     * cautions non versées, logements vides, interventions urgentes.
     *
     * @param array<string, mixed> $settings
     * @return list<array{level: string, title: string, detail: string, href: string}>
     */
    public function alerts(int $tenantId, array $settings, string $today): array
    {
        $alerts = [];
        $grace = (int) $settings['grace_days'];
        $late = $this->row(
            "SELECT COUNT(*) AS n, COALESCE(SUM(amount_minor - paid_minor), 0) AS amount, COUNT(DISTINCT lease_id) AS leases
             FROM loyer_rent_dues WHERE tenant_id = ? AND status IN ('pending', 'partial') AND DATE_ADD(due_date, INTERVAL ? DAY) < ?",
            [$tenantId, $grace, $today]
        );
        if ($late !== null && (int) $late['n'] > 0) {
            $alerts[] = ['level' => 'danger', 'title' => (int) $late['n'] . ' loyer(s) en retard', 'detail' => Money::format((int) $late['amount']) . ' à récupérer auprès de ' . (int) $late['leases'] . ' locataire(s)', 'href' => '/immo/loyers?vue=retards'];
        }
        $soon = $this->row(
            "SELECT COUNT(*) AS n, COALESCE(SUM(amount_minor - paid_minor), 0) AS amount FROM loyer_rent_dues
             WHERE tenant_id = ? AND status IN ('pending', 'partial') AND due_date BETWEEN ? AND ?",
            [$tenantId, $today, gmdate('Y-m-d', (int) strtotime($today . ' +7 days'))]
        );
        if ($soon !== null && (int) $soon['n'] > 0) {
            $alerts[] = ['level' => 'warning', 'title' => (int) $soon['n'] . ' échéance(s) dans les 7 jours', 'detail' => Money::format((int) $soon['amount']) . ' attendus', 'href' => '/immo/loyers?vue=a-encaisser'];
        }
        $partial = (int) $this->value("SELECT COUNT(*) FROM loyer_rent_dues WHERE tenant_id = ? AND status = 'partial'", [$tenantId]);
        if ($partial > 0) {
            $alerts[] = ['level' => 'info', 'title' => $partial . ' loyer(s) payé(s) en partie', 'detail' => 'Le reste est à encaisser', 'href' => '/immo/loyers?vue=partiels'];
        }
        $ending = $this->rows(
            "SELECT l.id, c.full_name, l.ends_on FROM loyer_leases l JOIN clients c ON c.id = l.client_id
             WHERE l.tenant_id = ? AND l.status = 'active' AND l.ends_on IS NOT NULL AND l.ends_on BETWEEN ? AND ? ORDER BY l.ends_on",
            [$tenantId, $today, gmdate('Y-m-d', (int) strtotime($today . ' +' . (int) $settings['lease_end_alert_days'] . ' days'))]
        );
        foreach ($ending as $lease) {
            $alerts[] = ['level' => 'warning', 'title' => 'Contrat de bail de ' . $lease['full_name'] . ' : fin le ' . ImmoCatalog::date((string) $lease['ends_on']), 'detail' => 'Renouveler ou préparer l\'état des lieux', 'href' => '/immo/baux/' . $lease['id']];
        }
        $deposits = (int) $this->value("SELECT COUNT(*) FROM loyer_leases WHERE tenant_id = ? AND status = 'active' AND deposit_minor > 0 AND deposit_paid_on IS NULL", [$tenantId]);
        if ($deposits > 0) {
            $alerts[] = ['level' => 'info', 'title' => $deposits . ' caution(s) non versée(s)', 'detail' => 'À encaisser à l\'entrée dans les lieux', 'href' => '/immo/baux?vue=caution'];
        }
        $urgent = (int) $this->value("SELECT COUNT(*) FROM immo_interventions WHERE tenant_id = ? AND status <> 'terminee' AND priority = 'urgente'", [$tenantId]);
        if ($urgent > 0) {
            $alerts[] = ['level' => 'danger', 'title' => $urgent . ' intervention(s) urgente(s)', 'detail' => 'À traiter rapidement', 'href' => '/immo/interventions'];
        }
        $vacant = (int) $this->value(
            "SELECT COUNT(*) FROM loyer_units u WHERE u.tenant_id = ? AND u.is_active = 1 AND NOT EXISTS (SELECT 1 FROM loyer_leases l WHERE l.unit_id = u.id AND l.status = 'active')",
            [$tenantId]
        );
        if ($vacant > 0) {
            $alerts[] = ['level' => 'muted', 'title' => $vacant . ' logement(s) libre(s)', 'detail' => 'À relouer : chaque mois vide est un loyer perdu', 'href' => '/immo/biens'];
        }

        return $alerts;
    }

    /**
     * @return list<array{key: string, label: string, expected: int, paid: int}>
     */
    private function collectionByMonth(int $tenantId, int $months): array
    {
        $first = gmdate('Y-m-01', (int) strtotime('first day of -' . ($months - 1) . ' months'));
        $rows = $this->rows(
            "SELECT DATE_FORMAT(due_date, '%Y-%m') AS ym, SUM(amount_minor) AS expected, SUM(paid_minor) AS paid
             FROM loyer_rent_dues WHERE tenant_id = ? AND due_date >= ? AND due_date < ? AND status <> 'cancelled' GROUP BY ym",
            [$tenantId, $first, gmdate('Y-m-01', (int) strtotime('first day of next month'))]
        );
        $byMonth = [];
        foreach ($rows as $row) {
            $byMonth[(string) $row['ym']] = $row;
        }
        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $key = gmdate('Y-m', (int) strtotime($first . ' +' . $i . ' months'));
            $series[] = [
                'key' => $key,
                'label' => mb_substr(ImmoCatalog::monthLabel($key . '-01'), 0, 4),
                'expected' => (int) ($byMonth[$key]['expected'] ?? 0),
                'paid' => (int) ($byMonth[$key]['paid'] ?? 0),
            ];
        }

        return $series;
    }

    // ================================================================ Loyers (échéances)

    /**
     * @param array{mois?: string, vue?: string, q?: string} $filters
     * @return array<string, mixed>
     */
    public function dues(User $actor, array $filters): array
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_VIEW);
        $this->ensureSchedule($tenantId);
        $settings = $this->settingsFor($tenantId);
        $today = gmdate('Y-m-d');
        $view = (string) ($filters['vue'] ?? 'mois');
        $month = (string) ($filters['mois'] ?? '');
        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            $month = gmdate('Y-m');
        }
        $q = trim((string) ($filters['q'] ?? ''));
        $grace = (int) $settings['grace_days'];
        $lateSql = "d.status IN ('pending', 'partial') AND DATE_ADD(d.due_date, INTERVAL " . $grace . ' DAY) < ?';
        [$where, $params, $order] = match ($view) {
            'retards' => [$lateSql, [$today], 'd.due_date ASC'],
            'partiels' => ["d.status = 'partial'", [], 'd.due_date ASC'],
            'a-encaisser' => ["d.status IN ('pending', 'partial') AND d.due_date <= ?", [gmdate('Y-m-d', (int) strtotime('+31 days'))], 'd.due_date ASC'],
            'payes' => ["d.status = 'paid'", [], 'd.paid_at DESC'],
            default => ["DATE_FORMAT(d.due_date, '%Y-%m') = ? AND d.status <> 'cancelled'", [$month], 'd.due_date ASC, c.full_name ASC'],
        };
        if ($q !== '') {
            $where .= ' AND (c.full_name LIKE ? OR u.label LIKE ? OR pr.name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        $rows = $this->dueRows($tenantId, $where, $params, $order, 300, $settings);
        $counts = $this->row(
            "SELECT
                SUM(" . $lateSql . ") AS retards,
                SUM(d.status = 'partial') AS partiels,
                SUM(d.status IN ('pending', 'partial') AND d.due_date <= ?) AS a_encaisser
             FROM loyer_rent_dues d WHERE d.tenant_id = ?",
            [$today, gmdate('Y-m-d', (int) strtotime('+31 days')), $tenantId]
        ) ?? [];

        return [
            'rows' => $rows,
            'view' => in_array($view, ['retards', 'partiels', 'a-encaisser', 'payes'], true) ? $view : 'mois',
            'month' => $month,
            'q' => $q,
            'counts' => array_map('intval', $counts),
            'totals' => [
                'amount' => array_sum(array_column($rows, 'amount_minor')),
                'paid' => array_sum(array_column($rows, 'paid_minor')),
                'remaining' => array_sum(array_column($rows, 'remaining')),
            ],
            'settings' => $settings,
        ];
    }

    /**
     * @param list<mixed> $params
     * @param array<string, mixed> $settings
     * @return list<array<string, mixed>>
     */
    private function dueRows(int $tenantId, string $where, array $params, string $order, int $limit, array $settings): array
    {
        $rows = $this->rows(
            'SELECT d.id, d.lease_id, d.due_date, d.amount_minor, d.rent_part_minor, d.charges_part_minor, d.paid_minor, d.status, d.paid_at,
                    d.payment_id, d.last_reminder_at, d.reminder_count,
                    l.client_id, c.full_name, c.phone, c.email, u.label AS unit_label, pr.name AS property_name
             FROM loyer_rent_dues d
             JOIN loyer_leases l ON l.id = d.lease_id
             JOIN clients c ON c.id = l.client_id
             JOIN loyer_units u ON u.id = l.unit_id
             JOIN loyer_properties pr ON pr.id = u.property_id
             WHERE d.tenant_id = ? AND ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . $limit,
            array_merge([$tenantId], $params)
        );
        $today = gmdate('Y-m-d');
        foreach ($rows as &$row) {
            $row['remaining'] = max(0, (int) $row['amount_minor'] - (int) $row['paid_minor']);
            $row['state'] = $this->dueState((string) $row['status'], (string) $row['due_date'], $today, (int) $settings['grace_days']);
            $row['days_late'] = $row['state'] === 'late' ? (int) floor((strtotime($today . ' UTC') - strtotime($row['due_date'] . ' UTC')) / 86400) : 0;
        }

        return $rows;
    }

    public function dueState(string $status, string $dueDate, string $today, int $grace): string
    {
        if ($status === 'paid' || $status === 'cancelled') {
            return $status;
        }
        if ((new \DateTimeImmutable($dueDate))->modify('+' . $grace . ' days')->format('Y-m-d') < $today) {
            return 'late';
        }

        return $status;
    }

    /**
     * @return array<string, mixed>
     */
    public function due(User $actor, int $id): array
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_VIEW);
        $settings = $this->settingsFor($tenantId);
        $rows = $this->dueRows($tenantId, 'd.id = ?', [$id], 'd.id', 1, $settings);
        if ($rows === []) {
            throw new HttpException(404, 'Échéance introuvable.');
        }
        $due = $rows[0];
        $message = $this->reminderText($settings, $due);
        $phone = WhatsApp::normalize((string) ($due['phone'] ?? ''));
        $online = null;
        if ($due['payment_id'] !== null) {
            $online = $this->row('SELECT id, status, amount_minor, created_at FROM payments WHERE id = ? AND tenant_id = ?', [(int) $due['payment_id'], $tenantId]);
        }

        return [
            'due' => $due,
            'settings' => $settings,
            'payments' => $this->rows('SELECT id, amount_minor, method, reference, paid_on, note, receipt_number, payment_id FROM immo_payments WHERE due_id = ? AND tenant_id = ? ORDER BY paid_on, id', [$id, $tenantId]),
            'reminders' => $this->rows('SELECT kind, channel, message, status, created_at FROM immo_reminders WHERE due_id = ? AND tenant_id = ? ORDER BY created_at DESC LIMIT 20', [$id, $tenantId]),
            'message' => $message,
            'whatsapp' => WhatsApp::isValid($phone) ? WhatsApp::link($phone, $message) : '',
            'online' => $online,
            'methods' => ImmoCatalog::MANUAL_METHODS,
        ];
    }

    /**
     * Encaissement à la main : total ou partiel (si l'agence l'autorise).
     *
     * @param array<string, mixed> $input
     */
    public function recordPayment(User $actor, int $dueId, array $input): string
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_DUES);
        $settings = $this->settingsFor($tenantId);
        $due = $this->row('SELECT id, amount_minor, paid_minor, status FROM loyer_rent_dues WHERE id = ? AND tenant_id = ?', [$dueId, $tenantId]);
        if ($due === null) {
            throw new HttpException(404, 'Échéance introuvable.');
        }
        $remaining = (int) $due['amount_minor'] - (int) $due['paid_minor'];
        $amount = (int) preg_replace('/\D+/', '', (string) ($input['amount'] ?? ''));
        $method = (string) ($input['method'] ?? '');
        $paidOn = trim((string) ($input['paid_on'] ?? ''));
        $errors = [];
        if ($due['status'] === 'paid' || $remaining <= 0) {
            throw new ValidationException(['amount' => 'Ce loyer est déjà entièrement payé.']);
        }
        if ($amount < 1) {
            $errors['amount'] = 'Indiquez le montant reçu.';
        } elseif ($amount > $remaining) {
            $errors['amount'] = 'Le montant dépasse le reste à payer (' . Money::format($remaining) . ').';
        } elseif ($amount < $remaining) {
            if ($settings['allow_partial'] !== 1) {
                $errors['amount'] = 'Les paiements partiels sont désactivés dans les réglages : encaissez ' . Money::format($remaining) . '.';
            } elseif ($amount < (int) $settings['min_partial_minor']) {
                $errors['amount'] = 'Paiement partiel minimum : ' . Money::format((int) $settings['min_partial_minor']) . '.';
            }
        }
        if (!isset(ImmoCatalog::MANUAL_METHODS[$method])) {
            $errors['method'] = 'Choisissez le moyen de paiement.';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidOn) !== 1 || $paidOn > gmdate('Y-m-d')) {
            $errors['paid_on'] = 'Date invalide (pas dans le futur).';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $this->ledger->record($tenantId, $dueId, [
            'amount' => $amount,
            'method' => $method,
            'paid_on' => $paidOn,
            'reference' => mb_substr(trim((string) ($input['reference'] ?? '')), 0, 80) ?: null,
            'note' => mb_substr(trim((string) ($input['note'] ?? '')), 0, 255) ?: null,
            'actor_user_id' => $actor->id(),
        ]);
        $left = $remaining - $amount;

        return $left > 0
            ? 'Paiement partiel de ' . Money::format($amount) . ' enregistré. Reste ' . Money::format($left) . ' à encaisser.'
            : 'Loyer soldé : ' . Money::format($amount) . ' enregistré. La quittance est prête.';
    }

    /**
     * Lien de paiement en ligne (Wave / Orange Money via NabooPay) pour le reste ou une partie du loyer.
     */
    public function paymentLink(User $actor, int $dueId, string $rawAmount): int
    {
        if ($this->payments === null) {
            throw new HttpException(503, 'Paiement en ligne indisponible.');
        }
        $tenantId = $this->tenantId($actor, Permission::LOYER_DUES);
        $settings = $this->settingsFor($tenantId);
        $rows = $this->dueRows($tenantId, 'd.id = ?', [$dueId], 'd.id', 1, $settings);
        if ($rows === []) {
            throw new HttpException(404, 'Échéance introuvable.');
        }
        $due = $rows[0];
        if ($due['remaining'] <= 0) {
            throw new ValidationException(['link' => 'Ce loyer est déjà entièrement payé.']);
        }
        if ($due['payment_id'] !== null && $this->value('SELECT status FROM payments WHERE id = ?', [(int) $due['payment_id']]) === 'pending') {
            throw new ValidationException(['link' => 'Un lien de paiement est déjà en attente pour ce loyer.']);
        }
        $amount = (int) preg_replace('/\D+/', '', $rawAmount);
        $amount = $amount > 0 ? $amount : $due['remaining'];
        if ($amount > $due['remaining'] || ($amount < $due['remaining'] && ($settings['allow_partial'] !== 1 || $amount < (int) $settings['min_partial_minor']))) {
            throw new ValidationException(['link' => 'Montant du lien invalide (entre ' . Money::format(min($due['remaining'], (int) $settings['min_partial_minor'])) . ' et ' . Money::format($due['remaining']) . ').']);
        }
        $result = $this->payments->initiate($actor, [
            'amount_minor' => $amount,
            'description' => 'Loyer ' . ImmoCatalog::monthLabel((string) $due['due_date']) . ' — ' . $due['unit_label'],
            'client_id' => (int) $due['client_id'],
        ]);
        $paymentId = $result['payment']->id();
        $this->pdo->prepare('UPDATE loyer_rent_dues SET payment_id = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND tenant_id = ?')->execute([$paymentId, $dueId, $tenantId]);

        return $paymentId;
    }

    /**
     * @return array<string, mixed>
     */
    public function receipt(User $actor, int $paymentId): array
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_VIEW);
        $payment = $this->row(
            'SELECT p.*, d.due_date, d.amount_minor AS due_amount, d.rent_part_minor, d.charges_part_minor, d.paid_minor, d.status AS due_status,
                    c.full_name, c.phone, u.label AS unit_label, pr.name AS property_name, pr.address, l.reference AS lease_reference
             FROM immo_payments p JOIN loyer_rent_dues d ON d.id = p.due_id JOIN loyer_leases l ON l.id = p.lease_id
             JOIN clients c ON c.id = l.client_id JOIN loyer_units u ON u.id = l.unit_id JOIN loyer_properties pr ON pr.id = u.property_id
             WHERE p.id = ? AND p.tenant_id = ?',
            [$paymentId, $tenantId]
        );
        if ($payment === null) {
            throw new HttpException(404, 'Quittance introuvable.');
        }

        return ['payment' => $payment, 'settings' => $this->settingsFor($tenantId), 'methods' => ImmoCatalog::METHODS];
    }

    // ================================================================ Relances

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $due
     */
    private function reminderText(array $settings, array $due): string
    {
        return strtr((string) $settings['reminder_message'], [
            '{locataire}' => (string) $due['full_name'],
            '{mois}' => ImmoCatalog::monthLabel((string) $due['due_date']),
            '{logement}' => trim($due['property_name'] . ' — ' . $due['unit_label']),
            '{montant}' => Money::format((int) $due['amount_minor']),
            '{reste}' => Money::format((int) ($due['remaining'] ?? ((int) $due['amount_minor'] - (int) $due['paid_minor']))),
            '{echeance}' => ImmoCatalog::date((string) $due['due_date']),
            '{agence}' => (string) $settings['display_name'],
        ]);
    }

    /**
     * Relance envoyée à la main depuis la fiche du loyer (message modifiable).
     */
    public function remind(User $actor, int $dueId, string $message): string
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_DUES);
        $settings = $this->settingsFor($tenantId);
        $rows = $this->dueRows($tenantId, 'd.id = ?', [$dueId], 'd.id', 1, $settings);
        if ($rows === []) {
            throw new HttpException(404, 'Échéance introuvable.');
        }
        $due = $rows[0];
        if ($due['remaining'] <= 0) {
            throw new ValidationException(['message' => 'Ce loyer est déjà payé.']);
        }
        $message = trim($message) !== '' ? mb_substr(trim($message), 0, 800) : $this->reminderText($settings, $due);
        $status = $this->deliver($tenantId, $due, 'manual', $message, $actor->id(), true);

        return $status === 'failed'
            ? 'Relance notée, mais le locataire n\'a ni numéro valide ni e-mail : envoyez-la vous-même.'
            : 'Relance envoyée à ' . $due['full_name'] . '.';
    }

    /**
     * Relances automatiques du jour selon les réglages (avant l'échéance, le jour même, après).
     * Chaque relance n'est envoyée qu'une fois par jour et par loyer.
     */
    public function runReminders(int $tenantId, ?string $today = null): int
    {
        $today ??= gmdate('Y-m-d');
        $settings = $this->settingsFor($tenantId);
        $targets = [];
        if ((int) $settings['remind_before_days'] > 0) {
            $targets[gmdate('Y-m-d', (int) strtotime($today . ' +' . (int) $settings['remind_before_days'] . ' days'))] = 'before';
        }
        if ((int) $settings['remind_on_due'] === 1) {
            $targets[$today] = 'due';
        }
        foreach (array_filter(explode(',', (string) $settings['remind_after_days'])) as $days) {
            $targets[gmdate('Y-m-d', (int) strtotime($today . ' -' . (int) $days . ' days'))] = 'late';
        }
        if ($targets === []) {
            return 0;
        }
        $dates = array_keys($targets);
        $rows = $this->dueRows(
            $tenantId,
            "d.status IN ('pending', 'partial') AND l.status = 'active' AND d.due_date IN (" . implode(', ', array_fill(0, count($dates), '?')) . ')',
            $dates,
            'd.due_date',
            500,
            $settings
        );
        $sent = 0;
        foreach ($rows as $due) {
            $kind = $targets[(string) $due['due_date']] ?? null;
            if ($kind === null) {
                continue;
            }
            $exists = (int) $this->value('SELECT COUNT(*) FROM immo_reminders WHERE due_id = ? AND kind = ? AND sent_on = ?', [(int) $due['id'], $kind, $today]);
            if ($exists > 0) {
                continue;
            }
            $this->deliver($tenantId, $due, $kind, $this->reminderText($settings, $due), null, false, $today);
            $sent++;
        }

        return $sent;
    }

    /**
     * @param array<string, mixed> $due
     */
    private function deliver(int $tenantId, array $due, string $kind, string $message, ?int $actorId, bool $manual, ?string $today = null): string
    {
        $today ??= gmdate('Y-m-d');
        $phone = WhatsApp::normalize((string) ($due['phone'] ?? ''));
        $email = trim((string) ($due['email'] ?? ''));
        $status = 'failed';
        $channel = 'whatsapp';
        if (WhatsApp::isValid($phone) || filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $status = 'sent';
            if ($this->messaging !== null && $this->context->id() === $tenantId) {
                // WhatsApp d'abord, e-mail en secours si WhatsApp ne remet pas le message.
                $result = $this->messaging->notifyClient(WhatsApp::isValid($phone) ? $phone : '', $email, (string) $due['full_name'], 'Votre loyer — rappel', $message);
                $status = $result['status'] === 'failed' ? 'failed' : 'sent';
                $channel = $result['channel'];
            }
        }
        // Une relance manuelle peut s'ajouter à une automatique du même jour.
        $this->pdo->prepare(
            'INSERT IGNORE INTO immo_reminders (tenant_id, due_id, client_id, kind, channel, recipient, message, status, actor_user_id, sent_on, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
        )->execute([$tenantId, (int) $due['id'], (int) $due['client_id'], $kind, $channel, $channel === 'email' ? mb_substr($email, 0, 60) : ($phone !== '' ? $phone : null), $message, $status, $actorId, $manual ? gmdate('Y-m-d') . '' : $today]);
        $this->pdo->prepare('UPDATE loyer_rent_dues SET last_reminder_at = UTC_TIMESTAMP(), reminder_count = reminder_count + 1 WHERE id = ?')->execute([(int) $due['id']]);

        return $status;
    }

    /**
     * @return array<string, mixed>
     */
    public function reminders(User $actor): array
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_VIEW);
        $settings = $this->settingsFor($tenantId);

        return [
            'settings' => $settings,
            'journal' => $this->rows(
                'SELECT r.id, r.due_id, r.kind, r.status, r.message, r.recipient, r.created_at, c.full_name, d.due_date
                 FROM immo_reminders r JOIN loyer_rent_dues d ON d.id = r.due_id LEFT JOIN clients c ON c.id = r.client_id
                 WHERE r.tenant_id = ? ORDER BY r.created_at DESC, r.id DESC LIMIT 100',
                [$tenantId]
            ),
            'toRemind' => $this->dueRows($tenantId, "d.status IN ('pending', 'partial') AND DATE_ADD(d.due_date, INTERVAL " . (int) $settings['grace_days'] . ' DAY) < ?', [gmdate('Y-m-d')], 'd.due_date ASC', 50, $settings),
        ];
    }

    public function runRemindersNow(User $actor): int
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_DUES);
        $this->ensureSchedule($tenantId);

        return $this->runReminders($tenantId);
    }

    // ================================================================ Locataires

    /**
     * @return array<string, mixed>
     */
    public function tenants(User $actor, string $q, string $view): array
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_VIEW);
        $settings = $this->settingsFor($tenantId);
        $where = ' WHERE c.tenant_id = ? AND (EXISTS (SELECT 1 FROM loyer_leases x WHERE x.client_id = c.id) OR EXISTS (SELECT 1 FROM immo_tenant_profiles ip WHERE ip.client_id = c.id))';
        $params = [$tenantId];
        if ($q !== '') {
            $where .= ' AND (c.full_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        $rows = $this->rows(
            "SELECT c.id, c.full_name, c.phone, c.email,
                    (SELECT u.label FROM loyer_leases l JOIN loyer_units u ON u.id = l.unit_id WHERE l.client_id = c.id AND l.status = 'active' ORDER BY l.id DESC LIMIT 1) AS unit_label,
                    (SELECT pr.name FROM loyer_leases l JOIN loyer_units u ON u.id = l.unit_id JOIN loyer_properties pr ON pr.id = u.property_id WHERE l.client_id = c.id AND l.status = 'active' ORDER BY l.id DESC LIMIT 1) AS property_name,
                    (SELECT l.rent_minor + l.charges_minor FROM loyer_leases l WHERE l.client_id = c.id AND l.status = 'active' ORDER BY l.id DESC LIMIT 1) AS monthly,
                    (SELECT COALESCE(SUM(d.amount_minor - d.paid_minor), 0) FROM loyer_rent_dues d JOIN loyer_leases l ON l.id = d.lease_id
                       WHERE l.client_id = c.id AND d.status IN ('pending', 'partial') AND DATE_ADD(d.due_date, INTERVAL " . (int) $settings['grace_days'] . " DAY) < ?) AS balance
             FROM clients c" . $where . ' ORDER BY c.full_name ASC LIMIT 300',
            array_merge([gmdate('Y-m-d')], $params)
        );
        if ($view === 'retards') {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => (int) $r['balance'] > 0));
        } elseif ($view === 'anciens') {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => $r['unit_label'] === null));
        } elseif ($view !== 'tous') {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => $r['unit_label'] !== null));
        }

        return ['rows' => $rows, 'q' => $q, 'view' => in_array($view, ['retards', 'anciens', 'tous'], true) ? $view : 'actifs'];
    }

    /**
     * @return array<string, mixed>
     */
    public function tenantFile(User $actor, int $clientId): array
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_VIEW);
        $client = $this->row('SELECT id, full_name, phone, email, created_at FROM clients WHERE id = ? AND tenant_id = ?', [$clientId, $tenantId]);
        if ($client === null) {
            throw new HttpException(404, 'Locataire introuvable.');
        }
        $settings = $this->settingsFor($tenantId);
        $dues = $this->dueRows($tenantId, 'l.client_id = ?', [$clientId], 'd.due_date DESC', 24, $settings);

        return [
            'client' => $client,
            'profile' => $this->row('SELECT * FROM immo_tenant_profiles WHERE client_id = ? AND tenant_id = ?', [$clientId, $tenantId]) ?? [],
            'leases' => $this->leaseRows($tenantId, 'l.client_id = ?', [$clientId]),
            'dues' => $dues,
            'balance' => array_sum(array_map(static fn (array $d): int => $d['state'] === 'late' ? $d['remaining'] : 0, $dues)),
            'paidTotal' => (int) $this->value('SELECT COALESCE(SUM(p.amount_minor), 0) FROM immo_payments p JOIN loyer_leases l ON l.id = p.lease_id WHERE l.client_id = ? AND p.tenant_id = ?', [$clientId, $tenantId]),
            'reminders' => $this->rows('SELECT kind, status, message, created_at FROM immo_reminders WHERE client_id = ? AND tenant_id = ? ORDER BY created_at DESC LIMIT 10', [$clientId, $tenantId]),
        ];
    }

    /**
     * Crée ou modifie un locataire (fiche client + fiche locataire).
     *
     * @param array<string, mixed> $input
     */
    public function saveTenant(User $actor, ?int $clientId, array $input): int
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_LEASES);
        $text = static fn (string $key, int $max): string => mb_substr(trim((string) ($input[$key] ?? '')), 0, $max);
        $name = $text('full_name', 120);
        $phone = $text('phone', 40);
        $email = $text('email', 190);
        $errors = [];
        if ($name === '') {
            $errors['full_name'] = 'Écrivez le nom du locataire.';
        }
        if (strlen((string) preg_replace('/\D+/', '', $phone)) < 8) {
            $errors['phone'] = 'Numéro de téléphone invalide (pour les rappels WhatsApp).';
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'E-mail invalide.';
        }
        $idType = (string) ($input['id_type'] ?? '');
        if ($idType !== '' && !isset(ImmoCatalog::ID_TYPES[$idType])) {
            $errors['id_type'] = 'Type de pièce inconnu.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        if ($clientId !== null) {
            if ($this->row('SELECT id FROM clients WHERE id = ? AND tenant_id = ?', [$clientId, $tenantId]) === null) {
                throw new HttpException(404, 'Locataire introuvable.');
            }
            $this->pdo->prepare('UPDATE clients SET full_name = ?, phone = ?, email = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND tenant_id = ?')
                ->execute([$name, $phone, $email !== '' ? $email : null, $clientId, $tenantId]);
        } else {
            $this->pdo->prepare("INSERT INTO clients (tenant_id, full_name, email, phone, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'active', UTC_TIMESTAMP(), UTC_TIMESTAMP())")
                ->execute([$tenantId, $name, $email !== '' ? $email : null, $phone]);
            $clientId = (int) $this->pdo->lastInsertId();
        }
        $profile = [
            'id_type' => $idType !== '' ? $idType : null,
            'id_number' => $text('id_number', 60) ?: null,
            'profession' => $text('profession', 120) ?: null,
            'employer' => $text('employer', 120) ?: null,
            'emergency_name' => $text('emergency_name', 120) ?: null,
            'emergency_phone' => $text('emergency_phone', 40) ?: null,
            'guarantor_name' => $text('guarantor_name', 120) ?: null,
            'guarantor_phone' => $text('guarantor_phone', 40) ?: null,
            'notes' => $text('notes', 1000) ?: null,
        ];
        $columns = array_keys($profile);
        $this->pdo->prepare(
            'INSERT INTO immo_tenant_profiles (client_id, tenant_id, ' . implode(', ', $columns) . ', updated_at)
             VALUES (?, ?, ' . implode(', ', array_fill(0, count($columns), '?')) . ', UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(static fn (string $c): string => $c . ' = VALUES(' . $c . ')', $columns)) . ', updated_at = UTC_TIMESTAMP()'
        )->execute(array_merge([$clientId, $tenantId], array_values($profile)));

        return $clientId;
    }

    // ================================================================ Biens et logements

    /**
     * @return list<array<string, mixed>>
     */
    public function properties(User $actor): array
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_VIEW);

        return $this->rows(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM loyer_units u WHERE u.property_id = p.id AND u.is_active = 1) AS units,
                    (SELECT COUNT(*) FROM loyer_units u WHERE u.property_id = p.id AND u.is_active = 1 AND EXISTS (SELECT 1 FROM loyer_leases l WHERE l.unit_id = u.id AND l.status = 'active')) AS occupied,
                    (SELECT COALESCE(SUM(l.rent_minor + l.charges_minor), 0) FROM loyer_leases l JOIN loyer_units u ON u.id = l.unit_id WHERE u.property_id = p.id AND l.status = 'active') AS monthly,
                    (SELECT COUNT(*) FROM immo_interventions i WHERE i.property_id = p.id AND i.status <> 'terminee') AS open_interventions
             FROM loyer_properties p WHERE p.tenant_id = ? ORDER BY p.name",
            [$tenantId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function property(User $actor, int $id): array
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_VIEW);
        $property = $this->row('SELECT * FROM loyer_properties WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
        if ($property === null) {
            throw new HttpException(404, 'Bien introuvable.');
        }

        return [
            'property' => $property,
            'units' => $this->rows(
                "SELECT u.*, l.id AS lease_id, l.rent_minor, l.charges_minor, c.id AS client_id, c.full_name
                 FROM loyer_units u
                 LEFT JOIN loyer_leases l ON l.unit_id = u.id AND l.status = 'active'
                 LEFT JOIN clients c ON c.id = l.client_id
                 WHERE u.property_id = ? AND u.tenant_id = ? ORDER BY u.is_active DESC, u.label",
                [$id, $tenantId]
            ),
            'interventions' => $this->rows('SELECT i.*, u.label AS unit_label FROM immo_interventions i LEFT JOIN loyer_units u ON u.id = i.unit_id WHERE i.property_id = ? AND i.tenant_id = ? ORDER BY i.status = \'terminee\', i.reported_on DESC LIMIT 20', [$id, $tenantId]),
            'collected' => (int) $this->value(
                'SELECT COALESCE(SUM(p.amount_minor), 0) FROM immo_payments p JOIN loyer_leases l ON l.id = p.lease_id JOIN loyer_units u ON u.id = l.unit_id
                 WHERE u.property_id = ? AND p.tenant_id = ? AND p.paid_on >= ?',
                [$id, $tenantId, gmdate('Y-01-01')]
            ),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveProperty(User $actor, ?int $id, array $input): int
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_PROPERTIES);
        $text = static fn (string $key, int $max): ?string => ($v = mb_substr(trim((string) ($input[$key] ?? '')), 0, $max)) !== '' ? $v : null;
        $name = $text('name', 120);
        $kind = (string) ($input['kind'] ?? 'immeuble');
        $errors = [];
        if ($name === null) {
            $errors['name'] = 'Donnez un nom au bien (ex. Immeuble Liberté 6).';
        }
        if (!isset(ImmoCatalog::PROPERTY_KINDS[$kind])) {
            $errors['kind'] = 'Type de bien inconnu.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $data = [$name, $kind, $text('address', 255), $text('city', 80), $text('owner_name', 120), $text('owner_phone', 40), $text('notes', 1000)];
        if ($id === null) {
            $this->pdo->prepare('INSERT INTO loyer_properties (tenant_id, name, kind, address, city, owner_name, owner_phone, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')
                ->execute(array_merge([$tenantId], $data));

            return (int) $this->pdo->lastInsertId();
        }
        $this->pdo->prepare('UPDATE loyer_properties SET name = ?, kind = ?, address = ?, city = ?, owner_name = ?, owner_phone = ?, notes = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND tenant_id = ?')
            ->execute(array_merge($data, [$id, $tenantId]));

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    public function unit(User $actor, int $id): array
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_VIEW);
        $unit = $this->row('SELECT u.*, p.name AS property_name FROM loyer_units u JOIN loyer_properties p ON p.id = u.property_id WHERE u.id = ? AND u.tenant_id = ?', [$id, $tenantId]);
        if ($unit === null) {
            throw new HttpException(404, 'Logement introuvable.');
        }

        return $unit;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveUnit(User $actor, int $propertyId, ?int $unitId, array $input): int
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_PROPERTIES);
        if ($this->row('SELECT id FROM loyer_properties WHERE id = ? AND tenant_id = ?', [$propertyId, $tenantId]) === null) {
            throw new HttpException(404, 'Bien introuvable.');
        }
        $label = mb_substr(trim((string) ($input['label'] ?? '')), 0, 80);
        $kind = (string) ($input['kind'] ?? 'appartement');
        $money = static fn (string $key): int => (int) preg_replace('/\D+/', '', (string) ($input[$key] ?? '0'));
        $errors = [];
        if ($label === '') {
            $errors['label'] = 'Nommez le logement (ex. Appartement A1).';
        }
        if (!isset(ImmoCatalog::UNIT_KINDS[$kind])) {
            $errors['kind'] = 'Type de logement inconnu.';
        }
        if ($money('default_rent_minor') < 1) {
            $errors['default_rent_minor'] = 'Indiquez le loyer mensuel.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $rooms = (int) ($input['rooms'] ?? 0);
        $surface = (int) ($input['surface_m2'] ?? 0);
        $data = [
            $label, $kind, $rooms > 0 ? min(50, $rooms) : null, $surface > 0 ? min(60000, $surface) : null,
            mb_substr(trim((string) ($input['floor_label'] ?? '')), 0, 30) ?: null,
            $money('default_rent_minor'), $money('default_charges_minor'), $money('default_deposit_minor'),
            mb_substr(trim((string) ($input['description'] ?? '')), 0, 500) ?: null,
            empty($input['is_active']) && $unitId !== null ? 0 : 1,
        ];
        if ($unitId === null) {
            $this->pdo->prepare(
                'INSERT INTO loyer_units (tenant_id, property_id, label, kind, rooms, surface_m2, floor_label, default_rent_minor, default_charges_minor, default_deposit_minor, description, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            )->execute(array_merge([$tenantId, $propertyId], $data));

            return (int) $this->pdo->lastInsertId();
        }
        $this->pdo->prepare(
            'UPDATE loyer_units SET label = ?, kind = ?, rooms = ?, surface_m2 = ?, floor_label = ?, default_rent_minor = ?, default_charges_minor = ?,
                default_deposit_minor = ?, description = ?, is_active = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND tenant_id = ?'
        )->execute(array_merge($data, [$unitId, $tenantId]));

        return $unitId;
    }

    // ================================================================ Baux

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    private function leaseRows(int $tenantId, string $where, array $params): array
    {
        return $this->rows(
            "SELECT l.*, c.full_name, c.phone, u.label AS unit_label, pr.name AS property_name, pr.id AS property_id,
                    (SELECT COALESCE(SUM(d.amount_minor - d.paid_minor), 0) FROM loyer_rent_dues d WHERE d.lease_id = l.id AND d.status IN ('pending', 'partial') AND d.due_date < ?) AS balance
             FROM loyer_leases l JOIN clients c ON c.id = l.client_id JOIN loyer_units u ON u.id = l.unit_id JOIN loyer_properties pr ON pr.id = u.property_id
             WHERE l.tenant_id = ? AND " . $where . ' ORDER BY l.status = \'active\' DESC, l.starts_on DESC LIMIT 300',
            array_merge([gmdate('Y-m-d'), $tenantId], $params)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function leases(User $actor, string $view): array
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_VIEW);
        $settings = $this->settingsFor($tenantId);
        [$where, $params] = match ($view) {
            'termines' => ["l.status <> 'active'", []],
            'fin-proche' => ["l.status = 'active' AND l.ends_on IS NOT NULL AND l.ends_on <= ?", [gmdate('Y-m-d', (int) strtotime('+' . (int) $settings['lease_end_alert_days'] . ' days'))]],
            'caution' => ["l.status = 'active' AND l.deposit_minor > 0 AND l.deposit_paid_on IS NULL", []],
            default => ["l.status = 'active'", []],
        };

        return ['rows' => $this->leaseRows($tenantId, $where, $params), 'view' => in_array($view, ['termines', 'fin-proche', 'caution'], true) ? $view : 'actifs'];
    }

    /**
     * @return array<string, mixed>
     */
    public function lease(User $actor, int $id): array
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_VIEW);
        $this->ensureSchedule($tenantId);
        $rows = $this->leaseRows($tenantId, 'l.id = ?', [$id]);
        if ($rows === []) {
            throw new HttpException(404, 'Contrat de bail introuvable.');
        }
        $settings = $this->settingsFor($tenantId);

        return [
            'lease' => $rows[0],
            'dues' => $this->dueRows($tenantId, 'd.lease_id = ?', [$id], 'd.due_date DESC', 36, $settings),
            'paidTotal' => (int) $this->value('SELECT COALESCE(SUM(amount_minor), 0) FROM immo_payments WHERE lease_id = ? AND tenant_id = ?', [$id, $tenantId]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function leaseFormCatalog(User $actor): array
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_LEASES);

        return [
            'units' => $this->rows(
                "SELECT u.id, u.label, u.default_rent_minor, u.default_charges_minor, u.default_deposit_minor, p.name AS property_name
                 FROM loyer_units u JOIN loyer_properties p ON p.id = u.property_id
                 WHERE u.tenant_id = ? AND u.is_active = 1 AND NOT EXISTS (SELECT 1 FROM loyer_leases l WHERE l.unit_id = u.id AND l.status = 'active')
                 ORDER BY p.name, u.label",
                [$tenantId]
            ),
            'tenants' => $this->rows(
                "SELECT c.id, c.full_name, c.phone FROM clients c WHERE c.tenant_id = ? AND c.status = 'active'
                   AND NOT EXISTS (SELECT 1 FROM loyer_leases l WHERE l.client_id = c.id AND l.status = 'active') ORDER BY c.full_name LIMIT 500",
                [$tenantId]
            ),
            'settings' => $this->settingsFor($tenantId),
        ];
    }

    /**
     * Nouveau contrat de bail : un locataire existant ou saisi sur place, un logement libre, loyer, charges, caution, échéance.
     *
     * @param array<string, mixed> $input
     */
    public function createLease(User $actor, array $input): int
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_LEASES);
        $money = static fn (string $key): int => (int) preg_replace('/\D+/', '', (string) ($input[$key] ?? '0'));
        $unitId = (int) ($input['unit_id'] ?? 0);
        $clientId = (int) ($input['client_id'] ?? 0);
        $startsOn = trim((string) ($input['starts_on'] ?? ''));
        $duration = (int) ($input['duration_months'] ?? 0);
        $dueDay = (int) ($input['due_day'] ?? 5);
        $errors = [];
        $unit = $this->row(
            "SELECT u.id FROM loyer_units u WHERE u.id = ? AND u.tenant_id = ? AND u.is_active = 1 AND NOT EXISTS (SELECT 1 FROM loyer_leases l WHERE l.unit_id = u.id AND l.status = 'active')",
            [$unitId, $tenantId]
        );
        if ($unit === null) {
            $errors['unit_id'] = 'Choisissez un logement libre.';
        }
        if ($clientId > 0 && $this->row('SELECT id FROM clients WHERE id = ? AND tenant_id = ?', [$clientId, $tenantId]) === null) {
            $errors['client_id'] = 'Locataire introuvable.';
        }
        if ($clientId === 0 && trim((string) ($input['full_name'] ?? '')) === '') {
            $errors['client_id'] = 'Choisissez un locataire ou saisissez le nouveau locataire.';
        }
        if ($money('rent_minor') < 1) {
            $errors['rent_minor'] = 'Indiquez le loyer mensuel.';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startsOn) !== 1) {
            $errors['starts_on'] = 'Date d\'entrée invalide.';
        }
        if ($dueDay < 1 || $dueDay > 28) {
            $errors['due_day'] = 'Jour d\'échéance entre 1 et 28.';
        }
        if ($duration < 0 || $duration > 120) {
            $errors['duration_months'] = 'Durée entre 0 (sans fin) et 120 mois.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $this->pdo->beginTransaction();
        try {
            if ($clientId === 0) {
                $clientId = $this->saveTenant($actor, null, $input);
            }
            $endsOn = $duration > 0 ? (new \DateTimeImmutable($startsOn))->modify('+' . $duration . ' months')->modify('-1 day')->format('Y-m-d') : null;
            $deposit = $money('deposit_minor');
            $this->pdo->prepare(
                "INSERT INTO loyer_leases (tenant_id, unit_id, client_id, rent_minor, charges_minor, deposit_minor, deposit_paid_on, due_day, notice_months, starts_on, ends_on, status, notes, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
            )->execute([
                $tenantId, $unitId, $clientId, $money('rent_minor'), $money('charges_minor'), $deposit,
                $deposit > 0 && !empty($input['deposit_paid']) ? $startsOn : null,
                $dueDay, max(0, min(12, (int) ($input['notice_months'] ?? 1))), $startsOn, $endsOn,
                mb_substr(trim((string) ($input['notes'] ?? '')), 0, 1000) ?: null,
            ]);
            $leaseId = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare('UPDATE loyer_leases SET reference = ? WHERE id = ?')->execute(['BAIL-' . str_pad((string) $leaseId, 5, '0', STR_PAD_LEFT), $leaseId]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
        $this->ensureSchedule($tenantId);

        return $leaseId;
    }

    public function markDeposit(User $actor, int $leaseId, string $date): void
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_LEASES);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || $date > gmdate('Y-m-d')) {
            throw new ValidationException(['deposit_paid_on' => 'Date invalide.']);
        }
        $this->pdo->prepare('UPDATE loyer_leases SET deposit_paid_on = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND tenant_id = ?')->execute([$date, $leaseId, $tenantId]);
    }

    /**
     * Fin de bail : plus d'échéances après la date de sortie, le logement redevient libre.
     */
    public function endLease(User $actor, int $leaseId, string $date, string $reason): void
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_LEASES);
        $lease = $this->row("SELECT id, starts_on FROM loyer_leases WHERE id = ? AND tenant_id = ? AND status = 'active'", [$leaseId, $tenantId]);
        if ($lease === null) {
            throw new HttpException(404, 'Contrat de bail en cours introuvable.');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || $date < (string) $lease['starts_on']) {
            throw new ValidationException(['ended_on' => 'Date de sortie invalide.']);
        }
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("UPDATE loyer_leases SET status = 'ended', ended_on = ?, end_reason = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND tenant_id = ?")
                ->execute([$date, mb_substr(trim($reason), 0, 255) ?: null, $leaseId, $tenantId]);
            $this->pdo->prepare("UPDATE loyer_rent_dues SET status = 'cancelled', updated_at = UTC_TIMESTAMP() WHERE lease_id = ? AND tenant_id = ? AND due_date > ? AND status = 'pending'")
                ->execute([$leaseId, $tenantId, $date]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    // ================================================================ Interventions

    /**
     * @return array<string, mixed>
     */
    public function interventions(User $actor, string $view): array
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_VIEW);
        $where = $view === 'terminees' ? "i.status = 'terminee'" : "i.status <> 'terminee'";

        return [
            'rows' => $this->rows(
                "SELECT i.*, p.name AS property_name, u.label AS unit_label FROM immo_interventions i
                 JOIN loyer_properties p ON p.id = i.property_id LEFT JOIN loyer_units u ON u.id = i.unit_id
                 WHERE i.tenant_id = ? AND " . $where . " ORDER BY FIELD(i.priority, 'urgente', 'normale', 'basse'), i.reported_on DESC LIMIT 200",
                [$tenantId]
            ),
            'view' => $view === 'terminees' ? 'terminees' : 'en-cours',
            'units' => $this->rows('SELECT u.id, u.label, u.property_id, p.name AS property_name FROM loyer_units u JOIN loyer_properties p ON p.id = u.property_id WHERE u.tenant_id = ? ORDER BY p.name, u.label', [$tenantId]),
            'properties' => $this->rows('SELECT id, name FROM loyer_properties WHERE tenant_id = ? ORDER BY name', [$tenantId]),
            'costYear' => (int) $this->value("SELECT COALESCE(SUM(cost_minor), 0) FROM immo_interventions WHERE tenant_id = ? AND reported_on >= ?", [$tenantId, gmdate('Y-01-01')]),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveIntervention(User $actor, array $input): void
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_DUES);
        $propertyId = (int) ($input['property_id'] ?? 0);
        $unitId = (int) ($input['unit_id'] ?? 0);
        $title = mb_substr(trim((string) ($input['title'] ?? '')), 0, 160);
        $priority = (string) ($input['priority'] ?? 'normale');
        $errors = [];
        if ($unitId > 0) {
            $unit = $this->row('SELECT property_id FROM loyer_units WHERE id = ? AND tenant_id = ?', [$unitId, $tenantId]);
            $propertyId = $unit !== null ? (int) $unit['property_id'] : 0;
        }
        if ($this->row('SELECT id FROM loyer_properties WHERE id = ? AND tenant_id = ?', [$propertyId, $tenantId]) === null) {
            $errors['property_id'] = 'Choisissez le bien ou le logement concerné.';
        }
        if ($title === '') {
            $errors['title'] = 'Décrivez le problème en quelques mots.';
        }
        if (!isset(ImmoCatalog::PRIORITIES[$priority])) {
            $errors['priority'] = 'Priorité inconnue.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $this->pdo->prepare(
            "INSERT INTO immo_interventions (tenant_id, property_id, unit_id, title, description, priority, status, provider, cost_minor, reported_on, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 'ouverte', ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
        )->execute([
            $tenantId, $propertyId, $unitId > 0 ? $unitId : null, $title,
            mb_substr(trim((string) ($input['description'] ?? '')), 0, 1000) ?: null, $priority,
            mb_substr(trim((string) ($input['provider'] ?? '')), 0, 120) ?: null,
            (int) preg_replace('/\D+/', '', (string) ($input['cost_minor'] ?? '0')), gmdate('Y-m-d'),
        ]);
        if ($priority === 'urgente') {
            $where = $this->row('SELECT p.name, u.label FROM loyer_properties p LEFT JOIN loyer_units u ON u.id = ? WHERE p.id = ?', [$unitId, $propertyId]);
            \Sesam\Services\Notifier::tenant(
                $tenantId,
                'immo.intervention',
                'Intervention urgente : ' . $title,
                trim(($where['name'] ?? '') . (($where['label'] ?? null) !== null ? ' — ' . $where['label'] : '')) . ' · signalée par ' . $actor->fullName(),
                '/immo/interventions',
                'danger',
                null,
                $actor->id(),
            );
        }
    }

    /**
     * @param array<string, mixed> $input
     */
    public function updateIntervention(User $actor, int $id, array $input): void
    {
        $tenantId = $this->tenantId($actor, Permission::LOYER_DUES);
        $status = (string) ($input['status'] ?? '');
        if (!isset(ImmoCatalog::INTERVENTION_STATUSES[$status])) {
            throw new ValidationException(['status' => 'Statut inconnu.']);
        }
        $cost = (string) ($input['cost_minor'] ?? '');
        $this->pdo->prepare(
            'UPDATE immo_interventions SET status = ?, done_on = ?, cost_minor = IF(? = \'\', cost_minor, ?), provider = COALESCE(NULLIF(?, \'\'), provider), updated_at = UTC_TIMESTAMP() WHERE id = ? AND tenant_id = ?'
        )->execute([
            $status, $status === 'terminee' ? gmdate('Y-m-d') : null, $cost, (int) preg_replace('/\D+/', '', $cost),
            mb_substr(trim((string) ($input['provider'] ?? '')), 0, 120), $id, $tenantId,
        ]);
    }

    // ================================================================ Outils

    private function tenantId(User $actor, string $permission): int
    {
        $tenantId = $actor->tenantId();
        if ($tenantId === null) {
            throw new HttpException(403, 'Réservé aux espaces Sesam Immo.');
        }
        if (!$actor->can($permission)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation de faire cette action.');
        }

        return $tenantId;
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $params): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_values(array_filter($statement->fetchAll(\PDO::FETCH_ASSOC), 'is_array'));
    }

    /**
     * @param list<mixed> $params
     * @return array<string, mixed>|null
     */
    private function row(string $sql, array $params): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param list<mixed> $params
     */
    private function value(string $sql, array $params): mixed
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn();
    }
}
