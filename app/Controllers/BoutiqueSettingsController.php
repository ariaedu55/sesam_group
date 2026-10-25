<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Core\Auth\Permission;
use Sesam\Core\Boutique\WhatsApp;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\Tenant;
use Sesam\Models\User;
use Sesam\Services\AuthService;
use Sesam\Services\Boutique\BoutiqueService;
use Sesam\Services\NotificationService;

final class BoutiqueSettingsController extends Controller
{
    use RendersAppArea;

    private const FIELDS = [
        'display_name', 'whatsapp_phone', 'tagline', 'description', 'address', 'opening_hours', 'delivery_info',
        'instagram_url', 'facebook_url', 'tiktok_url', 'theme',
    ];

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantContext $context,
        private readonly BoutiqueService $boutique,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($views);
    }

    public function form(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::BOUTIQUE_VIEW);
        $settings = $this->boutique->settingsFor($actor);
        $tenant = $this->context->tenant();

        return $this->view($actor, [
            'display_name' => $tenant instanceof Tenant ? $tenant->displayName() : '',
            'whatsapp_phone' => WhatsApp::display($settings->whatsappPhone()),
            'tagline' => $settings->tagline(),
            'description' => $settings->description(),
            'address' => $settings->address(),
            'opening_hours' => $settings->openingHours(),
            'delivery_info' => $settings->deliveryInfo(),
            'instagram_url' => $settings->instagramUrl(),
            'facebook_url' => $settings->facebookUrl(),
            'tiktok_url' => $settings->tiktokUrl(),
            'theme' => $settings->theme(),
            'is_open' => $settings->isOpen() ? '1' : '',
            'show_stock' => $settings->showStock() ? '1' : '',
        ]);
    }

    public function update(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::BOUTIQUE_SETTINGS);
        $input = [];
        foreach (self::FIELDS as $field) {
            $input[$field] = (string) $request->input($field, '');
        }
        foreach (['is_open', 'show_stock', 'remove_logo', 'remove_cover'] as $flag) {
            $input[$flag] = $request->input($flag) !== null ? '1' : '';
        }
        try {
            $this->boutique->saveSettings($actor, $input, $request->file('logo'), $request->file('cover'));
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            if (isset($errors['image'])) {
                $errors['logo'] = $errors['image'];
                unset($errors['image']);
            }

            return $this->view($actor, $input, $errors, 422);
        }
        $this->session->put('status', 'Votre boutique est à jour.');

        return Response::redirect('/boutique/parametres');
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function view(User $actor, array $old, array $errors = [], int $status = 200): Response
    {
        return $this->renderAppArea('tenant/boutique/parametres', [
            'title' => 'Ma boutique',
            'navTitle' => 'Ma boutique',
            'current' => 'boutique-parametres',
            'settings' => $this->boutique->settingsFor($actor),
            'publicUrl' => $this->boutique->publicUrlFor($actor),
            'old' => $old,
            'errors' => $errors,
            'canUpdate' => $actor->can(Permission::BOUTIQUE_SETTINGS),
        ], $actor, $status);
    }
}
