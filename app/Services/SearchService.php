<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Models\User;
use Sesam\Models\Client;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\UserRepository;

final class SearchService extends Service
{
    private readonly UserRepository $users;
    private readonly TenantRepository $tenants;
    private readonly ClientService $clients;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        array $database,
        private readonly TenantContext $context,
    ) {
        $pdo = Connection::make($database);
        $this->users = new UserRepository($pdo);
        $this->tenants = new TenantRepository($pdo);
        $this->clients = new ClientService($database, $context);
    }

    /**
     * @return array{
     *     query: string,
     *     users: list<User>,
     *     tenants: list<\Sesam\Models\Tenant>,
     *     clients: list<Client>
     * }
     */
    public function search(User $actor, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['query' => '', 'users' => [], 'tenants' => [], 'clients' => []];
        }
        if (mb_strlen($query) > 120) {
            $query = mb_substr($query, 0, 120);
        }

        if ($actor->tenantId() !== null) {
            $tenantId = $this->context->requireId();
            if ($tenantId !== $actor->tenantId()) {
                throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
            }
            $clients = $actor->can(Permission::CLIENTS_VIEW)
                ? $this->clients->quickSearch($actor, $query)
                : [];
            if (!$actor->can(Permission::USERS_VIEW)) {
                return ['query' => $query, 'users' => [], 'tenants' => [], 'clients' => $clients];
            }

            return [
                'query' => $query,
                'users' => $this->users->search($query, 10, 0, $tenantId, false),
                'tenants' => [],
                'clients' => $clients,
            ];
        }

        $users = $actor->can(Permission::USERS_VIEW)
            ? $this->users->search($query, 10, 0, null, true)
            : [];
        $tenants = [];
        if ($actor->can(Permission::TENANTS_VIEW)) {
            foreach ($this->tenants->all() as $tenant) {
                if (
                    str_contains(mb_strtolower($tenant->name()), mb_strtolower($query))
                    || str_contains(mb_strtolower($tenant->displayName()), mb_strtolower($query))
                    || str_contains(mb_strtolower($tenant->slug()), mb_strtolower($query))
                ) {
                    $tenants[] = $tenant;
                }
                if (count($tenants) >= 10) {
                    break;
                }
            }
        }

        return [
            'query' => $query,
            'users' => $users,
            'tenants' => $tenants,
            'clients' => [],
        ];
    }
}
