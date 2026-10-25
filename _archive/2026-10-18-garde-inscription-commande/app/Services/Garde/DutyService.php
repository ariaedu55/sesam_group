<?php

declare(strict_types=1);

namespace Sesam\Services\Garde;

use PDO;
use Sesam\Core\Auth\Permission;
use Sesam\Core\Database\Connection;
use Sesam\Core\Garde\ExpiryStatus;
use Sesam\Core\Garde\MovementType;
use Sesam\Core\HttpException;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\GardeBatch;
use Sesam\Models\GardeProduct;
use Sesam\Models\GardeShift;
use Sesam\Models\GardeStaff;
use Sesam\Models\Tenant;
use Sesam\Models\User;
use Sesam\Repositories\GardeBatchRepository;
use Sesam\Repositories\GardeMovementRepository;
use Sesam\Repositories\GardeProductRepository;
use Sesam\Repositories\GardeShiftRepository;
use Sesam\Repositories\GardeStaffRepository;
use Sesam\Services\Service;
use Sesam\Services\Storage\ImageUploadService;

final class DutyService extends Service
{
    public const PER_PAGE = 20;

    private readonly PDO $pdo;
    private readonly GardeStaffRepository $staff;
    private readonly GardeShiftRepository $shifts;
    private readonly GardeProductRepository $products;
    private readonly GardeBatchRepository $batches;
    private readonly GardeMovementRepository $movements;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        array $database,
        private readonly TenantContext $context,
        private readonly ImageUploadService $images,
    ) {
        $this->pdo = Connection::make($database);
        $this->staff = new GardeStaffRepository($this->pdo);
        $this->shifts = new GardeShiftRepository($this->pdo);
        $this->products = new GardeProductRepository($this->pdo);
        $this->batches = new GardeBatchRepository($this->pdo);
        $this->movements = new GardeMovementRepository($this->pdo);
    }

    /**
     * @return list<GardeStaff>
     */
    public function listStaff(User $actor): array
    {
        $this->assertGarde($actor, Permission::GARDE_VIEW);

        return $this->staff->listAll($this->tenantId($actor));
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createStaff(User $actor, array $input): int
    {
        $this->assertGarde($actor, Permission::GARDE_STAFF);
        $name = trim((string) ($input['name'] ?? ''));
        $role = trim((string) ($input['role_label'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw new ValidationException(['name' => 'Nom du personnel invalide.']);
        }
        if (mb_strlen($role) > 80) {
            throw new ValidationException(['role_label' => 'Fonction trop longue.']);
        }

        return $this->staff->insert($this->tenantId($actor), $name, $role);
    }

    /**
     * @return array{
     *     title: string,
     *     month: string,
     *     prev: string,
     *     next: string,
     *     weekdays: list<string>,
     *     cells: list<array{label: string, date: string, today: bool, shifts: list<GardeShift>}>,
     *     staff: list<GardeStaff>
     * }
     */
    public function calendar(User $actor, string $month): array
    {
        $this->assertGarde($actor, Permission::GARDE_VIEW);

        return $this->buildCalendar($this->tenantId($actor), $month);
    }

    /**
     * @return array{
     *     title: string,
     *     month: string,
     *     prev: string,
     *     next: string,
     *     weekdays: list<string>,
     *     cells: list<array{label: string, date: string, today: bool, shifts: list<GardeShift>}>,
     *     staff: list<GardeStaff>
     * }
     */
    public function publicCalendar(string $month): array
    {
        $tenant = $this->context->tenant();
        if (!$tenant instanceof Tenant || $tenant->sector() !== Sector::GARDE) {
            throw new HttpException(404, 'Page introuvable.');
        }

        return $this->buildCalendar($tenant->id(), $month);
    }

    /**
     * @return array{
     *     title: string,
     *     month: string,
     *     prev: string,
     *     next: string,
     *     weekdays: list<string>,
     *     cells: list<array{label: string, date: string, today: bool, shifts: list<GardeShift>}>,
     *     staff: list<GardeStaff>
     * }
     */
    private function buildCalendar(int $tenantId, string $month): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = gmdate('Y-m');
        }
        $start = new \DateTimeImmutable($month . '-01', new \DateTimeZone('UTC'));
        $from = $start->format('Y-m-d');
        $to = $start->modify('last day of this month')->format('Y-m-d');
        $shifts = $this->shifts->listBetween($tenantId, $from, $to);
        $byDate = [];
        foreach ($shifts as $shift) {
            $byDate[$shift->shiftDate()][] = $shift;
        }
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];
        $pad = (int) $start->format('N') - 1;
        $daysInMonth = (int) $start->format('t');
        $today = gmdate('Y-m-d');
        $cells = [];
        for ($i = 0; $i < $pad; ++$i) {
            $cells[] = ['label' => '', 'date' => '', 'today' => false, 'shifts' => []];
        }
        for ($day = 1; $day <= $daysInMonth; ++$day) {
            $date = $start->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            $cells[] = [
                'label' => (string) $day,
                'date' => $date,
                'today' => $date === $today,
                'shifts' => $byDate[$date] ?? [],
            ];
        }

        return [
            'title' => ($months[(int) $start->format('n')] ?? '') . ' ' . $start->format('Y'),
            'month' => $month,
            'prev' => $start->modify('-1 month')->format('Y-m'),
            'next' => $start->modify('+1 month')->format('Y-m'),
            'weekdays' => ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
            'cells' => $cells,
            'staff' => $this->staff->listActive($tenantId),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createShift(User $actor, array $input): int
    {
        $this->assertGarde($actor, Permission::GARDE_SHIFTS);
        $tenantId = $this->tenantId($actor);
        $staffId = (int) ($input['staff_id'] ?? 0);
        $date = $this->parseDate((string) ($input['shift_date'] ?? ''), 'shift_date');
        $note = trim((string) ($input['note'] ?? ''));
        if (mb_strlen($note) > 180) {
            throw new ValidationException(['note' => 'Note trop longue.']);
        }
        $member = $this->staff->findById($staffId, $tenantId);
        if ($member === null || !$member->isActive()) {
            throw new ValidationException(['staff_id' => 'Personnel introuvable.']);
        }
        if ($this->shifts->existsOnDay($tenantId, $staffId, $date)) {
            throw new ValidationException(['shift_date' => 'Cette personne est déjà de garde ce jour-là.']);
        }

        return $this->shifts->insert($tenantId, $staffId, $date, $note);
    }

    public function deleteShift(User $actor, int $id): void
    {
        $this->assertGarde($actor, Permission::GARDE_SHIFTS);
        $tenantId = $this->tenantId($actor);
        if ($this->shifts->findById($id, $tenantId) === null) {
            throw new HttpException(404, 'Garde introuvable.');
        }
        $this->shifts->delete($id, $tenantId);
    }

    /**
     * @return list<GardeProduct>
     */
    public function listProducts(User $actor): array
    {
        $this->assertGarde($actor, Permission::GARDE_VIEW);

        return $this->products->listAll($this->tenantId($actor));
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createProduct(User $actor, array $input): int
    {
        $this->assertGarde($actor, Permission::GARDE_STOCK);
        $name = trim((string) ($input['name'] ?? ''));
        $unit = trim((string) ($input['unit'] ?? 'unité'));
        $min = (int) ($input['min_quantity'] ?? 0);
        if ($name === '' || mb_strlen($name) > 160) {
            throw new ValidationException(['name' => 'Nom de produit invalide.']);
        }
        if ($unit === '' || mb_strlen($unit) > 40) {
            throw new ValidationException(['unit' => 'Unité invalide.']);
        }
        if ($min < 0 || $min > 100000) {
            throw new ValidationException(['min_quantity' => 'Seuil minimum invalide.']);
        }
        $tenantId = $this->tenantId($actor);
        $imagePath = null;
        $upload = $this->optionalImage($input['image'] ?? null);
        if ($upload !== null) {
            $imagePath = $this->images->store($upload, 'garde/' . $tenantId);
        }
        try {
            return $this->products->insert($tenantId, $name, $unit, $min, $imagePath);
        } catch (\Throwable $exception) {
            $this->images->delete($imagePath);
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $input
     */
    public function replaceProductImage(User $actor, int $id, array $input): void
    {
        $this->assertGarde($actor, Permission::GARDE_STOCK);
        $tenantId = $this->tenantId($actor);
        $product = $this->products->findById($id, $tenantId);
        if ($product === null) {
            throw new HttpException(404, 'Produit introuvable.');
        }
        $upload = $this->optionalImage($input['image'] ?? null);
        if ($upload === null) {
            throw new ValidationException(['image' => 'Ajoutez une photo.']);
        }
        $imagePath = $this->images->store($upload, 'garde/' . $tenantId);
        $previous = $product->imagePath();
        $this->products->updateImagePath($id, $tenantId, $imagePath);
        if ($previous !== '' && $previous !== $imagePath) {
            $this->images->delete($previous);
        }
    }

    /**
     * @return array{contents: string, mime: string}
     */
    public function productImageFile(User $actor, int $id): array
    {
        $this->assertGarde($actor, Permission::GARDE_VIEW);
        $product = $this->products->findById($id, $this->tenantId($actor));
        if ($product === null || !$product->hasImage()) {
            throw new HttpException(404, 'Image introuvable.');
        }

        return $this->images->read($product->imagePath());
    }

    /**
     * @return array{product: GardeProduct, batches: list<GardeBatch>}
     */
    public function productDetail(User $actor, int $id): array
    {
        $this->assertGarde($actor, Permission::GARDE_VIEW);
        $tenantId = $this->tenantId($actor);
        $product = $this->products->findById($id, $tenantId);
        if ($product === null) {
            throw new HttpException(404, 'Produit introuvable.');
        }

        return [
            'product' => $product,
            'batches' => $this->batches->listForProduct($id, $tenantId),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createLot(User $actor, array $input): int
    {
        $this->assertGarde($actor, Permission::GARDE_STOCK);
        $tenantId = $this->tenantId($actor);
        $productId = (int) ($input['product_id'] ?? 0);
        $lot = trim((string) ($input['lot_code'] ?? ''));
        $quantity = (int) ($input['quantity'] ?? 0);
        $expires = $this->parseDate((string) ($input['expires_on'] ?? ''), 'expires_on');
        $product = $this->products->findById($productId, $tenantId);
        if ($product === null || !$product->isActive()) {
            throw new ValidationException(['product_id' => 'Produit introuvable.']);
        }
        if ($lot === '' || mb_strlen($lot) > 80) {
            throw new ValidationException(['lot_code' => 'Numéro de lot invalide.']);
        }
        if ($quantity < 1 || $quantity > 100000) {
            throw new ValidationException(['quantity' => 'Quantité invalide.']);
        }
        if ($this->batches->findByLot($tenantId, $productId, $lot) !== null) {
            throw new ValidationException(['lot_code' => 'Ce lot existe déjà pour ce produit.']);
        }

        $this->pdo->beginTransaction();
        try {
            $batchId = $this->batches->insert($tenantId, $productId, $lot, $quantity, $expires);
            $this->movements->insert(
                $tenantId,
                $productId,
                $batchId,
                MovementType::IN,
                $quantity,
                'Nouveau lot',
                $actor->id(),
            );
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $batchId;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function recordMovement(User $actor, array $input): int
    {
        $this->assertGarde($actor, Permission::GARDE_STOCK);
        $tenantId = $this->tenantId($actor);
        $type = (string) ($input['type'] ?? '');
        $batchId = (int) ($input['batch_id'] ?? 0);
        $quantity = (int) ($input['quantity'] ?? 0);
        $reason = trim((string) ($input['reason'] ?? ''));
        if (!in_array($type, MovementType::ALL, true)) {
            throw new ValidationException(['type' => 'Type de mouvement invalide.']);
        }
        if ($quantity < 0 || $quantity > 100000) {
            throw new ValidationException(['quantity' => 'Quantité invalide.']);
        }
        if ($type !== MovementType::ADJUST && $quantity < 1) {
            throw new ValidationException(['quantity' => 'Quantité invalide.']);
        }
        if (mb_strlen($reason) > 180) {
            throw new ValidationException(['reason' => 'Motif trop long.']);
        }
        if ($type === MovementType::ADJUST && $reason === '') {
            throw new ValidationException(['reason' => 'Un motif est obligatoire pour un ajustement.']);
        }
        $batch = $this->batches->findById($batchId, $tenantId);
        if ($batch === null) {
            throw new ValidationException(['batch_id' => 'Lot introuvable.']);
        }

        $newQty = match ($type) {
            MovementType::IN => $batch->quantity() + $quantity,
            MovementType::OUT => $batch->quantity() - $quantity,
            MovementType::ADJUST => $quantity,
            default => $batch->quantity(),
        };
        if ($newQty < 0) {
            throw new ValidationException(['quantity' => 'Stock insuffisant sur ce lot.']);
        }

        $this->pdo->beginTransaction();
        try {
            $this->batches->setQuantity($batch->id(), $tenantId, $newQty);
            $id = $this->movements->insert(
                $tenantId,
                $batch->productId(),
                $batch->id(),
                $type,
                $type === MovementType::ADJUST ? $quantity : $quantity,
                $reason,
                $actor->id(),
            );
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $id;
    }

    /**
     * @return array{
     *     movements: list<\Sesam\Models\GardeMovement>,
     *     total: int,
     *     page: int,
     *     pages: int,
     *     batches: list<GardeBatch>
     * }
     */
    public function paginateMovements(User $actor, int $page): array
    {
        $this->assertGarde($actor, Permission::GARDE_VIEW);
        $tenantId = $this->tenantId($actor);
        $page = max(1, $page);
        $total = $this->movements->countForTenant($tenantId);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        return [
            'movements' => $this->movements->listForTenant($tenantId, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'batches' => $this->batches->listWithStock($tenantId),
        ];
    }

    /**
     * @return list<GardeBatch>
     */
    public function listAlerts(User $actor, string $filter): array
    {
        $this->assertGarde($actor, Permission::GARDE_VIEW);
        $batches = $this->batches->listWithStock($this->tenantId($actor));
        if ($filter === '' || $filter === 'all') {
            return $batches;
        }
        $wanted = match ($filter) {
            ExpiryStatus::EXPIRED, ExpiryStatus::SOON, ExpiryStatus::OK => $filter,
            default => '',
        };
        if ($wanted === '') {
            return $batches;
        }

        return array_values(array_filter(
            $batches,
            static fn (GardeBatch $batch): bool => $batch->expiryStatus() === $wanted
        ));
    }

    /**
     * @return array{shifts_today: int, expiry_alerts: int, stock_moves: int, staff_on_duty: int}
     */
    public function dashboardCounts(int $tenantId): array
    {
        $today = gmdate('Y-m-d');
        $soon = gmdate('Y-m-d', strtotime('+' . ExpiryStatus::SOON_DAYS . ' days') ?: time());

        return [
            'shifts_today' => $this->shifts->countOnDate($tenantId, $today),
            'expiry_alerts' => $this->batches->countAlerts($tenantId, $today, $soon),
            'stock_moves' => $this->movements->countSince($tenantId, $today . ' 00:00:00'),
            'staff_on_duty' => $this->shifts->countDistinctStaffOnDate($tenantId, $today),
        ];
    }

    /**
     * @return list<GardeShift>
     */
    public function upcomingShifts(int $tenantId, int $limit): array
    {
        $from = gmdate('Y-m-d');
        $to = gmdate('Y-m-d', strtotime('+45 days') ?: time());
        $items = $this->shifts->listBetween($tenantId, $from, $to);

        return array_slice($items, 0, $limit);
    }

    /**
     * @return list<GardeBatch>
     */
    public function watchedLots(int $tenantId, int $limit): array
    {
        $items = $this->batches->listWithStock($tenantId);
        usort($items, static function (GardeBatch $a, GardeBatch $b): int {
            $rank = [
                ExpiryStatus::EXPIRED => 0,
                ExpiryStatus::SOON => 1,
                ExpiryStatus::OK => 2,
            ];

            return ($rank[$a->expiryStatus()] ?? 3) <=> ($rank[$b->expiryStatus()] ?? 3)
                ?: $a->expiresOn() <=> $b->expiresOn();
        });

        return array_slice($items, 0, $limit);
    }

    /**
     * @return list<string>
     */
    public function dutyDates(int $tenantId, string $from, string $to): array
    {
        return $this->shifts->datesInRange($tenantId, $from, $to);
    }

    /**
     * @return list<\Sesam\Models\GardeMovement>
     */
    public function recentMovements(int $tenantId, int $limit): array
    {
        return $this->movements->listForTenant($tenantId, $limit, 0);
    }

    /**
     * @param mixed $file
     * @return array<string, mixed>|null
     */
    private function optionalImage(mixed $file): ?array
    {
        if (!is_array($file)) {
            return null;
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    private function parseDate(string $value, string $field): string
    {
        if ($value === '') {
            throw new ValidationException([$field => 'Date requise.']);
        }
        $timestamp = strtotime($value . ' UTC');
        if ($timestamp === false) {
            throw new ValidationException([$field => 'Date invalide.']);
        }

        return gmdate('Y-m-d', $timestamp);
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

    private function assertGarde(User $actor, string $permission): void
    {
        if (!$actor->can($permission)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
        $tenant = $this->context->tenant();
        if (!$tenant instanceof Tenant || $tenant->sector() !== Sector::GARDE) {
            throw new HttpException(403, 'Ce module est réservé aux commerces secteur Garde.');
        }
    }
}
