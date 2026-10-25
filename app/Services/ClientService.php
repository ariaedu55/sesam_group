<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Crm\ClientActivityKind;
use Sesam\Core\Crm\ClientStatus;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Logger\Logger;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Core\Validation\Validator;
use Sesam\Models\Client;
use Sesam\Models\ClientActivity;
use Sesam\Models\ClientNote;
use Sesam\Models\ClientTag;
use Sesam\Models\User;
use Sesam\Repositories\ClientActivityRepository;
use Sesam\Repositories\ClientNoteRepository;
use Sesam\Repositories\ClientRepository;
use Sesam\Repositories\ClientTagRepository;

final class ClientService extends Service
{
    public const PER_PAGE = 20;

    private readonly ClientRepository $clients;
    private readonly ClientNoteRepository $notes;
    private readonly ClientTagRepository $tags;
    private readonly ClientActivityRepository $activities;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        array $database,
        private readonly TenantContext $context,
        private readonly ?Logger $logger = null,
        private readonly Validator $validator = new Validator(),
    ) {
        $pdo = Connection::make($database);
        $this->clients = new ClientRepository($pdo);
        $this->notes = new ClientNoteRepository($pdo);
        $this->tags = new ClientTagRepository($pdo);
        $this->activities = new ClientActivityRepository($pdo);
    }

    /**
     * @return array{
     *     clients: list<Client>,
     *     total: int,
     *     page: int,
     *     pages: int,
     *     query: string,
     *     status: string,
     *     tagId: ?int,
     *     tags: list<ClientTag>
     * }
     */
    public function paginate(User $actor, string $query, ?string $status, ?int $tagId, int $page): array
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::CLIENTS_VIEW);
        $query = trim($query);
        $status = $this->normalizeStatusFilter($status);
        $tagId = $tagId !== null && $tagId > 0 ? $tagId : null;
        $page = max(1, $page);
        $total = $this->clients->countSearch($tenantId, $query, $status, $tagId);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $clients = $this->clients->search($tenantId, $query, $status, $tagId, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        $clients = array_map(fn (Client $client) => $this->withTags($client, $tenantId), $clients);

        return [
            'clients' => $clients,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'query' => $query,
            'status' => $status ?? '',
            'tagId' => $tagId,
            'tags' => $this->tags->allForTenant($tenantId),
        ];
    }

    /**
     * @return array{
     *     client: Client,
     *     notes: list<ClientNote>,
     *     activities: list<ClientActivity>,
     *     allTags: list<ClientTag>
     * }
     */
    public function detail(User $actor, int $id): array
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::CLIENTS_VIEW);
        $client = $this->withTags($this->findOrDeny($tenantId, $id), $tenantId);

        return [
            'client' => $client,
            'notes' => $this->notes->forClient($tenantId, $id),
            'activities' => $this->activities->forClient($tenantId, $id),
            'allTags' => $this->tags->allForTenant($tenantId),
        ];
    }

    public function find(User $actor, int $id): Client
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::CLIENTS_VIEW);

        return $this->findOrDeny($tenantId, $id);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(User $actor, array $input): int
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::CLIENTS_CREATE);
        $data = $this->validated($input, ClientStatus::ACTIVE);
        $id = $this->clients->insert($tenantId, $data);
        $this->activities->insert(
            $tenantId,
            $id,
            $actor->id(),
            ClientActivityKind::CREATED,
            'Fiche client créée.',
        );
        if ($actor->can(Permission::CLIENTS_TAGS)) {
            $this->syncTags($actor, $tenantId, $id, $input, true);
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(User $actor, int $id, array $input): void
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::CLIENTS_UPDATE);
        $client = $this->findOrDeny($tenantId, $id);
        $status = $client->isArchived() ? ClientStatus::ARCHIVED : ClientStatus::ACTIVE;
        $data = $this->validated($input, $status);
        $this->clients->update($id, $tenantId, $data);
        $this->activities->insert(
            $tenantId,
            $id,
            $actor->id(),
            ClientActivityKind::UPDATED,
            'Coordonnées mises à jour.',
        );
        if ($actor->can(Permission::CLIENTS_TAGS)) {
            $this->syncTags($actor, $tenantId, $id, $input, false);
        }
    }

    public function addNote(User $actor, int $clientId, string $body): void
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::CLIENTS_NOTES);
        $this->findOrDeny($tenantId, $clientId);
        $body = trim($body);
        if ($body === '') {
            throw new ValidationException(['body' => 'Saisissez une note.']);
        }
        if (mb_strlen($body) > 5000) {
            throw new ValidationException(['body' => 'La note ne peut pas dépasser 5000 caractères.']);
        }
        $this->notes->insert($tenantId, $clientId, $actor->id(), $body);
        $summary = mb_strlen($body) > 120 ? mb_substr($body, 0, 117) . '…' : $body;
        $this->activities->insert(
            $tenantId,
            $clientId,
            $actor->id(),
            ClientActivityKind::NOTE_ADDED,
            'Note : ' . $summary,
        );
    }

    public function archive(User $actor, int $id): void
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::CLIENTS_ARCHIVE);
        $client = $this->findOrDeny($tenantId, $id);
        if ($client->isArchived()) {
            return;
        }
        $this->clients->update($id, $tenantId, [
            'full_name' => $client->fullName(),
            'email' => $client->email(),
            'phone' => $client->phone(),
            'status' => ClientStatus::ARCHIVED,
        ]);
        $this->activities->insert(
            $tenantId,
            $id,
            $actor->id(),
            ClientActivityKind::ARCHIVED,
            'Client archivé.',
        );
    }

    public function restore(User $actor, int $id): void
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::CLIENTS_ARCHIVE);
        $client = $this->findOrDeny($tenantId, $id);
        if (!$client->isArchived()) {
            return;
        }
        $this->clients->update($id, $tenantId, [
            'full_name' => $client->fullName(),
            'email' => $client->email(),
            'phone' => $client->phone(),
            'status' => ClientStatus::ACTIVE,
        ]);
        $this->activities->insert(
            $tenantId,
            $id,
            $actor->id(),
            ClientActivityKind::RESTORED,
            'Client restauré.',
        );
    }

    /**
     * @return list<Client>
     */
    public function exportRows(User $actor, string $query, ?string $status, ?int $tagId): array
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::CLIENTS_EXPORT);
        $query = trim($query);
        $status = $this->normalizeStatusFilter($status);
        $tagId = $tagId !== null && $tagId > 0 ? $tagId : null;

        return $this->clients->listForExport($tenantId, $query, $status, $tagId);
    }

    /**
     * @return list<ClientActivity>
     */
    public function recentActivities(User $actor, int $limit = 8): array
    {
        $tenantId = $this->requireTenant($actor);
        if (!$actor->can(Permission::CLIENTS_VIEW)) {
            return [];
        }

        return $this->activities->recentForTenant($tenantId, max(1, min(20, $limit)));
    }

    /**
     * @return list<ClientTag>
     */
    public function allTags(User $actor): array
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::CLIENTS_VIEW);

        return $this->tags->allForTenant($tenantId);
    }

    /**
     * @return list<Client>
     */
    public function quickSearch(User $actor, string $query, int $limit = 10): array
    {
        $tenantId = $this->requireTenant($actor);
        if (!$actor->can(Permission::CLIENTS_VIEW)) {
            return [];
        }
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        return $this->clients->search($tenantId, $query, null, null, max(1, min(10, $limit)), 0);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{full_name: string, email: ?string, phone: ?string, status: string}
     */
    private function validated(array $input, string $status): array
    {
        $fullName = trim((string) ($input['full_name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $errors = [];
        if ($fullName === '') {
            $errors['full_name'] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($fullName) > 120) {
            $errors['full_name'] = '120 caractères maximum.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail invalide.';
        } elseif (mb_strlen($email) > 190) {
            $errors['email'] = '190 caractères maximum.';
        }
        if ($phone !== '' && mb_strlen($phone) > 40) {
            $errors['phone'] = '40 caractères maximum.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'full_name' => $fullName,
            'email' => $email === '' ? null : $email,
            'phone' => $phone === '' ? null : $phone,
            'status' => $status,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function syncTags(User $actor, int $tenantId, int $clientId, array $input, bool $isCreate): void
    {
        $existing = $this->tags->forClient($tenantId, $clientId);
        $existingIds = array_map(static fn (ClientTag $tag) => $tag->id(), $existing);
        $selected = $this->parseTagIds($input);
        $newNames = $this->parseNewTagNames($input);
        foreach ($newNames as $name) {
            $tag = $this->tags->findByName($tenantId, $name);
            if ($tag === null) {
                $tagId = $this->tags->insert($tenantId, $name);
                $tag = new ClientTag(['id' => $tagId, 'tenant_id' => $tenantId, 'name' => $name]);
            }
            $selected[] = $tag->id();
        }
        $selected = array_values(array_unique(array_filter($selected, static fn (int $id) => $id > 0)));
        $this->tags->replaceClientTags($tenantId, $clientId, $selected);
        if ($isCreate) {
            foreach ($newNames as $name) {
                $this->activities->insert(
                    $tenantId,
                    $clientId,
                    $actor->id(),
                    ClientActivityKind::TAG_ADDED,
                    'Tag « ' . $name . ' » ajouté.',
                );
            }

            return;
        }
        $removed = array_diff($existingIds, $selected);
        $added = array_diff($selected, $existingIds);
        foreach ($added as $tagId) {
            $tag = $this->findTagName($tenantId, (int) $tagId);
            if ($tag !== '') {
                $this->activities->insert(
                    $tenantId,
                    $clientId,
                    $actor->id(),
                    ClientActivityKind::TAG_ADDED,
                    'Tag « ' . $tag . ' » ajouté.',
                );
            }
        }
        foreach ($removed as $tagId) {
            $tag = $this->findTagName($tenantId, (int) $tagId);
            if ($tag !== '') {
                $this->activities->insert(
                    $tenantId,
                    $clientId,
                    $actor->id(),
                    ClientActivityKind::TAG_REMOVED,
                    'Tag « ' . $tag . ' » retiré.',
                );
            }
        }
    }

    private function findTagName(int $tenantId, int $tagId): string
    {
        foreach ($this->tags->allForTenant($tenantId) as $tag) {
            if ($tag->id() === $tagId) {
                return $tag->name();
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $input
     * @return list<int>
     */
    private function parseTagIds(array $input): array
    {
        $raw = $input['tag_ids'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $value) {
            if (is_numeric($value)) {
                $ids[] = (int) $value;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $input
     * @return list<string>
     */
    private function parseNewTagNames(array $input): array
    {
        $raw = trim((string) ($input['new_tags'] ?? ''));
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[,;]+/', $raw) ?: [];
        $names = [];
        foreach ($parts as $part) {
            $name = trim((string) $part);
            if ($name === '') {
                continue;
            }
            if (mb_strlen($name) > 60) {
                throw new ValidationException(['new_tags' => 'Chaque tag : 60 caractères maximum.']);
            }
            $names[] = $name;
        }

        return array_values(array_unique($names));
    }

    private function withTags(Client $client, int $tenantId): Client
    {
        return $client->with(['tags' => $this->tags->forClient($tenantId, $client->id())]);
    }

    private function findOrDeny(int $tenantId, int $id): Client
    {
        $client = $this->clients->findById($id, $tenantId);
        if ($client === null) {
            $this->deny($tenantId, 'read client=' . $id);
        }

        return $client;
    }

    private function normalizeStatusFilter(?string $status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }
        if (!in_array($status, ClientStatus::all(), true)) {
            return null;
        }

        return $status;
    }

    private function requireTenant(User $actor): int
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

    private function assertCan(User $actor, string $permission): void
    {
        if (!$actor->can($permission)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
    }

    private function deny(int $tenantId, string $reason): never
    {
        $this->logger?->info('tenant isolation tenant=' . $tenantId . ' ' . $reason);
        throw new HttpException(404, 'Client introuvable.');
    }
}
