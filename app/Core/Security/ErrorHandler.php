<?php

declare(strict_types=1);

namespace Sesam\Core\Security;

use Sesam\Core\HttpException;
use Sesam\Core\Logger\Logger;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Validation\ValidationException;
use Throwable;

final class ErrorHandler
{
    public function __construct(
        private readonly Logger $logger,
        private readonly ViewRenderer $views,
    ) {
    }

    public function renderHttp(HttpException $exception): Response
    {
        $errors = $exception instanceof ValidationException ? $exception->errors() : [];

        return $this->html($exception->status(), $this->title($exception->status()), $exception->getMessage(), $errors);
    }

    public function renderUnexpected(Throwable $exception): Response
    {
        try {
            $this->logger->error($exception::class . ' ' . $exception->getMessage() . ' @ ' . $exception->getFile() . ':' . $exception->getLine());
        } catch (Throwable) {
            // La réponse reste générique si le journal ne peut pas être écrit.
        }

        return $this->html(500, 'Erreur', 'Une erreur est survenue.', []);
    }

    /**
     * @param array<string, string> $errors
     */
    private function html(int $status, string $title, string $message, array $errors): Response
    {
        try {
            $html = $this->views->render('http_error', [
                'title' => $title,
                'message' => $message,
                'errors' => $errors,
            ]);
        } catch (Throwable) {
            $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Erreur</title></head><body><p>Une erreur est survenue.</p></body></html>';
        }

        return Response::html($html, $status);
    }

    private function title(int $status): string
    {
        return match ($status) {
            401 => 'Connexion requise',
            403 => 'Accès refusé',
            404 => 'Page introuvable',
            405 => 'Méthode non autorisée',
            419 => 'Jeton invalide',
            422 => 'Informations invalides',
            429 => 'Trop de tentatives',
            default => 'Erreur',
        };
    }
}
