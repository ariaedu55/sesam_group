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

final class LoyerPropertyController extends Controller
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

        return $this->renderAppArea('tenant/loyer/biens/index', [
            'title' => 'Biens — Loyer',
            'navTitle' => 'Biens',
            'current' => 'loyer-biens',
            'items' => $this->rent->listProperties($actor),
            'canManage' => $actor->can(Permission::LOYER_PROPERTIES),
        ], $actor);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::LOYER_PROPERTIES);
        $this->assertLoyerSector($this->context);

        return $this->renderAppArea('tenant/loyer/biens/form', [
            'title' => 'Nouveau bien',
            'navTitle' => 'Nouveau bien',
            'current' => 'loyer-biens',
            'old' => ['name' => '', 'address' => ''],
            'errors' => [],
        ], $actor);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::LOYER_PROPERTIES);
        $this->assertLoyerSector($this->context);
        $input = ['name' => $request->input('name'), 'address' => $request->input('address')];
        try {
            $id = $this->rent->createProperty($actor, $input);
        } catch (ValidationException $exception) {
            return $this->renderAppArea('tenant/loyer/biens/form', [
                'title' => 'Nouveau bien',
                'navTitle' => 'Nouveau bien',
                'current' => 'loyer-biens',
                'old' => $input,
                'errors' => $exception->errors(),
            ], $actor, 422);
        }

        return Response::redirect('/loyer/biens/' . $id);
    }

    public function show(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::LOYER_VIEW);
        $this->assertLoyerSector($this->context);
        $id = $this->id($request);
        $property = $this->rent->findProperty($actor, $id);

        return $this->renderAppArea('tenant/loyer/biens/show', [
            'title' => $property->name(),
            'navTitle' => $property->name(),
            'current' => 'loyer-biens',
            'property' => $property,
            'units' => $this->rent->listUnits($actor, $id),
            'canManage' => $actor->can(Permission::LOYER_PROPERTIES),
        ], $actor);
    }

    public function storeUnit(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::LOYER_PROPERTIES);
        $this->assertLoyerSector($this->context);
        $id = $this->id($request);
        $input = [
            'label' => $request->input('label'),
            'default_rent_minor' => $request->input('default_rent_minor'),
        ];
        try {
            $this->rent->createUnit($actor, $id, $input);
        } catch (ValidationException $exception) {
            $this->session->put('status', implode(' ', $exception->errors()));

            return Response::redirect('/loyer/biens/' . $id);
        }
        $this->session->put('status', 'Unité ajoutée.');

        return Response::redirect('/loyer/biens/' . $id);
    }

    private function id(Request $request): int
    {
        $raw = (string) $request->attribute('id', '');
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new HttpException(404, 'Bien introuvable.');
        }

        return (int) $raw;
    }
}
