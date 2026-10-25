<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Database\Connection;
use Sesam\Core\Validation\ValidationException;
use Sesam\Core\Validation\Validator;
use Sesam\Models\User;
use Sesam\Repositories\UserRepository;

final class ProfileService extends Service
{
    private readonly UserRepository $users;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        array $database,
        private readonly Validator $validator = new Validator(),
    ) {
        $this->users = new UserRepository(Connection::make($database));
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(User $actor, array $input): void
    {
        $data = $this->validator->validate($input, [
            'full_name' => 'required|string|max:120',
        ]);
        $this->users->update($actor->id(), [
            'full_name' => (string) $data['full_name'],
            'email' => $actor->email(),
            'is_active' => $actor->isActive() ? 1 : 0,
        ]);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function changePassword(User $actor, array $input): void
    {
        $data = $this->validator->validate($input, [
            'current_password' => 'required|string|max:255',
            'password' => 'required|string|min:12|max:255',
            'password_confirmation' => 'required|string|max:255',
        ]);
        if ((string) $data['password'] !== (string) $data['password_confirmation']) {
            throw new ValidationException([
                'password_confirmation' => 'Les deux mots de passe doivent être identiques.',
            ]);
        }
        if (!password_verify((string) $data['current_password'], $actor->passwordHash())) {
            throw new ValidationException([
                'current_password' => 'Mot de passe actuel incorrect.',
            ]);
        }
        $this->users->updatePassword($actor->id(), password_hash((string) $data['password'], PASSWORD_DEFAULT));
    }
}
