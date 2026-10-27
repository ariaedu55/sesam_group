<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Controllers\Concerns\RendersListPartial;
use Sesam\Core\Auth\Permission;
use Sesam\Core\Boutique\OrderStatus;
use Sesam\Core\HttpException;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\User;
use Sesam\Services\AuthService;
use Sesam\Services\Boutique\BoutiqueService;
use Sesam\Services\NotificationService;

final class BoutiqueOrderController extends Controller
{
    use RendersAppArea;
    use RendersListPartial;

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
        $status = trim((string) $request->input('statut', ''));
        $status = OrderStatus::isValid($status) ? $status : '';
        $query = trim((string) $request->input('q', ''));

        $data = [
            'title' => 'Commandes',
            'navTitle' => 'Commandes',
            'current' => 'boutique-commandes',
            'page' => $this->boutique->ordersPage($actor, $status, $query, (int) $request->input('page', '1')),
            'status' => $status,
            'query' => $query,
            'canManage' => $actor->can(Permission::BOUTIQUE_ORDERS),
        ];
        $partial = $this->renderListPartial($request, 'tenant/boutique/commandes/_results', $data);
        if ($partial !== null) {
            return $partial;
        }

        return $this->renderAppArea('tenant/boutique/commandes/index', $data, $actor);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::BOUTIQUE_ORDERS);

        return $this->form($actor, ['client_id' => (string) $request->input('client', '')], []);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::BOUTIQUE_ORDERS);
        $input = [
            'client_id' => (string) $request->input('client_id', ''),
            'customer_name' => (string) $request->input('customer_name', ''),
            'customer_phone' => (string) $request->input('customer_phone', ''),
            'customer_address' => (string) $request->input('customer_address', ''),
            'note' => (string) $request->input('note', ''),
            'status' => (string) $request->input('status', OrderStatus::NEW),
            'is_paid' => $request->input('is_paid') !== null ? '1' : '',
            'product_id' => is_array($request->input('product_id')) ? $request->input('product_id') : [],
            'quantity' => is_array($request->input('quantity')) ? $request->input('quantity') : [],
        ];
        try {
            $id = $this->boutique->createOrder($actor, $input);
        } catch (ValidationException $exception) {
            return $this->form($actor, $input, $exception->errors(), 422);
        }
        $this->session->put('status', 'Commande enregistrée.');

        return Response::redirect('/boutique/commandes/' . $id);
    }

    public function show(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::BOUTIQUE_VIEW);

        return $this->detail($actor, $this->id($request));
    }

    public function status(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::BOUTIQUE_ORDERS);
        $id = $this->id($request);
        $next = (string) $request->input('status', '');
        try {
            $order = $this->boutique->changeStatus($actor, $id, $next);
        } catch (ValidationException $exception) {
            return $this->detail($actor, $id, $exception->errors(), 422);
        }
        $this->session->put('status', 'Commande ' . $order->orderNumber() . ' : ' . OrderStatus::label($order->status()) . '.');

        return Response::redirect('/boutique/commandes/' . $id);
    }

    public function paid(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::BOUTIQUE_ORDERS);
        $id = $this->id($request);
        $paid = $this->boutique->togglePaid($actor, $id);
        $this->session->put('status', $paid ? 'Commande marquée comme payée.' : 'Commande marquée comme non payée.');

        return Response::redirect('/boutique/commandes/' . $id);
    }

    /**
     * @param array<string, string> $errors
     */
    private function detail(User $actor, int $id, array $errors = [], int $status = 200): Response
    {
        $detail = $this->boutique->orderDetail($actor, $id);

        return $this->renderAppArea('tenant/boutique/commandes/show', [
            'title' => 'Commande ' . $detail['order']->orderNumber(),
            'navTitle' => 'Commande',
            'current' => 'boutique-commandes',
            'detail' => $detail,
            'errors' => $errors,
            'canManage' => $actor->can(Permission::BOUTIQUE_ORDERS),
        ], $actor, $status);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function form(User $actor, array $old, array $errors, int $status = 200): Response
    {
        return $this->renderAppArea('tenant/boutique/commandes/form', [
            'title' => 'Nouvelle commande',
            'navTitle' => 'Nouvelle commande',
            'current' => 'boutique-commandes',
            'data' => $this->boutique->orderFormData($actor),
            'old' => $old,
            'errors' => $errors,
        ], $actor, $status);
    }

    private function id(Request $request): int
    {
        $id = (int) $request->attribute('id', '0');
        if ($id < 1) {
            throw new HttpException(404, 'Commande introuvable.');
        }

        return $id;
    }
}
