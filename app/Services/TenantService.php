<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Auth\Role;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Logger\Logger;
use Sesam\Core\Money\Country;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\Slug;
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
    private const MAX_SLUG_LENGTH = 80;

    private readonly TenantRepository $tenants;
    private readonly UserRepository $users;
    private readonly RoleRepository $roles;
    private readonly \Sesam\Repositories\TenantAccessRepository $access;
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
        $this->access = new \Sesam\Repositories\TenantAccessRepository($this->pdo);
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

    /** Fin de lien public ajoutée quand un client prend plusieurs activités (ex. « awa-immo », « awa-reservation »). */
    private const SLUG_SUFFIX = [
        Sector::RESERVATION => 'reservation',
        Sector::BOUTIQUE => 'boutique',
        Sector::LOYER => 'immo',
    ];

    /**
     * @param array<string, mixed> $input
     */
    public function create(User $actor, array $input): int
    {
        return $this->createWithActivities($actor, $input)['ids'][0];
    }

    /**
     * Crée un commerce pour chaque activité cochée (Immo, Réservation, Boutique), toutes ouvertes avec le même compte.
     * Si l'e-mail appartient déjà à un compte commerce, les nouvelles activités sont ajoutées à ce compte.
     *
     * @param array<string, mixed> $input
     * @return array{ids: list<int>, linked: bool}
     */
    public function createWithActivities(User $actor, array $input): array
    {
        $this->assertCan($actor, Permission::TENANTS_CREATE);
        $sectors = $this->selectedSectors($input);
        $data = $this->validated(['sector' => $sectors[0]] + $input);
        $email = strtolower(trim((string) ($input['owner_email'] ?? '')));
        $existing = filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $this->users->findByEmail($email) : null;
        if ($existing !== null && $existing->tenantId() === null) {
            throw new ValidationException(['owner_email' => 'Cet e-mail appartient à un compte de l\'équipe SESAM. Utilisez l\'e-mail du client.']);
        }
        if ($existing !== null && !$existing->hasRole(Role::TENANT_OWNER)) {
            throw new ValidationException(['owner_email' => 'Cet e-mail appartient à un membre d\'équipe. Utilisez le compte responsable du client.']);
        }
        $owner = $existing === null ? $this->validatedOwner($input) : null;
        $country = $this->validatedCountry($input);

        $rows = [];
        foreach ($sectors as $sector) {
            $slug = count($sectors) > 1 ? $this->activitySlug($data['slug'], '-' . self::SLUG_SUFFIX[$sector]) : $data['slug'];
            $this->assertUniqueSlug($slug);
            $rows[] = ['slug' => $slug, 'sector' => $sector] + $data;
        }

        $this->pdo->beginTransaction();
        try {
            $ids = [];
            foreach ($rows as $row) {
                $id = $this->tenants->insert($row);
                // Le pays décide de la devise de l'abonnement (FCFA, dollar ou euro).
                $this->tenants->updateCountry($id, $country);
                $ids[] = $id;
            }
            $phone = mb_substr(trim((string) ($input['owner_phone'] ?? '')), 0, 40);
            if ($existing !== null) {
                $userId = $existing->id();
                foreach ($ids as $id) {
                    $this->access->grant($userId, $id);
                }
            } else {
                $userId = $this->users->insert([
                    'tenant_id' => $ids[0],
                    'full_name' => $owner['full_name'],
                    'email' => $owner['email'],
                    'password_hash' => password_hash($owner['password'], PASSWORD_DEFAULT),
                    'is_active' => 1,
                ]);
                $this->roles->replaceUserRoles($userId, $this->roles->idsForSlugs([Role::TENANT_OWNER]));
                foreach (array_slice($ids, 1) as $id) {
                    $this->access->grant($userId, $id);
                }
                if ($phone !== '') {
                    $this->pdo->prepare('UPDATE users SET phone = ? WHERE id = ?')->execute([$phone, $userId]);
                }
            }
            if ($phone !== '') {
                foreach ($ids as $id) {
                    $this->pdo->prepare('UPDATE tenants SET phone = ? WHERE id = ?')->execute([$phone, $id]);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
        $this->logger?->info('tenant create sectors=' . implode(',', $sectors) . ' ids=' . implode(',', $ids) . ' user=' . $userId . ($existing !== null ? ' linked' : '') . ' actor=' . $actor->id());

        return ['ids' => $ids, 'linked' => $existing !== null];
    }

    /**
     * Ajoute une activité au client d'un commerce existant : même compte, même nom, nouveau lien public.
     */
    public function addActivity(User $actor, int $tenantId, string $sector): int
    {
        $this->assertCan($actor, Permission::TENANTS_CREATE);
        $source = $this->tenants->findById($tenantId);
        if ($source === null) {
            throw new HttpException(404, 'Commerce introuvable.');
        }
        if (!in_array($sector, Sector::ALL, true)) {
            throw new ValidationException(['sector' => 'Choisissez une activité.']);
        }
        $ownerId = $this->access->ownerIdOf($tenantId);
        if ($ownerId === null) {
            throw new ValidationException(['sector' => 'Ce commerce n\'a pas de responsable : impossible de lui ajouter une activité.']);
        }
        $owner = $this->users->findById($ownerId);
        foreach ($this->access->tenantIdsFor($ownerId, $owner?->tenantId()) as $id) {
            if ($this->tenants->findById($id)?->sector() === $sector) {
                throw new ValidationException(['sector' => 'Ce client a déjà Sesam ' . Sector::label($sector) . '.']);
            }
        }

        $base = (string) preg_replace('/-(' . implode('|', self::SLUG_SUFFIX) . ')$/', '', $source->slug());
        $suffix = '-' . self::SLUG_SUFFIX[$sector];
        $slug = $this->activitySlug($base, $suffix);
        for ($n = 2; !$this->tenants->slugAvailable($slug); $n++) {
            $slug = $this->activitySlug($base, $suffix . '-' . $n);
        }
        $this->pdo->beginTransaction();
        try {
            $id = $this->tenants->insert([
                'slug' => $slug,
                'name' => $source->name(),
                'sector' => $sector,
                'status' => 'active',
                'display_name' => $source->displayName(),
            ]);
            if ($source->phone() !== '') {
                $this->pdo->prepare('UPDATE tenants SET phone = ? WHERE id = ?')->execute([$source->phone(), $id]);
            }
            $this->tenants->updateCountry($id, $source->country());
            $this->access->grant($ownerId, $id);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
        $this->logger?->info('tenant add activity source=' . $tenantId . ' new=' . $id . ' sector=' . $sector . ' user=' . $ownerId . ' actor=' . $actor->id());

        return $id;
    }

    /**
     * Activités ouvertes par le même compte que ce commerce (lui compris), pour la fiche commerce de la console.
     *
     * @return array{owner: ?User, tenants: list<Tenant>}
     */
    public function activitiesOf(int $tenantId): array
    {
        $ownerId = $this->access->ownerIdOf($tenantId);
        $owner = $ownerId !== null ? $this->users->findById($ownerId) : null;
        if ($owner === null) {
            return ['owner' => null, 'tenants' => []];
        }
        $tenants = [];
        foreach ($this->access->tenantIdsFor($owner->id(), $owner->tenantId()) as $id) {
            $tenant = $this->tenants->findById($id);
            if ($tenant !== null) {
                $tenants[] = $tenant;
            }
        }

        return ['owner' => $owner, 'tenants' => $tenants];
    }

    private function activitySlug(string $base, string $suffix): string
    {
        return rtrim(substr($base, 0, self::MAX_SLUG_LENGTH - strlen($suffix)), '-') . $suffix;
    }

    /**
     * Activités cochées dans le formulaire (au moins une), dans l'ordre Réservation, Boutique, Immo.
     *
     * @param array<string, mixed> $input
     * @return non-empty-list<string>
     */
    private function selectedSectors(array $input): array
    {
        $raw = $input['sectors'] ?? null;
        $picked = is_array($raw) ? array_map('strval', $raw) : [(string) ($input['sector'] ?? '')];
        $sectors = array_values(array_filter(Sector::ALL, static fn (string $sector): bool => in_array($sector, $picked, true)));
        if ($sectors === []) {
            throw new ValidationException(['sector' => 'Cochez au moins une activité.']);
        }

        return $sectors;
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
        $country = $this->validatedCountry($input, $current->country());
        $this->tenants->update($id, $data);
        $this->tenants->updateCountry($id, $country);
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
     * Le responsable choisit le lien public de son commerce (/t/…). L'ancien lien continue de rediriger.
     * Renvoie le nouveau lien (inchangé s'il est identique).
     */
    public function changePublicLink(User $actor, string $wanted): string
    {
        $id = $this->requireOwnTenant($actor);
        $tenant = $this->tenants->findById($id);
        if ($tenant === null) {
            throw new HttpException(404, 'Commerce introuvable.');
        }
        $permission = match ($tenant->sector()) {
            Sector::BOUTIQUE => Permission::BOUTIQUE_SETTINGS,
            Sector::RESERVATION => Permission::RESERVATION_SETTINGS,
            Sector::LOYER => Permission::LOYER_SETTINGS,
            default => Permission::SETTINGS_UPDATE,
        };
        $this->assertCan($actor, $permission);
        $slug = Slug::from($wanted);
        if (strlen($slug) < Slug::MIN) {
            throw new ValidationException(['slug' => 'Choisissez un lien d\'au moins 3 lettres ou chiffres, par exemple « boutique-awa ».']);
        }
        if ($slug === $tenant->slug()) {
            return $slug;
        }
        if (!$this->tenants->slugAvailable($slug, $id)) {
            throw new ValidationException(['slug' => 'Ce lien est déjà pris par un autre commerce. Essayez une variante, par exemple « ' . $slug . '-dakar ».']);
        }
        $this->tenants->changeSlug($id, $tenant->slug(), $slug);
        $this->logger?->info('public link tenant=' . $id . ' ' . $tenant->slug() . ' -> ' . $slug . ' actor=' . $actor->id());

        return $slug;
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
            'slug' => 'required|string|max:' . self::MAX_SLUG_LENGTH,
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

    /**
     * @param array<string, mixed> $input
     */
    private function validatedCountry(array $input, string $default = Country::DEFAULT): string
    {
        $value = $input['country'] ?? $default;
        $country = is_string($value) ? strtoupper(trim($value)) : '';
        if (!Country::exists($country)) {
            throw new ValidationException(['country' => 'Choisissez le pays du commerce : il décide de la devise de l\'abonnement.']);
        }

        return $country;
    }

    private function assertUniqueSlug(string $slug, ?int $exceptId = null): void
    {
        if (!$this->tenants->slugAvailable($slug, $exceptId)) {
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
