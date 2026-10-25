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

final class InscriptionFeeTypeController extends Controller
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

        return $this->renderAppArea('tenant/inscription/frais/index', [
            'title' => 'Types de frais',
            'navTitle' => 'Frais',
            'current' => 'inscription-frais',
            'feeTypes' => $this->inscription->listFeeTypes($actor),
            'canManage' => $actor->can(Permission::INSCRIPTION_FEES),
        ], $actor);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::INSCRIPTION_FEES);
        $this->assertInscriptionSector($this->context);

        return $this->renderAppArea('tenant/inscription/frais/form', [
            'title' => 'Nouveau type de frais',
            'navTitle' => 'Nouveau frais',
            'current' => 'inscription-frais',
            'old' => ['name' => '', 'total_minor' => '', 'installment_count' => '3'],
            'errors' => [],
        ], $actor);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::INSCRIPTION_FEES);
        $this->assertInscriptionSector($this->context);
        $input = [
            'name' => $request->input('name'),
            'total_minor' => $request->input('total_minor'),
            'installment_count' => $request->input('installment_count'),
        ];
        try {
            $this->inscription->createFeeType($actor, $input);
        } catch (ValidationException $exception) {
            return $this->renderAppArea('tenant/inscription/frais/form', [
                'title' => 'Nouveau type de frais',
                'navTitle' => 'Nouveau frais',
                'current' => 'inscription-frais',
                'old' => [
                    'name' => (string) ($input['name'] ?? ''),
                    'total_minor' => (string) ($input['total_minor'] ?? ''),
                    'installment_count' => (string) ($input['installment_count'] ?? ''),
                ],
                'errors' => $exception->errors(),
            ], $actor);
        }
        $this->session->put('status', 'Type de frais créé.');

        return Response::redirect('/inscription/frais');
    }
}
