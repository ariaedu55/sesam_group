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
use Sesam\Core\Tenant\TenantContext;
use Sesam\Services\AuthService;
use Sesam\Services\NotificationService;
use Sesam\Services\StatisticsService;

final class StatisticsController extends Controller
{
    use RendersAppArea;

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantContext $context,
        private readonly StatisticsService $statistics,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($views);
    }

    public function index(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::STATS_VIEW);
        $report = $this->statistics->report($actor, (string) $request->input('periode', StatisticsService::DEFAULT_PERIOD));

        return $this->renderAppArea('tenant/statistiques/index', [
            'title' => 'Statistiques — SESAM',
            'navTitle' => 'Statistiques',
            'current' => 'statistiques',
            'report' => $report,
        ], $actor);
    }
}
