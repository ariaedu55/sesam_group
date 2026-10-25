<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Controllers\Concerns\RequiresGardeSector;
use Sesam\Core\Auth\Permission;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Services\AuthService;
use Sesam\Services\Garde\DutyService;
use Sesam\Services\NotificationService;

final class GardeStaffController extends Controller
{
    use RendersAppArea;
    use RequiresGardeSector;

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantContext $context,
        private readonly DutyService $garde,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($views);
    }

    public function index(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_VIEW);
        $this->assertGardeSector($this->context);

        return $this->renderAppArea('tenant/garde/personnel/index', [
            'title' => 'Personnel de garde',
            'navTitle' => 'Personnel',
            'current' => 'garde-personnel',
            'staff' => $this->garde->listStaff($actor),
            'canManage' => $actor->can(Permission::GARDE_STAFF),
        ], $actor);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_STAFF);
        $this->assertGardeSector($this->context);

        return $this->renderAppArea('tenant/garde/personnel/form', [
            'title' => 'Ajouter du personnel',
            'navTitle' => 'Nouveau personnel',
            'current' => 'garde-personnel',
            'old' => ['name' => '', 'role_label' => ''],
            'errors' => [],
        ], $actor);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_STAFF);
        $this->assertGardeSector($this->context);
        try {
            $this->garde->createStaff($actor, [
                'name' => $request->input('name'),
                'role_label' => $request->input('role_label'),
            ]);
        } catch (ValidationException $exception) {
            return $this->renderAppArea('tenant/garde/personnel/form', [
                'title' => 'Ajouter du personnel',
                'navTitle' => 'Nouveau personnel',
                'current' => 'garde-personnel',
                'old' => [
                    'name' => (string) $request->input('name', ''),
                    'role_label' => (string) $request->input('role_label', ''),
                ],
                'errors' => $exception->errors(),
            ], $actor);
        }
        $this->session->put('status', 'Personne ajoutée à l\'équipe de garde.');

        return Response::redirect('/garde/personnel');
    }
}
