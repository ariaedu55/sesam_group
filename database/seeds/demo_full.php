<?php

declare(strict_types=1);

/*
 * Données complètes de démonstration, chargées par demo.php : clientes, rendez-vous, commandes, loyers,
 * paiements simulés (bac à sable NabooPay), agence et comptes d'équipe. Chaque bloc ne s'ajoute qu'une fois.
 * Tous les numéros sont fictifs (77 00x xx xx) et les e-mails en @exemple.sn.
 */

use Sesam\Core\Auth\Role;
use Sesam\Core\Tenant\Sector;
use Sesam\Repositories\RoleRepository;
use Sesam\Repositories\UserRepository;

/**
 * @param array<string, int> $tenantIds secteur => id du commerce de démo
 */
function seedFullDemo(PDO $pdo, array $tenantIds, UserRepository $users, RoleRepository $roles, string $hash): void
{
    $team = [
        Sector::RESERVATION => ['reservation', 'Ndèye Gérante', 'Aminata Employée'],
        Sector::BOUTIQUE => ['boutique', 'Moussa Gérant', 'Binta Vendeuse'],
        Sector::LOYER => ['loyer', 'Ibrahima Gérant', 'Coumba Assistante'],
    ];
    foreach ($team as $sector => [$slug, $manager, $staff]) {
        if (!isset($tenantIds[$sector])) {
            continue;
        }
        ensureUser($users, $roles, 'demo.' . $slug . '.gerant@sesam.local', $manager, $tenantIds[$sector], [Role::MANAGER], $hash);
        ensureUser($users, $roles, 'demo.' . $slug . '.employe@sesam.local', $staff, $tenantIds[$sector], [Role::STAFF], $hash);
    }
    echo "Équipes : un gérant et un employé par commerce\n";

    if (isset($tenantIds[Sector::RESERVATION])) {
        demoReservation($pdo, $tenantIds[Sector::RESERVATION]);
    }
    if (isset($tenantIds[Sector::BOUTIQUE])) {
        demoBoutique($pdo, $tenantIds[Sector::BOUTIQUE]);
    }
    if (isset($tenantIds[Sector::LOYER])) {
        demoLoyer($pdo, $tenantIds[Sector::LOYER]);
        demoImmo($pdo, $tenantIds[Sector::LOYER]);
    }
    demoAgency($pdo);
    demoTrialSignup($pdo, $users, $roles, $hash);
    demoQuoteRequests($pdo);
    foreach ($tenantIds as $tenantId) {
        demoNotifications($pdo, $tenantId);
    }
}

// ------------------------------------------------------------------ Outils

function demoAt(string $modifier, string $time = ''): string
{
    $date = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify($modifier);

    return $time === '' ? $date->format('Y-m-d H:i:s') : $date->format('Y-m-d') . ' ' . $time;
}

function demoDate(string $modifier): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify($modifier)->format('Y-m-d');
}

function demoOwner(PDO $pdo, int $tenantId): ?int
{
    $statement = $pdo->prepare(
        "SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id
         WHERE u.tenant_id = ? AND r.slug = 'tenant-owner' ORDER BY u.id LIMIT 1"
    );
    $statement->execute([$tenantId]);
    $id = $statement->fetchColumn();

    return $id === false ? null : (int) $id;
}

function demoAlready(PDO $pdo, string $sql, array $params): bool
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn() > 0;
}

/**
 * Ajoute (ou retrouve) une fiche cliente et renvoie son id.
 */
function demoClient(PDO $pdo, int $tenantId, string $name, string $phone, ?string $email, string $createdAt): int
{
    $find = $pdo->prepare('SELECT id FROM clients WHERE tenant_id = ? AND phone = ? LIMIT 1');
    $find->execute([$tenantId, $phone]);
    $id = $find->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $pdo->prepare(
        "INSERT INTO clients (tenant_id, full_name, email, phone, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'active', ?, ?)"
    )->execute([$tenantId, $name, $email, $phone, $createdAt, $createdAt]);

    return (int) $pdo->lastInsertId();
}

/**
 * @param list<string> $names
 * @return array<string, int>
 */
function demoTags(PDO $pdo, int $tenantId, array $names): array
{
    $ids = [];
    foreach ($names as $name) {
        $find = $pdo->prepare('SELECT id FROM client_tags WHERE tenant_id = ? AND name = ?');
        $find->execute([$tenantId, $name]);
        $id = $find->fetchColumn();
        if ($id === false) {
            $pdo->prepare('INSERT INTO client_tags (tenant_id, name, created_at) VALUES (?, ?, UTC_TIMESTAMP())')->execute([$tenantId, $name]);
            $id = $pdo->lastInsertId();
        }
        $ids[$name] = (int) $id;
    }

    return $ids;
}

function demoTag(PDO $pdo, int $tenantId, int $clientId, int $tagId): void
{
    $pdo->prepare('INSERT IGNORE INTO client_tags_map (client_id, tag_id, tenant_id) VALUES (?, ?, ?)')->execute([$clientId, $tagId, $tenantId]);
}

function demoNote(PDO $pdo, int $tenantId, int $clientId, ?int $authorId, string $body, string $createdAt): void
{
    if ($authorId === null) {
        return;
    }
    $pdo->prepare('INSERT INTO client_notes (tenant_id, client_id, author_user_id, body, created_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$tenantId, $clientId, $authorId, $body, $createdAt]);
}

function demoActivity(PDO $pdo, int $tenantId, int $clientId, string $kind, string $summary, string $createdAt): void
{
    $pdo->prepare('INSERT INTO client_activities (tenant_id, client_id, actor_user_id, kind, summary, created_at) VALUES (?, ?, NULL, ?, ?, ?)')
        ->execute([$tenantId, $clientId, $kind, $summary, $createdAt]);
}

/**
 * Paiement simulé (bac à sable NabooPay) avec son historique : lien créé, puis payé ou annulé.
 */
function demoPayment(PDO $pdo, int $tenantId, ?int $clientId, int $amount, string $description, string $status, string $createdAt): int
{
    $paidAt = $status === 'success' ? (new DateTimeImmutable($createdAt))->modify('+14 minutes')->format('Y-m-d H:i:s') : null;
    $external = 'sandbox_tx_' . bin2hex(random_bytes(8));
    $pdo->prepare(
        "INSERT INTO payments (tenant_id, client_id, reference, amount_minor, currency, status, gateway, mode, description, external_id, paid_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, 'XOF', ?, 'naboopay', 'sandbox', ?, ?, ?, ?, ?)"
    )->execute([$tenantId, $clientId, bin2hex(random_bytes(16)), $amount, $status, $description, $status === 'success' ? $external : null, $paidAt, $createdAt, $paidAt ?? $createdAt]);
    $paymentId = (int) $pdo->lastInsertId();
    $tx = $pdo->prepare('INSERT INTO payment_transactions (payment_id, tenant_id, kind, status, external_id, payload, created_at) VALUES (?, ?, ?, ?, ?, NULL, ?)');
    $tx->execute([$paymentId, $tenantId, 'checkout_created', 'pending', 'sandbox_ck_' . bin2hex(random_bytes(6)), $createdAt]);
    if ($status === 'success') {
        $tx->execute([$paymentId, $tenantId, 'sandbox_confirm', 'success', $external, $paidAt]);
    } elseif ($status === 'cancelled') {
        $tx->execute([$paymentId, $tenantId, 'sandbox_cancel', 'cancelled', null, (new DateTimeImmutable($createdAt))->modify('+5 minutes')->format('Y-m-d H:i:s')]);
    }

    return $paymentId;
}

// ------------------------------------------------------------------ Réservation

function demoReservation(PDO $pdo, int $tenantId): void
{
    if (demoAlready($pdo, "SELECT COUNT(*) FROM clients WHERE tenant_id = ? AND email LIKE '%@exemple.sn'", [$tenantId])) {
        echo "Salon : données complètes déjà présentes\n";

        return;
    }
    $services = [];
    $statement = $pdo->prepare('SELECT id, name, duration_minutes, price_minor, deposit_minor FROM reservation_services WHERE tenant_id = ?');
    $statement->execute([$tenantId]);
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $services[(string) $row['name']] = $row;
    }
    $staff = [];
    $statement = $pdo->prepare('SELECT id, name FROM reservation_staff WHERE tenant_id = ?');
    $statement->execute([$tenantId]);
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $staff[(string) $row['name']] = (int) $row['id'];
    }
    if (!isset($services['Tresses africaines'], $staff['Aminata'], $staff['Khady'])) {
        echo "Salon : prestations de démo absentes, données complètes ignorées\n";

        return;
    }
    $owner = demoOwner($pdo, $tenantId);
    $tags = demoTags($pdo, $tenantId, ['Fidèle', 'VIP', 'Nouvelle cliente']);
    $people = [
        ['Rokhaya Sène', '770010001', 'rokhaya.sene@exemple.sn', ['Fidèle', 'VIP']],
        ['Aïssatou Diallo', '770010002', 'aissatou.diallo@exemple.sn', ['Fidèle']],
        ['Ndeye Fatou Mbaye', '770010003', null, ['Nouvelle cliente']],
        ['Coumba Gaye', '770010004', 'coumba.gaye@exemple.sn', ['Fidèle']],
        ['Marième Faye', '770010005', null, []],
        ['Astou Ndour', '770010006', 'astou.ndour@exemple.sn', ['VIP']],
        ['Khadija Sy', '770010007', null, ['Nouvelle cliente']],
        ['Penda Thiam', '770010008', 'penda.thiam@exemple.sn', []],
        ['Yacine Cissé', '770010009', null, ['Fidèle']],
        ['Oumou Kane', '770010010', 'oumou.kane@exemple.sn', []],
    ];
    $clients = [];
    foreach ($people as $i => [$name, $phone, $email, $clientTags]) {
        $id = demoClient($pdo, $tenantId, $name, $phone, $email, demoAt('-' . (60 - $i * 4) . ' days', '10:00:00'));
        $clients[] = [$id, $name];
        foreach ($clientTags as $tag) {
            demoTag($pdo, $tenantId, $id, $tags[$tag]);
        }
    }
    demoNote($pdo, $tenantId, $clients[0][0], $owner, 'Préfère Aminata. Cheveux longs, prévoir 3 h pour les tresses.', demoAt('-20 days'));
    demoNote($pdo, $tenantId, $clients[5][0], $owner, 'Cliente VIP : offrir le soin du visage pour son anniversaire (12 novembre).', demoAt('-8 days'));

    $insert = $pdo->prepare(
        'INSERT INTO reservation_appointments (tenant_id, client_id, service_id, staff_id, starts_at, duration_minutes, price_minor, status, source,
            deposit_amount_minor, payment_id, is_paid, notes, cancelled_at, completed_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)'
    );
    // [jour relatif, heure, cliente, prestation, personne, statut, source, payé, note]
    $plan = [
        [-20, '10:00', 0, 'Tresses africaines', 'Aminata', 'completed', 'online', 1, null],
        [-19, '15:00', 1, 'Brushing', 'Aminata', 'completed', 'manual', 1, null],
        [-18, '11:00', 5, 'Soin du visage éclat', 'Khady', 'completed', 'online', 1, null],
        [-16, '09:30', 2, 'Coupe et coiffage', 'Aminata', 'no_show', 'online', 0, null],
        [-15, '14:00', 3, 'Pose de vernis semi-permanent', 'Khady', 'completed', 'manual', 1, null],
        [-13, '10:00', 4, 'Tresses africaines', 'Aminata', 'completed', 'online', 1, null],
        [-12, '16:00', 6, 'Soin du visage éclat', 'Khady', 'cancelled', 'online', 0, 'Annulé par la cliente (voyage).'],
        [-11, '11:30', 7, 'Brushing', 'Aminata', 'completed', 'manual', 1, null],
        [-9, '15:30', 8, 'Pose de vernis semi-permanent', 'Khady', 'completed', 'online', 1, null],
        [-8, '10:00', 9, 'Coupe et coiffage', 'Aminata', 'completed', 'online', 0, 'Reste 5 000 FCFA à régler.'],
        [-6, '09:00', 0, 'Brushing', 'Aminata', 'completed', 'online', 1, null],
        [-5, '14:00', 1, 'Soin du visage éclat', 'Khady', 'completed', 'manual', 1, null],
        [-4, '10:30', 3, 'Tresses africaines', 'Aminata', 'completed', 'online', 1, null],
        [-3, '17:00', 5, 'Pose de vernis semi-permanent', 'Khady', 'no_show', 'online', 0, null],
        [-2, '11:00', 6, 'Coupe et coiffage', 'Aminata', 'completed', 'manual', 1, null],
        [-1, '15:00', 2, 'Soin du visage éclat', 'Khady', 'completed', 'online', 1, null],
        [1, '10:00', 4, 'Brushing', 'Aminata', 'confirmed', 'online', 0, null],
        [1, '16:00', 8, 'Soin du visage éclat', 'Khady', 'confirmed', 'online', 0, null],
        [2, '09:00', 5, 'Tresses africaines', 'Aminata', 'confirmed', 'online', 0, 'Mèches apportées par la cliente.'],
        [2, '14:30', 9, 'Pose de vernis semi-permanent', 'Khady', 'pending', 'online', 0, null],
        [3, '11:00', 7, 'Coupe et coiffage', 'Aminata', 'pending', 'online', 0, null],
        [4, '15:00', 0, 'Soin du visage éclat', 'Khady', 'confirmed', 'manual', 0, null],
        [5, '10:00', 1, 'Tresses africaines', 'Aminata', 'pending', 'online', 0, null],
        [6, '12:00', 3, 'Brushing', 'Aminata', 'confirmed', 'online', 0, null],
    ];
    $created = [];
    foreach ($plan as [$day, $time, $who, $serviceName, $person, $status, $source, $paid, $note]) {
        $service = $services[$serviceName];
        $startsAt = demoAt(($day >= 0 ? '+' : '') . $day . ' days', $time . ':00');
        // Le dimanche est fermé : on décale au lundi.
        if ((int) (new DateTimeImmutable($startsAt))->format('N') === 7) {
            $startsAt = (new DateTimeImmutable($startsAt))->modify('+1 day')->format('Y-m-d H:i:s');
        }
        $createdAt = (new DateTimeImmutable($startsAt))->modify('-3 days')->format('Y-m-d H:i:s');
        $insert->execute([
            $tenantId, $clients[$who][0], (int) $service['id'], $staff[$person], $startsAt, (int) $service['duration_minutes'],
            (int) $service['price_minor'], $status, $source, (int) $service['deposit_minor'], $paid, $note,
            $status === 'cancelled' ? $createdAt : null,
            $status === 'completed' ? (new DateTimeImmutable($startsAt))->modify('+' . (int) $service['duration_minutes'] . ' minutes')->format('Y-m-d H:i:s') : null,
            $createdAt, $createdAt,
        ]);
        $id = (int) $pdo->lastInsertId();
        $created[] = [$id, $clients[$who], $service, $startsAt, $status];
        demoActivity($pdo, $tenantId, $clients[$who][0], 'reservation', 'Rendez-vous ' . $serviceName . ' le ' . (new DateTimeImmutable($startsAt))->format('d/m à H:i'), $createdAt);
    }

    // Acomptes payés en ligne (simulation NabooPay) pour les prestations qui en demandent un.
    $link = $pdo->prepare('UPDATE reservation_appointments SET payment_id = ? WHERE id = ?');
    $deposits = 0;
    foreach ($created as [$id, [$clientId, $name], $service, $startsAt, $status]) {
        if ((int) $service['deposit_minor'] < 100 || $status === 'no_show') {
            continue;
        }
        $state = $status === 'cancelled' ? 'cancelled' : ($status === 'pending' ? 'pending' : 'success');
        $paymentId = demoPayment($pdo, $tenantId, $clientId, (int) $service['deposit_minor'], 'Acompte ' . $service['name'] . ' — ' . $name,
            $state, (new DateTimeImmutable($startsAt))->modify('-3 days')->format('Y-m-d H:i:s'));
        $link->execute([$paymentId, $id]);
        $deposits++;
    }
    // Un lien de paiement en attente, à confirmer soi-même dans le bac à sable.
    demoPayment($pdo, $tenantId, $clients[9][0], 5000, 'Solde coupe et coiffage — Oumou Kane', 'pending', demoAt('-1 day', '18:00:00'));
    echo 'Salon : 10 clientes, ' . count($plan) . " rendez-vous sur 4 semaines, {$deposits} acomptes et 1 lien de paiement en attente\n";
}

// ------------------------------------------------------------------ Boutique

function demoBoutique(PDO $pdo, int $tenantId): void
{
    if (demoAlready($pdo, "SELECT COUNT(*) FROM boutique_orders WHERE tenant_id = ? AND order_number LIKE 'BTQ-DEMO-%'", [$tenantId])) {
        echo "Boutique : commandes de démo déjà présentes\n";

        return;
    }
    $products = [];
    $statement = $pdo->prepare('SELECT id, name, price_minor, stock_quantity, track_stock FROM boutique_products WHERE tenant_id = ?');
    $statement->execute([$tenantId]);
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $products[(string) $row['name']] = $row;
    }
    if (count($products) < 4) {
        echo "Boutique : catalogue de démo absent, commandes ignorées\n";

        return;
    }
    $owner = demoOwner($pdo, $tenantId);
    $tags = demoTags($pdo, $tenantId, ['Fidèle', 'Grossiste', 'Instagram']);
    $people = [
        ['Mame Diarra Fall', '770020001', 'mame.fall@exemple.sn', 'Sacré-Cœur 3, villa 45', ['Fidèle']],
        ['Adama Ndiaye', '770020002', null, 'Parcelles U17', ['Instagram']],
        ['Sokhna Mbacké', '770020003', 'sokhna.mbacke@exemple.sn', 'Point E, rue 4', ['Fidèle', 'Grossiste']],
        ['Fatim Diop', '770020004', null, 'Ouakam, cité Avion', ['Instagram']],
        ['Rama Sarr', '770020005', 'rama.sarr@exemple.sn', 'Mermoz Pyrotechnie', []],
        ['Aby Niang', '770020006', null, 'Grand Yoff', ['Fidèle']],
        ['Bineta Kâ', '770020007', null, 'Médina, rue 22', []],
        ['Daba Seck', '770020008', 'daba.seck@exemple.sn', 'Almadies', ['Grossiste']],
    ];
    $clients = [];
    foreach ($people as $i => [$name, $phone, $email, $address, $clientTags]) {
        $id = demoClient($pdo, $tenantId, $name, $phone, $email, demoAt('-' . (45 - $i * 4) . ' days', '12:00:00'));
        $clients[] = [$id, $name, $phone, $address];
        foreach ($clientTags as $tag) {
            demoTag($pdo, $tenantId, $id, $tags[$tag]);
        }
    }
    demoNote($pdo, $tenantId, $clients[2][0], $owner, 'Revend en boutique à Touba : tarif grossiste à partir de 5 pièces.', demoAt('-15 days'));

    // [jours, cliente, statut, payé, source, lignes [produit, qté], note]
    $plan = [
        [-28, 0, 'delivered', 1, 'whatsapp', [['Robe wax « Teranga »', 1], ['Parure de bijoux dorés', 1]], null],
        [-24, 1, 'delivered', 1, 'whatsapp', [['Sac en raphia', 1]], null],
        [-21, 2, 'delivered', 1, 'manual', [['Ensemble boubou brodé', 2], ['Pochette perlée', 1]], 'Commande pour revente.'],
        [-17, 3, 'cancelled', 0, 'whatsapp', [['Foulard en soie', 1]], 'Produit en rupture, cliente remboursée.'],
        [-14, 4, 'delivered', 1, 'whatsapp', [['Robe wax « Teranga »', 2]], null],
        [-10, 5, 'delivered', 1, 'manual', [['Parure de bijoux dorés', 2]], null],
        [-7, 6, 'delivered', 0, 'whatsapp', [['Sac en raphia', 1], ['Parure de bijoux dorés', 1]], 'Paiement à la livraison.'],
        [-4, 7, 'confirmed', 1, 'whatsapp', [['Ensemble boubou brodé', 1]], 'Livraison prévue samedi.'],
        [-2, 0, 'confirmed', 0, 'whatsapp', [['Robe wax « Teranga »', 1]], null],
        [-1, 2, 'new', 0, 'whatsapp', [['Pochette perlée', 1], ['Sac en raphia', 1]], 'Possible de livrer avant 18 h ?'],
        [0, 4, 'new', 0, 'whatsapp', [['Parure de bijoux dorés', 1]], null],
        [0, 1, 'new', 0, 'whatsapp', [['Robe wax « Teranga »', 1], ['Sac en raphia', 1]], null],
    ];
    $order = $pdo->prepare(
        'INSERT INTO boutique_orders (tenant_id, client_id, order_number, status, source, customer_name, customer_phone, customer_address, note,
            total_minor, is_paid, stock_applied, confirmed_at, delivered_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $item = $pdo->prepare(
        'INSERT INTO boutique_order_items (tenant_id, order_id, product_id, product_name, quantity, unit_price_minor, line_total_minor) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stock = $pdo->prepare('UPDATE boutique_products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ? AND tenant_id = ?');
    $movement = $pdo->prepare(
        "INSERT INTO boutique_stock_movements (tenant_id, product_id, type, quantity, stock_after, note, order_id, user_id, created_at)
         VALUES (?, ?, 'sale', ?, (SELECT stock_quantity FROM boutique_products WHERE id = ?), ?, ?, NULL, ?)"
    );
    $paidOrders = 0;
    foreach ($plan as $n => [$day, $who, $status, $paid, $source, $lines, $note]) {
        [$clientId, $name, $phone, $address] = $clients[$who];
        $createdAt = demoAt(($day >= 0 ? '+' : '') . $day . ' days', sprintf('%02d:%02d:00', 9 + $n % 9, ($n * 17) % 60));
        $total = 0;
        foreach ($lines as [$productName, $qty]) {
            $total += (int) $products[$productName]['price_minor'] * $qty;
        }
        $applied = in_array($status, ['confirmed', 'delivered'], true) ? 1 : 0;
        $confirmedAt = $applied === 1 ? (new DateTimeImmutable($createdAt))->modify('+2 hours')->format('Y-m-d H:i:s') : null;
        $deliveredAt = $status === 'delivered' ? (new DateTimeImmutable($createdAt))->modify('+1 day')->format('Y-m-d H:i:s') : null;
        $number = 'BTQ-DEMO-' . str_pad((string) ($n + 1), 3, '0', STR_PAD_LEFT);
        $order->execute([$tenantId, $clientId, $number, $status, $source, $name, $phone, $address, $note, $total, $paid, $applied, $confirmedAt, $deliveredAt, $createdAt, $deliveredAt ?? $confirmedAt ?? $createdAt]);
        $orderId = (int) $pdo->lastInsertId();
        foreach ($lines as [$productName, $qty]) {
            $product = $products[$productName];
            $item->execute([$tenantId, $orderId, (int) $product['id'], $productName, $qty, (int) $product['price_minor'], (int) $product['price_minor'] * $qty]);
            if ($applied === 1 && (int) $product['track_stock'] === 1) {
                $stock->execute([$qty, (int) $product['id'], $tenantId]);
                $movement->execute([$tenantId, (int) $product['id'], -$qty, (int) $product['id'], 'Commande ' . $number, $orderId, $confirmedAt]);
            }
        }
        demoActivity($pdo, $tenantId, $clientId, 'boutique', 'Commande ' . $number . ' (' . number_format($total, 0, ',', ' ') . ' FCFA)', $createdAt);
        // Les commandes payées en ligne passent par un lien de paiement simulé.
        if ($paid === 1 && $source === 'whatsapp') {
            demoPayment($pdo, $tenantId, $clientId, $total, 'Commande ' . $number . ' — ' . $name, 'success', (new DateTimeImmutable($createdAt))->modify('+1 hour')->format('Y-m-d H:i:s'));
            $paidOrders++;
        }
    }
    demoPayment($pdo, $tenantId, $clients[0][0], (int) $products['Robe wax « Teranga »']['price_minor'], 'Commande BTQ-DEMO-009 — Mame Diarra Fall', 'pending', demoAt('-2 days', '15:00:00'));
    echo 'Boutique : 8 clientes, ' . count($plan) . " commandes (nouvelles, confirmées, livrées, annulée), {$paidOrders} paiements Wave simulés et 1 en attente\n";
}

// ------------------------------------------------------------------ Loyer

function demoLoyer(PDO $pdo, int $tenantId): void
{
    if (demoAlready($pdo, 'SELECT COUNT(*) FROM loyer_properties WHERE tenant_id = ?', [$tenantId])) {
        echo "Loyer : biens déjà présents\n";

        return;
    }
    $now = gmdate('Y-m-d H:i:s');
    $owner = demoOwner($pdo, $tenantId);
    $property = $pdo->prepare('INSERT INTO loyer_properties (tenant_id, name, address, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
    $unit = $pdo->prepare('INSERT INTO loyer_units (tenant_id, property_id, label, default_rent_minor, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?)');
    $units = [];
    foreach ([
        ['Immeuble Liberté 6', 'Liberté 6 extension, Dakar', [['Appartement A1 — F3', 250000], ['Appartement A2 — F3', 250000], ['Studio B1', 120000], ['Local commercial RDC', 300000]]],
        ['Villa Almadies', 'Route des Almadies, Dakar', [['Villa F5 meublée', 650000]]],
    ] as [$name, $address, $list]) {
        $property->execute([$tenantId, $name, $address, $now, $now]);
        $propertyId = (int) $pdo->lastInsertId();
        foreach ($list as [$label, $rent]) {
            $unit->execute([$tenantId, $propertyId, $label, $rent, $now, $now]);
            $units[$label] = [(int) $pdo->lastInsertId(), $rent];
        }
    }
    $tags = demoTags($pdo, $tenantId, ['Locataire', 'Bon payeur', 'Retards']);
    $tenants = [
        ['Ousmane Diagne', '770030001', 'ousmane.diagne@exemple.sn', 'Appartement A1 — F3', '-14 months', ['Locataire', 'Bon payeur']],
        ['Awa Thiaw', '770030002', 'awa.thiaw@exemple.sn', 'Appartement A2 — F3', '-8 months', ['Locataire']],
        ['Cheikh Fall', '770030003', null, 'Studio B1', '-5 months', ['Locataire', 'Retards']],
        ['Société Teranga Print', '770030004', 'contact@terangaprint.exemple.sn', 'Local commercial RDC', '-20 months', ['Locataire', 'Bon payeur']],
        ['Famille Lô', '770030005', 'famille.lo@exemple.sn', 'Villa F5 meublée', '-3 months', ['Locataire']],
    ];
    $lease = $pdo->prepare(
        "INSERT INTO loyer_leases (tenant_id, unit_id, client_id, rent_minor, due_day, starts_on, ends_on, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, 5, ?, NULL, 'active', ?, ?)"
    );
    $due = $pdo->prepare(
        'INSERT INTO loyer_rent_dues (tenant_id, lease_id, due_date, amount_minor, status, payment_id, paid_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $utc = new DateTimeZone('UTC');
    $thisMonth = new DateTimeImmutable('first day of this month', $utc);
    $counts = ['paid' => 0, 'late' => 0, 'pending' => 0];
    foreach ($tenants as $index => [$name, $phone, $email, $label, $since, $clientTags]) {
        $start = (new DateTimeImmutable($since, $utc))->modify('first day of this month');
        $clientId = demoClient($pdo, $tenantId, $name, $phone, $email, $start->format('Y-m-d') . ' 09:00:00');
        foreach ($clientTags as $tag) {
            demoTag($pdo, $tenantId, $clientId, $tags[$tag]);
        }
        [$unitId, $rent] = $units[$label];
        $lease->execute([$tenantId, $unitId, $clientId, $rent, $start->format('Y-m-d'), $now, $now]);
        $leaseId = (int) $pdo->lastInsertId();
        // Six derniers mois d'échéances (ou depuis le début du bail) + le mois prochain.
        for ($m = -5; $m <= 1; $m++) {
            $month = $thisMonth->modify(($m >= 0 ? '+' : '') . $m . ' months');
            if ($month < $start) {
                continue;
            }
            $dueDate = $month->format('Y-m') . '-05';
            $createdAt = $month->modify('-10 days')->format('Y-m-d') . ' 08:00:00';
            $late = $index === 2 && $m >= -1 && $m <= 0; // Cheikh Fall : deux mois en retard
            $isPaid = $m < 0 && !$late || ($m === 0 && in_array($index, [0, 3], true));
            $paymentId = null;
            $paidAt = null;
            if ($isPaid) {
                $paidAt = $month->modify('+' . (2 + $index) . ' days')->format('Y-m-d') . ' 11:20:00';
                // Les loyers réglés par Wave / Orange Money passent par un paiement simulé.
                if ($index !== 3) {
                    $paymentId = demoPayment($pdo, $tenantId, $clientId, $rent, 'Loyer ' . $month->format('m/Y') . ' — ' . $label, 'success', (new DateTimeImmutable($paidAt))->modify('-14 minutes')->format('Y-m-d H:i:s'));
                }
                $counts['paid']++;
            } elseif ($m === 0 && $index === 1) {
                // Lien envoyé ce mois-ci, pas encore payé : à confirmer dans le bac à sable.
                $paymentId = demoPayment($pdo, $tenantId, $clientId, $rent, 'Loyer ' . $month->format('m/Y') . ' — ' . $label, 'pending', demoAt('-1 day', '09:00:00'));
                $counts['pending']++;
            } else {
                $counts[$dueDate < gmdate('Y-m-d') ? 'late' : 'pending']++;
            }
            $due->execute([$tenantId, $leaseId, $dueDate, $rent, $isPaid ? 'paid' : 'pending', $paymentId, $paidAt, $createdAt, $paidAt ?? $createdAt]);
        }
        demoActivity($pdo, $tenantId, $clientId, 'loyer', 'Bail signé : ' . $label . ' — ' . number_format($rent, 0, ',', ' ') . ' FCFA / mois', $start->format('Y-m-d') . ' 09:00:00');
    }
    $find = $pdo->prepare('SELECT id FROM clients WHERE tenant_id = ? AND phone = ?');
    $find->execute([$tenantId, '770030003']);
    $late = $find->fetchColumn();
    if ($late !== false) {
        demoNote($pdo, $tenantId, (int) $late, $owner, 'A promis de régler les deux mois en retard avant le 20. Relancer par WhatsApp.', demoAt('-3 days'));
    }
    echo "Loyer : 2 biens, 5 logements, 5 baux, {$counts['paid']} loyers payés, {$counts['late']} en retard, {$counts['pending']} à venir\n";
}

// ------------------------------------------------------------------ Agence SESAM

function demoAgency(PDO $pdo): void
{
    if (demoAlready($pdo, "SELECT COUNT(*) FROM agency_prospects WHERE email LIKE '%@exemple.sn'", [])) {
        echo "Agence : pipeline de démo déjà présent\n";

        return;
    }
    $staff = $pdo->query("SELECT id FROM users WHERE email = 'demo.commercial@sesam.local'")->fetchColumn();
    $actor = $staff === false ? null : (int) $staff;
    $prospect = $pdo->prepare(
        "INSERT INTO agency_prospects (full_name, business_name, email, phone, activity, source, contact_request_id, stage, next_action_on, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)"
    );
    $event = $pdo->prepare('INSERT INTO agency_prospect_events (prospect_id, kind, body, actor_user_id, created_at) VALUES (?, ?, ?, ?, ?)');
    $quote = $pdo->prepare(
        'INSERT INTO agency_quotes (prospect_id, reference, offer, site_path, amount, description, status, sent_at, decided_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $project = $pdo->prepare(
        'INSERT INTO agency_projects (prospect_id, quote_id, title, offer, amount, status, start_month, started_on, delivered_on, site_url, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $invoice = $pdo->prepare(
        'INSERT INTO agency_invoices (project_id, reference, label, amount, status, due_on, paid_on, payment_method, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $year = gmdate('Y');
    $quoteNo = 900;
    $invoiceNo = 900;
    // [nom, entreprise, activité, source, étape, il y a (jours), prochaine action (jours), devis?, projet?]
    $list = [
        ['Mamadou Sow', 'Restaurant Le Baobab', 'autre', 'site', 'nouveau', 2, 1, null, null],
        ['Fatou Kiné Diop', 'Kiné Beauty', 'salon-beaute', 'manuel', 'contacte', 6, 2, null, null],
        ['Pape Ndiaye', 'Ndiaye Immobilier', 'proprietaire', 'site', 'rendez-vous', 9, 3, null, null],
        ['Aminata Touré', 'Atelier Touré Couture', 'salon-couture', 'manuel', 'devis-envoye', 14, 4, ['professionnel', 'site-neuf', 450000, 'sent'], null],
        ['Serigne Mbaye', 'Mbaye Électronique', 'boutique', 'site', 'negociation', 20, 1, ['essentiel', 'site-existant', 250000, 'sent'], null],
        ['Ndèye Coumba Lo', 'Clinique dentaire Lo', 'autre', 'manuel', 'projet-en-cours', 40, null, ['professionnel', 'site-neuf', 520000, 'accepted'], ['in_progress', -25, null]],
        ['Ibou Faye', 'Faye Auto Pièces', 'boutique', 'site', 'livre', 90, null, ['essentiel', 'site-neuf', 220000, 'accepted'], ['delivered', -80, -35]],
        ['Marie Gomis', 'Hôtel Saly Plage', 'autre', 'manuel', 'perdu', 50, null, ['sur-mesure', 'site-neuf', 1200000, 'refused'], null],
    ];
    foreach ($list as [$name, $business, $activity, $source, $stage, $ago, $next, $quoteData, $projectData]) {
        $createdAt = demoAt('-' . $ago . ' days', '10:00:00');
        $email = strtolower(str_replace([' ', 'è', 'é', 'ô'], ['.', 'e', 'e', 'o'], $name)) . '@exemple.sn';
        $prospect->execute([$name, $business, $email, '7700400' . str_pad((string) ($ago % 100), 2, '0', STR_PAD_LEFT), $activity, $source, $stage,
            $next === null ? null : demoDate('+' . $next . ' days'), $actor, $createdAt, $createdAt]);
        $prospectId = (int) $pdo->lastInsertId();
        $event->execute([$prospectId, 'created', $source === 'site' ? 'Demande reçue depuis le site.' : 'Prospect ajouté à la main.', $actor, $createdAt]);
        if ($stage !== 'nouveau') {
            $event->execute([$prospectId, 'note', 'Premier appel : besoin d\'un site vitrine avec prise de contact WhatsApp.', $actor, demoAt('-' . max(1, $ago - 1) . ' days', '15:00:00')]);
        }
        if ($quoteData === null) {
            continue;
        }
        [$offer, $path, $amount, $status] = $quoteData;
        $sentAt = demoAt('-' . max(1, $ago - 3) . ' days', '11:00:00');
        $decidedAt = in_array($status, ['accepted', 'refused'], true) ? demoAt('-' . max(1, $ago - 6) . ' days', '16:00:00') : null;
        $quote->execute([$prospectId, sprintf('DV-%s-%04d', $year, ++$quoteNo), $offer, $path, $amount,
            'Site ' . $business . ' : pages Accueil, Services, Galerie, Contact, bouton WhatsApp, hébergement 1 an.', $status, $sentAt, $decidedAt, $sentAt, $decidedAt ?? $sentAt]);
        $quoteId = (int) $pdo->lastInsertId();
        $event->execute([$prospectId, 'quote', 'Devis envoyé : ' . number_format($amount, 0, ',', ' ') . ' FCFA.', $actor, $sentAt]);
        if ($projectData === null) {
            continue;
        }
        [$projectStatus, $startedAgo, $deliveredAgo] = $projectData;
        $startedOn = demoDate($startedAgo . ' days');
        $deliveredOn = $deliveredAgo === null ? null : demoDate($deliveredAgo . ' days');
        $stamp = $startedOn . ' 09:00:00';
        $project->execute([$prospectId, $quoteId, 'Site ' . $business, $offer, $amount, $projectStatus, substr($startedOn, 0, 7), $startedOn, $deliveredOn,
            $projectStatus === 'delivered' ? 'https://faye-auto.exemple.sn' : '', $stamp, $deliveredOn !== null ? $deliveredOn . ' 17:00:00' : $stamp]);
        $projectId = (int) $pdo->lastInsertId();
        $half = intdiv($amount, 2);
        $invoice->execute([$projectId, sprintf('FA-%s-%04d', $year, ++$invoiceNo), 'Acompte 50 % — démarrage', $half, 'paid', $startedOn, $startedOn, 'wave', $stamp, $stamp]);
        if ($deliveredOn !== null) {
            $invoice->execute([$projectId, sprintf('FA-%s-%04d', $year, ++$invoiceNo), 'Solde 50 % — livraison', $amount - $half, 'paid', $deliveredOn, $deliveredOn, 'orange-money', $deliveredOn . ' 17:00:00', $deliveredOn . ' 17:00:00']);
        } else {
            $invoice->execute([$projectId, sprintf('FA-%s-%04d', $year, ++$invoiceNo), 'Solde 50 % — livraison', $amount - $half, 'issued', demoDate('+10 days'), null, null, $stamp, $stamp]);
        }
    }
    echo "Agence : 8 prospects à toutes les étapes, 5 devis, 2 projets, 4 factures\n";
}

// ------------------------------------------------------------------ Notifications

function demoNotifications(PDO $pdo, int $tenantId): void
{
    if (demoAlready($pdo, "SELECT COUNT(*) FROM notifications WHERE tenant_id = ? AND title LIKE 'Démo · %'", [$tenantId])) {
        return;
    }
    $owner = demoOwner($pdo, $tenantId);
    $insert = $pdo->prepare('INSERT INTO notifications (tenant_id, user_id, title, body, read_at, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ([
        ['Démo · Paiement reçu', 'Un paiement Wave simulé a été confirmé dans le bac à sable NabooPay.', null, '-2 hours'],
        ['Démo · Nouvelle demande', 'Une cliente a fait une demande depuis votre page publique.', null, '-5 hours'],
        ['Démo · Bienvenue', 'Votre espace est prêt : les données affichées sont fictives.', '-3 days', '-4 days'],
    ] as [$title, $body, $readAgo, $ago]) {
        $insert->execute([$tenantId, $owner, $title, $body, $readAgo === null ? null : demoAt($readAgo), demoAt($ago)]);
    }
}

// ------------------------------------------------------------------ Immo (réglages, fiches détaillées, partiel, interventions)

function demoImmo(PDO $pdo, int $tenantId): void
{
    // Échéances créées avant le module Immo : part loyer, montant payé et ligne d'encaissement.
    $pdo->prepare('UPDATE loyer_rent_dues SET rent_part_minor = amount_minor WHERE tenant_id = ? AND rent_part_minor = 0')->execute([$tenantId]);
    $pdo->prepare("UPDATE loyer_rent_dues SET paid_minor = amount_minor WHERE tenant_id = ? AND status = 'paid' AND paid_minor = 0")->execute([$tenantId]);
    $pdo->prepare(
        "INSERT INTO immo_payments (tenant_id, due_id, lease_id, amount_minor, method, reference, paid_on, note, payment_id, receipt_number, actor_user_id, created_at)
         SELECT d.tenant_id, d.id, d.lease_id, d.amount_minor, IF(d.payment_id IS NULL, 'especes', 'en-ligne'), NULL, DATE(COALESCE(d.paid_at, d.updated_at)), NULL,
                IF(p.status = 'success', d.payment_id, NULL), CONCAT('Q-', LPAD(d.id, 6, '0')), NULL, COALESCE(d.paid_at, d.updated_at)
         FROM loyer_rent_dues d LEFT JOIN payments p ON p.id = d.payment_id
         WHERE d.tenant_id = ? AND d.status = 'paid' AND NOT EXISTS (SELECT 1 FROM immo_payments ip WHERE ip.due_id = d.id)"
    )->execute([$tenantId]);

    if (demoAlready($pdo, 'SELECT COUNT(*) FROM immo_settings WHERE tenant_id = ?', [$tenantId])) {
        echo "Immo : réglages déjà présents\n";

        return;
    }
    $pdo->prepare(
        "INSERT INTO immo_settings (tenant_id, display_name, whatsapp_phone, email, address, receipt_footer, signature_name, default_due_day, grace_days,
            remind_before_days, remind_on_due, remind_after_days, auto_reminders, allow_partial, min_partial_minor, lease_end_alert_days, created_at, updated_at)
         VALUES (?, 'Immeuble Démo — Gestion locative', '221770000003', 'gestion@immeuble-demo.exemple.sn', 'Liberté 6 extension, Dakar',
            'NINEA 00000000 · RC SN-DKR-2026-B-0000 (données fictives)', 'Fatou Démo', 5, 3, 3, 1, '3,7,15', 1, 1, 10000, 60, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
    )->execute([$tenantId]);

    $pdo->prepare("UPDATE loyer_properties SET kind = 'immeuble', city = 'Dakar', owner_name = 'M. Abdoulaye Sarr', owner_phone = '770040001', notes = 'Gardien : Modou (77 004 00 02). Compteurs au rez-de-chaussée.' WHERE tenant_id = ? AND name = 'Immeuble Liberté 6'")->execute([$tenantId]);
    $pdo->prepare("UPDATE loyer_properties SET kind = 'villa', city = 'Dakar' WHERE tenant_id = ? AND name = 'Villa Almadies'")->execute([$tenantId]);
    foreach ([
        ['Appartement A1 — F3', 'appartement', 3, 78, '1er étage', 15000, 500000],
        ['Appartement A2 — F3', 'appartement', 3, 75, '1er étage', 15000, 500000],
        ['Studio B1', 'studio', 1, 32, '2e étage', 5000, 240000],
        ['Local commercial RDC', 'magasin', 2, 60, 'RDC', 0, 900000],
        ['Villa F5 meublée', 'villa', 5, 240, 'Plain-pied', 50000, 1300000],
    ] as [$label, $kind, $rooms, $surface, $floor, $charges, $deposit]) {
        $pdo->prepare('UPDATE loyer_units SET kind = ?, rooms = ?, surface_m2 = ?, floor_label = ?, default_charges_minor = ?, default_deposit_minor = ? WHERE tenant_id = ? AND label = ?')
            ->execute([$kind, $rooms, $surface, $floor, $charges, $deposit, $tenantId, $label]);
    }
    // Conditions des baux : caution, préavis, fin de bail proche pour la villa, caution pas encore versée pour le studio.
    $leases = $pdo->prepare('SELECT l.id, u.label, l.starts_on, l.rent_minor FROM loyer_leases l JOIN loyer_units u ON u.id = l.unit_id WHERE l.tenant_id = ?');
    $leases->execute([$tenantId]);
    foreach ($leases->fetchAll(PDO::FETCH_ASSOC) as $lease) {
        $deposit = (int) $lease['rent_minor'] * 2;
        $paidOn = $lease['label'] === 'Studio B1' ? null : $lease['starts_on'];
        $endsOn = $lease['label'] === 'Villa F5 meublée' ? demoDate('+40 days') : null;
        $pdo->prepare('UPDATE loyer_leases SET deposit_minor = ?, deposit_paid_on = ?, notice_months = ?, ends_on = COALESCE(?, ends_on) WHERE id = ?')
            ->execute([$deposit, $paidOn, $lease['label'] === 'Local commercial RDC' ? 3 : 1, $endsOn, (int) $lease['id']]);
    }

    $profile = $pdo->prepare(
        'INSERT IGNORE INTO immo_tenant_profiles (client_id, tenant_id, id_type, id_number, profession, employer, emergency_name, emergency_phone, guarantor_name, guarantor_phone, notes, updated_at)
         SELECT c.id, c.tenant_id, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP() FROM clients c WHERE c.tenant_id = ? AND c.phone = ?'
    );
    foreach ([
        ['770030001', 'cni', '1 755 1990 01234', 'Comptable', 'Banque de l\'Habitat', 'Aïda Diagne', '770030101', 'Mamadou Diagne', '770030102', 'Locataire exemplaire depuis plus d\'un an.'],
        ['770030002', 'cni', '2 811 1994 04567', 'Infirmière', 'Hôpital Principal', 'Moussa Thiaw', '770030103', null, null, null],
        ['770030003', 'passeport', 'A01234567', 'Commerçant', null, 'Ndèye Fall', '770030104', 'Ibrahima Fall', '770030105', 'Paie souvent en deux fois : accepter les paiements partiels.'],
        ['770030004', 'ninea', '005678912', 'Imprimerie', 'Teranga Print SARL', 'Oumar Ba (gérant)', '770030106', null, null, 'Bail commercial de 3 ans renouvelable.'],
        ['770030005', 'cni', '1 702 1985 07890', 'Expatrié — consultant', 'Cabinet international', 'Awa Lô', '770030107', 'Société employeur', '770030108', null],
    ] as [$phone, $idType, $idNumber, $profession, $employer, $emergency, $emergencyPhone, $guarantor, $guarantorPhone, $notes]) {
        $profile->execute([$idType, $idNumber, $profession, $employer, $emergency, $emergencyPhone, $guarantor, $guarantorPhone, $notes, $tenantId, $phone]);
    }

    // Paiement partiel : Cheikh Fall a réglé une partie du plus ancien loyer en retard.
    $late = $pdo->prepare(
        "SELECT d.id FROM loyer_rent_dues d JOIN loyer_leases l ON l.id = d.lease_id JOIN clients c ON c.id = l.client_id
         WHERE d.tenant_id = ? AND c.phone = '770030003' AND d.status = 'pending' AND d.due_date < ? ORDER BY d.due_date LIMIT 1"
    );
    $late->execute([$tenantId, gmdate('Y-m-d')]);
    $dueId = $late->fetchColumn();
    if ($dueId !== false) {
        (new Sesam\Core\Immo\ImmoLedger($pdo))->record($tenantId, (int) $dueId, ['amount' => 50000, 'method' => 'wave', 'paid_on' => demoDate('-6 days'), 'reference' => 'WAVE-DEMO-50K', 'note' => 'Acompte, le reste promis avant le 20']);
        $reminder = $pdo->prepare(
            "INSERT IGNORE INTO immo_reminders (tenant_id, due_id, client_id, kind, channel, recipient, message, status, actor_user_id, sent_on, created_at)
             SELECT ?, d.id, l.client_id, ?, 'whatsapp', '221770030003', ?, 'sent', NULL, ?, ? FROM loyer_rent_dues d JOIN loyer_leases l ON l.id = d.lease_id WHERE d.id = ?"
        );
        foreach ([['due', '-40 days'], ['late', '-37 days'], ['late', '-33 days'], ['manual', '-8 days']] as [$kind, $ago]) {
            $reminder->execute([$tenantId, $kind, 'Bonjour Cheikh Fall, ceci est un rappel pour votre loyer du Studio B1. Merci. Immeuble Démo', demoDate($ago), demoAt($ago, '09:00:00'), (int) $dueId]);
        }
        $pdo->prepare('UPDATE loyer_rent_dues SET reminder_count = 4, last_reminder_at = ? WHERE id = ?')->execute([demoAt('-8 days', '09:00:00'), (int) $dueId]);
    }

    $property = $pdo->prepare('SELECT id FROM loyer_properties WHERE tenant_id = ? AND name = ?');
    $unit = $pdo->prepare('SELECT id FROM loyer_units WHERE tenant_id = ? AND label = ?');
    $intervention = $pdo->prepare(
        'INSERT INTO immo_interventions (tenant_id, property_id, unit_id, title, description, priority, status, provider, cost_minor, reported_on, done_on, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );
    foreach ([
        ['Immeuble Liberté 6', 'Appartement A2 — F3', 'Fuite sous l\'évier de la cuisine', 'La locataire signale une fuite depuis deux jours.', 'urgente', 'ouverte', null, 0, '-1 day', null],
        ['Immeuble Liberté 6', null, 'Peinture de la cage d\'escalier', 'Devis demandé au peintre du quartier.', 'basse', 'en-cours', 'Atelier Diop Peinture', 180000, '-12 days', null],
        ['Villa Almadies', 'Villa F5 meublée', 'Entretien de la climatisation', 'Nettoyage des filtres avant la saison chaude.', 'normale', 'terminee', 'Froid Service Dakar', 45000, '-25 days', '-20 days'],
    ] as [$propertyName, $unitLabel, $title, $description, $priority, $status, $provider, $cost, $reported, $done]) {
        $property->execute([$tenantId, $propertyName]);
        $propertyId = $property->fetchColumn();
        if ($propertyId === false) {
            continue;
        }
        $unitId = null;
        if ($unitLabel !== null) {
            $unit->execute([$tenantId, $unitLabel]);
            $found = $unit->fetchColumn();
            $unitId = $found === false ? null : (int) $found;
        }
        $intervention->execute([$tenantId, (int) $propertyId, $unitId, $title, $description, $priority, $status, $provider, $cost, demoDate($reported), $done === null ? null : demoDate($done)]);
    }
    echo "Immo : réglages, fiches des locataires, cautions, paiement partiel avec relances, 3 interventions\n";
}

// ------------------------------------------------------------------ Commerce inscrit seul depuis le site (essai en cours)

function demoTrialSignup(PDO $pdo, UserRepository $users, RoleRepository $roles, string $hash): void
{
    $email = 'demo.essai@sesam.local';
    if ($users->findByEmail($email) !== null) {
        echo "Essai : compte déjà présent\n";

        return;
    }
    $pdo->prepare("INSERT INTO tenants (slug, name, sector, status, phone, signup_source, created_at, updated_at) VALUES ('demo-essai', 'Salon Essai Démo', ?, 'active', '221770000009', 'site', ?, ?)")
        ->execute([Sector::RESERVATION, demoAt('-25 days'), demoAt('-25 days')]);
    $tenantId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tenant_settings (tenant_id, display_name, created_at, updated_at) VALUES (?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute([$tenantId, 'Salon Essai Démo']);
    $pdo->prepare('INSERT IGNORE INTO reservation_settings (tenant_id, whatsapp_phone, created_at, updated_at) VALUES (?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute([$tenantId, '221770000009']);
    ensureUser($users, $roles, $email, 'Mariama Essai', $tenantId, [Role::TENANT_OWNER], $hash);
    $pdo->prepare("INSERT INTO tenant_subscriptions (tenant_id, status, monthly_amount_minor, period_start, period_end, created_at, updated_at) VALUES (?, 'trial', 15000, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())")
        ->execute([$tenantId, demoDate('-25 days'), demoDate('+5 days')]);
    $pdo->prepare("INSERT INTO tenant_subscription_events (tenant_id, action, from_status, to_status, period_end, note, actor_user_id, created_at) VALUES (?, 'trial', NULL, 'trial', ?, 'Essai gratuit de 30 jours — Inscription depuis le site', NULL, ?)")
        ->execute([$tenantId, demoDate('+5 days'), demoAt('-25 days')]);
    echo "Essai : Salon Essai Démo inscrit depuis le site, essai qui se termine dans 5 jours ({$email})\n";
}

// ------------------------------------------------------------------ Demandes de devis site web (formulaire détaillé)

function demoQuoteRequests(PDO $pdo): void
{
    if (demoAlready($pdo, "SELECT COUNT(*) FROM contact_requests WHERE email LIKE '%@exemple.sn'", [])) {
        return;
    }
    $insert = $pdo->prepare(
        'INSERT INTO contact_requests (full_name, business_name, email, phone, activity, site_type, site_state, pages, features, has_domain, budget, deadline, inspiration, message, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([
        'Khadija Ndoye', 'Maison Khadija Traiteur', 'khadija.ndoye@exemple.sn', '770050001', 'autre', 'vitrine', 'nouveau',
        'Accueil, À propos, Services / prestations, Galerie photos, Tarifs, Contact', 'Bouton WhatsApp, Formulaire de contact, Carte Google Maps', 'non', '200-450k', '1-mois',
        'Sites de traiteurs avec beaucoup de photos de plats',
        "Traiteur pour mariages et baptêmes à Dakar. Je veux présenter mes menus, des photos et être contactée sur WhatsApp.\n\nSite : Site vitrine (présenter mon activité) — je n'ai pas encore de site",
        demoAt('-1 day', '20:15:00'),
    ]);
    $insert->execute([
        'Babacar Gueye', 'Gueye Auto Location', 'babacar.gueye@exemple.sn', '770050002', 'autre', 'reservation', 'refonte',
        'Accueil, Services / prestations, Tarifs, FAQ, Contact', 'Réservation en ligne, Paiement Wave / Orange Money, Référencement Google', 'oui', '450k-1m', '2-3-mois',
        'www.exemple-location.sn',
        "Location de voitures avec chauffeur. Le site actuel est vieux et ne marche pas sur téléphone. Il faut pouvoir réserver et payer un acompte.\n\nSite : Site avec réservation — j'ai un site à refaire",
        demoAt('-3 days', '11:40:00'),
    ]);
    echo "Agence : 2 demandes de devis site web détaillées\n";
}
