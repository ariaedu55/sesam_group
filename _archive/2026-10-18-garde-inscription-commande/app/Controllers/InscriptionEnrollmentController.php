<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Controllers\Concerns\RequiresInscriptionSector;
use Sesam\Core\Auth\Permission;
use Sesam\Core\HttpException;
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

final class InscriptionEnrollmentController extends Controller
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
        $page = (int) $request->input('page', 1);

        return $this->renderAppArea('tenant/inscription/dossiers/index', [
            'title' => 'Dossiers d\'inscription',
            'navTitle' => 'Dossiers',
            'current' => 'inscription-dossiers',
            'list' => $this->inscription->paginateEnrollments($actor, $page),
            'canManage' => $actor->can(Permission::INSCRIPTION_ENROLLMENTS),
        ], $actor);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::INSCRIPTION_ENROLLMENTS);
        $this->assertInscriptionSector($this->context);
        $today = gmdate('Y-m-d');

        return $this->renderAppArea('tenant/inscription/dossiers/form', [
            'title' => 'Nouvelle inscription',
            'navTitle' => 'Nouvelle inscription',
            'current' => 'inscription-dossiers',
            'catalog' => $this->inscription->enrollmentFormCatalog($actor),
            'old' => [
                'class_id' => '',
                'student_name' => '',
                'guardian_client_id' => '',
                'first_due_date' => $today,
                'fee_type_ids' => [],
            ],
            'errors' => [],
        ], $actor);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::INSCRIPTION_ENROLLMENTS);
        $this->assertInscriptionSector($this->context);
        /** @var list<string> $feeIds */
        $feeIds = is_array($request->input('fee_type_ids')) ? $request->input('fee_type_ids') : [];
        $input = [
            'class_id' => $request->input('class_id'),
            'student_name' => $request->input('student_name'),
            'guardian_client_id' => $request->input('guardian_client_id'),
            'first_due_date' => $request->input('first_due_date'),
            'fee_type_ids' => $feeIds,
        ];
        try {
            $id = $this->inscription->createEnrollment($actor, $input);
        } catch (ValidationException $exception) {
            return $this->renderAppArea('tenant/inscription/dossiers/form', [
                'title' => 'Nouvelle inscription',
                'navTitle' => 'Nouvelle inscription',
                'current' => 'inscription-dossiers',
                'catalog' => $this->inscription->enrollmentFormCatalog($actor),
                'old' => [
                    'class_id' => (string) ($input['class_id'] ?? ''),
                    'student_name' => (string) ($input['student_name'] ?? ''),
                    'guardian_client_id' => (string) ($input['guardian_client_id'] ?? ''),
                    'first_due_date' => (string) ($input['first_due_date'] ?? ''),
                    'fee_type_ids' => $feeIds,
                ],
                'errors' => $exception->errors(),
            ], $actor);
        }
        $this->session->put('status', 'Inscription enregistrée. Les tranches de frais ont été générées.');

        return Response::redirect('/inscription/dossiers/' . $id);
    }

    public function show(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::INSCRIPTION_VIEW);
        $this->assertInscriptionSector($this->context);
        $id = $this->id($request);
        $detail = $this->inscription->enrollmentDetail($actor, $id);

        return $this->renderAppArea('tenant/inscription/dossiers/show', [
            'title' => 'Dossier — ' . $detail['enrollment']->studentName(),
            'navTitle' => 'Dossier',
            'current' => 'inscription-dossiers',
            'detail' => $detail,
            'canManage' => $actor->can(Permission::INSCRIPTION_ENROLLMENTS),
            'canPay' => $actor->can(Permission::PAYMENTS_CREATE),
        ], $actor);
    }

    public function collect(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::INSCRIPTION_ENROLLMENTS);
        $this->assertInscriptionSector($this->context);
        $enrollmentId = $this->id($request);
        $installmentId = $this->installmentId($request);
        try {
            $url = $this->inscription->initiateInstallmentPayment($actor, $enrollmentId, $installmentId);
        } catch (ValidationException $exception) {
            $this->session->put('status', implode(' ', $exception->errors()));

            return Response::redirect('/inscription/dossiers/' . $enrollmentId);
        }

        return Response::redirect($url);
    }

    private function id(Request $request): int
    {
        $raw = (string) $request->attribute('id', '');
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new HttpException(404, 'Dossier introuvable.');
        }

        return (int) $raw;
    }

    private function installmentId(Request $request): int
    {
        $raw = (string) $request->attribute('installmentId', '');
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new HttpException(404, 'Tranche introuvable.');
        }

        return (int) $raw;
    }
}
