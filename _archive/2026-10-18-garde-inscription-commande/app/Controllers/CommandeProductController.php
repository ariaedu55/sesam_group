<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Controllers\Concerns\RequiresCommandeSector;
use Sesam\Core\Auth\Permission;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Services\AuthService;
use Sesam\Services\Commande\OrderWorkflowService;
use Sesam\Services\NotificationService;

final class CommandeProductController extends Controller
{
    use RendersAppArea;
    use RequiresCommandeSector;

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantContext $context,
        private readonly OrderWorkflowService $orders,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($views);
    }

    public function index(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::COMMANDE_VIEW);
        $this->assertCommandeSector($this->context);

        return $this->renderAppArea('tenant/commande/produits/index', [
            'title' => 'Produits — Commande',
            'navTitle' => 'Produits',
            'current' => 'commande-produits',
            'items' => $this->orders->listProducts($actor),
            'canManage' => $actor->can(Permission::COMMANDE_PRODUCTS),
        ], $actor);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::COMMANDE_PRODUCTS);
        $this->assertCommandeSector($this->context);

        return $this->renderAppArea('tenant/commande/produits/form', [
            'title' => 'Nouveau produit',
            'navTitle' => 'Nouveau produit',
            'current' => 'commande-produits',
            'old' => ['name' => '', 'price_minor' => '', 'deposit_minor' => '0'],
            'errors' => [],
        ], $actor);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::COMMANDE_PRODUCTS);
        $this->assertCommandeSector($this->context);
        $input = [
            'name' => $request->input('name'),
            'price_minor' => $request->input('price_minor'),
            'deposit_minor' => $request->input('deposit_minor'),
        ];
        try {
            $this->orders->createProduct($actor, $input);
        } catch (ValidationException $exception) {
            return $this->renderAppArea('tenant/commande/produits/form', [
                'title' => 'Nouveau produit',
                'navTitle' => 'Nouveau produit',
                'current' => 'commande-produits',
                'old' => $input,
                'errors' => $exception->errors(),
            ], $actor, 422);
        }
        $this->session->put('status', 'Produit enregistré.');

        return Response::redirect('/commande/produits');
    }
}
