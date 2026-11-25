<?php

declare(strict_types=1);

namespace Sesam\Middleware;

use Sesam\Core\Logger\Logger;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Security\Honeypot;
use Sesam\Core\Session\Session;

/**
 * Bloque les robots sur les formulaires publics (voir Honeypot). Un robot pris au piège reçoit une redirection
 * ordinaire, comme si l'envoi avait marché : rien n'est enregistré, et il n'apprend pas qu'il a été repéré.
 */
final class HoneypotMiddleware implements Middleware
{
    /**
     * Formulaires publics protégés : motif du chemin => délai minimum (secondes) entre l'affichage et l'envoi.
     *
     * @var array<string, int>
     */
    private const PROTECTED = [
        '#^/commencer$#' => 3,
        '#^/devis$#' => 3,
        '#^/connexion$#' => 1,
        '#^/mot-de-passe$#' => 2,
        '#^/mot-de-passe/reinitialiser/[^/]+$#' => 2,
        '#^/t/[^/]+$#' => 3,
        '#^/t/[^/]+/commander$#' => 3,
    ];

    public function __construct(
        private readonly Honeypot $honeypot,
        private readonly Session $session,
        private readonly ?Logger $logger = null,
        private readonly bool $enabled = true,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->enabled || $request->method() !== 'POST') {
            return $next($request);
        }
        $minSeconds = null;
        foreach (self::PROTECTED as $pattern => $seconds) {
            if (preg_match($pattern, $request->path()) === 1) {
                $minSeconds = $seconds;
                break;
            }
        }
        if ($minSeconds === null) {
            return $next($request);
        }

        $verdict = $this->honeypot->check($request->input(Honeypot::FIELD), $request->input(Honeypot::TIME_FIELD), $minSeconds);
        if ($verdict === 'ok') {
            return $next($request);
        }

        $this->logger?->info('honeypot blocked ' . $verdict . ' path=' . $request->path() . ' ip=' . $request->ip());
        if ($verdict === 'fast' || $verdict === 'expired') {
            // Peut-être un humain très rapide ou une page restée ouverte : on lui explique, sans rien enregistrer.
            $this->session->put('status', $verdict === 'fast'
                ? 'Envoi trop rapide : vérifiez vos informations puis envoyez de nouveau.'
                : 'La page était ouverte depuis longtemps : envoyez de nouveau le formulaire.');
        }

        return Response::redirect($request->path(), 303);
    }
}
