<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Core\HttpException;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Validation\ValidationException;
use Sesam\Services\AuthService;

final class AuthController extends Controller
{
    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
    ) {
        parent::__construct($views);
    }

    public function loginForm(Request $request): Response
    {
        return $this->loginView();
    }

    public function login(Request $request): Response
    {
        try {
            $this->auth->login([
                'email' => $request->input('email'),
                'password' => $request->input('password'),
            ], $request->ip());
        } catch (ValidationException $exception) {
            return $this->loginView([
                'email' => $request->input('email'),
            ], $exception->errors(), 422);
        }

        return Response::redirect('/espace');
    }

    public function googleLogin(Request $request): Response
    {
        $from = (string) $request->input('from', 'login');
        $token = $request->input('id_token');
        try {
            $target = $this->auth->completeGoogleIdToken(
                is_string($token) ? $token : '',
                (string) $request->input('service', ''),
                $request->ip(),
            );
        } catch (ValidationException $exception) {
            $this->session->put('status', (string) array_values($exception->errors())[0]);

            return Response::redirect($from === 'signup' ? '/commencer' : '/connexion');
        } catch (HttpException $exception) {
            $this->session->put('status', $exception->getMessage());

            return Response::redirect($from === 'signup' ? '/commencer' : '/connexion');
        }

        return Response::redirect($target);
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();

        return Response::redirect('/connexion');
    }

    public function forgotForm(Request $request): Response
    {
        return $this->forgotView();
    }

    public function forgot(Request $request): Response
    {
        try {
            $this->auth->requestReset([
                'email' => $request->input('email'),
            ], $request->ip());
        } catch (ValidationException $exception) {
            return $this->forgotView([
                'email' => $request->input('email'),
            ], $exception->errors(), 422);
        }

        $this->session->put('status', 'Si un compte correspond à cette adresse, un e-mail avec un lien de réinitialisation vient de partir. Il est valable une heure. Pensez à regarder dans les courriers indésirables.');

        return Response::redirect('/mot-de-passe');
    }

    public function resetForm(Request $request): Response
    {
        return $this->resetView((string) $request->attribute('token', ''));
    }

    public function reset(Request $request): Response
    {
        $token = (string) $request->attribute('token', '');
        try {
            $this->auth->resetPassword($token, [
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
            ]);
        } catch (ValidationException $exception) {
            return $this->resetView($token, $exception->errors(), 422);
        }

        $this->session->put('status', 'Le mot de passe a été mis à jour. Connectez-vous.');

        return Response::redirect('/connexion');
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function loginView(array $old = [], array $errors = [], int $status = 200): Response
    {
        return $this->render('auth/login', [
            'title' => 'Connexion — SESAM',
            'csrf' => $this->csrf->token(),
            'old' => $old,
            'errors' => $errors,
            'status' => $this->pullStatus(),
            'googleEnabled' => $this->auth->googleEnabled(),
            'googleConfig' => $this->auth->googleWebConfig(),
        ], $status, 'layouts/auth');
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function forgotView(array $old = [], array $errors = [], int $status = 200): Response
    {
        return $this->render('auth/forgot', [
            'title' => 'Mot de passe oublié — SESAM',
            'csrf' => $this->csrf->token(),
            'old' => $old,
            'errors' => $errors,
            'status' => $this->pullStatus(),
        ], $status, 'layouts/auth');
    }

    /**
     * @param array<string, string> $errors
     */
    private function resetView(string $token, array $errors = [], int $status = 200): Response
    {
        return $this->render('auth/reset', [
            'title' => 'Nouveau mot de passe — SESAM',
            'csrf' => $this->csrf->token(),
            'token' => $token,
            'errors' => $errors,
        ], $status, 'layouts/auth');
    }

    private function pullStatus(): string
    {
        $status = $this->session->get('status', '');
        $this->session->forget('status');

        return is_string($status) ? $status : '';
    }
}
