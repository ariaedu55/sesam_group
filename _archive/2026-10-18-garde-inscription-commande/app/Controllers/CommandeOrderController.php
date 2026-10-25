<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Controllers\Concerns\RequiresCommandeSector;
use Sesam\Core\Auth\Permission;
use Sesam\Core\Commande\OrderStatus;
use Sesam\Core\HttpException;
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

final class CommandeOrderController extends Controller
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
        $page = (int) $request->input('page', 1);

        return $this->renderAppArea('tenant/commande/commandes/index', [
            'title' => 'Commandes',
            'navTitle' => 'Commandes',
            'current' => 'commande-commandes',
            'list' => $this->orders->paginateOrders($actor, $page),
            'canManage' => $actor->can(Permission::COMMANDE_ORDERS),
        ], $actor);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::COMMANDE_ORDERS);
        $this->assertCommandeSector($this->context);

        return $this->renderAppArea('tenant/commande/commandes/form', [
            'title' => 'Nouvelle commande',
            'navTitle' => 'Nouvelle commande',
            'current' => 'commande-commandes',
            'catalog' => $this->orders->formCatalog($actor),
            'old' => ['client_id' => ''],
            'errors' => [],
        ], $actor);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::COMMANDE_ORDERS);
        $this->assertCommandeSector($this->context);
        $input = [
            'client_id' => $request->input('client_id'),
            'product_id' => $request->input('product_id'),
            'quantity' => $request->input('quantity'),
        ];
        try {
            $id = $this->orders->createOrder($actor, $input);
        } catch (ValidationException $exception) {
            return $this->renderAppArea('tenant/commande/commandes/form', [
                'title' => 'Nouvelle commande',
                'navTitle' => 'Nouvelle commande',
                'current' => 'commande-commandes',
                'catalog' => $this->orders->formCatalog($actor),
                'old' => $input,
                'errors' => $exception->errors(),
            ], $actor, 422);
        }
        $this->session->put('status', 'Commande créée.');

        return Response::redirect('/commande/commandes/' . $id);
    }

    public function show(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::COMMANDE_VIEW);
        $this->assertCommandeSector($this->context);
        $id = $this->id($request);
        $detail = $this->orders->orderDetail($actor, $id);
        $order = $detail['order'];
        $nextStatus = OrderStatus::next($order->status());

        return $this->renderAppArea('tenant/commande/commandes/show', [
            'title' => 'Commande ' . $order->orderNumber(),
            'navTitle' => 'Détail commande',
            'current' => 'commande-commandes',
            'detail' => $detail,
            'nextStatus' => $nextStatus,
            'nextStatusLabel' => $nextStatus !== null ? OrderStatus::label($nextStatus) : null,
            'canManage' => $actor->can(Permission::COMMANDE_ORDERS),
            'canPay' => $actor->can(Permission::PAYMENTS_CREATE),
        ], $actor);
    }

    public function advance(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::COMMANDE_ORDERS);
        $this->assertCommandeSector($this->context);
        $id = $this->id($request);
        try {
            $this->orders->advanceStatus($actor, $id);
        } catch (ValidationException $exception) {
            $this->session->put('status', implode(' ', $exception->errors()));

            return Response::redirect('/commande/commandes/' . $id);
        }
        $this->session->put('status', 'Statut mis à jour.');

        return Response::redirect('/commande/commandes/' . $id);
    }

    public function cancel(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::COMMANDE_ORDERS);
        $this->assertCommandeSector($this->context);
        $id = $this->id($request);
        try {
            $this->orders->cancel($actor, $id);
        } catch (ValidationException $exception) {
            $this->session->put('status', implode(' ', $exception->errors()));

            return Response::redirect('/commande/commandes/' . $id);
        }
        $this->session->put('status', 'Commande annulée.');

        return Response::redirect('/commande/commandes/' . $id);
    }

    public function storeMeasurement(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::COMMANDE_ORDERS);
        $this->assertCommandeSector($this->context);
        $id = $this->id($request);
        $input = [
            'chest_cm' => $request->input('chest_cm'),
            'waist_cm' => $request->input('waist_cm'),
            'hips_cm' => $request->input('hips_cm'),
            'length_cm' => $request->input('length_cm'),
            'notes' => $request->input('notes'),
        ];
        try {
            $this->orders->addMeasurement($actor, $id, $input);
        } catch (ValidationException $exception) {
            $this->session->put('status', implode(' ', $exception->errors()));

            return Response::redirect('/commande/commandes/' . $id);
        }
        $this->session->put('status', 'Mesures enregistrées.');

        return Response::redirect('/commande/commandes/' . $id);
    }

    public function updateDetails(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::COMMANDE_ORDERS);
        $this->assertCommandeSector($this->context);
        $id = $this->id($request);
        $input = [
            'fabric_notes' => $request->input('fabric_notes'),
            'instructions' => $request->input('instructions'),
            'photo_url' => $request->input('photo_url'),
        ];
        try {
            $this->orders->updateDetails($actor, $id, $input);
        } catch (ValidationException $exception) {
            $this->session->put('status', implode(' ', $exception->errors()));

            return Response::redirect('/commande/commandes/' . $id);
        }
        $this->session->put('status', 'Détails enregistrés.');

        return Response::redirect('/commande/commandes/' . $id);
    }

    public function scheduleDelivery(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::COMMANDE_ORDERS);
        $this->assertCommandeSector($this->context);
        $id = $this->id($request);
        $input = [
            'delivery_due_at' => $request->input('delivery_due_at'),
            'delivery_notes' => $request->input('delivery_notes'),
        ];
        try {
            $this->orders->scheduleDelivery($actor, $id, $input);
        } catch (ValidationException $exception) {
            $this->session->put('status', implode(' ', $exception->errors()));

            return Response::redirect('/commande/commandes/' . $id);
        }
        $this->session->put('status', 'Livraison planifiée.');

        return Response::redirect('/commande/commandes/' . $id);
    }

    public function deposit(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::COMMANDE_ORDERS);
        $this->assertCommandeSector($this->context);
        $id = $this->id($request);
        try {
            $url = $this->orders->initiateDeposit($actor, $id);
        } catch (ValidationException $exception) {
            $this->session->put('status', implode(' ', $exception->errors()));

            return Response::redirect('/commande/commandes/' . $id);
        }

        return Response::redirect($url);
    }

    private function id(Request $request): int
    {
        $raw = (string) $request->attribute('id', '');
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new HttpException(404, 'Commande introuvable.');
        }

        return (int) $raw;
    }
}
