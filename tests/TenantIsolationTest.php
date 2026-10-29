<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Auth\Role;
use Sesam\Core\Bootstrap;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Core\HttpException;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Models\Tenant;
use Sesam\Models\User;
use Sesam\Repositories\RoleRepository;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\UserRepository;
use Sesam\Services\UserService;

final class TenantIsolationTest extends TestCase
{
    /** @var list<string> */
    private array $emails = [];

    /** @var list<int> */
    private array $tenantIds = [];

    /** @var list<string> */
    private array $ips = [];

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        foreach ($this->emails as $email) {
            $statement = $pdo->prepare('DELETE FROM users WHERE email = :email');
            $statement->execute(['email' => $email]);
        }
        foreach ($this->tenantIds as $id) {
            $pdo->prepare('DELETE FROM tenant_settings WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM tenants WHERE id = :id')->execute(['id' => $id]);
        }
        foreach ($this->ips as $ip) {
            $statement = $pdo->prepare('DELETE FROM auth_attempts WHERE ip = :ip');
            $statement->execute(['ip' => $ip]);
        }
    }

    public function testOwnerCannotReadOrUpdateAnotherTenantUser(): void
    {
        $tenantA = $this->createTenant('alpha');
        $tenantB = $this->createTenant('beta');
        $ownerA = $this->createUser('owner-a', [Role::TENANT_OWNER], $tenantA->id());
        $userB = $this->createUser('user-b', [Role::STAFF], $tenantB->id());
        $context = new TenantContext();
        $context->bind($tenantA);
        $service = new UserService($this->config()['db'], $context);
        $actor = $this->actor($ownerA);

        try {
            $service->find($actor, $userB);
            $this->fail('Le tenant A ne doit pas lire un utilisateur du tenant B.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->status());
        }

        try {
            $service->update($actor, $userB, [
                'full_name' => 'Intrus',
                'email' => $this->email('hack'),
                'is_active' => '1',
                'roles' => [Role::STAFF],
                'tenant_id' => $tenantA->id(),
            ]);
            $this->fail('Le tenant A ne doit pas modifier un utilisateur du tenant B.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->status());
        }

        $list = $service->paginate($actor, '', 1);
        foreach ($list['users'] as $user) {
            $this->assertSame($tenantA->id(), $user->tenantId());
        }
    }

    public function testPostedTenantIdDoesNotMoveACreatedUser(): void
    {
        $tenantA = $this->createTenant('gamma');
        $tenantB = $this->createTenant('delta');
        $ownerA = $this->createUser('owner-g', [Role::TENANT_OWNER], $tenantA->id());
        $context = new TenantContext();
        $context->bind($tenantA);
        $service = new UserService($this->config()['db'], $context);
        $email = $this->email('kept');
        $id = $service->create($this->actor($ownerA), [
            'full_name' => 'Équipe A',
            'email' => $email,
            'password' => 'TenantPass-123',
            'is_active' => '1',
            'roles' => [Role::STAFF],
            'tenant_id' => $tenantB->id(),
        ]);
        $created = $this->actor($id);
        $this->assertSame($tenantA->id(), $created->tenantId());
        $this->assertNotSame($tenantB->id(), $created->tenantId());
    }

    public function testSesamListDoesNotIncludeTenantUsers(): void
    {
        $tenant = $this->createTenant('epsilon');
        $this->createUser('hidden', [Role::TENANT_OWNER], $tenant->id());
        $superId = $this->createUser('sa', [Role::SUPER_ADMIN], null);
        $service = new UserService($this->config()['db'], new TenantContext());
        $list = $service->paginate($this->actor($superId), 'hidden', 1);
        foreach ($list['users'] as $user) {
            $this->assertNull($user->tenantId());
        }
    }

    public function testTenantOwnerCannotOpenCommercesAndSesamCannotOpenSettings(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant('zeta');
        $email = $this->email('zeta-owner');
        $this->createUser('zeta-owner', [Role::TENANT_OWNER], $tenant->id(), $email, 'ValidPass-1234');
        $this->login($kernel, $email, 'ValidPass-1234');

        $this->assertSame(403, $kernel->handle(new Request('GET', '/commerces'))->status());
        // Un salon règle tout dans « Mon salon » : /parametres y renvoie.
        $settings = $kernel->handle(new Request('GET', '/parametres'));
        $this->assertSame(302, $settings->status());
        $this->assertSame('/reservation/salon', $settings->headers()['Location'] ?? '');
        $salon = $kernel->handle(new Request('GET', '/reservation/salon'));
        $this->assertSame(200, $salon->status());
        $this->assertStringContainsString("Horaires d'ouverture", html_entity_decode($salon->content(), ENT_QUOTES, 'UTF-8'));

        $kernel->handle($this->post('/deconnexion', ['_csrf' => $this->csrf($kernel, '/espace')]));

        $sesamEmail = $this->email('sesam-sa');
        $this->createUser('sesam-sa', [Role::SUPER_ADMIN], null, $sesamEmail, 'ValidPass-1234');
        $this->login($kernel, $sesamEmail, 'ValidPass-1234');
        $this->assertSame(200, $kernel->handle(new Request('GET', '/commerces'))->status());
        $this->assertSame(403, $kernel->handle(new Request('GET', '/parametres'))->status());
        $home = $kernel->handle(new Request('GET', '/espace'));
        $this->assertStringContainsString('Commerces par module', $home->content());
    }

    public function testSuspendedTenantCannotLogin(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant('theta', 'suspended');
        $email = $this->email('theta');
        $this->createUser('theta', [Role::MANAGER], $tenant->id(), $email, 'ValidPass-1234');
        $denied = $kernel->handle($this->post('/connexion', [
            '_csrf' => $this->csrf($kernel),
            'email' => $email,
            'password' => 'ValidPass-1234',
        ]));
        $this->assertSame(422, $denied->status());
        $this->assertStringContainsString('E-mail ou mot de passe incorrect.', $denied->content());
        $this->assertStringNotContainsString('suspendu', strtolower($denied->content()));
    }

    public function testTenantEspaceShowsSectorDashboard(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant('iota');
        $email = $this->email('iota');
        $this->createUser('iota', [Role::MANAGER], $tenant->id(), $email, 'ValidPass-1234');
        $this->login($kernel, $email, 'ValidPass-1234');
        $home = $kernel->handle(new Request('GET', '/espace'));
        $this->assertSame(200, $home->status());
        $this->assertStringContainsString($tenant->displayName(), $home->content());
        $html = html_entity_decode($home->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString("Rendez-vous aujourd'hui", $html);
        $this->assertStringContainsString('Votre lien de réservation', $html);
        $this->assertStringContainsString('/reservation/agenda', $html);
        $this->assertStringContainsString('Prochain rendez-vous', $html);
        $this->assertStringContainsString('Programme du jour', $html);
        $this->assertStringContainsString('Votre semaine', $html);
        $this->assertStringContainsString('Acomptes reçus ce mois', $html);
        $this->assertDoesNotMatchRegularExpression('#href="[^"]*/paiements"#', $html, 'Un salon n\'a plus d\'entrée Paiements dans le menu.');
        $this->assertStringNotContainsString('Gérer les commerces', $home->content());
    }

    public function testCreatingACommerceCreatesItsOwner(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $sesamEmail = $this->email('create-sa');
        $this->createUser('create-sa', [Role::SUPER_ADMIN], null, $sesamEmail, 'ValidPass-1234');
        $this->login($kernel, $sesamEmail, 'ValidPass-1234');
        $ownerEmail = $this->email('new-owner');
        $slug = 'p5-new-' . bin2hex(random_bytes(3));
        $created = $kernel->handle($this->post('/commerces', [
            '_csrf' => $this->csrf($kernel, '/commerces/nouveau'),
            'name' => 'Atelier test',
            'display_name' => 'Atelier vitrine',
            'slug' => $slug,
            'sector' => Sector::BOUTIQUE,
            'status' => 'active',
            'owner_full_name' => 'Responsable test',
            'owner_email' => $ownerEmail,
            'owner_password' => 'OwnerPass-1234',
            'tenant_id' => '999',
        ]));
        $this->assertSame(302, $created->status());
        $tenant = (new TenantRepository($this->pdo()))->findBySlug($slug);
        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->tenantIds[] = $tenant->id();
        $this->assertSame('/commerces/' . $tenant->id(), $created->headers()['Location'] ?? '');
        // Par défaut, un commerce créé par SESAM démarre avec un essai gratuit d'un mois.
        $statement = $this->pdo()->prepare('SELECT status, period_end FROM tenant_subscriptions WHERE tenant_id = ?');
        $statement->execute([$tenant->id()]);
        $this->assertSame(['status' => 'trial', 'period_end' => gmdate('Y-m-d', strtotime('+30 days'))], $statement->fetch(\PDO::FETCH_ASSOC));
        $owner = (new UserRepository($this->pdo()))->findByEmail($ownerEmail);
        $this->assertInstanceOf(User::class, $owner);
        $this->assertSame($tenant->id(), $owner->tenantId());
        $this->assertTrue($owner->hasRole(Role::TENANT_OWNER));

        $kernel->handle($this->post('/deconnexion', ['_csrf' => $this->csrf($kernel, '/espace')]));
        $this->login($kernel, $ownerEmail, 'OwnerPass-1234');
        $home = $kernel->handle(new Request('GET', '/espace'));
        $this->assertStringContainsString('Atelier vitrine', $home->content());
        $this->assertSame(403, $kernel->handle(new Request('GET', '/commerces'))->status());
    }

    private function createTenant(string $prefix, string $status = 'active'): Tenant
    {
        $slug = 'p5-' . $prefix . '-' . bin2hex(random_bytes(3));
        $id = (new TenantRepository($this->pdo()))->insert([
            'slug' => $slug,
            'name' => 'Commerce ' . $prefix,
            'sector' => Sector::RESERVATION,
            'status' => $status,
            'display_name' => 'Marque ' . $prefix,
        ]);
        $this->tenantIds[] = $id;
        $tenant = (new TenantRepository($this->pdo()))->findById($id);
        $this->assertInstanceOf(Tenant::class, $tenant);

        return $tenant;
    }

    /**
     * @param list<string> $roleSlugs
     */
    private function createUser(string $prefix, array $roleSlugs, ?int $tenantId, ?string $email = null, string $password = 'ValidPass-1234'): int
    {
        $email ??= $this->email($prefix);
        $users = new UserRepository($this->pdo());
        $roles = new RoleRepository($this->pdo());
        $id = $users->insert([
            'tenant_id' => $tenantId,
            'full_name' => 'Test ' . $prefix,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
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

    private function login(\Sesam\Core\Kernel $kernel, string $email, string $password): void
    {
        $login = $kernel->handle($this->post('/connexion', [
            '_csrf' => $this->csrf($kernel),
            'email' => $email,
            'password' => $password,
        ]));
        $this->assertSame(302, $login->status());
        $this->assertSame('/espace', $login->headers()['Location'] ?? '');
    }

    private function csrf(\Sesam\Core\Kernel $kernel, string $path = '/connexion'): string
    {
        $page = $kernel->handle(new Request('GET', $path));
        preg_match('/name="_csrf" value="([^"]+)"/', $page->content(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        return (string) $matches[1];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(string $path, array $body): Request
    {
        return new Request('POST', $path, [], $body, [], [], false, $this->ip());
    }

    private function ip(): string
    {
        $ip = '198.51.100.' . random_int(1, 250);
        $this->ips[] = $ip;

        return $ip;
    }

    private function email(string $prefix = 'user'): string
    {
        $email = 'p5-' . $prefix . '-' . bin2hex(random_bytes(3)) . '@example.com';
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
