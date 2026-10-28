<?php

declare(strict_types=1);

namespace Sesam\Middleware;

use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Services\AuthService;

final class AuthMiddleware implements Middleware
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $path = $request->path();
        $user = $this->auth->user();

        if (str_starts_with($path, '/webhooks/')) {
            return $next($request);
        }

        if ($this->isProtected($path) && $user === null) {
            return Response::redirect('/connexion');
        }

        if ($this->isGuestOnly($path) && $user !== null) {
            return Response::redirect('/espace');
        }

        return $next($request);
    }

    private function isProtected(string $path): bool
    {
        foreach (['/espace', '/utilisateurs', '/commerces', '/revenus', '/tarifs', '/agence', '/parametres', '/notifications', '/profil', '/recherche', '/clients', '/paiements', '/statistiques', '/reservation', '/boutique', '/loyer', '/immo', '/abonnement', '/finance'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return $path === '/deconnexion';
    }

    private function isGuestOnly(string $path): bool
    {
        return $path === '/connexion'
            || $path === '/connexion/google'
            || $path === '/mot-de-passe'
            || str_starts_with($path, '/mot-de-passe/reinitialiser/');
    }
}
