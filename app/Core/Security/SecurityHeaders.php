<?php

declare(strict_types=1);

namespace Sesam\Core\Security;

use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;

final class SecurityHeaders
{
    /**
     * Les attributs style="" (barres de progression) imposent 'unsafe-inline' sur les styles ;
     * aucun script en ligne n'est autorisé. form-action est absent : le paiement redirige vers le prestataire.
     */
    public const CONTENT_SECURITY_POLICY = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:; font-src 'self'; connect-src 'self' https://fcmregistrations.googleapis.com https://firebaseinstallations.googleapis.com https://firebase.googleapis.com; worker-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'";

    public function apply(Request $request, Response $response): Response
    {
        $response = $response
            ->withHeader('Content-Security-Policy', self::CONTENT_SECURITY_POLICY)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'same-origin')
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()')
            ->withHeader('Cross-Origin-Opener-Policy', 'same-origin');

        if (!isset($response->headers()['Cache-Control'])) {
            $response = $response->withHeader('Cache-Control', 'no-store');
        }
        if ($request->isSecure()) {
            $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
