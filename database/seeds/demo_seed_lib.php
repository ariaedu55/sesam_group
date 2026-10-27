<?php

declare(strict_types=1);

use Sesam\Core\Boutique\StockMovementType;
use Sesam\Core\Tenant\Sector;
use Sesam\Repositories\RoleRepository;
use Sesam\Repositories\UserRepository;

/**
 * @return list<array{0: string, 1: string, 2: int, 3: ?int, 4: int, 5: string}>
 */
function demoCatalogProducts(): array
{
    return [
        ['Robe wax « Teranga »', 'Robes', 25000, 30000, 12, 'Coupe évasée, tissu wax 100 % coton. Tailles S à XL.'],
        ['Ensemble boubou brodé', 'Robes', 38000, null, 5, 'Broderie main, bazin riche. Plusieurs couleurs disponibles.'],
        ['Sac en raphia', 'Sacs', 12000, null, 8, 'Tressé main, doublure coton, fermeture aimantée.'],
        ['Pochette perlée', 'Sacs', 8500, null, 2, 'Perles de verre, idéale pour les cérémonies.'],
        ['Parure de bijoux dorés', 'Bijoux', 7500, 9000, 20, 'Collier et boucles plaqués or.'],
        ['Foulard en soie', 'Accessoires', 9000, null, 0, 'Imprimé exclusif, 90 × 90 cm.'],
    ];
}

function seedSubscription(PDO $pdo, int $tenantId, string $sector): void
{
    $check = $pdo->prepare('SELECT COUNT(*) FROM tenant_subscription_payments WHERE tenant_id = :tenant_id');
    $check->execute(['tenant_id' => $tenantId]);
    if ((int) $check->fetchColumn() > 0) {
        return;
    }
    $price = $pdo->prepare('SELECT monthly_amount FROM saas_prices WHERE sector = :sector');
    $price->execute(['sector' => $sector]);
    $monthly = (int) ($price->fetchColumn() ?: 10000);
    $utc = new DateTimeZone('UTC');
    $months = $sector === Sector::LOYER ? 5 : 4;
    $start = (new DateTimeImmutable('today', $utc))->modify('-' . ($sector === Sector::LOYER ? $months : $months - 1) . ' months')->modify('-6 days');
    $methods = ['wave', 'orange-money', 'wave', 'especes', 'wave'];
    $insert = $pdo->prepare(
        'INSERT INTO tenant_subscription_payments (tenant_id, amount, months, paid_on, method, reference, period_end, note, actor_user_id, created_at)
         VALUES (:tenant_id, :amount, 1, :paid_on, :method, :reference, :period_end, :note, NULL, :created_at)'
    );
    $end = $start;
    for ($i = 0; $i < $months; $i++) {
        $paidOn = $start->modify('+' . $i . ' months');
        $end = $paidOn->modify('+1 month')->modify('-1 day');
        $insert->execute([
            'tenant_id' => $tenantId,
            'amount' => $monthly,
            'paid_on' => $paidOn->format('Y-m-d'),
            'method' => $methods[$i % count($methods)],
            'reference' => 'DEMO-' . $tenantId . '-' . ($i + 1),
            'period_end' => $end->format('Y-m-d'),
            'note' => '',
            'created_at' => $paidOn->format('Y-m-d') . ' 10:00:00',
        ]);
    }
    $pdo->prepare(
        "INSERT INTO tenant_subscriptions (tenant_id, status, monthly_amount_minor, period_start, period_end, created_at, updated_at)
         VALUES (:tenant_id, 'active', :amount, :period_start, :period_end, NOW(), NOW())
         ON DUPLICATE KEY UPDATE status = VALUES(status), monthly_amount_minor = VALUES(monthly_amount_minor),
            period_start = VALUES(period_start), period_end = VALUES(period_end), updated_at = NOW()"
    )->execute(['tenant_id' => $tenantId, 'amount' => $monthly, 'period_start' => $start->format('Y-m-d'), 'period_end' => $end->format('Y-m-d')]);
    echo "Abonnement de démonstration : {$months} mois encaissés\n";
}

function seedSalon(PDO $pdo, int $tenantId): void
{
    $now = gmdate('Y-m-d H:i:s');
    $count = $pdo->prepare('SELECT COUNT(*) FROM reservation_settings WHERE tenant_id = ?');
    $count->execute([$tenantId]);
    if ((int) $count->fetchColumn() > 0) {
        echo "Salon : déjà configuré\n";

        return;
    }
    $pdo->prepare(
        'INSERT INTO reservation_settings (tenant_id, whatsapp_phone, tagline, description, address, theme, is_open, auto_confirm,
            slot_step_minutes, min_notice_hours, max_advance_days, buffer_minutes, cancellation_policy, created_at, updated_at)
         VALUES (:tenant_id, :phone, :tagline, :description, :address, :theme, 1, 0, 30, 2, 60, 10, :policy, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE updated_at = updated_at'
    )->execute([
        'tenant_id' => $tenantId,
        'phone' => '221770000001',
        'tagline' => 'Coiffure afro, ongles et soins, sur rendez-vous à Mermoz',
        'description' => 'Salon de démonstration Sesam Réservation. Les réservations sont fictives.',
        'address' => 'Mermoz, Dakar',
        'theme' => 'ocean',
        'policy' => 'Merci de prévenir 24 h à l\'avance en cas d\'empêchement.',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $pdo->prepare('DELETE FROM reservation_hours WHERE tenant_id = ?')->execute([$tenantId]);
    $hours = $pdo->prepare('INSERT INTO reservation_hours (tenant_id, weekday, opens_at, closes_at) VALUES (?, ?, ?, ?)');
    for ($day = 1; $day <= 6; $day++) {
        $hours->execute([$tenantId, $day, '09:00:00', $day === 6 ? '17:00:00' : '19:00:00']);
    }
    $service = $pdo->prepare(
        'INSERT INTO reservation_services (tenant_id, name, description, category, duration_minutes, price_minor, deposit_minor, is_active, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)'
    );
    $services = [];
    foreach ([
        ['Tresses africaines', 'Mèches non comprises.', 'Coiffure', 180, 25000, 5000],
        ['Brushing', 'Shampooing inclus.', 'Coiffure', 45, 8000, 0],
        ['Coupe et coiffage', '', 'Coiffure', 60, 10000, 0],
        ['Pose de vernis semi-permanent', '', 'Ongles', 45, 7000, 0],
        ['Soin du visage éclat', 'Nettoyage, gommage, masque.', 'Soins', 60, 15000, 3000],
    ] as [$name, $description, $category, $duration, $price, $deposit]) {
        $service->execute([$tenantId, $name, $description !== '' ? $description : null, $category, $duration, $price, $deposit, $now, $now]);
        $services[$name] = [(int) $pdo->lastInsertId(), $duration, $price];
    }
    $staff = $pdo->prepare('INSERT INTO reservation_staff (tenant_id, name, role_label, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?)');
    $staff->execute([$tenantId, 'Aminata', 'Coiffeuse', $now, $now]);
    $aminata = (int) $pdo->lastInsertId();
    $staff->execute([$tenantId, 'Khady', 'Esthéticienne', $now, $now]);
    $khady = (int) $pdo->lastInsertId();
    $link = $pdo->prepare('INSERT INTO reservation_staff_services (tenant_id, staff_id, service_id) VALUES (?, ?, ?)');
    foreach (['Tresses africaines', 'Brushing', 'Coupe et coiffage'] as $name) {
        $link->execute([$tenantId, $aminata, $services[$name][0]]);
    }
    foreach (['Pose de vernis semi-permanent', 'Soin du visage éclat'] as $name) {
        $link->execute([$tenantId, $khady, $services[$name][0]]);
    }
    $client = $pdo->prepare('INSERT INTO clients (tenant_id, full_name, phone, status, created_at, updated_at) VALUES (?, ?, ?, \'active\', ?, ?)');
    $appointment = $pdo->prepare(
        'INSERT INTO reservation_appointments (tenant_id, client_id, service_id, staff_id, starts_at, duration_minutes, price_minor, status, source,
            deposit_amount_minor, notes, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, ?, ?)'
    );
    $today = gmdate('Y-m-d');
    $tomorrow = gmdate('Y-m-d', strtotime('+1 day'));
    foreach ([
        ['Fatou Ndiaye', '771112233', 'Tresses africaines', $aminata, $today . ' 10:00:00', 'confirmed', 'online'],
        ['Awa Sarr', '772223344', 'Soin du visage éclat', $khady, $today . ' 11:00:00', 'confirmed', 'manual'],
        ['Mariama Ba', '773334455', 'Brushing', $aminata, $today . ' 14:30:00', 'pending', 'online'],
        ['Seynabou Diop', '774445566', 'Pose de vernis semi-permanent', $khady, $tomorrow . ' 15:00:00', 'pending', 'online'],
    ] as [$name, $phone, $serviceName, $staffId, $startsAt, $status, $source]) {
        $client->execute([$tenantId, $name, $phone, $now, $now]);
        [$serviceId, $duration, $price] = $services[$serviceName];
        $appointment->execute([$tenantId, (int) $pdo->lastInsertId(), $serviceId, $staffId, $startsAt, $duration, $price, $status, $source, $now, $now]);
    }
    echo "Salon : 5 prestations, 2 personnes, 4 rendez-vous ajoutés\n";
}

/**
 * @param list<array{0: string, 1: string, 2: int, 3: ?int, 4: int, 5: string}> $products
 */
function seedBoutique(PDO $pdo, int $tenantId, array $products): void
{
    $now = gmdate('Y-m-d H:i:s');
    $pdo->prepare(
        'INSERT INTO boutique_settings (tenant_id, whatsapp_phone, tagline, description, address, opening_hours, delivery_info, theme, is_open, show_stock, created_at, updated_at)
         VALUES (:tenant_id, :phone, :tagline, :description, :address, :hours, :delivery, :theme, 1, 0, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE updated_at = updated_at'
    )->execute([
        'tenant_id' => $tenantId,
        'phone' => '221770000000',
        'tagline' => 'Mode wax et accessoires faits main, livrés partout à Dakar',
        'description' => 'Boutique de démonstration Sesam Boutique. Les commandes ouvrent WhatsApp sur un numéro fictif.',
        'address' => 'Sacré-Cœur 3, Dakar',
        'hours' => 'Lun–Sam, 9 h – 20 h',
        'delivery' => 'Livraison à Dakar en 24 h — 1 500 FCFA',
        'theme' => 'emeraude',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $count = $pdo->prepare('SELECT COUNT(*) FROM boutique_products WHERE tenant_id = ?');
    $count->execute([$tenantId]);
    if ((int) $count->fetchColumn() > 0) {
        echo "Boutique : produits déjà présents\n";

        return;
    }
    $insert = $pdo->prepare(
        'INSERT INTO boutique_products (tenant_id, name, description, category, price_minor, compare_price_minor, track_stock, stock_quantity, low_stock_threshold, is_published, created_at, updated_at)
         VALUES (:tenant_id, :name, :description, :category, :price, :compare, 1, :stock, 3, 1, :created_at, :updated_at)'
    );
    $movement = $pdo->prepare(
        'INSERT INTO boutique_stock_movements (tenant_id, product_id, type, quantity, stock_after, note, created_at)
         VALUES (:tenant_id, :product_id, :type, :quantity, :stock_after, :note, :created_at)'
    );
    foreach ($products as [$name, $category, $price, $compare, $stock, $description]) {
        $insert->execute([
            'tenant_id' => $tenantId,
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'price' => $price,
            'compare' => $compare,
            'stock' => $stock,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if ($stock > 0) {
            $movement->execute([
                'tenant_id' => $tenantId,
                'product_id' => (int) $pdo->lastInsertId(),
                'type' => StockMovementType::IN,
                'quantity' => $stock,
                'stock_after' => $stock,
                'note' => 'Stock de départ',
                'created_at' => $now,
            ]);
        }
    }
    echo 'Boutique : ' . count($products) . " produits ajoutés\n";
}

/**
 * @param list<string> $roleSlugs
 */
function ensureUser(
    UserRepository $users,
    RoleRepository $roles,
    string $email,
    string $name,
    ?int $tenantId,
    array $roleSlugs,
    string $hash,
): void {
    $existing = $users->findByEmail($email);
    if ($existing === null) {
        $id = $users->insert([
            'tenant_id' => $tenantId,
            'full_name' => $name,
            'email' => $email,
            'password_hash' => $hash,
            'is_active' => 1,
        ]);
    } else {
        $id = $existing->id();
        if ($existing->tenantId() !== $tenantId) {
            fwrite(STDERR, "{$email} existe déjà sur un autre commerce. Rien n'a été modifié pour ce compte.\n");
            exit(1);
        }
        $users->update($id, [
            'full_name' => $name,
            'email' => $email,
            'is_active' => 1,
            'password_hash' => $hash,
        ]);
    }
    $roles->replaceUserRoles($id, $roles->idsForSlugs($roleSlugs));
}
