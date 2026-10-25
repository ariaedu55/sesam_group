<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Core\Push\NotificationCatalog;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Helpers\Html;
use Sesam\Models\User;
use Sesam\Services\AuthService;
use Sesam\Services\NotificationService;
use Sesam\Services\Notifier;

/**
 * Notifications push : service worker Firebase, enregistrement des appareils, préférences par catégorie, test.
 * Commun aux espaces des commerces et à la console SESAM.
 */
final class PushController extends Controller
{
    use RendersAppArea;

    /**
     * @param array<string, mixed> $firebase config/firebase.php
     */
    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantContext $context,
        private readonly NotificationService $notifications,
        private readonly Notifier $notifier,
        private readonly array $firebase,
    ) {
        parent::__construct($views);
    }

    /**
     * Service worker servi depuis la racine de l'application pour couvrir toutes ses pages.
     */
    public function serviceWorker(Request $request): Response
    {
        $vendor = Html::asset('assets/vendor/firebase-10.12.2/firebase-app-compat.js');
        $messaging = Html::asset('assets/vendor/firebase-10.12.2/firebase-messaging-compat.js');
        $icon = Html::asset('assets/images/sesam-logo-360.jpg');
        $script = (string) file_get_contents(dirname(__DIR__) . '/Views/push/service-worker.js');
        $script = strtr($script, [
            '__FIREBASE_APP__' => html_entity_decode($vendor),
            '__FIREBASE_MESSAGING__' => html_entity_decode($messaging),
            '__FIREBASE_CONFIG__' => (string) json_encode($this->firebase['web'] ?? [], JSON_UNESCAPED_SLASHES),
            '__ICON__' => html_entity_decode($icon),
        ]);
        $base = rtrim(html_entity_decode(Html::url('/')), '/') . '/';

        return new Response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => $base,
        ]);
    }

    public function register(Request $request): Response
    {
        $user = $this->auth->requireUser();
        try {
            $this->notifier->registerDevice($user->id(), $user->tenantId(), (string) $request->input('token', ''), $this->label((string) $request->header('User-Agent')));
        } catch (\InvalidArgumentException $exception) {
            return Response::json((string) json_encode(['ok' => false, 'error' => $exception->getMessage()]), 422);
        }

        return Response::json((string) json_encode(['ok' => true]));
    }

    public function unregister(Request $request): Response
    {
        $user = $this->auth->requireUser();
        $this->notifier->forgetToken($user->id(), (string) $request->input('token', ''));

        return Response::json((string) json_encode(['ok' => true]));
    }

    public function removeDevice(Request $request): Response
    {
        $user = $this->auth->requireUser();
        $this->notifier->forgetDevice($user->id(), (int) $request->attribute('id', '0'));
        $this->session->put('status', 'Appareil retiré : il ne recevra plus les notifications.');

        return Response::redirect('/notifications/reglages');
    }

    public function settings(Request $request): Response
    {
        $user = $this->auth->requireUser();

        return $this->renderAppArea('app/notifications/settings', [
            'title' => 'Réglages des notifications',
            'navTitle' => 'Notifications',
            'current' => 'notifications',
            'categories' => $this->categories($user),
            'preferences' => $this->notifier->preferencesOf($user->id()),
            'devices' => $this->notifier->devices($user->id()),
            'live' => $this->notifier->isLive(),
        ], $user);
    }

    public function savePreferences(Request $request): Response
    {
        $user = $this->auth->requireUser();
        $choices = [];
        foreach (array_keys($this->categories($user)) as $category) {
            $field = str_replace('.', '_', $category);
            $choices[$category] = [
                'in_app' => (string) $request->input('in_app_' . $field, '') === '1',
                'push' => (string) $request->input('push_' . $field, '') === '1',
            ];
        }
        $this->notifier->savePreferences($user->id(), $choices);
        $this->session->put('status', 'Préférences de notification enregistrées.');

        return Response::redirect('/notifications/reglages');
    }

    public function test(Request $request): Response
    {
        $user = $this->auth->requireUser();
        $result = $this->notifier->test($user->id(), $user->tenantId());
        if ($result['devices'] === 0) {
            $message = 'Notification de test ajoutée dans la cloche. Aucun appareil n\'est encore activé : cliquez sur « Activer sur cet appareil ».';
        } elseif ($result['errors'] !== []) {
            $message = 'Test envoyé à ' . $result['sent'] . ' appareil(s) sur ' . $result['devices'] . '. Erreur : ' . $result['errors'][0];
        } else {
            $message = 'Notification de test envoyée à ' . $result['devices'] . ' appareil(s).';
        }
        $this->session->put('status', $message);

        return Response::redirect('/notifications/reglages');
    }

    public function markAllRead(Request $request): Response
    {
        $user = $this->auth->requireUser();
        $this->notifications->markAllRead($user);

        return Response::redirect('/notifications');
    }

    /**
     * @return array<string, array{label: string, hint: string, sector: ?string, permission: string, staff: bool}>
     */
    private function categories(User $user): array
    {
        return NotificationCatalog::forUser($user->tenantId() === null, $this->context->tenant()?->sector(), $user->permissions());
    }

    private function label(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Navigateur',
        };
        $system = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iPhone / iPad',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => '',
        };

        return trim($browser . ($system !== '' ? ' · ' . $system : ''));
    }
}
