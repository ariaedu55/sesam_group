<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
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
use Sesam\Services\NotificationService;
use Sesam\Services\PaymentService;

final class PaymentController extends Controller
{
    use RendersAppArea;

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly PaymentService $payments,
        private readonly TenantContext $context,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($views);
    }

    public function index(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::PAYMENTS_VIEW);
        $page = (int) $request->input('page', 1);
        $list = $this->payments->paginate($actor, $page);

        return $this->renderAppArea('tenant/payments/index', [
            'title' => 'Paiements — SESAM',
            'navTitle' => 'Paiements',
            'current' => 'paiements',
            'list' => $list,
            'canCreate' => $actor->can(Permission::PAYMENTS_CREATE),
        ], $actor);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::PAYMENTS_CREATE);

        return $this->renderAppArea('tenant/payments/form', [
            'title' => 'Nouveau paiement — SESAM',
            'navTitle' => 'Nouveau paiement',
            'current' => 'paiements',
            'old' => ['amount_minor' => '', 'description' => '', 'client_id' => ''],
            'errors' => [],
            'clients' => $this->payments->clientsForSelect($actor),
            'sandboxNotice' => true,
        ], $actor);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::PAYMENTS_CREATE);
        $input = [
            'amount_minor' => $request->input('amount_minor'),
            'description' => $request->input('description'),
            'client_id' => $request->input('client_id'),
        ];
        try {
            $result = $this->payments->initiate($actor, $input);
        } catch (ValidationException $exception) {
            return $this->renderAppArea('tenant/payments/form', [
                'title' => 'Nouveau paiement — SESAM',
                'navTitle' => 'Nouveau paiement',
                'current' => 'paiements',
                'old' => $input,
                'errors' => $exception->errors(),
                'clients' => $this->payments->clientsForSelect($actor),
                'sandboxNotice' => true,
            ], $actor, 422);
        }

        return Response::redirect($result['checkout_url']);
    }

    public function show(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::PAYMENTS_VIEW);
        $detail = $this->payments->detail($actor, $this->id($request));

        return $this->renderAppArea('tenant/payments/show', [
            'title' => 'Paiement — SESAM',
            'navTitle' => 'Détail paiement',
            'current' => 'paiements',
            'payment' => $detail['payment'],
            'transactions' => $detail['transactions'],
            'canSimulate' => $actor->can(Permission::PAYMENTS_CREATE),
        ], $actor);
    }

    public function sandbox(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::PAYMENTS_CREATE);
        $detail = $this->payments->detail($actor, $this->id($request));
        $payment = $detail['payment'];
        if ($payment->mode() !== 'sandbox') {
            throw new HttpException(403, 'Bac à sable NabooPay uniquement.');
        }

        return $this->renderAppArea('tenant/payments/sandbox', [
            'title' => 'Bac à sable NabooPay',
            'navTitle' => 'Bac à sable',
            'current' => 'paiements',
            'payment' => $payment,
        ], $actor);
    }

    public function sandboxConfirm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::PAYMENTS_CREATE);
        $id = $this->id($request);
        $this->payments->sandboxConfirm($actor, $id);
        $this->session->put('status', 'Paiement simulé comme réussi (bac à sable NabooPay).');

        return Response::redirect('/paiements/' . $id);
    }

    public function sandboxCancel(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::PAYMENTS_CREATE);
        $id = $this->id($request);
        $this->payments->sandboxCancel($actor, $id);
        $this->session->put('status', 'Paiement annulé dans le bac à sable.');

        return Response::redirect('/paiements/' . $id);
    }

    public function returnFromProvider(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::PAYMENTS_VIEW);
        $id = $this->id($request);
        $tenantId = $actor->tenantId();
        if ($tenantId === null) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
        $payment = $this->payments->syncFromProvider($tenantId, $id);
        $this->session->put(
            'status',
            $payment->status() === \Sesam\Core\Payment\PaymentStatus::SUCCESS
                ? 'Paiement confirmé par NabooPay.'
                : 'Retour NabooPay enregistré. Statut actuel : ' . \Sesam\Core\Payment\PaymentStatus::label($payment->status()) . '.'
        );

        return Response::redirect('/paiements/' . $id);
    }

    private function id(Request $request): int
    {
        $raw = (string) $request->attribute('id', '');
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new HttpException(404, 'Paiement introuvable.');
        }

        return (int) $raw;
    }
}
