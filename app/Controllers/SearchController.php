<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Services\AuthService;
use Sesam\Services\NotificationService;
use Sesam\Services\SearchService;

final class SearchController extends Controller
{
    use RendersAppArea;

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantContext $context,
        private readonly SearchService $search,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($views);
    }

    public function index(Request $request): Response
    {
        $user = $this->auth->requireUser();
        $query = trim((string) $request->input('q', ''));
        $results = $this->search->search($user, $query);

        return $this->renderAppArea('app/search/results', [
            'title' => 'Recherche — SESAM',
            'navTitle' => 'Recherche',
            'current' => 'recherche',
            'searchQuery' => $query,
            'results' => $results,
        ], $user);
    }
}
