<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Core\Auth\Permission;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Models\Tenant;
use Sesam\Services\Admin\AdminService;
use Sesam\Services\AuthService;
use Sesam\Services\Boutique\BoutiqueService;
use Sesam\Services\Reservation\SalonService;
use Sesam\Services\ClientService;
use Sesam\Services\DashboardService;
use Sesam\Services\NotificationService;

final class AccountController extends Controller
{
    use RendersAppArea;

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantContext $context,
        private readonly DashboardService $dashboard,
        private readonly ClientService $clients,
        private readonly NotificationService $notifications,
        private readonly BoutiqueService $boutique,
        private readonly ?SalonService $salon = null,
        private readonly ?AdminService $admin = null,
        private readonly ?\Sesam\Services\Immo\ImmoService $immo = null,
    ) {
        parent::__construct($views);
    }

    public function show(Request $request): Response
    {
        $user = $this->auth->requireUser();
        $tenant = $this->context->tenant();

        if ($user->tenantId() !== null && $tenant instanceof Tenant && $tenant->sector() === Sector::BOUTIQUE && $user->can(Permission::BOUTIQUE_VIEW)) {
            return $this->renderAppArea('tenant/boutique/dashboard', [
                'title' => 'Accueil — ' . $tenant->displayName(),
                'navTitle' => 'Accueil',
                'current' => 'espace',
                'greeting' => $this->dashboard->greetingName($user),
                'board' => $this->boutique->dashboard($user),
            ], $user);
        }

        if ($user->tenantId() !== null && $tenant instanceof Tenant && $tenant->sector() === Sector::RESERVATION && $this->salon !== null && $user->can(Permission::RESERVATION_VIEW)) {
            return $this->renderAppArea('tenant/reservation/dashboard', [
                'title' => 'Accueil — ' . $tenant->displayName(),
                'navTitle' => 'Accueil',
                'current' => 'espace',
                'greeting' => $this->dashboard->greetingName($user),
                'board' => $this->salon->dashboard($user),
            ], $user);
        }

        if ($user->tenantId() !== null && $tenant instanceof Tenant && $tenant->sector() === Sector::LOYER && $this->immo !== null && $user->can(Permission::LOYER_VIEW)) {
            return $this->renderAppArea('tenant/immo/dashboard', [
                'title' => 'Accueil — ' . $tenant->displayName(),
                'navTitle' => 'Accueil',
                'current' => 'espace',
                'greeting' => $this->dashboard->greetingName($user),
                'board' => $this->immo->dashboard($user),
            ], $user);
        }

        if ($user->tenantId() !== null && $tenant instanceof Tenant) {
            return $this->renderAppArea('tenant/dashboard', [
                'title' => 'Tableau de bord — ' . $tenant->displayName(),
                'navTitle' => 'Tableau de bord',
                'current' => 'espace',
                'greeting' => $this->dashboard->greetingName($user),
                'dashboard' => $this->dashboard->forTenant($tenant),
            ], $user);
        }

        if ($user->tenantId() === null && $this->admin !== null) {
            return $this->renderAppArea('staff/admin/dashboard', [
                'title' => 'Tableau de bord — Console SESAM',
                'navTitle' => 'Tableau de bord',
                'current' => 'espace',
                'board' => $this->admin->dashboard($user),
            ], $user);
        }

        return $this->renderAppArea('staff/home', [
            'title' => 'Espace SESAM',
            'navTitle' => 'Espace',
            'current' => 'espace',
            'user' => $user,
            'tenant' => $tenant,
        ], $user);
    }
}
