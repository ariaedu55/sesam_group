<?php

declare(strict_types=1);

namespace Sesam\Services\Commande;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Commande\OrderStatus;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\CommandeOrder;
use Sesam\Models\Tenant;
use Sesam\Models\User;
use Sesam\Repositories\ClientRepository;
use Sesam\Repositories\CommandeDeliveryRepository;
use Sesam\Repositories\CommandeOrderItemRepository;
use Sesam\Repositories\CommandeOrderMeasurementRepository;
use Sesam\Repositories\CommandeOrderRepository;
use Sesam\Repositories\CommandeProductRepository;
use Sesam\Services\PaymentService;
use Sesam\Services\Service;

final class OrderWorkflowService extends Service
{
    public const PER_PAGE = 20;

    private readonly CommandeProductRepository $products;
    private readonly CommandeOrderRepository $orders;
    private readonly CommandeOrderItemRepository $items;
    private readonly CommandeOrderMeasurementRepository $measurements;
    private readonly CommandeDeliveryRepository $deliveries;
    private readonly ClientRepository $clients;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        array $database,
        private readonly TenantContext $context,
        private readonly ?PaymentService $payments = null,
    ) {
        $pdo = Connection::make($database);
        $this->products = new CommandeProductRepository($pdo);
        $this->orders = new CommandeOrderRepository($pdo);
        $this->items = new CommandeOrderItemRepository($pdo);
        $this->measurements = new CommandeOrderMeasurementRepository($pdo);
        $this->deliveries = new CommandeDeliveryRepository($pdo);
        $this->clients = new ClientRepository($pdo);
    }

    /**
     * @return list<\Sesam\Models\CommandeProduct>
     */
    public function listProducts(User $actor): array
    {
        $this->assertCommande($actor, Permission::COMMANDE_VIEW);

        return $this->products->listAll($this->tenantId($actor));
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createProduct(User $actor, array $input): void
    {
        $tenantId = $this->tenantId($actor);
        $this->assertCommande($actor, Permission::COMMANDE_PRODUCTS);
        $name = trim((string) ($input['name'] ?? ''));
        $price = (int) ($input['price_minor'] ?? 0);
        $deposit = (int) ($input['deposit_minor'] ?? 0);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new ValidationException(['name' => 'Nom de produit invalide.']);
        }
        if ($price < 0 || $deposit < 0 || $deposit > $price) {
            throw new ValidationException(['price_minor' => 'Tarif ou acompte invalide.']);
        }
        $this->products->insert($tenantId, [
            'name' => $name,
            'price_minor' => $price,
            'deposit_minor' => $deposit,
            'is_active' => 1,
        ]);
    }

    /**
     * @return array{orders: list<CommandeOrder>, total: int, page: int, pages: int}
     */
    public function paginateOrders(User $actor, int $page): array
    {
        $tenantId = $this->tenantId($actor);
        $this->assertCommande($actor, Permission::COMMANDE_VIEW);
        $page = max(1, $page);
        $total = $this->orders->countForTenant($tenantId);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        return [
            'orders' => $this->orders->listForTenant($tenantId, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /**
     * @return array{
     *     order: CommandeOrder,
     *     items: list<\Sesam\Models\CommandeOrderItem>,
     *     measurements: list<\Sesam\Models\CommandeOrderMeasurement>,
     *     delivery: ?array{due_at: string, notes: ?string, delivered_at: ?string}
     * }
     */
    public function orderDetail(User $actor, int $id): array
    {
        $tenantId = $this->tenantId($actor);
        $order = $this->findOrder($actor, $id);

        return [
            'order' => $order,
            'items' => $this->items->listForOrder($id, $tenantId),
            'measurements' => $this->measurements->listForOrder($id, $tenantId),
            'delivery' => $this->deliveries->findByOrder($id, $tenantId),
        ];
    }

    public function findOrder(User $actor, int $id): CommandeOrder
    {
        $tenantId = $this->tenantId($actor);
        $this->assertCommande($actor, Permission::COMMANDE_VIEW);
        $order = $this->orders->findById($id, $tenantId);
        if ($order === null) {
            throw new HttpException(404, 'Commande introuvable.');
        }

        return $order;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createOrder(User $actor, array $input): int
    {
        $tenantId = $this->tenantId($actor);
        $this->assertCommande($actor, Permission::COMMANDE_ORDERS);
        $clientId = (int) ($input['client_id'] ?? 0);
        if ($this->clients->findById($clientId, $tenantId) === null) {
            throw new ValidationException(['client_id' => 'Client introuvable.']);
        }
        $parsed = $this->parseLines($tenantId, $input);
        if ($parsed === []) {
            throw new ValidationException(['lines' => 'Ajoutez au moins une ligne de commande.']);
        }
        $total = 0;
        $deposit = 0;
        $lines = [];
        foreach ($parsed as $line) {
            $total += $line['line_total_minor'];
            $deposit += $line['deposit_line'];
            $lines[] = [
                'product_id' => $line['product_id'],
                'product_name' => $line['product_name'],
                'quantity' => $line['quantity'],
                'unit_price_minor' => $line['unit_price_minor'],
                'line_total_minor' => $line['line_total_minor'],
            ];
        }
        $orderId = $this->orders->insert($tenantId, [
            'client_id' => $clientId,
            'order_number' => $this->generateOrderNumber(),
            'status' => OrderStatus::NEW,
            'total_minor' => $total,
            'deposit_amount_minor' => $deposit,
        ]);
        $this->items->insertLines($tenantId, $orderId, $lines);

        return $orderId;
    }

    public function advanceStatus(User $actor, int $id): void
    {
        $tenantId = $this->tenantId($actor);
        $this->assertCommande($actor, Permission::COMMANDE_ORDERS);
        $order = $this->findOrder($actor, $id);
        if ($order->status() === OrderStatus::CANCELLED) {
            throw new ValidationException(['status' => 'Commande annulée.']);
        }
        $next = OrderStatus::next($order->status());
        if ($next === null) {
            throw new ValidationException(['status' => 'Aucune étape suivante.']);
        }
        $this->orders->updateStatus($id, $tenantId, $next);
        if ($next === OrderStatus::DELIVERED) {
            $this->orders->markDelivered($id, $tenantId);
            $this->deliveries->markDelivered($id, $tenantId);
        }
    }

    public function cancel(User $actor, int $id): void
    {
        $tenantId = $this->tenantId($actor);
        $this->assertCommande($actor, Permission::COMMANDE_ORDERS);
        $order = $this->findOrder($actor, $id);
        if ($order->status() === OrderStatus::DELIVERED) {
            throw new ValidationException(['status' => 'Impossible d\'annuler une commande livrée.']);
        }
        $this->orders->updateStatus($id, $tenantId, OrderStatus::CANCELLED);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function addMeasurement(User $actor, int $orderId, array $input): void
    {
        $tenantId = $this->tenantId($actor);
        $this->assertCommande($actor, Permission::COMMANDE_ORDERS);
        $order = $this->findOrder($actor, $orderId);
        if ($order->status() === OrderStatus::CANCELLED) {
            throw new ValidationException(['status' => 'Commande annulée.']);
        }
        $this->measurements->insert($tenantId, $orderId, [
            'author_user_id' => $actor->id(),
            'chest_cm' => $this->optionalDecimal($input, 'chest_cm'),
            'waist_cm' => $this->optionalDecimal($input, 'waist_cm'),
            'hips_cm' => $this->optionalDecimal($input, 'hips_cm'),
            'length_cm' => $this->optionalDecimal($input, 'length_cm'),
            'notes' => $this->optionalNotes($input),
        ]);
        if ($order->status() === OrderStatus::NEW) {
            $this->orders->updateStatus($orderId, $tenantId, OrderStatus::MEASUREMENTS);
        }
    }

    /**
     * @param array<string, mixed> $input
     */
    public function updateDetails(User $actor, int $orderId, array $input): void
    {
        $tenantId = $this->tenantId($actor);
        $this->assertCommande($actor, Permission::COMMANDE_ORDERS);
        $this->findOrder($actor, $orderId);
        $fabric = trim((string) ($input['fabric_notes'] ?? ''));
        $instructions = trim((string) ($input['instructions'] ?? ''));
        $photo = trim((string) ($input['photo_url'] ?? ''));
        if (mb_strlen($fabric) > 500 || mb_strlen($instructions) > 500) {
            throw new ValidationException(['fabric_notes' => 'Texte trop long.']);
        }
        if ($photo !== '' && (mb_strlen($photo) > 500 || !filter_var($photo, FILTER_VALIDATE_URL))) {
            throw new ValidationException(['photo_url' => 'URL de photo invalide.']);
        }
        $this->orders->updateDetails($orderId, $tenantId, $fabric, $instructions, $photo !== '' ? $photo : null);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function scheduleDelivery(User $actor, int $orderId, array $input): void
    {
        $tenantId = $this->tenantId($actor);
        $this->assertCommande($actor, Permission::COMMANDE_ORDERS);
        $this->findOrder($actor, $orderId);
        $dueAt = $this->parseDateTime(trim((string) ($input['delivery_due_at'] ?? '')));
        $notes = trim((string) ($input['delivery_notes'] ?? ''));
        if (mb_strlen($notes) > 500) {
            throw new ValidationException(['delivery_notes' => 'Notes trop longues.']);
        }
        $this->orders->updateDeliveryDue($orderId, $tenantId, $dueAt);
        $this->deliveries->upsertForOrder($tenantId, $orderId, $dueAt, $notes !== '' ? $notes : null);
    }

    public function initiateDeposit(User $actor, int $orderId): string
    {
        if ($this->payments === null) {
            throw new HttpException(503, 'Paiements non disponibles.');
        }
        $tenantId = $this->tenantId($actor);
        $this->assertCommande($actor, Permission::COMMANDE_ORDERS);
        $order = $this->findOrder($actor, $orderId);
        if ($order->depositAmountMinor() < 100) {
            throw new ValidationException(['deposit' => 'Aucun acompte requis pour cette commande.']);
        }
        if ($order->paymentId() !== null) {
            throw new ValidationException(['deposit' => 'Un paiement est déjà lié.']);
        }
        $result = $this->payments->initiate($actor, [
            'amount_minor' => $order->depositAmountMinor(),
            'description' => 'Acompte commande ' . $order->orderNumber(),
            'client_id' => $order->clientId(),
        ]);
        $this->orders->attachPayment($orderId, $tenantId, $result['payment']->id());

        return $result['checkout_url'];
    }

    /**
     * @return array{products: list<\Sesam\Models\CommandeProduct>, clients: list<\Sesam\Models\Client>}
     */
    public function formCatalog(User $actor): array
    {
        $tenantId = $this->tenantId($actor);
        $this->assertCommande($actor, Permission::COMMANDE_ORDERS);

        return [
            'products' => $this->products->listActive($tenantId),
            'clients' => $this->clients->search($tenantId, '', null, null, 200, 0),
        ];
    }

    /**
     * @return array{orders_today: int, orders_progress: int, deliveries_due: int}
     */
    public function dashboardCounts(int $tenantId): array
    {
        $dayStart = gmdate('Y-m-d') . ' 00:00:00';
        $dayEnd = gmdate('Y-m-d', strtotime('+1 day')) . ' 00:00:00';

        return [
            'orders_today' => $this->orders->countToday($tenantId, $dayStart, $dayEnd),
            'orders_progress' => $this->orders->countInProgress($tenantId),
            'deliveries_due' => $this->orders->countDeliveriesDue($tenantId, $dayStart, $dayEnd),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return list<array{product_id: int, product_name: string, quantity: int, unit_price_minor: int, line_total_minor: int, deposit_line: int}>
     */
    private function parseLines(int $tenantId, array $input): array
    {
        $productIds = $input['product_id'] ?? [];
        $quantities = $input['quantity'] ?? [];
        if (!is_array($productIds)) {
            $productIds = [$productIds];
        }
        if (!is_array($quantities)) {
            $quantities = [$quantities];
        }
        $lines = [];
        $count = max(count($productIds), count($quantities));
        for ($i = 0; $i < $count; ++$i) {
            $productId = (int) ($productIds[$i] ?? 0);
            $qty = (int) ($quantities[$i] ?? 0);
            if ($productId < 1 || $qty < 1) {
                continue;
            }
            if ($qty > 99) {
                throw new ValidationException(['quantity' => 'Quantité maximale : 99.']);
            }
            $product = $this->products->findById($productId, $tenantId);
            if ($product === null || !$product->isActive()) {
                throw new ValidationException(['product_id' => 'Produit introuvable.']);
            }
            $lineTotal = $product->priceMinor() * $qty;
            $lines[] = [
                'product_id' => $productId,
                'product_name' => $product->name(),
                'quantity' => $qty,
                'unit_price_minor' => $product->priceMinor(),
                'line_total_minor' => $lineTotal,
                'deposit_line' => $product->depositMinor() * $qty,
            ];
        }

        return $lines;
    }

    private function generateOrderNumber(): string
    {
        return 'CMD-' . gmdate('Ymd') . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
    }

    private function parseDateTime(string $value): string
    {
        if ($value === '') {
            throw new ValidationException(['delivery_due_at' => 'Indiquez une date de livraison.']);
        }
        $normalized = str_replace('T', ' ', $value);
        $timestamp = strtotime($normalized . ' UTC');
        if ($timestamp === false) {
            throw new ValidationException(['delivery_due_at' => 'Date invalide.']);
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function optionalDecimal(array $input, string $key): ?float
    {
        $raw = trim((string) ($input[$key] ?? ''));
        if ($raw === '') {
            return null;
        }
        if (!is_numeric($raw)) {
            throw new ValidationException([$key => 'Mesure invalide.']);
        }
        $value = (float) $raw;
        if ($value <= 0 || $value > 300) {
            throw new ValidationException([$key => 'Mesure hors plage.']);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function optionalNotes(array $input): ?string
    {
        $notes = trim((string) ($input['notes'] ?? ''));

        return $notes !== '' ? mb_substr($notes, 0, 500) : null;
    }

    /**
     * @return array{products: list<\Sesam\Models\CommandeProduct>}
     */
    public function publicCatalog(): array
    {
        $tenantId = $this->requirePublicSector();

        return [
            'products' => $this->products->listActive($tenantId),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function publicCreate(array $input): int
    {
        $tenantId = $this->requirePublicSector();
        $clientId = (int) ($input['client_id'] ?? 0);
        if ($this->clients->findById($clientId, $tenantId) === null) {
            throw new ValidationException(['full_name' => 'Vos informations sont incomplètes.']);
        }
        $parsed = $this->parseLines($tenantId, [
            'product_id' => [$input['product_id'] ?? 0],
            'quantity' => [$input['quantity'] ?? 1],
        ]);
        if ($parsed === []) {
            throw new ValidationException(['product_id' => 'Choisissez un produit.']);
        }
        $total = 0;
        $deposit = 0;
        $lines = [];
        foreach ($parsed as $line) {
            $total += $line['line_total_minor'];
            $deposit += $line['deposit_line'];
            $lines[] = [
                'product_id' => $line['product_id'],
                'product_name' => $line['product_name'],
                'quantity' => $line['quantity'],
                'unit_price_minor' => $line['unit_price_minor'],
                'line_total_minor' => $line['line_total_minor'],
            ];
        }
        $orderId = $this->orders->insert($tenantId, [
            'client_id' => $clientId,
            'order_number' => $this->generateOrderNumber(),
            'status' => OrderStatus::NEW,
            'total_minor' => $total,
            'deposit_amount_minor' => $deposit,
        ]);
        $this->items->insertLines($tenantId, $orderId, $lines);
        $hasMeasure = trim((string) ($input['chest_cm'] ?? '')) !== ''
            || trim((string) ($input['waist_cm'] ?? '')) !== ''
            || trim((string) ($input['hips_cm'] ?? '')) !== ''
            || trim((string) ($input['length_cm'] ?? '')) !== ''
            || trim((string) ($input['notes'] ?? '')) !== '';
        if ($hasMeasure) {
            $this->measurements->insert($tenantId, $orderId, [
                'author_user_id' => null,
                'chest_cm' => $this->optionalDecimal($input, 'chest_cm'),
                'waist_cm' => $this->optionalDecimal($input, 'waist_cm'),
                'hips_cm' => $this->optionalDecimal($input, 'hips_cm'),
                'length_cm' => $this->optionalDecimal($input, 'length_cm'),
                'notes' => $this->optionalNotes($input),
            ]);
            $this->orders->updateStatus($orderId, $tenantId, OrderStatus::MEASUREMENTS);
        }
        $fabric = trim((string) ($input['fabric_notes'] ?? ''));
        $instructions = trim((string) ($input['instructions'] ?? ''));
        if ($fabric !== '' || $instructions !== '') {
            if (mb_strlen($fabric) > 500 || mb_strlen($instructions) > 500) {
                throw new ValidationException(['fabric_notes' => 'Texte trop long.']);
            }
            $this->orders->updateDetails($orderId, $tenantId, $fabric, $instructions, null);
        }

        return $orderId;
    }

    public function publicFind(int $id): CommandeOrder
    {
        $order = $this->orders->findById($id, $this->requirePublicSector());
        if ($order === null) {
            throw new HttpException(404, 'Commande introuvable.');
        }

        return $order;
    }

    public function publicFindByPayment(int $paymentId): ?CommandeOrder
    {
        return $this->orders->findByPaymentId($paymentId, $this->requirePublicSector());
    }

    public function publicAttachPayment(int $orderId, int $paymentId): void
    {
        $this->orders->attachPayment($orderId, $this->requirePublicSector(), $paymentId);
    }

    private function requirePublicSector(): int
    {
        $tenant = $this->context->tenant();
        if (!$tenant instanceof Tenant || $tenant->sector() !== Sector::COMMANDE) {
            throw new HttpException(404, 'Page introuvable.');
        }

        return $tenant->id();
    }

    private function tenantId(User $actor): int
    {
        if ($actor->tenantId() === null) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
        $tenantId = $this->context->requireId();
        if ($tenantId !== $actor->tenantId()) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }

        return $tenantId;
    }

    private function assertCommande(User $actor, string $permission): void
    {
        if (!$actor->can($permission)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
        $tenant = $this->context->tenant();
        if (!$tenant instanceof Tenant || $tenant->sector() !== Sector::COMMANDE) {
            throw new HttpException(403, 'Ce module est réservé aux commerces secteur Commande.');
        }
    }
}

