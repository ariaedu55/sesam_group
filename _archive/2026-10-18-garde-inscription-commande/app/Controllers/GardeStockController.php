<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Controllers\Concerns\RequiresGardeSector;
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
use Sesam\Services\Garde\DutyService;
use Sesam\Services\NotificationService;

final class GardeStockController extends Controller
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

    public function products(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_VIEW);
        $this->assertGardeSector($this->context);

        return $this->renderAppArea('tenant/garde/produits/index', [
            'title' => 'Stock pharmacie',
            'navTitle' => 'Produits',
            'current' => 'garde-produits',
            'products' => $this->garde->listProducts($actor),
            'canManage' => $actor->can(Permission::GARDE_STOCK),
        ], $actor);
    }

    public function createProductForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_STOCK);
        $this->assertGardeSector($this->context);

        return $this->renderAppArea('tenant/garde/produits/form', [
            'title' => 'Nouveau produit',
            'navTitle' => 'Nouveau produit',
            'current' => 'garde-produits',
            'old' => ['name' => '', 'unit' => 'boîte', 'min_quantity' => '0'],
            'errors' => [],
        ], $actor);
    }

    public function storeProduct(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_STOCK);
        $this->assertGardeSector($this->context);
        try {
            $id = $this->garde->createProduct($actor, [
                'name' => $request->input('name'),
                'unit' => $request->input('unit'),
                'min_quantity' => $request->input('min_quantity'),
                'image' => $request->file('image'),
            ]);
        } catch (ValidationException $exception) {
            return $this->renderAppArea('tenant/garde/produits/form', [
                'title' => 'Nouveau produit',
                'navTitle' => 'Nouveau produit',
                'current' => 'garde-produits',
                'old' => [
                    'name' => (string) $request->input('name', ''),
                    'unit' => (string) $request->input('unit', ''),
                    'min_quantity' => (string) $request->input('min_quantity', '0'),
                ],
                'errors' => $exception->errors(),
            ], $actor);
        }
        $this->session->put('status', 'Produit ajouté au stock.');

        return Response::redirect('/garde/produits/' . $id);
    }

    public function showProduct(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_VIEW);
        $this->assertGardeSector($this->context);
        $detail = $this->garde->productDetail($actor, $this->id($request));

        return $this->renderAppArea('tenant/garde/produits/show', [
            'title' => $detail['product']->name(),
            'navTitle' => 'Produit',
            'current' => 'garde-produits',
            'detail' => $detail,
            'canManage' => $actor->can(Permission::GARDE_STOCK),
            'errors' => [],
        ], $actor);
    }

    public function productImage(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_VIEW);
        $this->assertGardeSector($this->context);
        $file = $this->garde->productImageFile($actor, $this->id($request));

        return new Response($file['contents'], 200, [
            'Content-Type' => $file['mime'],
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function storeProductImage(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_STOCK);
        $this->assertGardeSector($this->context);
        $id = $this->id($request);
        try {
            $this->garde->replaceProductImage($actor, $id, [
                'image' => $request->file('image'),
            ]);
        } catch (ValidationException $exception) {
            $detail = $this->garde->productDetail($actor, $id);

            return $this->renderAppArea('tenant/garde/produits/show', [
                'title' => $detail['product']->name(),
                'navTitle' => 'Produit',
                'current' => 'garde-produits',
                'detail' => $detail,
                'canManage' => true,
                'errors' => $exception->errors(),
            ], $actor);
        }
        $this->session->put('status', 'Photo du produit enregistrée.');

        return Response::redirect('/garde/produits/' . $id);
    }

    public function createLotForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_STOCK);
        $this->assertGardeSector($this->context);
        $productId = (string) $request->input('produit', '');

        return $this->renderAppArea('tenant/garde/lots/form', [
            'title' => 'Nouveau lot',
            'navTitle' => 'Nouveau lot',
            'current' => 'garde-produits',
            'products' => $this->garde->listProducts($actor),
            'old' => [
                'product_id' => $productId,
                'lot_code' => '',
                'quantity' => '',
                'expires_on' => '',
            ],
            'errors' => [],
        ], $actor);
    }

    public function storeLot(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_STOCK);
        $this->assertGardeSector($this->context);
        $input = [
            'product_id' => $request->input('product_id'),
            'lot_code' => $request->input('lot_code'),
            'quantity' => $request->input('quantity'),
            'expires_on' => $request->input('expires_on'),
        ];
        try {
            $this->garde->createLot($actor, $input);
        } catch (ValidationException $exception) {
            return $this->renderAppArea('tenant/garde/lots/form', [
                'title' => 'Nouveau lot',
                'navTitle' => 'Nouveau lot',
                'current' => 'garde-produits',
                'products' => $this->garde->listProducts($actor),
                'old' => [
                    'product_id' => (string) ($input['product_id'] ?? ''),
                    'lot_code' => (string) ($input['lot_code'] ?? ''),
                    'quantity' => (string) ($input['quantity'] ?? ''),
                    'expires_on' => (string) ($input['expires_on'] ?? ''),
                ],
                'errors' => $exception->errors(),
            ], $actor);
        }
        $this->session->put('status', 'Lot enregistré. L\'alerte de péremption est calculée automatiquement.');

        return Response::redirect('/garde/produits/' . (int) $input['product_id']);
    }

    public function alerts(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_VIEW);
        $this->assertGardeSector($this->context);
        $filter = (string) $request->input('etat', 'all');

        return $this->renderAppArea('tenant/garde/alertes/index', [
            'title' => 'Alertes de péremption',
            'navTitle' => 'Alertes',
            'current' => 'garde-alertes',
            'filter' => $filter,
            'batches' => $this->garde->listAlerts($actor, $filter),
            'canManage' => $actor->can(Permission::GARDE_STOCK),
        ], $actor);
    }

    public function movements(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_VIEW);
        $this->assertGardeSector($this->context);
        $page = (int) $request->input('page', 1);

        return $this->renderAppArea('tenant/garde/mouvements/index', [
            'title' => 'Mouvements de stock',
            'navTitle' => 'Mouvements',
            'current' => 'garde-produits',
            'list' => $this->garde->paginateMovements($actor, $page),
            'canManage' => $actor->can(Permission::GARDE_STOCK),
        ], $actor);
    }

    public function createMovementForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_STOCK);
        $this->assertGardeSector($this->context);
        $list = $this->garde->paginateMovements($actor, 1);

        return $this->renderAppArea('tenant/garde/mouvements/form', [
            'title' => 'Enregistrer un mouvement',
            'navTitle' => 'Mouvement',
            'current' => 'garde-produits',
            'batches' => $list['batches'],
            'old' => [
                'batch_id' => (string) $request->input('lot', ''),
                'type' => 'out',
                'quantity' => '',
                'reason' => '',
            ],
            'errors' => [],
        ], $actor);
    }

    public function storeMovement(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_STOCK);
        $this->assertGardeSector($this->context);
        $input = [
            'batch_id' => $request->input('batch_id'),
            'type' => $request->input('type'),
            'quantity' => $request->input('quantity'),
            'reason' => $request->input('reason'),
        ];
        try {
            $this->garde->recordMovement($actor, $input);
        } catch (ValidationException $exception) {
            $list = $this->garde->paginateMovements($actor, 1);

            return $this->renderAppArea('tenant/garde/mouvements/form', [
                'title' => 'Enregistrer un mouvement',
                'navTitle' => 'Mouvement',
                'current' => 'garde-produits',
                'batches' => $list['batches'],
                'old' => [
                    'batch_id' => (string) ($input['batch_id'] ?? ''),
                    'type' => (string) ($input['type'] ?? ''),
                    'quantity' => (string) ($input['quantity'] ?? ''),
                    'reason' => (string) ($input['reason'] ?? ''),
                ],
                'errors' => $exception->errors(),
            ], $actor);
        }
        $this->session->put('status', 'Mouvement de stock enregistré.');

        return Response::redirect('/garde/mouvements');
    }

    private function id(Request $request): int
    {
        $raw = (string) $request->attribute('id', '');
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new HttpException(404, 'Produit introuvable.');
        }

        return (int) $raw;
    }
}
