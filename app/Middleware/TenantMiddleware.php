<?php

declare(strict_types=1);

namespace Sesam\Middleware;

use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Repositories\TenantRepository;
use Sesam\Services\AuthService;
use Sesam\Services\SaasService;

final class TenantMiddleware implements Middleware
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly TenantRepository $tenants,
        private readonly TenantContext $context,
        private readonly ?SaasService $saas = null,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (preg_match('#^/t/([^/]+)#', $request->path(), $matches) === 1) {
            $tenant = $this->tenants->findBySlug(rawurldecode($matches[1]));
            $this->context->bind($tenant !== null && $tenant->isActive() ? $tenant : null);

            return $next($request);
        }

        $user = $this->auth->user();
        if ($user === null) {
            $this->context->bind(null);

            return $next($request);
        }

        $this->dailyNotifications();
        $tenantId = $user->tenantId();
        if ($tenantId === null) {
            $this->context->bind(null);

            return $next($request);
        }

        $tenant = $this->tenants->findById($tenantId);
        if ($tenant === null || !$tenant->isActive()) {
            $this->auth->logout();
            $this->context->bind(null);

            return Response::redirect('/connexion');
        }

        $this->context->bind($tenant);
        if ($this->saas !== null) {
            $access = $this->saas->access($tenant->id());
            $this->context->setSubscription($access);
            // Essai terminé ou abonnement échu depuis trop longtemps : seules la page Abonnement et le profil restent ouverts.
            if ($access['blocked'] && !$this->allowedWhenBlocked($request->path())) {
                return Response::redirect('/abonnement');
            }
        }

        return $next($request);
    }

    /**
     * Rappels du jour (une fois par jour) si aucune tâche planifiée ne les a déjà envoyés.
     */
    private function dailyNotifications(): void
    {
        try {
            \Sesam\Services\DailyNotifications::current()?->runIfDue();
            \Sesam\Services\ReminderPump::current()?->runIfDue();
        } catch (\Throwable) {
            // Un rappel en échec ne doit jamais empêcher d'utiliser l'application.
        }
    }

    private function allowedWhenBlocked(string $path): bool
    {
        foreach (['/abonnement', '/profil', '/deconnexion', '/connexion', '/notifications'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return !str_starts_with($path, '/') || preg_match('#^/(espace|clients|paiements|statistiques|parametres|utilisateurs|recherche|reservation|boutique|immo|loyer)(/|$)#', $path) !== 1;
    }
}
