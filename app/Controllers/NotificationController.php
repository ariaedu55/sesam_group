<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Core\Auth\Permission;
use Sesam\Core\HttpException;
use Sesam\Core\Messaging\DeliveryStatus;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Services\AuthService;
use Sesam\Services\MessagingService;
use Sesam\Services\NotificationService;

final class NotificationController extends Controller
{
    use RendersAppArea;

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantContext $context,
        private readonly NotificationService $notifications,
        private readonly MessagingService $messaging,
    ) {
        parent::__construct($views);
    }

    public function index(Request $request): Response
    {
        // Personnel SESAM et commerces : chacun voit ses propres notifications.
        $user = $this->auth->requireUser();

        return $this->renderAppArea('tenant/notifications/index', [
            'title' => 'Notifications — SESAM',
            'navTitle' => 'Notifications',
            'current' => 'notifications',
            'items' => $this->notifications->list($user),
        ], $user);
    }

    public function history(Request $request): Response
    {
        $user = $this->auth->requirePermission(Permission::NOTIFICATIONS_HISTORY);
        $this->requireTenantUserId($user);
        $page = (int) $request->input('page', 1);

        return $this->renderAppArea('tenant/notifications/history', [
            'title' => 'Historique — Notifications',
            'navTitle' => 'Historique',
            'current' => 'notifications-history',
            'list' => $this->messaging->history($user, $page),
            'mockMode' => $this->messaging->isMockMode(),
        ], $user);
    }

    public function sendForm(Request $request): Response
    {
        $user = $this->auth->requirePermission(Permission::NOTIFICATIONS_SEND);
        $this->requireTenantUserId($user);

        return $this->renderAppArea('tenant/notifications/send', [
            'title' => 'Envoyer — Notifications',
            'navTitle' => 'Envoyer',
            'current' => 'notifications-send',
            'old' => [
                'channel' => 'internal',
                'template_slug' => 'test_message',
                'recipient' => '',
                'name' => $user->fullName(),
                'amount' => '',
            ],
            'errors' => [],
            'templates' => $this->messaging->templates($user),
            'mockMode' => $this->messaging->isMockMode(),
            'lastResult' => '',
        ], $user);
    }

    public function send(Request $request): Response
    {
        $user = $this->auth->requirePermission(Permission::NOTIFICATIONS_SEND);
        $this->requireTenantUserId($user);
        $input = [
            'channel' => $request->input('channel'),
            'template_slug' => $request->input('template_slug'),
            'recipient' => $request->input('recipient'),
            'name' => $request->input('name'),
            'amount' => $request->input('amount'),
            'client' => $request->input('name'),
        ];
        try {
            $delivery = $this->messaging->send($user, $input);
        } catch (ValidationException $exception) {
            return $this->renderAppArea('tenant/notifications/send', [
                'title' => 'Envoyer — Notifications',
                'navTitle' => 'Envoyer',
                'current' => 'notifications-send',
                'old' => $input,
                'errors' => $exception->errors(),
                'templates' => $this->messaging->templates($user),
                'mockMode' => $this->messaging->isMockMode(),
                'lastResult' => '',
            ], $user, 422);
        }

        $message = match ($delivery->status()) {
            DeliveryStatus::SENT => 'Message interne publié dans la boîte de réception.',
            DeliveryStatus::MOCK => 'Enregistré en mock (non expédié). Consultez l\'historique.',
            DeliveryStatus::FAILED => 'Échec : ' . ($delivery->errorMessage() ?? 'erreur inconnue'),
            default => 'Enregistré.',
        };
        $this->session->put('status', $message);

        return Response::redirect('/notifications/historique');
    }

    public function markRead(Request $request): Response
    {
        $user = $this->auth->requireUser();
        $link = $this->notifications->markRead($user, $this->id($request));
        // « Ouvrir » : on va directement à l'écran concerné (commande, loyer, rendez-vous…).
        if ((string) $request->input('ouvrir', '') === '1' && $link !== null && str_starts_with($link, '/') && !str_starts_with($link, '//')) {
            return Response::redirect($link);
        }
        $this->session->put('status', 'Notification marquée comme lue.');

        return Response::redirect('/notifications');
    }

    private function requireTenantUser(): \Sesam\Models\User
    {
        $user = $this->auth->requireUser();
        if ($user->tenantId() === null) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }

        return $user;
    }

    private function requireTenantUserId(\Sesam\Models\User $user): void
    {
        if ($user->tenantId() === null) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
    }

    private function id(Request $request): int
    {
        $raw = (string) $request->attribute('id', '');
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new HttpException(404, 'Notification introuvable.');
        }

        return (int) $raw;
    }
}
