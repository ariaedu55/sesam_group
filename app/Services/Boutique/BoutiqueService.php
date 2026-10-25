<?php

declare(strict_types=1);

namespace Sesam\Services\Boutique;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Boutique\BoutiqueTheme;
use Sesam\Core\Boutique\Money;
use Sesam\Core\Boutique\OrderStatus;
use Sesam\Core\Boutique\StockMovementType;
use Sesam\Core\Boutique\WhatsApp;
use Sesam\Core\Crm\ClientStatus;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\BoutiqueOrder;
use Sesam\Models\BoutiqueProduct;
use Sesam\Models\BoutiqueSettings;
use Sesam\Models\Client;
use Sesam\Models\Tenant;
use Sesam\Models\User;
use Sesam\Repositories\AuthAttemptRepository;
use Sesam\Repositories\BoutiqueOrderRepository;
use Sesam\Repositories\BoutiqueProductRepository;
use Sesam\Repositories\BoutiqueSettingsRepository;
use Sesam\Repositories\BoutiqueStockRepository;
use Sesam\Repositories\ClientActivityRepository;
use Sesam\Repositories\ClientRepository;
use Sesam\Repositories\TenantRepository;
use Sesam\Services\Service;
use Sesam\Services\Storage\ImageUploadService;

/**
 * Module Boutique : catalogue, stock, commandes et clients d'un commerce, plus sa page publique
 * où le visiteur choisit ses produits puis envoie sa commande au commerçant sur WhatsApp.
 */
final class BoutiqueService extends Service
{
    public const PER_PAGE = 20;
    public const PUBLIC_RATE_MAX = 12;
    public const PUBLIC_RATE_WINDOW = 15;
    private const MAX_QTY = 99;
    private const MAX_LINES = 30;

    private readonly \PDO $pdo;
    private readonly BoutiqueProductRepository $products;
    private readonly BoutiqueOrderRepository $orders;
    private readonly BoutiqueStockRepository $stock;
    private readonly BoutiqueSettingsRepository $settings;
    private readonly ClientRepository $clients;
    private readonly ClientActivityRepository $activities;
    private readonly TenantRepository $tenants;
    private readonly AuthAttemptRepository $attempts;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        array $database,
        private readonly TenantContext $context,
        private readonly ImageUploadService $images,
        private readonly string $appUrl,
    ) {
        $pdo = Connection::make($database);
        $this->pdo = $pdo;
        $this->products = new BoutiqueProductRepository($pdo);
        $this->orders = new BoutiqueOrderRepository($pdo);
        $this->stock = new BoutiqueStockRepository($pdo);
        $this->settings = new BoutiqueSettingsRepository($pdo);
        $this->clients = new ClientRepository($pdo);
        $this->activities = new ClientActivityRepository($pdo);
        $this->tenants = new TenantRepository($pdo);
        $this->attempts = new AuthAttemptRepository($pdo);
    }

    // ---------------------------------------------------------------- Tableau de bord

    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $actor): array
    {
        $tenant = $this->tenant($actor, Permission::BOUTIQUE_VIEW);
        $tenantId = $tenant->id();
        $settings = $this->settings->find($tenantId);
        $monthStart = gmdate('Y-m-01') . ' 00:00:00';
        $statusCounts = $this->orders->countByStatus($tenantId);
        $shareText = $this->shareText($tenant, $settings);

        return [
            'tenant' => $tenant,
            'settings' => $settings,
            'publicUrl' => $this->publicUrl($tenant),
            'shareLink' => 'https://wa.me/?text=' . rawurlencode($shareText),
            'shareText' => $shareText,
            'whatsappReady' => $settings->whatsappPhone() !== '',
            'statusCounts' => $statusCounts,
            'ordersToday' => $this->orders->countSince($tenantId, gmdate('Y-m-d') . ' 00:00:00'),
            'revenueMonth' => $this->orders->revenueSince($tenantId, $monthStart),
            'products' => $this->products->summary($tenantId),
            'recentOrders' => $this->orders->listForTenant($tenantId, '', '', 6, 0),
            'lowStock' => $this->products->listLowStock($tenantId, 6),
            'checklist' => $this->checklist($tenant, $settings),
        ];
    }

    /**
     * Les premières étapes à faire pour une boutique prête à vendre.
     *
     * @return list<array{label: string, done: bool, href: string}>
     */
    private function checklist(Tenant $tenant, BoutiqueSettings $settings): array
    {
        $summary = $this->products->summary($tenant->id());

        return [
            ['label' => 'Ajouter le numéro WhatsApp de la boutique', 'done' => $settings->whatsappPhone() !== '', 'href' => '/boutique/parametres'],
            ['label' => 'Mettre le logo de la boutique', 'done' => $settings->logoPath() !== null, 'href' => '/boutique/parametres'],
            ['label' => 'Ajouter un premier produit avec sa photo', 'done' => $summary['total'] > 0, 'href' => '/boutique/produits/nouveau'],
            ['label' => 'Partager le lien de la boutique', 'done' => $this->orders->countSince($tenant->id(), '2000-01-01 00:00:00') > 0, 'href' => '/espace#partager'],
        ];
    }

    // ---------------------------------------------------------------- Produits

    /**
     * @return array{products: list<BoutiqueProduct>, categories: list<string>, summary: array<string, int>}
     */
    public function productsPage(User $actor, string $query, string $category, string $filter): array
    {
        $tenantId = $this->tenant($actor, Permission::BOUTIQUE_VIEW)->id();

        return [
            'products' => $this->products->listForTenant($tenantId, mb_substr(trim($query), 0, 80), $category, $filter),
            'categories' => $this->products->categories($tenantId),
            'summary' => $this->products->summary($tenantId),
        ];
    }

    /**
     * @return list<string>
     */
    public function categories(User $actor): array
    {
        return $this->products->categories($this->tenant($actor, Permission::BOUTIQUE_VIEW)->id());
    }

    public function product(User $actor, int $id): BoutiqueProduct
    {
        $tenantId = $this->tenant($actor, Permission::BOUTIQUE_VIEW)->id();
        $product = $this->products->findById($id, $tenantId);
        if ($product === null) {
            throw new HttpException(404, 'Produit introuvable.');
        }

        return $product;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $image
     */
    public function createProduct(User $actor, array $input, ?array $image): int
    {
        $tenantId = $this->tenant($actor, Permission::BOUTIQUE_PRODUCTS)->id();
        $data = $this->validatedProduct($input);
        $initial = $this->intField($input, 'stock_quantity', 0, 100000, 'Indiquez une quantité entre 0 et 100 000.');
        $hasImage = $this->hasUpload($image);
        $path = $hasImage ? $this->images->store($image ?? [], 'boutique/' . $tenantId) : null;

        $this->pdo->beginTransaction();
        try {
            $id = $this->products->insert($tenantId, $data + ['stock_quantity' => $data['track_stock'] === 1 ? $initial : 0]);
            if ($path !== null) {
                $this->products->updateImage($id, $tenantId, $path);
            }
            if ($data['track_stock'] === 1 && $initial > 0) {
                $this->stock->insert($tenantId, [
                    'product_id' => $id,
                    'type' => StockMovementType::IN,
                    'quantity' => $initial,
                    'stock_after' => $initial,
                    'note' => 'Stock de départ',
                    'order_id' => null,
                    'user_id' => $actor->id(),
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            $this->images->delete($path);
            throw $exception;
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $image
     */
    public function updateProduct(User $actor, int $id, array $input, ?array $image, bool $removeImage): void
    {
        $tenantId = $this->tenant($actor, Permission::BOUTIQUE_PRODUCTS)->id();
        $product = $this->products->findById($id, $tenantId);
        if ($product === null) {
            throw new HttpException(404, 'Produit introuvable.');
        }
        $data = $this->validatedProduct($input);
        $this->products->update($id, $tenantId, $data);
        if ($this->hasUpload($image)) {
            $path = $this->images->store($image ?? [], 'boutique/' . $tenantId);
            $this->products->updateImage($id, $tenantId, $path);
            $this->images->delete($product->imagePath());
        } elseif ($removeImage && $product->imagePath() !== null) {
            $this->products->updateImage($id, $tenantId, null);
            $this->images->delete($product->imagePath());
        }
    }

    public function togglePublished(User $actor, int $id): bool
    {
        $tenantId = $this->tenant($actor, Permission::BOUTIQUE_PRODUCTS)->id();
        $product = $this->products->findById($id, $tenantId);
        if ($product === null) {
            throw new HttpException(404, 'Produit introuvable.');
        }
        $this->products->setPublished($id, $tenantId, !$product->isPublished());

        return !$product->isPublished();
    }

    public function deleteProduct(User $actor, int $id): void
    {
        $tenantId = $this->tenant($actor, Permission::BOUTIQUE_PRODUCTS)->id();
        $product = $this->products->findById($id, $tenantId);
        if ($product === null) {
            throw new HttpException(404, 'Produit introuvable.');
        }
        // Les lignes des commandes passées gardent le nom et le prix : seul le lien vers le produit disparaît.
        $this->products->delete($id, $tenantId);
        $this->images->delete($product->imagePath());
    }

    /**
     * @return array{contents: string, mime: string}
     */
    public function productImage(Tenant $tenant, int $id): array
    {
        $this->assertBoutiqueTenant($tenant);
        $product = $this->products->findById($id, $tenant->id());
        if ($product === null || $product->imagePath() === null) {
            throw new HttpException(404, 'Image introuvable.');
        }

        return $this->images->read($product->imagePath());
    }

    // ---------------------------------------------------------------- Stock

    /**
     * @return array{products: list<BoutiqueProduct>, movements: list<\Sesam\Models\BoutiqueStockMovement>, summary: array<string, int>, selected: ?BoutiqueProduct}
     */
    public function stockPage(User $actor, ?int $productId): array
    {
        $tenantId = $this->tenant($actor, Permission::BOUTIQUE_VIEW)->id();
        $selected = $productId !== null ? $this->products->findById($productId, $tenantId) : null;
        $tracked = array_values(array_filter(
            $this->products->listForTenant($tenantId),
            static fn (BoutiqueProduct $product): bool => $product->tracksStock()
        ));

        return [
            'products' => $tracked,
            'movements' => $this->stock->listForTenant($tenantId, $selected?->id(), 40),
            'summary' => $this->products->summary($tenantId),
            'selected' => $selected,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function recordMovement(User $actor, array $input): BoutiqueProduct
    {
        $tenantId = $this->tenant($actor, Permission::BOUTIQUE_PRODUCTS)->id();
        $type = (string) ($input['type'] ?? '');
        if (!in_array($type, StockMovementType::MANUAL, true)) {
            throw new ValidationException(['type' => 'Choisissez ce que vous voulez faire.']);
        }
        $quantity = $this->intField($input, 'quantity', $type === StockMovementType::ADJUST ? 0 : 1, 100000, 'Indiquez une quantité valide.');
        $note = mb_substr(trim((string) ($input['note'] ?? '')), 0, 255);

        $this->pdo->beginTransaction();
        try {
            $product = $this->products->lockById((int) ($input['product_id'] ?? 0), $tenantId);
            if ($product === null) {
                throw new ValidationException(['product_id' => 'Choisissez un produit.']);
            }
            if (!$product->tracksStock()) {
                throw new ValidationException(['product_id' => 'Le stock de ce produit n\'est pas suivi. Activez le suivi dans sa fiche.']);
            }
            $current = $product->stockQuantity();
            $after = match ($type) {
                StockMovementType::IN => $current + $quantity,
                StockMovementType::OUT => $current - $quantity,
                default => $quantity,
            };
            if ($after < 0) {
                throw new ValidationException(['quantity' => 'Il ne reste que ' . $current . ' en stock.']);
            }
            $this->products->updateStock($product->id(), $tenantId, $after);
            if ($after < $current && $after <= $product->lowStockThreshold()) {
                $this->stockAlerts[$product->id()] = ['name' => $product->name(), 'left' => $after];
            }
            $this->stock->insert($tenantId, [
                'product_id' => $product->id(),
                'type' => $type,
                'quantity' => $after - $current,
                'stock_after' => $after,
                'note' => $note !== '' ? $note : null,
                'order_id' => null,
                'user_id' => $actor->id(),
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            $this->stockAlerts = [];
            throw $exception;
        }
        $this->flushStockAlerts($tenantId);

        return $product->with(['stock_quantity' => $after]);
    }

    // ---------------------------------------------------------------- Commandes

    /**
     * @return array{orders: list<BoutiqueOrder>, total: int, page: int, pages: int, counts: array<string, int>}
     */
    public function ordersPage(User $actor, string $status, string $query, int $page): array
    {
        $tenantId = $this->tenant($actor, Permission::BOUTIQUE_VIEW)->id();
        $query = mb_substr(trim($query), 0, 80);
        $total = $this->orders->countForTenant($tenantId, $status, $query);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $pages);

        return [
            'orders' => $this->orders->listForTenant($tenantId, $status, $query, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'counts' => $this->orders->countByStatus($tenantId),
        ];
    }

    /**
     * @return array{order: BoutiqueOrder, items: list<\Sesam\Models\BoutiqueOrderItem>, client: ?Client, whatsapp: string}
     */
    public function orderDetail(User $actor, int $id): array
    {
        $tenant = $this->tenant($actor, Permission::BOUTIQUE_VIEW);
        $order = $this->orders->findById($id, $tenant->id());
        if ($order === null) {
            throw new HttpException(404, 'Commande introuvable.');
        }
        $client = $order->clientId() !== null ? $this->clients->findById($order->clientId(), $tenant->id()) : null;

        return [
            'order' => $order,
            'items' => $this->orders->items($id, $tenant->id()),
            'client' => $client,
            'whatsapp' => $this->customerLink($tenant, $order),
        ];
    }

    /**
     * @return array{products: list<BoutiqueProduct>, clients: list<Client>}
     */
    public function orderFormData(User $actor): array
    {
        $tenantId = $this->tenant($actor, Permission::BOUTIQUE_ORDERS)->id();

        return [
            'products' => $this->products->listForTenant($tenantId),
            'clients' => $this->clients->search($tenantId, '', ClientStatus::ACTIVE, null, 300, 0),
        ];
    }

    /**
     * Commande saisie par le commerçant (vente en boutique, appel, message reçu ailleurs).
     *
     * @param array<string, mixed> $input
     */
    public function createOrder(User $actor, array $input): int
    {
        $tenantId = $this->tenant($actor, Permission::BOUTIQUE_ORDERS)->id();
        $lines = $this->parseLines($tenantId, $this->linesFromForm($input), false);
        $clientId = (int) ($input['client_id'] ?? 0);
        $client = $clientId > 0 ? $this->clients->findById($clientId, $tenantId) : null;
        if ($clientId > 0 && $client === null) {
            throw new ValidationException(['client_id' => 'Client introuvable.']);
        }
        $name = trim((string) ($input['customer_name'] ?? ''));
        $phone = trim((string) ($input['customer_phone'] ?? ''));
        if ($client === null && $name === '') {
            throw new ValidationException(['customer_name' => 'Choisissez un client ou écrivez son nom.']);
        }
        $status = (string) ($input['status'] ?? OrderStatus::NEW);
        if (!in_array($status, [OrderStatus::NEW, OrderStatus::CONFIRMED, OrderStatus::DELIVERED], true)) {
            $status = OrderStatus::NEW;
        }
        $customer = $this->validatedCustomer($client?->fullName() ?? $name, $client?->phone() ?? $phone, $input);

        $this->pdo->beginTransaction();
        try {
            $clientId = $client?->id() ?? $this->findOrCreateClient($tenantId, $customer['name'], $customer['phone'], 'Ajouté depuis une commande de la boutique');
            $orderId = $this->insertOrder($tenantId, $clientId, $customer, $lines, 'manual');
            if ($status !== OrderStatus::NEW) {
                $this->applyStatus($actor, $tenantId, $orderId, $status);
            }
            if (!empty($input['is_paid'])) {
                $this->orders->setPaid($orderId, $tenantId, true);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            $this->stockAlerts = [];
            throw $exception;
        }
        $this->flushStockAlerts($tenantId);

        return $orderId;
    }

    public function changeStatus(User $actor, int $id, string $status): BoutiqueOrder
    {
        $tenantId = $this->tenant($actor, Permission::BOUTIQUE_ORDERS)->id();
        $this->pdo->beginTransaction();
        try {
            $this->applyStatus($actor, $tenantId, $id, $status);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            $this->stockAlerts = [];
            throw $exception;
        }
        $this->flushStockAlerts($tenantId);
        $order = $this->orders->findById($id, $tenantId);
        if ($order === null) {
            throw new HttpException(404, 'Commande introuvable.');
        }

        return $order;
    }

    public function togglePaid(User $actor, int $id): bool
    {
        $tenantId = $this->tenant($actor, Permission::BOUTIQUE_ORDERS)->id();
        $order = $this->orders->findById($id, $tenantId);
        if ($order === null) {
            throw new HttpException(404, 'Commande introuvable.');
        }
        $this->orders->setPaid($id, $tenantId, !$order->isPaid());

        return !$order->isPaid();
    }

    /**
     * @return list<BoutiqueOrder>
     */
    public function ordersForClient(User $actor, int $clientId): array
    {
        $tenant = $this->context->tenant();
        if (!$tenant instanceof Tenant || $tenant->sector() !== Sector::BOUTIQUE || !$actor->can(Permission::BOUTIQUE_VIEW)) {
            return [];
        }

        return $this->orders->listForClient($tenant->id(), $clientId, 20);
    }

    /**
     * Passe une commande d'un état à l'autre et met le stock à jour dans la même transaction.
     */
    private function applyStatus(User $actor, int $tenantId, int $orderId, string $status): void
    {
        $order = $this->orders->lockById($orderId, $tenantId);
        if ($order === null) {
            throw new HttpException(404, 'Commande introuvable.');
        }
        if (!OrderStatus::canMoveTo($order->status(), $status)) {
            throw new ValidationException(['status' => 'Cette commande ne peut pas passer à « ' . OrderStatus::label($status) . ' ».']);
        }
        $consume = OrderStatus::consumesStock($status);
        $applied = $order->stockApplied();
        if ($consume && !$applied) {
            $this->moveOrderStock($actor, $tenantId, $order, -1);
            $applied = true;
        } elseif (!$consume && $applied) {
            $this->moveOrderStock($actor, $tenantId, $order, 1);
            $applied = false;
        }
        $this->orders->updateStatus($orderId, $tenantId, $status, $applied);
    }

    /** @var array<int, array{name: string, left: int}> Produits passés sous le seuil d'alerte, notifiés après validation. */
    private array $stockAlerts = [];

    private function flushStockAlerts(int $tenantId): void
    {
        foreach ($this->stockAlerts as $productId => $alert) {
            \Sesam\Services\Notifier::tenant(
                $tenantId,
                'boutique.stock',
                $alert['left'] <= 0 ? 'Produit épuisé : ' . $alert['name'] : 'Stock bas : ' . $alert['name'],
                $alert['left'] <= 0 ? 'Il n\'en reste plus. Ajoutez du stock pour continuer à le vendre.' : 'Il en reste ' . $alert['left'] . '. Pensez à réapprovisionner.',
                '/boutique/stock?produit=' . $productId,
                $alert['left'] <= 0 ? 'danger' : 'warning',
                'boutique-stock-' . $tenantId . '-' . $productId . '-' . $alert['left'] . '-' . gmdate('Y-m-d'),
            );
        }
        $this->stockAlerts = [];
    }

    private function moveOrderStock(User $actor, int $tenantId, BoutiqueOrder $order, int $direction): void
    {
        $quantities = [];
        foreach ($this->orders->items($order->id(), $tenantId) as $item) {
            if ($item->productId() !== null) {
                $quantities[$item->productId()] = ($quantities[$item->productId()] ?? 0) + $item->quantity();
            }
        }
        ksort($quantities);
        foreach ($quantities as $productId => $quantity) {
            $product = $this->products->lockById($productId, $tenantId);
            if ($product === null || !$product->tracksStock()) {
                continue;
            }
            $after = $product->stockQuantity() + ($direction * $quantity);
            if ($after < 0) {
                throw new ValidationException(['status' => 'Stock insuffisant pour « ' . $product->name() . ' » : il en reste '
                    . $product->stockQuantity() . '. Ajoutez du stock avant de confirmer.']);
            }
            $this->products->updateStock($productId, $tenantId, $after);
            if ($direction < 0 && $after <= $product->lowStockThreshold()) {
                $this->stockAlerts[$productId] = ['name' => $product->name(), 'left' => $after];
            }
            $this->stock->insert($tenantId, [
                'product_id' => $productId,
                'type' => $direction < 0 ? StockMovementType::SALE : StockMovementType::RETURN,
                'quantity' => $direction * $quantity,
                'stock_after' => $after,
                'note' => 'Commande ' . $order->orderNumber(),
                'order_id' => $order->id(),
                'user_id' => $actor->id(),
            ]);
        }
    }

    // ---------------------------------------------------------------- Paramètres

    public function settingsFor(User $actor): BoutiqueSettings
    {
        return $this->settings->find($this->tenant($actor, Permission::BOUTIQUE_VIEW)->id());
    }

    public function publicUrlFor(User $actor): string
    {
        return $this->publicUrl($this->tenant($actor, Permission::BOUTIQUE_VIEW));
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $logo
     * @param array<string, mixed>|null $cover
     */
    public function saveSettings(User $actor, array $input, ?array $logo, ?array $cover): void
    {
        $tenant = $this->tenant($actor, Permission::BOUTIQUE_SETTINGS);
        $tenantId = $tenant->id();
        $errors = [];
        $name = trim((string) ($input['display_name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['display_name'] = 'Indiquez le nom de la boutique (120 caractères max.).';
        }
        $rawPhone = trim((string) ($input['whatsapp_phone'] ?? ''));
        $phone = $rawPhone === '' ? '' : WhatsApp::normalize($rawPhone);
        if ($rawPhone !== '' && !WhatsApp::isValid($phone)) {
            $errors['whatsapp_phone'] = 'Numéro WhatsApp invalide. Exemple : 77 123 45 67.';
        }
        $theme = (string) ($input['theme'] ?? BoutiqueTheme::DEFAULT);
        if (!BoutiqueTheme::isValid($theme)) {
            $theme = BoutiqueTheme::DEFAULT;
        }
        $texts = [];
        foreach (['tagline' => 160, 'description' => 1000, 'address' => 190, 'opening_hours' => 190, 'delivery_info' => 300] as $key => $max) {
            $value = trim((string) ($input[$key] ?? ''));
            if (mb_strlen($value) > $max) {
                $errors[$key] = 'Texte trop long (' . $max . ' caractères max.).';
            }
            $texts[$key] = $value !== '' ? $value : null;
        }
        $links = [];
        foreach (['instagram_url', 'facebook_url', 'tiktok_url'] as $key) {
            $value = trim((string) ($input[$key] ?? ''));
            if ($value !== '' && !preg_match('#^https?://#i', $value)) {
                $value = 'https://' . $value;
            }
            if ($value !== '' && (mb_strlen($value) > 255 || filter_var($value, FILTER_VALIDATE_URL) === false)) {
                $errors[$key] = 'Lien invalide.';
            }
            $links[$key] = $value !== '' ? $value : null;
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $current = $this->settings->find($tenantId);
        $logoPath = $this->hasUpload($logo) ? $this->images->store($logo ?? [], 'boutique/' . $tenantId) : null;
        try {
            $coverPath = $this->hasUpload($cover) ? $this->images->store($cover ?? [], 'boutique/' . $tenantId) : null;
        } catch (ValidationException $exception) {
            $this->images->delete($logoPath);
            throw new ValidationException(['cover' => $exception->errors()['image'] ?? 'Image invalide.']);
        }

        $this->tenants->updateDisplayName($tenantId, $name);
        $this->settings->save($tenantId, $texts + $links + [
            'whatsapp_phone' => $phone !== '' ? $phone : null,
            'theme' => $theme,
            'is_open' => !empty($input['is_open']) ? 1 : 0,
            'show_stock' => !empty($input['show_stock']) ? 1 : 0,
        ]);
        if ($logoPath !== null) {
            $this->settings->updateImage($tenantId, 'logo_path', $logoPath);
            $this->images->delete($current->logoPath());
        } elseif (!empty($input['remove_logo']) && $current->logoPath() !== null) {
            $this->settings->updateImage($tenantId, 'logo_path', null);
            $this->images->delete($current->logoPath());
        }
        if ($coverPath !== null) {
            $this->settings->updateImage($tenantId, 'cover_path', $coverPath);
            $this->images->delete($current->coverPath());
        } elseif (!empty($input['remove_cover']) && $current->coverPath() !== null) {
            $this->settings->updateImage($tenantId, 'cover_path', null);
            $this->images->delete($current->coverPath());
        }
    }

    /**
     * @param 'logo'|'cover' $kind
     * @return array{contents: string, mime: string}
     */
    public function brandImage(Tenant $tenant, string $kind): array
    {
        $this->assertBoutiqueTenant($tenant);
        $settings = $this->settings->find($tenant->id());
        $path = $kind === 'logo' ? $settings->logoPath() : $settings->coverPath();
        if ($path === null) {
            throw new HttpException(404, 'Image introuvable.');
        }

        return $this->images->read($path);
    }

    // ---------------------------------------------------------------- Page publique

    /**
     * @return array{settings: BoutiqueSettings, products: list<BoutiqueProduct>, categories: list<string>, whatsapp: string}
     */
    public function publicShop(Tenant $tenant): array
    {
        $this->assertBoutiqueTenant($tenant);
        $settings = $this->settings->find($tenant->id());

        return [
            'settings' => $settings,
            'products' => $this->products->listPublished($tenant->id()),
            'categories' => $this->products->categories($tenant->id(), true),
            'whatsapp' => $settings->whatsappPhone() !== ''
                ? WhatsApp::link($settings->whatsappPhone(), 'Bonjour ' . $tenant->displayName() . ', j\'ai vu votre boutique en ligne.')
                : '',
        ];
    }

    /**
     * Enregistre la commande du visiteur puis renvoie le lien WhatsApp du commerçant, message déjà rédigé.
     *
     * @param array<string, mixed> $input
     * @return array{order_number: string, whatsapp: string}
     */
    public function publicOrder(Tenant $tenant, array $input, string $ip): array
    {
        $this->assertBoutiqueTenant($tenant);
        if ($this->attempts->countRecent('boutique', $ip, self::PUBLIC_RATE_WINDOW) >= self::PUBLIC_RATE_MAX) {
            throw new HttpException(429, 'Trop de commandes envoyées. Réessayez dans quelques minutes.');
        }
        $this->attempts->record('boutique', $ip);
        $settings = $this->settings->find($tenant->id());
        if (!$settings->isOpen()) {
            throw new ValidationException(['cart' => 'La boutique ne prend pas de commande pour le moment.']);
        }
        if ($settings->whatsappPhone() === '') {
            throw new ValidationException(['cart' => 'La boutique n\'a pas encore de numéro WhatsApp.']);
        }
        $raw = $input['items'] ?? [];
        $requested = [];
        if (is_array($raw)) {
            foreach ($raw as $productId => $qty) {
                $requested[] = ['product_id' => (int) $productId, 'quantity' => (int) $qty];
            }
        }
        $lines = $this->parseLines($tenant->id(), $requested, true);
        $customer = $this->validatedCustomer(trim((string) ($input['customer_name'] ?? '')), trim((string) ($input['customer_phone'] ?? '')), $input, true);

        $this->pdo->beginTransaction();
        try {
            $clientId = $this->findOrCreateClient($tenant->id(), $customer['name'], $customer['phone'], 'Arrivé par la boutique en ligne');
            $orderId = $this->insertOrder($tenant->id(), $clientId, $customer, $lines, 'whatsapp');
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
        $order = $this->orders->findById($orderId, $tenant->id());
        $number = $order?->orderNumber() ?? '';
        $articles = array_sum(array_column($lines, 'quantity'));
        $short = $this->stockWarnings($tenant->id(), $lines);
        \Sesam\Services\Notifier::tenant(
            $tenant->id(),
            'boutique.order',
            'Nouvelle commande ' . $number,
            $customer['name'] . ' · ' . $articles . ' article(s) · ' . Money::format(array_sum(array_column($lines, 'line_total_minor')))
                . '. Le client l\'envoie aussi sur WhatsApp.'
                . ($short !== [] ? ' Stock à prévoir : ' . implode(', ', $short) . '.' : ''),
            '/boutique/commandes/' . $orderId,
            $short !== [] ? 'warning' : 'success',
        );

        return [
            'order_number' => $number,
            'whatsapp' => WhatsApp::link($settings->whatsappPhone(), $this->orderMessage($tenant, $number, $lines, $customer)),
        ];
    }

    /**
     * Produits qu'une commande en ligne va faire passer sous le seuil d'alerte une fois confirmée.
     *
     * @param list<array{product_id: int, product_name: string, quantity: int}> $lines
     * @return list<string>
     */
    private function stockWarnings(int $tenantId, array $lines): array
    {
        $warnings = [];
        foreach ($lines as $line) {
            $product = $this->products->findById($line['product_id'], $tenantId);
            if ($product === null || !$product->tracksStock()) {
                continue;
            }
            $left = $product->stockQuantity() - $line['quantity'];
            if ($left <= $product->lowStockThreshold()) {
                $warnings[] = $line['product_name'] . ($left <= 0 ? ' (épuisé après cette commande)' : ' (il en restera ' . $left . ')');
            }
        }

        return $warnings;
    }

    public function publicUrl(Tenant $tenant): string
    {
        return rtrim($this->appUrl, '/') . '/t/' . rawurlencode($tenant->slug());
    }

    /** Adresse publique d'un produit : WhatsApp en tire une carte avec la photo, le nom et le prix. */
    public function productUrl(Tenant $tenant, int $productId): string
    {
        return $this->publicUrl($tenant) . '/produit/' . $productId;
    }

    /**
     * Fiche publique d'un produit (lien partagé ou envoyé dans la commande WhatsApp).
     *
     * @return array{settings: BoutiqueSettings, product: BoutiqueProduct, url: string, imageUrl: string, whatsapp: string}
     */
    public function publicProduct(Tenant $tenant, int $productId): array
    {
        $this->assertBoutiqueTenant($tenant);
        $product = $this->products->findById($productId, $tenant->id());
        if ($product === null || !$product->isPublished()) {
            throw new HttpException(404, 'Ce produit n\'est plus en vente.');
        }
        $settings = $this->settings->find($tenant->id());
        $url = $this->productUrl($tenant, $product->id());

        return [
            'settings' => $settings,
            'product' => $product,
            'url' => $url,
            'imageUrl' => $product->imagePath() !== null ? $url . '/image?v=' . rawurlencode($product->updatedAt()) : '',
            'whatsapp' => $settings->whatsappPhone() !== ''
                ? WhatsApp::link($settings->whatsappPhone(), $url . "\n" . 'Bonjour ' . $tenant->displayName() . ', ce produit m\'intéresse : ' . $product->name() . ' (' . Money::format($product->priceMinor()) . ').')
                : '',
        ];
    }

    // ---------------------------------------------------------------- Outils internes

    /**
     * @param list<array{product_id: int, quantity: int}> $requested
     * @return list<array{product_id: int, product_name: string, quantity: int, unit_price_minor: int, line_total_minor: int}>
     */
    private function parseLines(int $tenantId, array $requested, bool $public): array
    {
        $merged = [];
        foreach ($requested as $line) {
            if ($line['product_id'] < 1 || $line['quantity'] < 1) {
                continue;
            }
            $merged[$line['product_id']] = ($merged[$line['product_id']] ?? 0) + $line['quantity'];
        }
        if ($merged === []) {
            throw new ValidationException(['cart' => $public ? 'Votre panier est vide. Choisissez au moins un produit.' : 'Ajoutez au moins un produit.']);
        }
        if (count($merged) > self::MAX_LINES) {
            throw new ValidationException(['cart' => 'Trop de produits différents dans une seule commande.']);
        }
        $lines = [];
        foreach ($merged as $productId => $quantity) {
            if ($quantity > self::MAX_QTY) {
                throw new ValidationException(['cart' => 'Quantité maximale : ' . self::MAX_QTY . ' par produit.']);
            }
            $product = $this->products->findById($productId, $tenantId);
            if ($product === null || ($public && !$product->isPublished())) {
                throw new ValidationException(['cart' => 'Un produit de la commande n\'est plus disponible.']);
            }
            if ($public && $product->tracksStock() && $quantity > $product->stockQuantity()) {
                throw new ValidationException(['cart' => $product->stockQuantity() > 0
                    ? 'Il ne reste que ' . $product->stockQuantity() . ' « ' . $product->name() . ' ».'
                    : '« ' . $product->name() . ' » est en rupture de stock.']);
            }
            $lines[] = [
                'product_id' => $productId,
                'product_name' => $product->name(),
                'quantity' => $quantity,
                'unit_price_minor' => $product->priceMinor(),
                'line_total_minor' => $product->priceMinor() * $quantity,
            ];
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $input
     * @return list<array{product_id: int, quantity: int}>
     */
    private function linesFromForm(array $input): array
    {
        $ids = is_array($input['product_id'] ?? null) ? $input['product_id'] : [];
        $quantities = is_array($input['quantity'] ?? null) ? $input['quantity'] : [];
        $lines = [];
        foreach ($ids as $index => $id) {
            $lines[] = ['product_id' => (int) $id, 'quantity' => (int) ($quantities[$index] ?? 0)];
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{name: string, phone: string, address: string, note: string}
     */
    private function validatedCustomer(string $name, string $phone, array $input, bool $public = false): array
    {
        $errors = [];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['customer_name'] = 'Indiquez votre nom (120 caractères max.).';
        }
        if ($public && $phone === '') {
            $errors['customer_phone'] = 'Indiquez votre numéro de téléphone.';
        }
        if (mb_strlen($phone) > 40) {
            $errors['customer_phone'] = 'Numéro trop long.';
        }
        $address = trim((string) ($input['customer_address'] ?? ''));
        $note = trim((string) ($input['note'] ?? ''));
        if (mb_strlen($address) > 255) {
            $errors['customer_address'] = 'Adresse trop longue.';
        }
        if (mb_strlen($note) > 500) {
            $errors['note'] = 'Message trop long (500 caractères max.).';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return ['name' => $name, 'phone' => $phone, 'address' => $address, 'note' => $note];
    }

    /**
     * Un client qui revient avec le même numéro retrouve sa fiche : ses commandes s'additionnent au même endroit.
     */
    private function findOrCreateClient(int $tenantId, string $name, string $phone, string $activity): ?int
    {
        if ($phone !== '') {
            $existing = $this->clients->findByPhone($phone, $tenantId);
            if ($existing !== null) {
                return $existing->id();
            }
        }
        $clientId = $this->clients->insert($tenantId, [
            'full_name' => $name,
            'email' => null,
            'phone' => $phone !== '' ? $phone : null,
            'status' => ClientStatus::ACTIVE,
        ]);
        $this->activities->insert($tenantId, $clientId, null, 'boutique', $activity);

        return $clientId;
    }

    /**
     * @param array{name: string, phone: string, address: string, note: string} $customer
     * @param list<array{product_id: int, product_name: string, quantity: int, unit_price_minor: int, line_total_minor: int}> $lines
     */
    private function insertOrder(int $tenantId, ?int $clientId, array $customer, array $lines, string $source): int
    {
        $total = array_sum(array_column($lines, 'line_total_minor'));
        $orderId = $this->orders->insert($tenantId, [
            'client_id' => $clientId,
            'order_number' => 'BTQ-' . gmdate('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5)),
            'status' => OrderStatus::NEW,
            'source' => $source,
            'customer_name' => $customer['name'],
            'customer_phone' => $customer['phone'] !== '' ? $customer['phone'] : null,
            'customer_address' => $customer['address'] !== '' ? $customer['address'] : null,
            'note' => $customer['note'] !== '' ? $customer['note'] : null,
            'total_minor' => $total,
        ]);
        $this->orders->insertItems($tenantId, $orderId, $lines);
        if ($clientId !== null) {
            $this->activities->insert($tenantId, $clientId, null, 'boutique', 'Commande ' . $this->orders->findById($orderId, $tenantId)?->orderNumber() . ' — ' . Money::format($total));
        }

        return $orderId;
    }

    /**
     * @param list<array{product_id: int, product_name: string, quantity: int, unit_price_minor: int, line_total_minor: int}> $lines
     * @param array{name: string, phone: string, address: string, note: string} $customer
     */
    private function orderMessage(Tenant $tenant, string $number, array $lines, array $customer): string
    {
        // Chaque produit est suivi de son lien : WhatsApp affiche en tête du message la carte (photo, nom, prix) du premier.
        $rows = ['Bonjour ' . $tenant->displayName() . ' !', 'Je souhaite commander :'];
        foreach ($lines as $line) {
            $rows[] = '';
            $rows[] = '• ' . $line['quantity'] . ' × ' . $line['product_name'] . ' — ' . Money::format($line['line_total_minor']);
            $rows[] = $this->productUrl($tenant, (int) $line['product_id']);
        }
        $rows[] = '';
        $rows[] = 'Total : ' . Money::format(array_sum(array_column($lines, 'line_total_minor')));
        $rows[] = '';
        $rows[] = 'Commande n° ' . $number;
        $rows[] = 'Nom : ' . $customer['name'];
        if ($customer['phone'] !== '') {
            $rows[] = 'Téléphone : ' . $customer['phone'];
        }
        if ($customer['address'] !== '') {
            $rows[] = 'Adresse : ' . $customer['address'];
        }
        if ($customer['note'] !== '') {
            $rows[] = 'Message : ' . $customer['note'];
        }

        return implode("\n", $rows);
    }

    /**
     * Lien pour écrire au client depuis la fiche commande, avec un message prêt selon l'étape.
     */
    private function customerLink(Tenant $tenant, BoutiqueOrder $order): string
    {
        $phone = WhatsApp::normalize($order->customerPhone());
        if (!WhatsApp::isValid($phone)) {
            return '';
        }
        $message = match ($order->status()) {
            OrderStatus::CONFIRMED => 'Bonjour ' . $order->customerName() . ', votre commande ' . $order->orderNumber() . ' (' . Money::format($order->totalMinor()) . ') est confirmée. Merci !',
            OrderStatus::DELIVERED => 'Bonjour ' . $order->customerName() . ', votre commande ' . $order->orderNumber() . ' a été livrée. Merci pour votre confiance !',
            OrderStatus::CANCELLED => 'Bonjour ' . $order->customerName() . ', votre commande ' . $order->orderNumber() . ' a été annulée.',
            default => 'Bonjour ' . $order->customerName() . ', nous avons bien reçu votre commande ' . $order->orderNumber() . '.',
        };

        return WhatsApp::link($phone, $message . ' — ' . $tenant->displayName());
    }

    private function shareText(Tenant $tenant, BoutiqueSettings $settings): string
    {
        $intro = $settings->tagline() !== '' ? $settings->tagline() : 'Découvrez nos produits et commandez en un clic sur WhatsApp.';

        return $tenant->displayName() . ' — ' . $intro . ' ' . $this->publicUrl($tenant);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{name: string, description: ?string, category: ?string, price_minor: int, compare_price_minor: ?int, track_stock: int, low_stock_threshold: int, is_published: int}
     */
    private function validatedProduct(array $input): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = 'Indiquez le nom du produit (120 caractères max.).';
        }
        $description = trim((string) ($input['description'] ?? ''));
        if (mb_strlen($description) > 1000) {
            $errors['description'] = 'Description trop longue (1 000 caractères max.).';
        }
        $category = trim((string) ($input['category'] ?? ''));
        if (mb_strlen($category) > 60) {
            $errors['category'] = 'Catégorie trop longue (60 caractères max.).';
        }
        $price = $this->moneyField($input, 'price_minor');
        if ($price === null) {
            $errors['price_minor'] = 'Indiquez un prix en FCFA (chiffres uniquement).';
        }
        $compare = trim((string) ($input['compare_price_minor'] ?? '')) === '' ? null : $this->moneyField($input, 'compare_price_minor');
        if (trim((string) ($input['compare_price_minor'] ?? '')) !== '' && $compare === null) {
            $errors['compare_price_minor'] = 'Ancien prix invalide.';
        }
        $threshold = (int) ($input['low_stock_threshold'] ?? 3);
        if ($threshold < 0 || $threshold > 10000) {
            $errors['low_stock_threshold'] = 'Seuil d\'alerte invalide.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'category' => $category !== '' ? mb_convert_case(mb_substr($category, 0, 1), MB_CASE_UPPER) . mb_substr($category, 1) : null,
            'price_minor' => (int) $price,
            'compare_price_minor' => $compare !== null && $compare > (int) $price ? $compare : null,
            'track_stock' => !empty($input['track_stock']) ? 1 : 0,
            'low_stock_threshold' => $threshold,
            'is_published' => !empty($input['is_published']) ? 1 : 0,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function moneyField(array $input, string $key): ?int
    {
        $raw = preg_replace('/[\s.\x{00A0}\x{202F}]|fcfa|f/iu', '', (string) ($input[$key] ?? '')) ?? '';
        if ($raw === '' || !ctype_digit($raw) || strlen($raw) > 9) {
            return null;
        }

        return (int) $raw;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function intField(array $input, string $key, int $min, int $max, string $message): int
    {
        $raw = trim((string) ($input[$key] ?? ''));
        if ($raw === '' && $min === 0) {
            return 0;
        }
        if (!preg_match('/^\d+$/', $raw) || (int) $raw < $min || (int) $raw > $max) {
            throw new ValidationException([$key => $message]);
        }

        return (int) $raw;
    }

    /**
     * @param array<string, mixed>|null $file
     */
    private function hasUpload(?array $file): bool
    {
        return is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    private function tenant(User $actor, string $permission): Tenant
    {
        if (!$actor->can($permission)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
        $tenant = $this->context->tenant();
        if ($actor->tenantId() === null || !$tenant instanceof Tenant || $tenant->id() !== $actor->tenantId()) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
        if ($tenant->sector() !== Sector::BOUTIQUE) {
            throw new HttpException(403, 'Ce module est réservé aux commerces abonnés à Sesam Boutique.');
        }

        return $tenant;
    }

    private function assertBoutiqueTenant(Tenant $tenant): void
    {
        $bound = $this->context->tenant();
        if (!$bound instanceof Tenant || $bound->id() !== $tenant->id() || $tenant->sector() !== Sector::BOUTIQUE) {
            throw new HttpException(404, 'Page introuvable.');
        }
    }
}
