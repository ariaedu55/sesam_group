<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Boutique\Money;
use Sesam\Core\Database\Connection;
use Sesam\Core\Tenant\Sector;

/**
 * Rappels envoyés une fois par jour : programme du salon, commandes de la boutique à traiter (et point du stock
 * le lundi), échéances et retards de loyer, fins de contrat,
 * essais et abonnements des commerces, synthèses pour le personnel SESAM.
 *
 * Lancés par la tâche planifiée « php bin/notifications-quotidiennes.php », ou à défaut par la première
 * visite d'un espace connecté après 7 h. Chaque rappel a une clé unique : il ne part jamais deux fois.
 */
final class DailyNotifications
{
    public const START_HOUR = 7;

    private static ?self $current = null;
    private readonly \PDO $pdo;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        array $database,
        private readonly Notifier $notifier,
        private readonly string $stampFile,
    ) {
        $this->pdo = Connection::make($database);
    }

    public static function setCurrent(?self $jobs): void
    {
        self::$current = $jobs;
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    /**
     * Sans tâche planifiée : la première requête connectée de la journée (après 7 h) lance les rappels.
     */
    public function runIfDue(): void
    {
        $today = gmdate('Y-m-d');
        if ((int) gmdate('G') < self::START_HOUR || (is_file($this->stampFile) && trim((string) @file_get_contents($this->stampFile)) === $today)) {
            return;
        }
        $dir = dirname($this->stampFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $handle = @fopen($this->stampFile, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            return;
        }
        try {
            if (trim((string) stream_get_contents($handle)) !== $today) {
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, $today);
                fflush($handle);
                $this->run($today);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return array<string, int> nombre de notifications par rappel
     */
    public function run(?string $today = null): array
    {
        $today ??= gmdate('Y-m-d');

        return [
            'reservation.today' => $this->appointmentsOfTheDay($today),
            'reservation.pending' => $this->appointmentsToConfirm($today),
            'boutique.pending' => $this->pendingOrders($today),
            'boutique.stock' => $this->weeklyStock($today),
            'immo.due_soon' => $this->duesSoon($today),
            'immo.late' => $this->duesLate($today),
            'immo.lease_end' => $this->leasesEnding($today),
            'subscription' => $this->subscriptions($today),
            'sesam' => $this->staffDigest($today),
        ];
    }

    /**
     * Boutique : commandes arrivées avant aujourd'hui et toujours « nouvelles » (ni confirmées ni annulées).
     */
    private function pendingOrders(string $today): int
    {
        $rows = $this->rows(
            "SELECT o.tenant_id, COUNT(*) AS n, SUM(o.total_minor) AS amount, MIN(o.created_at) AS oldest
             FROM boutique_orders o JOIN tenants t ON t.id = o.tenant_id
             WHERE t.status = 'active' AND t.sector = ? AND o.status = 'new' AND o.created_at < ?
             GROUP BY o.tenant_id",
            [Sector::BOUTIQUE, $today . ' 00:00:00']
        );
        $sent = 0;
        foreach ($rows as $row) {
            $n = (int) $row['n'];
            $days = max(1, (int) floor((strtotime($today . ' 00:00:00 UTC') - strtotime((string) $row['oldest'] . ' UTC')) / 86400) + 1);
            $sent += $this->notifier->toTenant(
                (int) $row['tenant_id'],
                'boutique.pending',
                $n . ' commande(s) à traiter',
                Money::format((int) $row['amount']) . ' en attente, la plus ancienne depuis ' . $days . ' jour(s). Confirmez ou annulez-les pour tenir le stock à jour.',
                '/boutique/commandes?statut=new',
                $days >= 3 ? 'warning' : 'info',
                'shop-pending-' . $row['tenant_id'] . '-' . $today,
            );
        }

        return $sent;
    }

    /**
     * Boutique : le lundi, point des produits publiés en stock bas ou épuisés.
     */
    private function weeklyStock(string $today): int
    {
        if (gmdate('N', (int) strtotime($today . ' UTC')) !== '1') {
            return 0;
        }
        $rows = $this->rows(
            "SELECT p.tenant_id, SUM(p.stock_quantity <= 0) AS out_n, SUM(p.stock_quantity > 0) AS low_n
             FROM boutique_products p JOIN tenants t ON t.id = p.tenant_id
             WHERE t.status = 'active' AND t.sector = ? AND p.is_published = 1 AND p.track_stock = 1
               AND p.stock_quantity <= p.low_stock_threshold
             GROUP BY p.tenant_id",
            [Sector::BOUTIQUE]
        );
        $sent = 0;
        foreach ($rows as $row) {
            $out = (int) $row['out_n'];
            $low = (int) $row['low_n'];
            $parts = array_filter([$out > 0 ? $out . ' épuisé(s)' : '', $low > 0 ? $low . ' en stock bas' : '']);
            $sent += $this->notifier->toTenant(
                (int) $row['tenant_id'],
                'boutique.stock',
                'Point stock de la semaine : ' . implode(', ', $parts),
                'Réapprovisionnez avant que les clients ne tombent sur « épuisé ».',
                '/boutique/stock',
                $out > 0 ? 'warning' : 'info',
                'shop-stock-week-' . $row['tenant_id'] . '-' . $today,
            );
        }

        return $sent;
    }

    /**
     * Salon : demandes de rendez-vous à venir encore « à confirmer » (la cliente attend une réponse).
     */
    private function appointmentsToConfirm(string $today): int
    {
        $rows = $this->rows(
            "SELECT a.tenant_id, COUNT(*) AS n, MIN(a.starts_at) AS first_at
             FROM reservation_appointments a JOIN tenants t ON t.id = a.tenant_id
             WHERE t.status = 'active' AND t.sector = ? AND a.status = 'pending' AND a.starts_at >= ?
             GROUP BY a.tenant_id",
            [Sector::RESERVATION, $today . ' 00:00:00']
        );
        $sent = 0;
        foreach ($rows as $row) {
            $first = strtotime((string) $row['first_at'] . ' UTC');
            $soon = $first !== false && $first - strtotime($today . ' 00:00:00 UTC') < 2 * 86400;
            $sent += $this->notifier->toTenant(
                (int) $row['tenant_id'],
                'reservation.pending',
                (int) $row['n'] . ' réservation(s) à confirmer',
                'La première le ' . ($first !== false ? gmdate('d/m à H:i', $first) : '') . '. Vos clientes attendent votre réponse sur WhatsApp.',
                '/reservation/rendez-vous?statut=pending',
                $soon ? 'warning' : 'info',
                'resa-pending-' . $row['tenant_id'] . '-' . $today,
            );
        }

        return $sent;
    }

    private function appointmentsOfTheDay(string $today): int
    {
        $rows = $this->rows(
            "SELECT a.tenant_id, COUNT(*) AS n, MIN(a.starts_at) AS first_at,
                    SUM(a.status = 'pending') AS pending
             FROM reservation_appointments a JOIN tenants t ON t.id = a.tenant_id
             WHERE t.status = 'active' AND t.sector = ? AND a.starts_at >= ? AND a.starts_at < ? AND a.status IN ('pending', 'confirmed')
             GROUP BY a.tenant_id",
            [Sector::RESERVATION, $today . ' 00:00:00', $today . ' 23:59:59']
        );
        $sent = 0;
        foreach ($rows as $row) {
            $body = 'Premier à ' . substr((string) $row['first_at'], 11, 5) . ((int) $row['pending'] > 0 ? ' · ' . (int) $row['pending'] . ' encore à confirmer' : '') . '. Bonne journée !';
            $sent += $this->notifier->toTenant((int) $row['tenant_id'], 'reservation.today', (int) $row['n'] . ' rendez-vous aujourd\'hui', $body, '/reservation/agenda', 'info', 'resa-today-' . $row['tenant_id'] . '-' . $today);
        }

        return $sent;
    }

    private function duesSoon(string $today): int
    {
        $target = gmdate('Y-m-d', (int) strtotime($today . ' +3 days'));
        $rows = $this->rows(
            "SELECT d.tenant_id, COUNT(*) AS n, SUM(d.amount_minor - d.paid_minor) AS amount
             FROM loyer_rent_dues d JOIN loyer_leases l ON l.id = d.lease_id JOIN tenants t ON t.id = d.tenant_id
             WHERE t.status = 'active' AND l.status = 'active' AND d.status IN ('pending', 'partial') AND d.due_date = ?
             GROUP BY d.tenant_id",
            [$target]
        );
        $sent = 0;
        foreach ($rows as $row) {
            $sent += $this->notifier->toTenant((int) $row['tenant_id'], 'immo.due_soon', (int) $row['n'] . ' loyer(s) à échéance le ' . gmdate('d/m', (int) strtotime($target)), Money::format((int) $row['amount']) . ' attendus dans 3 jours. Les locataires reçoivent leur rappel WhatsApp.', '/immo/loyers?vue=a-encaisser', 'info', 'immo-soon-' . $row['tenant_id'] . '-' . $target);
        }

        return $sent;
    }

    private function duesLate(string $today): int
    {
        // Loyers qui passent en retard aujourd'hui (échéance + délai de grâce dépassés depuis ce matin).
        $rows = $this->rows(
            "SELECT d.tenant_id, COUNT(*) AS n, SUM(d.amount_minor - d.paid_minor) AS amount, GROUP_CONCAT(c.full_name ORDER BY c.full_name SEPARATOR ', ') AS names
             FROM loyer_rent_dues d JOIN loyer_leases l ON l.id = d.lease_id JOIN clients c ON c.id = l.client_id JOIN tenants t ON t.id = d.tenant_id
             LEFT JOIN immo_settings s ON s.tenant_id = d.tenant_id
             WHERE t.status = 'active' AND d.status IN ('pending', 'partial')
               AND DATE_ADD(d.due_date, INTERVAL COALESCE(s.grace_days, 3) + 1 DAY) = ?
             GROUP BY d.tenant_id",
            [$today]
        );
        $sent = 0;
        foreach ($rows as $row) {
            $sent += $this->notifier->toTenant((int) $row['tenant_id'], 'immo.late', (int) $row['n'] . ' loyer(s) passent en retard', mb_substr((string) $row['names'], 0, 160) . ' · ' . Money::format((int) $row['amount']) . ' à récupérer.', '/immo/loyers?vue=retards', 'danger', 'immo-late-' . $row['tenant_id'] . '-' . $today);
        }

        return $sent;
    }

    private function leasesEnding(string $today): int
    {
        $rows = $this->rows(
            "SELECT l.id, l.tenant_id, l.ends_on, c.full_name, u.label
             FROM loyer_leases l JOIN clients c ON c.id = l.client_id JOIN loyer_units u ON u.id = l.unit_id JOIN tenants t ON t.id = l.tenant_id
             WHERE t.status = 'active' AND l.status = 'active' AND l.ends_on IS NOT NULL AND l.ends_on BETWEEN ? AND ?",
            [$today, gmdate('Y-m-d', (int) strtotime($today . ' +30 days'))]
        );
        $sent = 0;
        foreach ($rows as $row) {
            $sent += $this->notifier->toTenant((int) $row['tenant_id'], 'immo.lease_end', 'Fin de contrat de bail le ' . gmdate('d/m/Y', (int) strtotime((string) $row['ends_on'])), $row['full_name'] . ' · ' . $row['label'] . ' : renouveler le contrat ou préparer l\'état des lieux.', '/immo/baux/' . (int) $row['id'], 'warning', 'immo-lease-end-' . $row['id'] . '-' . $row['ends_on']);
        }

        return $sent;
    }

    private function subscriptions(string $today): int
    {
        $rows = $this->rows(
            "SELECT s.tenant_id, s.status, s.period_end, DATEDIFF(s.period_end, ?) AS days
             FROM tenant_subscriptions s JOIN tenants t ON t.id = s.tenant_id
             WHERE t.status = 'active' AND s.status IN ('trial', 'active') AND s.period_end BETWEEN ? AND ?",
            [$today, gmdate('Y-m-d', (int) strtotime($today . ' -1 day')), gmdate('Y-m-d', (int) strtotime($today . ' +7 days'))]
        );
        $sent = 0;
        foreach ($rows as $row) {
            $days = (int) $row['days'];
            $trial = $row['status'] === 'trial';
            [$title, $body, $level] = match (true) {
                $days === -1 && $trial => ['Votre essai gratuit est terminé', 'Abonnez-vous pour retrouver votre espace et toutes vos données.', 'danger'],
                $days === -1 => ['Votre abonnement est arrivé à échéance', 'Renouvelez-le depuis la page Abonnement pour garder l\'accès.', 'danger'],
                in_array($days, [7, 3, 1], true) && $trial => ['Essai gratuit : plus que ' . $days . ' jour(s)', 'Choisissez votre abonnement pour continuer sans interruption.', $days === 1 ? 'warning' : 'info'],
                in_array($days, [3, 1], true) => ['Abonnement : fin dans ' . $days . ' jour(s)', 'Renouvelez en 2 clics par Wave ou Orange Money.', 'warning'],
                default => [null, null, null],
            };
            if ($title === null) {
                continue;
            }
            $sent += $this->notifier->toTenant((int) $row['tenant_id'], 'subscription', $title, (string) $body, '/abonnement', (string) $level, 'sub-' . $row['tenant_id'] . '-' . $row['period_end'] . '-' . $days);
        }

        return $sent;
    }

    private function staffDigest(string $today): int
    {
        $sent = 0;
        $trials = $this->rows(
            "SELECT t.id, COALESCE(ts.display_name, t.name) AS name FROM tenant_subscriptions s JOIN tenants t ON t.id = s.tenant_id
             LEFT JOIN tenant_settings ts ON ts.tenant_id = t.id
             WHERE s.status = 'trial' AND s.period_end BETWEEN ? AND ? ORDER BY s.period_end",
            [$today, gmdate('Y-m-d', (int) strtotime($today . ' +3 days'))]
        );
        if ($trials !== []) {
            $sent += $this->notifier->toStaff('sesam.trial', count($trials) . ' essai(s) se terminent d\'ici 3 jours', mb_substr(implode(', ', array_column($trials, 'name')), 0, 200) . '. Le bon moment pour les appeler.', '/commerces?abonnement=trial', 'warning', 'staff-trials-' . $today . '-' . implode(',', array_column($trials, 'id')));
        }
        $overdue = $this->rows(
            "SELECT t.id, COALESCE(ts.display_name, t.name) AS name, s.monthly_amount_minor AS amount FROM tenant_subscriptions s JOIN tenants t ON t.id = s.tenant_id
             LEFT JOIN tenant_settings ts ON ts.tenant_id = t.id
             WHERE s.status = 'active' AND s.period_end = ?",
            [gmdate('Y-m-d', (int) strtotime($today . ' -1 day'))]
        );
        if ($overdue !== []) {
            $sent += $this->notifier->toStaff('sesam.overdue', count($overdue) . ' abonnement(s) arrivés à échéance', mb_substr(implode(', ', array_column($overdue, 'name')), 0, 200) . ' · ' . Money::format(array_sum(array_map('intval', array_column($overdue, 'amount')))) . ' à encaisser.', '/espace#echeances', 'warning', 'staff-overdue-' . $today . '-' . implode(',', array_column($overdue, 'id')));
        }

        // Essais terminés hier sans abonnement : le commerce est bloqué, c'est le moment de le rappeler.
        $lost = $this->rows(
            "SELECT t.id, COALESCE(ts.display_name, t.name) AS name FROM tenant_subscriptions s JOIN tenants t ON t.id = s.tenant_id
             LEFT JOIN tenant_settings ts ON ts.tenant_id = t.id
             WHERE s.status = 'trial' AND s.period_end = ? ORDER BY t.id",
            [gmdate('Y-m-d', (int) strtotime($today . ' -1 day'))]
        );
        if ($lost !== []) {
            $sent += $this->notifier->toStaff('sesam.lost', count($lost) . ' essai(s) terminés sans abonnement', mb_substr(implode(', ', array_column($lost, 'name')), 0, 200) . ' : leur espace est bloqué. Un appel aujourd\'hui peut les convertir.', '/commerces?abonnement=trial', 'danger', 'staff-lost-' . $today . '-' . implode(',', array_column($lost, 'id')));
        }

        // Le lundi : relance des abonnements toujours impayés plus d'une semaine après l'échéance.
        if (gmdate('N', (int) strtotime($today . ' UTC')) === '1') {
            $stillDue = $this->rows(
                "SELECT t.id, COALESCE(ts.display_name, t.name) AS name, s.monthly_amount_minor AS amount FROM tenant_subscriptions s JOIN tenants t ON t.id = s.tenant_id
                 LEFT JOIN tenant_settings ts ON ts.tenant_id = t.id
                 WHERE s.status = 'active' AND s.period_end < ? ORDER BY s.period_end",
                [gmdate('Y-m-d', (int) strtotime($today . ' -7 days'))]
            );
            if ($stillDue !== []) {
                $sent += $this->notifier->toStaff('sesam.overdue', count($stillDue) . ' abonnement(s) impayés depuis plus d\'une semaine', mb_substr(implode(', ', array_column($stillDue, 'name')), 0, 200) . ' · ' . Money::format(array_sum(array_map('intval', array_column($stillDue, 'amount')))) . ' à récupérer.', '/espace#echeances', 'danger', 'staff-overdue-week-' . $today . '-' . implode(',', array_column($stillDue, 'id')));
            }
        }

        // Demandes de devis du site (30 derniers jours) arrivées il y a plus de 2 jours et pas encore traitées.
        $requests = $this->rows(
            "SELECT c.id, c.full_name AS name FROM contact_requests c
             LEFT JOIN agency_prospects p ON p.contact_request_id = c.id
             WHERE p.id IS NULL AND c.created_at < ? AND c.created_at >= ? ORDER BY c.created_at",
            [gmdate('Y-m-d H:i:s', (int) strtotime($today . ' 00:00:00 UTC -1 day')), gmdate('Y-m-d H:i:s', (int) strtotime($today . ' 00:00:00 UTC -30 days'))]
        );
        if ($requests !== []) {
            $sent += $this->notifier->toStaff('sesam.quote', count($requests) . ' demande(s) de devis en attente', mb_substr(implode(', ', array_column($requests, 'name')), 0, 200) . ' attendent une réponse depuis plus de 2 jours.', '/agence/demandes', 'warning', 'staff-requests-' . $today . '-' . implode(',', array_column($requests, 'id')));
        }

        // Agence : relances de prospects prévues aujourd'hui (ou oubliées) et devis envoyés sans réponse depuis 7 jours.
        $followUps = $this->rows(
            "SELECT id, COALESCE(NULLIF(business_name, ''), full_name) AS name FROM agency_prospects
             WHERE next_action_on IS NOT NULL AND next_action_on <= ? AND stage NOT IN ('perdu', 'gagne', 'projet-en-cours', 'livre')
             ORDER BY next_action_on",
            [$today]
        );
        if ($followUps !== []) {
            $sent += $this->notifier->toStaff('sesam.agency', count($followUps) . ' prospect(s) à relancer aujourd\'hui', mb_substr(implode(', ', array_column($followUps, 'name')), 0, 200) . '.', '/agence/prospects', 'info', 'staff-followups-' . $today . '-' . implode(',', array_column($followUps, 'id')));
        }
        $quotes = $this->rows(
            "SELECT q.id, q.reference FROM agency_quotes q
             WHERE q.status = 'sent' AND COALESCE(q.sent_at, q.updated_at) < ? ORDER BY q.id",
            [gmdate('Y-m-d H:i:s', (int) strtotime($today . ' 00:00:00 UTC -7 days'))]
        );
        if ($quotes !== []) {
            $sent += $this->notifier->toStaff('sesam.agency', count($quotes) . ' devis sans réponse depuis 7 jours', 'Devis ' . mb_substr(implode(', ', array_column($quotes, 'reference')), 0, 180) . ' : un appel de relance ?', '/agence/prospects', 'warning', 'staff-quotes-' . $today . '-' . implode(',', array_column($quotes, 'id')));
        }

        // Agence : factures clients arrivées à échéance et toujours à payer.
        $invoices = $this->rows(
            "SELECT id, reference, amount FROM agency_invoices WHERE status = 'issued' AND due_on < ? ORDER BY due_on",
            [$today]
        );
        if ($invoices !== []) {
            $sent += $this->notifier->toStaff('sesam.invoices', count($invoices) . ' facture(s) agence en retard', mb_substr(implode(', ', array_column($invoices, 'reference')), 0, 180) . ' · ' . Money::format(array_sum(array_map('intval', array_column($invoices, 'amount')))) . ' à encaisser.', '/agence/projets', 'warning', 'staff-invoices-' . $today . '-' . implode(',', array_column($invoices, 'id')));
        }

        return $sent;
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $params): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }
}
