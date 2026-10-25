<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Controllers\Concerns\RequiresInscriptionSector;
use Sesam\Core\Auth\Permission;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Services\AuthService;
use Sesam\Services\Inscription\EnrollmentService;
use Sesam\Services\NotificationService;

final class InscriptionClassController extends Controller
{
    use RendersAppArea;
    use RequiresInscriptionSector;

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantContext $context,
        private readonly EnrollmentService $inscription,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($views);
    }

    public function index(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::INSCRIPTION_VIEW);
        $this->assertInscriptionSector($this->context);

        return $this->renderAppArea('tenant/inscription/classes/index', [
            'title' => 'Classes',
            'navTitle' => 'Classes',
            'current' => 'inscription-classes',
            'classes' => $this->inscription->listClasses($actor),
            'canManage' => $actor->can(Permission::INSCRIPTION_CLASSES),
        ], $actor);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::INSCRIPTION_CLASSES);
        $this->assertInscriptionSector($this->context);

        return $this->renderAppArea('tenant/inscription/classes/form', [
            'title' => 'Nouvelle classe',
            'navTitle' => 'Nouvelle classe',
            'current' => 'inscription-classes',
            'old' => ['name' => ''],
            'errors' => [],
        ], $actor);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::INSCRIPTION_CLASSES);
        $this->assertInscriptionSector($this->context);
        try {
            $this->inscription->createClass($actor, ['name' => $request->input('name')]);
        } catch (ValidationException $exception) {
            return $this->renderAppArea('tenant/inscription/classes/form', [
                'title' => 'Nouvelle classe',
                'navTitle' => 'Nouvelle classe',
                'current' => 'inscription-classes',
                'old' => ['name' => (string) $request->input('name', '')],
                'errors' => $exception->errors(),
            ], $actor);
        }
        $this->session->put('status', 'Classe créée.');

        return Response::redirect('/inscription/classes');
    }
}
