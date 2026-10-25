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

final class LoyerDueController extends Controller
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
        $late = (string) $request->input('retard', '');

        return $this->renderAppArea('tenant/loyer/echeances/index', [
            'title' => 'Échéances de loyer',
            'navTitle' => 'Échéances',
            'current' => 'loyer-echeances',
            'list' => $this->rent->paginateDues($actor, $page, $late === '1' ? '1' : null),
            'canManage' => $actor->can(Permission::LOYER_DUES),
            'canRemind' => $actor->can(Permission::NOTIFICATIONS_SEND),
            'canPay' => $actor->can(Permission::PAYMENTS_CREATE),
        ], $actor);
    }

    public function show(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::LOYER_VIEW);
        $this->assertLoyerSector($this->context);
        $id = $this->id($request);
        $due = $this->rent->findDue($actor, $id);

        return $this->renderAppArea('tenant/loyer/echeances/show', [
            'title' => 'Échéance ' . $due->dueDate(),
            'navTitle' => 'Échéance',
            'current' => 'loyer-echeances',
            'due' => $due,
            'canManage' => $actor->can(Permission::LOYER_DUES),
            'canRemind' => $actor->can(Permission::NOTIFICATIONS_SEND),
            'canPay' => $actor->can(Permission::PAYMENTS_CREATE),
        ], $actor);
    }

    public function collect(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::LOYER_DUES);
        $this->assertLoyerSector($this->context);
        $id = $this->id($request);
        try {
            $url = $this->rent->initiateDuePayment($actor, $id);
        } catch (ValidationException $exception) {
            $this->session->put('status', implode(' ', $exception->errors()));

            return Response::redirect('/loyer/echeances/' . $id);
        }

        return Response::redirect($url);
    }

    public function remind(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::LOYER_DUES);
        $this->assertLoyerSector($this->context);
        $id = $this->id($request);
        try {
            $this->rent->sendDueReminder($actor, $id);
        } catch (ValidationException $exception) {
            $this->session->put('status', implode(' ', $exception->errors()));

            return Response::redirect('/loyer/echeances/' . $id);
        }
        $this->session->put('status', 'Relance envoyée (mode mock si configuré).');

        return Response::redirect('/loyer/echeances/' . $id);
    }

    private function id(Request $request): int
    {
        $raw = (string) $request->attribute('id', '');
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new HttpException(404, 'Échéance introuvable.');
        }

        return (int) $raw;
    }
}
