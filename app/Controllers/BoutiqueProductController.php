<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Controllers\Concerns\RendersListPartial;
use Sesam\Core\Auth\Permission;
use Sesam\Core\HttpException;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\BoutiqueProduct;
use Sesam\Models\User;
use Sesam\Services\AuthService;
use Sesam\Services\Boutique\BoutiqueService;
use Sesam\Services\NotificationService;

final class BoutiqueProductController extends Controller
{
    use RendersAppArea;
    use RendersListPartial;

    private const FIELDS = ['name', 'description', 'category', 'price_minor', 'compare_price_minor', 'stock_quantity', 'low_stock_threshold'];

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

    public function index(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::BOUTIQUE_VIEW);
        $query = trim((string) $request->input('q', ''));
        $category = trim((string) $request->input('categorie', ''));
        $filter = trim((string) $request->input('filtre', ''));

        $data = [
            'title' => 'Mes produits',
            'navTitle' => 'Produits',
            'current' => 'boutique-produits',
            'page' => $this->boutique->productsPage($actor, $query, $category, $filter),
            'query' => $query,
            'category' => $category,
            'filter' => $filter,
            'canManage' => $actor->can(Permission::BOUTIQUE_PRODUCTS),
            'tenant' => $this->context->tenant(),
            'csrf' => $this->csrf->token(),
        ];
        $partial = $this->renderListPartial($request, 'tenant/boutique/produits/_results', $data);
        if ($partial !== null) {
            return $partial;
        }

        return $this->renderAppArea('tenant/boutique/produits/index', $data, $actor);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::BOUTIQUE_PRODUCTS);

        return $this->form($actor, null, [
            'track_stock' => '1',
            'is_published' => '1',
            'stock_quantity' => '',
            'low_stock_threshold' => '3',
        ]);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::BOUTIQUE_PRODUCTS);
        $input = $this->input($request);
        try {
            $this->boutique->createProduct($actor, $input, $request->file('image'));
        } catch (ValidationException $exception) {
            return $this->form($actor, null, $input, $exception->errors(), 422);
        }
        $this->session->put('status', 'Produit ajouté. Il est visible dans votre boutique en ligne.');

        return Response::redirect($request->input('encore') !== null ? '/boutique/produits/nouveau' : '/boutique/produits');
    }

    public function editForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::BOUTIQUE_PRODUCTS);
        $product = $this->boutique->product($actor, $this->id($request));

        return $this->form($actor, $product, [
            'name' => $product->name(),
            'description' => $product->description(),
            'category' => $product->category(),
            'price_minor' => (string) $product->priceMinor(),
            'compare_price_minor' => $product->comparePriceMinor() !== null ? (string) $product->comparePriceMinor() : '',
            'low_stock_threshold' => (string) $product->lowStockThreshold(),
            'track_stock' => $product->tracksStock() ? '1' : '',
            'is_published' => $product->isPublished() ? '1' : '',
        ]);
    }

    public function update(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::BOUTIQUE_PRODUCTS);
        $id = $this->id($request);
        $input = $this->input($request);
        try {
            $this->boutique->updateProduct($actor, $id, $input, $request->file('image'), $request->input('remove_image') !== null);
        } catch (ValidationException $exception) {
            return $this->form($actor, $this->boutique->product($actor, $id), $input, $exception->errors(), 422);
        }
        $this->session->put('status', 'Produit mis à jour.');

        return Response::redirect('/boutique/produits');
    }

    public function togglePublished(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::BOUTIQUE_PRODUCTS);
        $visible = $this->boutique->togglePublished($actor, $this->id($request));
        $this->session->put('status', $visible ? 'Produit affiché dans la boutique.' : 'Produit masqué de la boutique.');

        return Response::redirect('/boutique/produits');
    }

    public function destroy(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::BOUTIQUE_PRODUCTS);
        $this->boutique->deleteProduct($actor, $this->id($request));
        $this->session->put('status', 'Produit supprimé.');

        return Response::redirect('/boutique/produits');
    }

    public function stock(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::BOUTIQUE_VIEW);
        $productId = (int) $request->input('produit', '0');

        return $this->stockView($actor, $productId > 0 ? $productId : null, [], []);
    }

    public function storeMovement(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::BOUTIQUE_PRODUCTS);
        $input = [
            'product_id' => $request->input('product_id'),
            'type' => $request->input('type'),
            'quantity' => $request->input('quantity'),
            'note' => $request->input('note'),
        ];
        try {
            $product = $this->boutique->recordMovement($actor, $input);
        } catch (ValidationException $exception) {
            $productId = (int) ($input['product_id'] ?? 0);

            return $this->stockView($actor, $productId > 0 ? $productId : null, $input, $exception->errors(), 422);
        }
        $this->session->put('status', 'Stock mis à jour : ' . $product->name() . ' — ' . $product->stockQuantity() . ' en stock.');

        return Response::redirect('/boutique/stock');
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function stockView(User $actor, ?int $productId, array $old, array $errors, int $status = 200): Response
    {
        return $this->renderAppArea('tenant/boutique/stock', [
            'title' => 'Stock',
            'navTitle' => 'Stock',
            'current' => 'boutique-stock',
            'page' => $this->boutique->stockPage($actor, $productId),
            'old' => $old,
            'errors' => $errors,
            'canManage' => $actor->can(Permission::BOUTIQUE_PRODUCTS),
        ], $actor, $status);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function form(User $actor, ?BoutiqueProduct $product, array $old, array $errors = [], int $status = 200): Response
    {
        return $this->renderAppArea('tenant/boutique/produits/form', [
            'title' => $product === null ? 'Nouveau produit' : 'Modifier ' . $product->name(),
            'navTitle' => $product === null ? 'Nouveau produit' : 'Modifier le produit',
            'current' => 'boutique-produits',
            'product' => $product,
            'categories' => $this->boutique->categories($actor),
            'old' => $old,
            'errors' => $errors,
        ], $actor, $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function input(Request $request): array
    {
        $input = [];
        foreach (self::FIELDS as $field) {
            $input[$field] = (string) $request->input($field, '');
        }
        $input['track_stock'] = $request->input('track_stock') !== null ? '1' : '';
        $input['is_published'] = $request->input('is_published') !== null ? '1' : '';

        return $input;
    }

    private function id(Request $request): int
    {
        $id = (int) $request->attribute('id', '0');
        if ($id < 1) {
            throw new HttpException(404, 'Produit introuvable.');
        }

        return $id;
    }
}
