<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Controllers\Concerns\RequiresLoyerSector;
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
use Sesam\Services\Loyer\RentService;
use Sesam\Services\NotificationService;

final class LoyerLeaseController extends Controller
{
    use RendersAppArea;
    use RequiresLoyerSector;

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantContext $context,
        private readonly RentService $rent,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($views);
    }

    public function index(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::LOYER_VIEW);
        $this->assertLoyerSector($this->context);
        $page = (int) $request->input('page', 1);

        return $this->renderAppArea('tenant/loyer/contrats/index', [
            'title' => 'Contrats de location',
            'navTitle' => 'Contrats',
            'current' => 'loyer-contrats',
            'list' => $this->rent->paginateLeases($actor, $page),
            'canManage' => $actor->can(Permission::LOYER_LEASES),
        ], $actor);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::LOYER_LEASES);
        $this->assertLoyerSector($this->context);

        return $this->renderAppArea('tenant/loyer/contrats/form', [
            'title' => 'Nouveau contrat',
            'navTitle' => 'Nouveau contrat',
            'current' => 'loyer-contrats',
            'catalog' => $this->rent->leaseFormCatalog($actor),
            'old' => ['client_id' => '', 'unit_id' => '', 'rent_minor' => '', 'due_day' => '5', 'starts_on' => ''],
            'errors' => [],
        ], $actor);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::LOYER_LEASES);
        $this->assertLoyerSector($this->context);
        $input = [
            'client_id' => $request->input('client_id'),
            'unit_id' => $request->input('unit_id'),
            'rent_minor' => $request->input('rent_minor'),
            'due_day' => $request->input('due_day'),
            'starts_on' => $request->input('starts_on'),
        ];
        try {
            $id = $this->rent->createLease($actor, $input);
        } catch (ValidationException $exception) {
            return $this->renderAppArea('tenant/loyer/contrats/form', [
                'title' => 'Nouveau contrat',
                'navTitle' => 'Nouveau contrat',
                'current' => 'loyer-contrats',
                'catalog' => $this->rent->leaseFormCatalog($actor),
                'old' => $input,
                'errors' => $exception->errors(),
            ], $actor, 422);
        }
        $this->session->put('status', 'Contrat créé. Échéances générées sur 12 mois.');

        return Response::redirect('/loyer/contrats/' . $id);
    }

    public function show(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::LOYER_VIEW);
        $this->assertLoyerSector($this->context);
        $id = $this->id($request);
        $lease = $this->rent->findLease($actor, $id);

        return $this->renderAppArea('tenant/loyer/contrats/show', [
            'title' => 'Contrat — ' . $lease->clientName(),
            'navTitle' => 'Contrat',
            'current' => 'loyer-contrats',
            'lease' => $lease,
        ], $actor);
    }

    private function id(Request $request): int
    {
        $raw = (string) $request->attribute('id', '');
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new HttpException(404, 'Contrat introuvable.');
        }

        return (int) $raw;
    }
}
