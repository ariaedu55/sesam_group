<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Auth\Role;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Core\HttpException;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\User;
use Sesam\Repositories\RoleRepository;
use Sesam\Repositories\UserRepository;
use Sesam\Services\UserService;

final class UserServiceTest extends TestCase
{
    /** @var list<string> */
    private array $emails = [];

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        foreach ($this->emails as $email) {
            $statement = $pdo->prepare('DELETE FROM users WHERE email = :email');
            $statement->execute(['email' => $email]);
        }
    }

    public function testSuperAdminCreatesUserAndAdminCannotGrantSuperAdmin(): void
    {
        $service = new UserService($this->config()['db']);
        $super = $this->actor($this->createUser('super', [Role::SUPER_ADMIN]));
        $createdId = $service->create($super, [
            'full_name' => 'Admin test',
            'email' => $this->email(),
            'password' => 'AdminPass-1234',
            'is_active' => '1',
            'roles' => [Role::ADMIN],
        ]);
        $admin = $this->actor($createdId);

        try {
            $service->create($admin, [
                'full_name' => 'Intrus',
                'email' => $this->email(),
                'password' => 'IntrusPass-123',
                'is_active' => '1',
                'roles' => [Role::SUPER_ADMIN],
            ]);
            $this->fail('Un Admin ne doit pas attribuer Super Admin.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('roles', $exception->errors());
        }
    }

    public function testStaffCannotListUsers(): void
    {
        $service = new UserService($this->config()['db']);
        $staff = $this->actor($this->createUser('staff', [Role::STAFF]));

        $this->expectException(HttpException::class);
        $service->paginate($staff, '', 1);
    }

    public function testASuperAdminCanDeactivateAnotherSuperAdmin(): void
    {
        $service = new UserService($this->config()['db']);
        $actor = $this->actor($this->createUser('sa-a', [Role::SUPER_ADMIN]));
        $otherId = $this->createUser('sa-b', [Role::SUPER_ADMIN]);
        $other = $this->actor($otherId);

        $service->update($actor, $otherId, [
            'full_name' => $other->fullName(),
            'email' => $other->email(),
            'is_active' => '0',
            'roles' => [Role::SUPER_ADMIN],
        ]);

        $updated = $this->actor($otherId);
        $this->assertFalse($updated->isActive());
    }

    public function testShortPasswordIsRejected(): void
    {
        $service = new UserService($this->config()['db']);
        $super = $this->actor($this->createUser('sa-pass', [Role::SUPER_ADMIN]));

        $this->expectException(ValidationException::class);
        $service->create($super, [
            'full_name' => 'Court',
            'email' => $this->email(),
            'password' => 'short',
            'is_active' => '1',
            'roles' => [Role::STAFF],
        ]);
    }

    /**
     * @param list<string> $roleSlugs
     */
    private function createUser(string $prefix, array $roleSlugs): int
    {
        $email = $this->email($prefix);
        $pdo = $this->pdo();
        $users = new UserRepository($pdo);
        $roles = new RoleRepository($pdo);
        $id = $users->insert([
            'full_name' => 'Test ' . $prefix,
            'email' => $email,
            'password_hash' => password_hash('ValidPass-1234', PASSWORD_DEFAULT),
            'is_active' => 1,
        ]);
        $roles->replaceUserRoles($id, $roles->idsForSlugs($roleSlugs));

        return $id;
    }

    private function actor(int $id): User
    {
        $user = (new UserRepository($this->pdo()))->findById($id);
        $this->assertInstanceOf(User::class, $user);

        return $user;
    }

    private function email(string $prefix = 'user'): string
    {
        $email = 'p4-' . $prefix . '-' . bin2hex(random_bytes(3)) . '@example.com';
        $this->emails[] = $email;

        return $email;
    }

    private function pdo(): \PDO
    {
        return Connection::make($this->config()['db']);
    }

    /**
     * @return array{db: array{host: string, port: string, name: string, user: string, password: string}}
     */
    private function config(): array
    {
        Env::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

        /** @var array{db: array{host: string, port: string, name: string, user: string, password: string}} $config */
        $config = require dirname(__DIR__) . '/config/app.php';

        return $config;
    }
}
