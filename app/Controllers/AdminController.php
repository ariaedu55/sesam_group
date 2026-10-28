<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Validation\ValidationException;
use Sesam\Helpers\StaffLayout;
use Sesam\Models\User;
use Sesam\Services\Admin\AdminService;
use Sesam\Services\AuthService;

/**
 * Console SESAM : revenus et tarifs. Le tableau de bord est servi par /espace.
 */
final class AdminController extends Controller
{
    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly AdminService $admin,
        private readonly ?\Sesam\Services\SaasService $saas = null,
    ) {
        parent::__construct($views);
    }

    public function revenue(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::REVENUE_VIEW);

        return $this->page('staff/admin/revenue', [
            'title' => 'Revenus — Console SESAM',
            'current' => 'revenus',
            'revenue' => $this->admin->revenue($actor, (int) $request->input('annee', gmdate('Y')), (string) $request->input('mois', '')),
            'methods' => AdminService::METHODS,
        ], $actor);
    }

    public function statistics(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::REVENUE_VIEW);

        return $this->page('staff/admin/statistics', [
            'title' => 'Statistiques — Console SESAM',
            'navTitle' => 'Statistiques',
            'current' => 'statistiques',
            'stats' => $this->admin->statistics($actor),
        ], $actor);
    }

    public function export(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::REVENUE_VIEW);
        $year = (int) $request->input('annee', gmdate('Y'));
        $month = (string) $request->input('mois', '');
        $csv = $this->admin->revenueCsv($actor, $year, $month);

        return (new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sesam-revenus-' . ($month !== '' ? $month : (string) $year) . '.csv"',
        ]));
    }

    public function prices(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::TENANTS_VIEW);

        return $this->pricesView($actor, [], []);
    }

    public function savePrices(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::SUBSCRIPTIONS_MANAGE);
        $input = [];
        foreach (['reservation', 'boutique', 'loyer'] as $sector) {
            $input[$sector . '_monthly'] = (string) $request->input($sector . '_monthly', '0');
            $input[$sector . '_setup'] = (string) $request->input($sector . '_setup', '0');
        }
        try {
            $this->admin->savePrices($actor, $input);
        } catch (ValidationException $exception) {
            return $this->pricesView($actor, $input, $exception->errors(), 422);
        }
        $this->session->put('status', 'Tarifs enregistrés. Ils sont proposés à l\'activation des nouveaux abonnements.');

        return Response::redirect('/tarifs');
    }

    /** Essai gratuit d'un mois à l'inscription : activé ou désactivé à tout moment. */
    public function saveTrial(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::SUBSCRIPTIONS_MANAGE);
        if ($this->saas === null) {
            return Response::redirect('/tarifs');
        }
        $message = $this->saas->setTrialEnabled($actor, $request->input('trial_enabled') === '1');
        $this->session->put('status', $message);

        return Response::redirect('/tarifs#essai');
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function pricesView(User $actor, array $old, array $errors, int $status = 200): Response
    {
        return $this->page('staff/admin/prices', [
            'title' => 'Tarifs — Console SESAM',
            'current' => 'tarifs',
            'prices' => $this->admin->prices($actor),
            'trial' => $this->saas?->trialSetting() ?? ['enabled' => true, 'updated_at' => null, 'updated_by_name' => null],
            'old' => $old,
            'errors' => $errors,
            'canManage' => $actor->can(Permission::SUBSCRIPTIONS_MANAGE),
        ], $actor, $status);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function page(string $template, array $data, User $user, int $status = 200): Response
    {
        $message = $this->session->get('status', '');
        $this->session->forget('status');

        return $this->render($template, $data + StaffLayout::flags($user) + [
            'csrf' => $this->csrf->token(),
            'actor' => $user,
            'tenant' => null,
            'status' => is_string($message) ? $message : '',
            'resetLink' => '',
        ], $status, 'layouts/staff');
    }
}
