<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Auth\Role;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Logger\Logger;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Core\Validation\Validator;
use Sesam\Models\Tenant;
use Sesam\Models\User;
use Sesam\Repositories\RoleRepository;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\UserRepository;

final class TenantService extends Service
{
    private readonly TenantRepository $tenants;
    private readonly UserRepository $users;
    private readonly RoleRepository $roles;
    private readonly \PDO $pdo;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        array $database,
        private readonly TenantContext $context,
        private readonly ?Logger $logger = null,
        private readonly Validator $validator = new Validator(),
    ) {
        $this->pdo = Connection::make($database);
        $this->tenants = new TenantRepository($this->pdo);
        $this->users = new UserRepository($this->pdo);
        $this->roles = new RoleRepository($this->pdo);
    }

    /**
     * @return list<Tenant>
     */
    public function list(User $actor): array
    {
        $this->assertCan($actor, Permission::TENANTS_VIEW);

        return $this->tenants->all();
    }

    public function find(User $actor, int $id): Tenant
    {
        $this->assertCan($actor, Permission::TENANTS_VIEW);
        $tenant = $this->tenants->findById($id);
        if ($tenant === null) {
            throw new HttpException(404, 'Commerce introuvable.');
        }

        return $tenant;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(User $actor, array $input): int
    {
        $this->assertCan($actor, Permission::TENANTS_CREATE);
        $data = $this->validated($input);
        $owner = $this->validatedOwner($input);
        $this->assertUniqueSlug($data['slug']);
        if ($this->users->findByEmail($owner['email']) !== null) {
            throw new ValidationException(['owner_email' => 'Cet e-mail est déjà utilisé.']);
        }

        $this->pdo->beginTransaction();
        try {
            $id = $this->tenants->insert($data);
            $userId = $this->users->insert([
                'tenant_id' => $id,
                'full_name' => $owner['full_name'],
                'email' => $owner['email'],
                'password_hash' => password_hash($owner['password'], PASSWORD_DEFAULT),
                'is_active' => 1,
            ]);
            $this->roles->replaceUserRoles($userId, $this->roles->idsForSlugs([Role::TENANT_OWNER]));
            $phone = trim((string) ($input['owner_phone'] ?? ''));
            if ($phone !== '') {
                $this->pdo->prepare('UPDATE users SET phone = ? WHERE id = ?')->execute([mb_substr($phone, 0, 40), $userId]);
                $this->pdo->prepare('UPDATE tenants SET phone = ? WHERE id = ?')->execute([mb_substr($phone, 0, 40), $id]);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(User $actor, int $id, array $input): void
    {
        $this->assertCan($actor, Permission::TENANTS_UPDATE);
        $current = $this->tenants->findById($id);
        if ($current === null) {
            throw new HttpException(404, 'Commerce introuvable.');
        }
        $data = $this->validated($input);
        $this->assertUniqueSlug($data['slug'], $id);
        $this->tenants->update($id, $data);
    }

    public function current(User $actor): Tenant
    {
        $this->assertCan($actor, Permission::SETTINGS_VIEW);
        $id = $this->requireOwnTenant($actor);
        $tenant = $this->tenants->findById($id);
        if ($tenant === null) {
            throw new HttpException(404, 'Commerce introuvable.');
        }

        return $tenant;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function updateDisplayName(User $actor, array $input): void
    {
        $this->assertCan($actor, Permission::SETTINGS_UPDATE);
        $id = $this->requireOwnTenant($actor);
        unset($input['tenant_id']);
        $data = $this->validator->validate($input, [
            'display_name' => 'required|string|max:120',
        ]);
        $this->tenants->updateDisplayName($id, (string) $data['display_name']);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{slug: string, name: string, sector: string, status: string, display_name: string}
     */
    private function validated(array $input): array
    {
        unset($input['tenant_id']);
        $data = $this->validator->validate($input, [
            'name' => 'required|string|max:120',
            'slug' => 'required|string|max:80',
            'sector' => 'required|string|max:40',
            'status' => 'required|string|max:20',
            'display_name' => 'required|string|max:120',
        ]);
        $slug = strtolower((string) $data['slug']);
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw new ValidationException([
                'slug' => 'Le slug ne peut contenir que des lettres minuscules, des chiffres et des tirets.',
            ]);
        }
        $sector = (string) $data['sector'];
        if (!in_array($sector, Sector::ALL, true)) {
            throw new ValidationException(['sector' => 'Choisissez un secteur valide.']);
        }
        $status = (string) $data['status'];
        if (!in_array($status, ['active', 'suspended'], true)) {
            throw new ValidationException(['status' => 'Choisissez un état valide.']);
        }

        return [
            'slug' => $slug,
            'name' => (string) $data['name'],
            'sector' => $sector,
            'status' => $status,
            'display_name' => (string) $data['display_name'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{full_name: string, email: string, password: string}
     */
    private function validatedOwner(array $input): array
    {
        $data = $this->validator->validate($input, [
            'owner_full_name' => 'required|string|max:120',
            'owner_email' => 'required|email|max:190',
            'owner_password' => 'required|string|min:12|max:255',
        ]);

        return [
            'full_name' => (string) $data['owner_full_name'],
            'email' => strtolower((string) $data['owner_email']),
            'password' => (string) $data['owner_password'],
        ];
    }

    private function assertUniqueSlug(string $slug, ?int $exceptId = null): void
    {
        $existing = $this->tenants->findBySlug($slug);
        if ($existing !== null && $existing->id() !== $exceptId) {
            throw new ValidationException(['slug' => 'Ce slug est déjà utilisé.']);
        }
    }

    private function requireOwnTenant(User $actor): int
    {
        $id = $this->context->requireId();
        if ($actor->tenantId() !== $id) {
            $this->logger?->info('tenant isolation user=' . $actor->id() . ' settings mismatch');
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }

        return $id;
    }

    private function assertCan(User $actor, string $permission): void
    {
        if (!$actor->can($permission)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
    }
}
