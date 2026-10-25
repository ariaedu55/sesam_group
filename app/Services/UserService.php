<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Auth\Role;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Logger\Logger;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Core\Validation\Validator;
use Sesam\Models\User;
use Sesam\Repositories\RoleRepository;
use Sesam\Repositories\UserRepository;

final class UserService extends Service
{
    public const PER_PAGE = 20;

    private readonly UserRepository $users;
    private readonly RoleRepository $roles;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        array $database,
        private readonly TenantContext $context = new TenantContext(),
        private readonly ?Logger $logger = null,
        private readonly Validator $validator = new Validator(),
    ) {
        $pdo = Connection::make($database);
        $this->users = new UserRepository($pdo);
        $this->roles = new RoleRepository($pdo);
    }

    /**
     * @return array{users: list<User>, total: int, page: int, pages: int, query: string}
     */
    public function paginate(User $actor, string $query, int $page): array
    {
        $this->assertCan($actor, Permission::USERS_VIEW);
        [$tenantId, $sesamOnly] = $this->scope($actor);
        $page = max(1, $page);
        $total = $this->users->countSearch($query, $tenantId, $sesamOnly);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $users = $this->users->search($query, self::PER_PAGE, ($page - 1) * self::PER_PAGE, $tenantId, $sesamOnly);

        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'query' => $query,
        ];
    }

    public function find(User $actor, int $id): User
    {
        $this->assertCan($actor, Permission::USERS_VIEW);
        $user = $this->users->findById($id);
        if ($user === null || !$this->visibleTo($actor, $user)) {
            $this->deny($actor, 'read user=' . $id);
        }

        return $user;
    }

    /**
     * @return list<array{id: int, slug: string, name: string}>
     */
    public function catalogRoles(User $actor): array
    {
        $allowed = $this->allowedRoles($actor);
        $roles = [];
        foreach ($this->roles->all() as $role) {
            if (in_array($role['slug'], $allowed, true)) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(User $actor, array $input): int
    {
        $this->assertCan($actor, Permission::USERS_CREATE);
        unset($input['tenant_id']);
        $data = $this->validated($input, true);
        $roles = $this->normalizedRoles($actor, $input['roles'] ?? []);
        if ($this->users->findByEmail($data['email']) !== null) {
            throw new ValidationException(['email' => 'Cet e-mail est déjà utilisé.']);
        }

        $id = $this->users->insert([
            'tenant_id' => $actor->tenantId(),
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'is_active' => $data['is_active'],
        ]);
        if ($actor->can(Permission::USERS_ASSIGN_ROLES)) {
            $this->roles->replaceUserRoles($id, $this->roles->idsForSlugs($roles));
        }
        if ($actor->tenantId() === null) {
            \Sesam\Services\Notifier::staff(
                'sesam.security',
                'Nouveau compte dans l\'équipe : ' . $data['full_name'],
                $data['email'] . ($roles !== [] ? ' · ' . implode(', ', $roles) : '') . ' · créé par ' . $actor->fullName() . '. Si vous ne le connaissez pas, désactivez-le.',
                '/utilisateurs/' . $id,
                'warning',
                null,
                $actor->id(),
            );
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(User $actor, int $id, array $input): void
    {
        $this->assertCan($actor, Permission::USERS_UPDATE);
        unset($input['tenant_id']);
        $current = $this->users->findById($id);
        if ($current === null || !$this->visibleTo($actor, $current)) {
            $this->deny($actor, 'update user=' . $id);
        }
        if ($current->hasRole(Role::SUPER_ADMIN) && !$actor->hasRole(Role::SUPER_ADMIN)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }

        $passwordRequired = isset($input['password']) && is_string($input['password']) && trim($input['password']) !== '';
        if ($passwordRequired) {
            $this->assertCan($actor, Permission::USERS_SET_PASSWORD);
        }

        $data = $this->validated($input, $passwordRequired);
        $existing = $this->users->findByEmail($data['email']);
        if ($existing !== null && $existing->id() !== $id) {
            throw new ValidationException(['email' => 'Cet e-mail est déjà utilisé.']);
        }

        $isActive = $data['is_active'];
        if ($isActive === 0 && $current->isActive()) {
            $this->assertCan($actor, Permission::USERS_DEACTIVATE);
            $this->guardLastSuperAdmin($current, false);
        }

        $payload = [
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'is_active' => $isActive,
        ];
        if ($passwordRequired) {
            $payload['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        $this->users->update($id, $payload);

        if ($actor->can(Permission::USERS_ASSIGN_ROLES) && array_key_exists('roles', $input)) {
            $roles = $this->normalizedRoles($actor, $input['roles'] ?? [], $current);
            $this->guardLastSuperAdminRoles($current, $roles);
            $this->roles->replaceUserRoles($id, $this->roles->idsForSlugs($roles));
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array{full_name: string, email: string, password: string, is_active: int}
     */
    private function validated(array $input, bool $passwordRequired): array
    {
        $rules = [
            'full_name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'is_active' => 'integer',
        ];
        if ($passwordRequired) {
            $rules['password'] = 'required|string|min:12|max:255';
        }
        $data = $this->validator->validate($input, $rules);
        $activeRaw = $input['is_active'] ?? '0';
        $isActive = $activeRaw === '1' || $activeRaw === 1 || $activeRaw === true ? 1 : 0;

        return [
            'full_name' => (string) $data['full_name'],
            'email' => strtolower((string) $data['email']),
            'password' => $passwordRequired ? (string) $data['password'] : '',
            'is_active' => $isActive,
        ];
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private function normalizedRoles(User $actor, mixed $raw, ?User $target = null): array
    {
        $allowed = $this->allowedRoles($actor);
        $submitted = [];
        if (is_array($raw)) {
            foreach ($raw as $slug) {
                if (is_string($slug) && in_array($slug, $allowed, true)) {
                    $submitted[] = $slug;
                }
            }
        }
        $submitted = array_values(array_unique($submitted));
        if ($submitted === []) {
            throw new ValidationException(['roles' => 'Choisissez au moins un rôle.']);
        }

        if (!$actor->hasRole(Role::SUPER_ADMIN)) {
            if (in_array(Role::SUPER_ADMIN, $submitted, true)) {
                throw new ValidationException(['roles' => 'Seul un Super Admin peut attribuer ce rôle.']);
            }
            if ($target !== null && $target->hasRole(Role::SUPER_ADMIN)) {
                throw new ValidationException(['roles' => 'Ce compte Super Admin ne peut pas être modifié ainsi.']);
            }
        }

        return $submitted;
    }

    /**
     * @return list<string>
     */
    private function allowedRoles(User $actor): array
    {
        return $actor->tenantId() === null ? Role::SESAM : Role::TENANT;
    }

    /**
     * @return array{0: ?int, 1: bool}
     */
    private function scope(User $actor): array
    {
        $actorTenant = $actor->tenantId();
        $contextId = $this->context->id();
        if ($actorTenant !== $contextId) {
            $this->deny($actor, 'context mismatch');
        }
        if ($actorTenant !== null) {
            return [$actorTenant, false];
        }

        return [null, true];
    }

    private function visibleTo(User $actor, User $target): bool
    {
        $actorTenant = $actor->tenantId();
        $contextId = $this->context->id();
        if ($actorTenant !== $contextId) {
            return false;
        }
        if ($actorTenant === null) {
            return $target->tenantId() === null;
        }

        return $target->tenantId() === $actorTenant;
    }

    private function deny(User $actor, string $reason): never
    {
        $this->logger?->info('tenant isolation user=' . $actor->id() . ' ' . $reason);
        throw new HttpException(404, 'Utilisateur introuvable.');
    }

    private function guardLastSuperAdmin(User $current, bool $willRemainSuperAdmin): void
    {
        if (!$current->hasRole(Role::SUPER_ADMIN) || !$current->isActive()) {
            return;
        }
        if ($willRemainSuperAdmin) {
            return;
        }
        if ($this->users->countSuperAdmins($current->id()) === 0) {
            throw new ValidationException([
                'is_active' => 'Le dernier Super Admin actif ne peut pas être retiré.',
            ]);
        }
    }

    /**
     * @param list<string> $roles
     */
    private function guardLastSuperAdminRoles(User $current, array $roles): void
    {
        $keeps = in_array(Role::SUPER_ADMIN, $roles, true);
        $this->guardLastSuperAdmin($current, $keeps);
    }

    private function assertCan(User $actor, string $permission): void
    {
        if (!$actor->can($permission)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
    }
}
