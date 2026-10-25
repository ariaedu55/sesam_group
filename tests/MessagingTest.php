<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Auth\Role;
use Sesam\Core\Bootstrap;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Core\Messaging\MessageChannel;
use Sesam\Core\Messaging\TemplateRenderer;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\UserRepository;
use Sesam\Services\MessagingService;

final class MessagingTest extends TestCase
{
    /** @var list<string> */
    private array $emails = [];

    /** @var list<int> */
    private array $tenantIds = [];

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        foreach ($this->emails as $email) {
            $pdo->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => $email]);
        }
        foreach ($this->tenantIds as $id) {
            $pdo->prepare('DELETE FROM message_deliveries WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM message_reminders WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM notifications WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM users WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM tenant_settings WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM tenants WHERE id = :id')->execute(['id' => $id]);
        }
    }

    public function testTemplateRendererSubstitutesVariables(): void
    {
        $text = TemplateRenderer::render('Bonjour {{name}} — {{commerce}}', [
            'name' => 'Awa',
            'commerce' => 'Spa',
        ]);
        $this->assertSame('Bonjour Awa — Spa', $text);
    }

    public function testInternalSendCreatesInboxAndHistory(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant();
        $email = $this->email('msg');
        $this->createUser(Role::TENANT_OWNER, $tenant, $email);
        $this->login($kernel, $email);

        $send = $kernel->handle($this->post('/notifications/envoyer', [
            '_csrf' => $this->csrf($kernel, '/notifications/envoyer'),
            'channel' => MessageChannel::INTERNAL,
            'template_slug' => 'test_message',
            'recipient' => 'Équipe',
            'name' => 'Test',
            'amount' => '',
        ]));
        $this->assertSame(302, $send->status());

        $inbox = $kernel->handle(new Request('GET', '/notifications'));
        $this->assertStringContainsString('test interne', strtolower($inbox->content()));

        $history = $kernel->handle(new Request('GET', '/notifications/historique'));
        $this->assertStringContainsString('Envoyé', $history->content());
    }

    public function testReminderProcessingInMockMode(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createUserModel(Role::TENANT_OWNER, $tenant, $this->email('rem'));
        $context = new TenantContext();
        $context->bind($tenant);
        $config = require dirname(__DIR__) . '/config/notifications.php';
        $service = new MessagingService($this->config()['db'], $context, $config);
        $service->scheduleReminder($owner, [
            'channel' => MessageChannel::EMAIL,
            'recipient' => 'client@example.com',
            'template_slug' => 'test_message',
            'send_at' => gmdate('Y-m-d H:i:s', time() - 60),
            'name' => 'Rappel',
        ]);
        $this->assertSame(1, $service->processDueReminders());
    }

    public function testStaffCannotSend(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant();
        $email = $this->email('staff-msg');
        $this->createUser(Role::STAFF, $tenant, $email);
        $this->login($kernel, $email);

        $this->assertSame(403, $kernel->handle(new Request('GET', '/notifications/envoyer'))->status());
        $this->assertSame(200, $kernel->handle(new Request('GET', '/notifications/historique'))->status());
    }

    private function createTenant(): \Sesam\Models\Tenant
    {
        $slug = 'p9-' . bin2hex(random_bytes(3));
        $id = (new TenantRepository($this->pdo()))->insert([
            'slug' => $slug,
            'name' => 'Msg test',
            'sector' => Sector::RESERVATION,
            'status' => 'active',
            'display_name' => 'Msg ' . $slug,
        ]);
        $this->tenantIds[] = $id;
        $tenant = (new TenantRepository($this->pdo()))->findById($id);
        $this->assertInstanceOf(\Sesam\Models\Tenant::class, $tenant);

        return $tenant;
    }

    private function createUser(string $role, \Sesam\Models\Tenant $tenant, string $email): void
    {
        $this->createUserModel($role, $tenant, $email);
    }

    private function createUserModel(string $role, \Sesam\Models\Tenant $tenant, string $email): \Sesam\Models\User
    {
        $users = new UserRepository($this->pdo());
        $roles = new \Sesam\Repositories\RoleRepository($this->pdo());
        $id = $users->insert([
            'tenant_id' => $tenant->id(),
            'full_name' => 'Msg user',
            'email' => $email,
            'password_hash' => password_hash('ValidPass-1234', PASSWORD_DEFAULT),
            'is_active' => 1,
        ]);
        $roles->replaceUserRoles($id, $roles->idsForSlugs([$role]));
        $this->emails[] = $email;
        $user = $users->findById($id);
        $this->assertInstanceOf(\Sesam\Models\User::class, $user);

        return $user;
    }

    private function login(\Sesam\Core\Kernel $kernel, string $email): void
    {
        $login = $kernel->handle($this->post('/connexion', [
            '_csrf' => $this->csrf($kernel),
            'email' => $email,
            'password' => 'ValidPass-1234',
        ], '198.51.100.' . random_int(1, 250)));
        $this->assertSame(302, $login->status());
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
    private function post(string $path, array $body, string $ip = '198.51.100.42'): Request
    {
        return new Request('POST', $path, [], $body, [], [], false, $ip);
    }

    private function email(string $prefix): string
    {
        return 'p9-' . $prefix . '-' . bin2hex(random_bytes(3)) . '@example.com';
    }

    /** @return array{db: array{host: string, port: string, name: string, user: string, password: string}} */
    private function config(): array
    {
        Env::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

        return require dirname(__DIR__) . '/config/app.php';
    }

    private function pdo(): \PDO
    {
        return Connection::make($this->config()['db']);
    }
}
