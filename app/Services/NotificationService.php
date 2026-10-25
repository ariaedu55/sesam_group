<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Models\Notification;
use Sesam\Models\User;
use Sesam\Repositories\NotificationRepository;

/**
 * Cloche des notifications : un compte de commerce voit celles de son commerce, le personnel SESAM les siennes.
 */
final class NotificationService extends Service
{
    private readonly NotificationRepository $notifications;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        array $database,
        private readonly TenantContext $context,
    ) {
        $this->notifications = new NotificationRepository(Connection::make($database));
    }

    /**
     * @return list<Notification>
     */
    public function list(User $actor, int $limit = 50): array
    {
        return $this->notifications->forUser($this->scope($actor), $actor->id(), $limit);
    }

    public function unreadCount(User $actor): int
    {
        if ($actor->tenantId() !== null && $this->context->id() !== $actor->tenantId()) {
            return 0;
        }

        return $this->notifications->countUnread($actor->tenantId(), $actor->id());
    }

    /**
     * Marque la notification comme lue et renvoie son lien (s'il y en a un).
     */
    public function markRead(User $actor, int $id): ?string
    {
        $tenantId = $this->scope($actor);
        $item = $this->notifications->findForUser($id, $tenantId, $actor->id());
        if ($item === null) {
            throw new HttpException(404, 'Notification introuvable.');
        }
        $this->notifications->markRead($id, $tenantId, $actor->id());

        return $item->link();
    }

    public function markAllRead(User $actor): void
    {
        $this->notifications->markAllRead($this->scope($actor), $actor->id());
    }

    private function scope(User $actor): ?int
    {
        $tenantId = $actor->tenantId();
        if ($tenantId !== null && $this->context->id() !== $tenantId) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }

        return $tenantId;
    }
}
