<?php

declare(strict_types=1);

namespace Sesam\Controllers\Concerns;

use Sesam\Core\Response\Response;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Helpers\StaffLayout;
use Sesam\Helpers\TenantLayout;
use Sesam\Models\User;
use Sesam\Services\NotificationService;

trait RendersAppArea
{
    protected function renderAppArea(string $template, array $data, User $user, int $status = 200): Response
    {
        /** @var Csrf $csrf */
        $csrf = $this->csrf;
        /** @var Session $session */
        $session = $this->session;
        /** @var TenantContext $context */
        $context = $this->context;
        /** @var NotificationService $notifications */
        $notifications = $this->notifications;

        $layout = $user->tenantId() !== null ? 'layouts/tenant' : 'layouts/staff';
        $message = $session->get('status', '');
        $session->forget('status');
        $resetLink = $session->get('reset_link', '');
        $session->forget('reset_link');
        $tenant = $context->tenant();

        return $this->render($template, $data + StaffLayout::flags($user) + TenantLayout::meta($tenant, $user) + [
            'csrf' => $csrf->token(),
            'actor' => $user,
            'tenant' => $tenant,
            'status' => is_string($message) ? $message : '',
            'resetLink' => is_string($resetLink) ? $resetLink : '',
            'notificationUnread' => $notifications->unreadCount($user),
            'subscriptionAccess' => $user->tenantId() !== null ? $context->subscription() : [],
        ], $status, $layout);
    }
}
