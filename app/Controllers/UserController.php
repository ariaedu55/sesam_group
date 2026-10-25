<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Core\Auth\Permission;
use Sesam\Core\HttpException;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\User;
use Sesam\Services\AuthService;
use Sesam\Services\NotificationService;
use Sesam\Services\UserService;

final class UserController extends Controller
{
    use RendersAppArea;

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly UserService $users,
        private readonly TenantContext $context,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($views);
    }

    public function index(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::USERS_VIEW);
        $query = trim((string) $request->input('q', ''));
        $page = (int) $request->input('page', 1);
        $list = $this->users->paginate($actor, $query, $page);

        $isTenant = $actor->tenantId() !== null;

        return $this->renderAppArea('staff/users/index', [
            'title' => ($isTenant ? 'Équipe' : 'Utilisateurs') . ' — SESAM',
            'navTitle' => $isTenant ? 'Équipe' : 'Utilisateurs',
            'current' => 'utilisateurs',
            'list' => $list,
            'canCreate' => $actor->can(Permission::USERS_CREATE),
        ], $actor);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::USERS_CREATE);

        return $this->formView($actor, null, [
            'full_name' => '',
            'email' => '',
            'is_active' => '1',
            'roles' => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::USERS_CREATE);
        $input = $this->formInput($request);
        try {
            $this->users->create($actor, $input);
        } catch (ValidationException $exception) {
            return $this->formView($actor, null, $input, $exception->errors(), 422);
        }

        $this->session->put('status', 'Le compte a été créé.');

        return Response::redirect('/utilisateurs');
    }

    public function editForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::USERS_VIEW);
        $user = $this->users->find($actor, $this->id($request));

        return $this->formView($actor, $user, [
            'full_name' => $user->fullName(),
            'email' => $user->email(),
            'is_active' => $user->isActive() ? '1' : '0',
            'roles' => $user->roles(),
        ]);
    }

    public function update(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::USERS_UPDATE);
        $id = $this->id($request);
        $input = $this->formInput($request);
        try {
            $this->users->update($actor, $id, $input);
        } catch (ValidationException $exception) {
            $user = $this->users->find($actor, $id);

            return $this->formView($actor, $user, $input, $exception->errors(), 422);
        }

        $this->session->put('status', 'Le compte a été mis à jour.');

        return Response::redirect('/utilisateurs/' . $id);
    }

    public function resetLink(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::USERS_SET_PASSWORD);
        $id = $this->id($request);
        $user = $this->users->find($actor, $id);
        $token = $this->auth->issueResetToken($user->id());
        $mailed = $this->auth->sendResetLink($user, $token);
        $this->session->put('status', ($mailed ? 'Lien de réinitialisation envoyé par e-mail à ' . $user->email() . '. ' : 'Lien de réinitialisation créé (l\'e-mail n\'a pas pu partir). ')
            . 'Il expire dans une heure. Vous pouvez aussi le copier maintenant : il ne sera plus affiché.');
        $this->session->put('reset_link', '/mot-de-passe/reinitialiser/' . $token);

        return Response::redirect('/utilisateurs/' . $id);
    }

    /**
     * @return array<string, mixed>
     */
    private function formInput(Request $request): array
    {
        return [
            'full_name' => $request->input('full_name'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'is_active' => $request->input('is_active', '0'),
            'roles' => $request->input('roles', []),
        ];
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function formView(User $actor, ?User $user, array $old, array $errors = [], int $status = 200): Response
    {
        return $this->renderAppArea('staff/users/form', [
            'title' => ($user === null ? 'Nouvel utilisateur' : 'Modifier l\'utilisateur') . ' — SESAM',
            'navTitle' => $user === null ? 'Nouvel utilisateur' : 'Modifier',
            'current' => 'utilisateurs',
            'edited' => $user,
            'old' => $old,
            'errors' => $errors,
            'roles' => $this->users->catalogRoles($actor),
            'canAssignRoles' => $actor->can(Permission::USERS_ASSIGN_ROLES),
            'canSetPassword' => $actor->can(Permission::USERS_SET_PASSWORD),
            'canDeactivate' => $actor->can(Permission::USERS_DEACTIVATE),
        ], $actor, $status);
    }

    private function id(Request $request): int
    {
        $raw = (string) $request->attribute('id', '');
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new HttpException(404, 'Utilisateur introuvable.');
        }

        return (int) $raw;
    }
}
