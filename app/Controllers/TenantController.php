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
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Helpers\StaffLayout;
use Sesam\Models\Tenant;
use Sesam\Models\User;
use Sesam\Services\Admin\AdminService;
use Sesam\Services\AuthService;
use Sesam\Services\NotificationService;
use Sesam\Services\TenantConsoleService;
use Sesam\Services\TenantService;

final class TenantController extends Controller
{
    use RendersAppArea;

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantService $tenants,
        private readonly TenantContext $context,
        private readonly NotificationService $notifications,
        private readonly TenantConsoleService $console,
        private readonly ?AdminService $admin = null,
        private readonly ?\Sesam\Services\SaasService $saas = null,
    ) {
        parent::__construct($views);
    }

    public function index(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::TENANTS_VIEW);
        $query = [];
        foreach (['q', 'secteur', 'etat', 'abonnement', 'source'] as $key) {
            $query[$key] = $request->input($key, '');
        }

        return $this->staff('staff/tenants/index', $this->console->overview($actor, $query) + [
            'title' => 'Console SESAM — Commerces',
            'navTitle' => 'Console des commerces',
            'current' => $query['abonnement'] === 'trial' ? 'essais' : 'commerces',
            'sectors' => Sector::LABELS,
            'canCreate' => $actor->can(Permission::TENANTS_CREATE),
            'canSeeSubscriptions' => $actor->can(Permission::SUBSCRIPTIONS_VIEW),
        ], $actor);
    }

    public function show(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::TENANTS_VIEW);

        return $this->detailView($actor, $this->id($request));
    }

    public function subscription(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::SUBSCRIPTIONS_MANAGE);
        $id = $this->id($request);
        $input = [
            'action' => $request->input('action'),
            'period_start' => $request->input('period_start'),
            'monthly_amount' => $request->input('monthly_amount'),
            'note' => $request->input('note'),
        ];
        try {
            $message = $this->console->apply($actor, $id, $input);
        } catch (ValidationException $exception) {
            return $this->detailView($actor, $id, $input, $exception->errors(), 422);
        }
        $this->session->put('status', $message);

        return Response::redirect('/commerces/' . $id);
    }

    /** Encaissement d'une mensualité : enregistre le paiement et prolonge l'accès. */
    public function payment(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::SUBSCRIPTIONS_MANAGE);
        $id = $this->id($request);
        $input = [
            'amount' => (string) $request->input('amount', ''),
            'months' => (string) $request->input('months', '1'),
            'paid_on' => (string) $request->input('paid_on', ''),
            'method' => (string) $request->input('method', ''),
            'reference' => (string) $request->input('reference', ''),
            'note' => (string) $request->input('note', ''),
        ];
        try {
            $message = $this->requireAdmin()->recordPayment($actor, $id, $input);
        } catch (ValidationException $exception) {
            return $this->detailView($actor, $id, [], [], 422, $input, $exception->errors());
        }
        $this->session->put('status', $message);

        return Response::redirect('/commerces/' . $id);
    }

    public function trial(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::SUBSCRIPTIONS_MANAGE);
        $id = $this->id($request);
        if ($this->saas === null) {
            throw new HttpException(503, 'Service indisponible.');
        }
        try {
            $this->session->put('status', $this->saas->extendTrial($actor, $id, (int) $request->input('trial_days', '15')));
        } catch (ValidationException $exception) {
            return $this->detailView($actor, $id, [], $exception->errors(), 422);
        }

        return Response::redirect('/commerces/' . $id);
    }

    public function note(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::TENANTS_VIEW);
        $id = $this->id($request);
        try {
            $this->requireAdmin()->addNote($actor, $id, (string) $request->input('note_body', ''));
        } catch (ValidationException $exception) {
            return $this->detailView($actor, $id, [], $exception->errors(), 422);
        }
        $this->session->put('status', 'Note ajoutée.');

        return Response::redirect('/commerces/' . $id . '#notes');
    }

    private function requireAdmin(): AdminService
    {
        if ($this->admin === null) {
            throw new HttpException(503, 'Service indisponible.');
        }

        return $this->admin;
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::TENANTS_CREATE);

        return $this->formView($actor, null, [
            'name' => '',
            'slug' => '',
            'sector' => Sector::RESERVATION,
            'status' => 'active',
            'display_name' => '',
            'owner_full_name' => '',
            'owner_email' => '',
            'owner_password' => '',
        ]);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::TENANTS_CREATE);
        $input = $this->formInput($request);
        $trial = (int) (is_scalar($input['trial_days']) ? $input['trial_days'] : 30);
        if (!in_array($trial, [0, 15, 30], true)) {
            return $this->formView($actor, null, $input, ['trial_days' => 'Choisissez 15 jours, 1 mois ou sans essai.'], 422);
        }
        try {
            $id = $this->tenants->create($actor, $input);
        } catch (ValidationException $exception) {
            return $this->formView($actor, null, $input, $exception->errors(), 422);
        }
        if ($trial > 0 && $this->saas !== null) {
            $this->saas->startTrial($id, $trial, $actor->id(), 'Commerce créé par ' . $actor->fullName());
        }
        $this->session->put('status', $trial > 0 ? 'Commerce créé avec un essai gratuit de ' . ($trial === 30 ? '1 mois' : '15 jours') . '.' : 'Le commerce a été créé.');

        return Response::redirect('/commerces/' . $id);
    }

    public function editForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::TENANTS_VIEW);
        $tenant = $this->tenants->find($actor, $this->id($request));

        return $this->formView($actor, $tenant, [
            'name' => $tenant->name(),
            'slug' => $tenant->slug(),
            'sector' => $tenant->sector(),
            'status' => $tenant->status(),
            'display_name' => $tenant->displayName(),
        ]);
    }

    public function update(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::TENANTS_UPDATE);
        $id = $this->id($request);
        $input = $this->formInput($request);
        try {
            $this->tenants->update($actor, $id, $input);
        } catch (ValidationException $exception) {
            $tenant = $this->tenants->find($actor, $id);

            return $this->formView($actor, $tenant, $input, $exception->errors(), 422);
        }

        $this->session->put('status', 'Le commerce a été mis à jour.');

        return Response::redirect('/commerces/' . $id);
    }

    public function settingsForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::SETTINGS_VIEW);
        $tenant = $this->tenants->current($actor);
        if ($tenant->sector() === Sector::BOUTIQUE) {
            return Response::redirect('/boutique/parametres');
        }
        if ($tenant->sector() === Sector::RESERVATION) {
            return Response::redirect('/reservation/salon');
        }
        $canUpdate = $actor->can(Permission::SETTINGS_UPDATE);

        return $this->renderAppArea('staff/settings/form', [
            'title' => 'Paramètres — SESAM',
            'navTitle' => 'Paramètres',
            'current' => 'parametres',
            'tenant' => $tenant,
            'old' => ['display_name' => $tenant->displayName()],
            'errors' => [],
            'canUpdate' => $canUpdate,
        ], $actor);
    }

    public function settingsUpdate(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::SETTINGS_UPDATE);
        $input = [
            'display_name' => $request->input('display_name'),
            'tenant_id' => $request->input('tenant_id'),
        ];
        try {
            $this->tenants->updateDisplayName($actor, $input);
        } catch (ValidationException $exception) {
            $tenant = $this->tenants->current($actor);

            return $this->renderAppArea('staff/settings/form', [
                'title' => 'Paramètres — SESAM',
                'navTitle' => 'Paramètres',
                'current' => 'parametres',
                'tenant' => $tenant,
                'old' => $input,
                'errors' => $exception->errors(),
                'canUpdate' => true,
            ], $actor, 422);
        }

        $this->session->put('status', 'Le nom affiché a été mis à jour.');

        return Response::redirect('/parametres');
    }

    /**
     * @return array<string, mixed>
     */
    private function formInput(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'slug' => $request->input('slug'),
            'sector' => $request->input('sector'),
            'status' => $request->input('status'),
            'display_name' => $request->input('display_name'),
            'tenant_id' => $request->input('tenant_id'),
            'owner_full_name' => $request->input('owner_full_name'),
            'owner_email' => $request->input('owner_email'),
            'owner_password' => $request->input('owner_password'),
            'owner_phone' => $request->input('owner_phone'),
            'trial_days' => $request->input('trial_days', '30'),
        ];
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function detailView(User $actor, int $id, array $old = [], array $errors = [], int $status = 200, array $paymentOld = [], array $paymentErrors = []): Response
    {
        $detail = $this->console->detail($actor, $id);
        $extras = $this->admin?->tenantExtras($actor, $detail['tenant'], $detail['subscription']) ?? [];

        return $this->staff('staff/tenants/show', $detail + $extras + [
            'paymentOld' => $paymentOld,
            'paymentErrors' => $paymentErrors,
            'methods' => AdminService::METHODS,
            'title' => $detail['tenant']->displayName() . ' — Console SESAM',
            'navTitle' => 'Fiche commerce',
            'current' => 'commerces',
            'subscriptionOld' => $old,
            'subscriptionErrors' => $errors,
            'canUpdate' => $actor->can(Permission::TENANTS_UPDATE),
            'canSeeSubscriptions' => $actor->can(Permission::SUBSCRIPTIONS_VIEW),
            'canManageSubscriptions' => $actor->can(Permission::SUBSCRIPTIONS_MANAGE),
        ], $actor, $status);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function formView(User $actor, ?Tenant $tenant, array $old, array $errors = [], int $status = 200): Response
    {
        return $this->staff('staff/tenants/form', [
            'title' => ($tenant === null ? 'Nouveau commerce' : 'Modifier le commerce') . ' — SESAM',
            'navTitle' => $tenant === null ? 'Nouveau commerce' : 'Modifier',
            'current' => 'commerces',
            'edited' => $tenant,
            'old' => $old,
            'errors' => $errors,
            'sectors' => Sector::LABELS,
            'canUpdate' => $actor->can(Permission::TENANTS_UPDATE) || $tenant === null,
        ], $actor, $status);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function staff(string $template, array $data, User $user, int $status = 200): Response
    {
        $message = $this->session->get('status', '');
        $this->session->forget('status');

        return $this->render($template, $data + StaffLayout::flags($user) + [
            'csrf' => $this->csrf->token(),
            'actor' => $user,
            'tenant' => $data['tenant'] ?? $this->context->tenant(),
            'status' => is_string($message) ? $message : '',
            'resetLink' => '',
        ], $status, 'layouts/staff');
    }

    private function id(Request $request): int
    {
        $raw = (string) $request->attribute('id', '');
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new HttpException(404, 'Commerce introuvable.');
        }

        return (int) $raw;
    }
}
