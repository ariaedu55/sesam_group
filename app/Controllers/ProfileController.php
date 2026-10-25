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
use Sesam\Core\Validation\ValidationException;
use Sesam\Services\AuthService;
use Sesam\Services\NotificationService;
use Sesam\Services\ProfileService;

final class ProfileController extends Controller
{
    use RendersAppArea;

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantContext $context,
        private readonly ProfileService $profiles,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($views);
    }

    public function show(Request $request): Response
    {
        $user = $this->auth->requireUser();

        return $this->renderAppArea('app/profile/form', [
            'title' => 'Mon profil — SESAM',
            'navTitle' => 'Profil',
            'current' => 'profil',
            'old' => [
                'full_name' => $user->fullName(),
                'email' => $user->email(),
            ],
            'errors' => [],
        ], $user);
    }

    public function update(Request $request): Response
    {
        $user = $this->auth->requireUser();
        $input = [
            'full_name' => $request->input('full_name'),
            'current_password' => $request->input('current_password'),
            'password' => $request->input('password'),
            'password_confirmation' => $request->input('password_confirmation'),
        ];
        $errors = [];
        try {
            $this->profiles->update($user, $input);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
        }

        $passwordFilled = is_string($input['password']) && trim($input['password']) !== '';
        if ($passwordFilled && $errors === []) {
            try {
                $this->profiles->changePassword($user, $input);
            } catch (ValidationException $exception) {
                $errors = array_merge($errors, $exception->errors());
            }
        }

        if ($errors !== []) {
            return $this->renderAppArea('app/profile/form', [
                'title' => 'Mon profil — SESAM',
                'navTitle' => 'Profil',
                'current' => 'profil',
                'old' => $input,
                'errors' => $errors,
            ], $user, 422);
        }

        if ($passwordFilled) {
            $this->auth->logout();
            $this->session->put('status', 'Mot de passe mis à jour. Reconnectez-vous.');

            return Response::redirect('/connexion');
        }

        $this->session->put('status', 'Votre profil a été mis à jour.');

        return Response::redirect('/profil');
    }
}
