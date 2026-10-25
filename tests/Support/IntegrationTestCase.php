<?php

declare(strict_types=1);

namespace Sesam\Tests\Support;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Bootstrap;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Core\Kernel;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Repositories\RoleRepository;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\UserRepository;

/**
 * Base des tests d'intégration de la phase 20 : données préfixées « p20- », supprimées après chaque test.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected const PREFIX = 'p20-';
    protected const PASSWORD = 'ValidPass-1234';

    /** @var list<int> */
    private array $tenantIds = [];

    /** @var list<string> */
    private array $emails = [];

    /** @var list<string> */
    private array $ips = [];

    /** @var list<string> */
    protected array $webhookEvents = [];

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            if ($this->tenantIds !== []) {
                $ids = implode(',', array_map('intval', $this->tenantIds));
                foreach ($this->tenantTables() as $table) {
                    $pdo->exec('DELETE FROM `' . $table . '` WHERE tenant_id IN (' . $ids . ')');
                }
                $pdo->exec('DELETE FROM tenants WHERE id IN (' . $ids . ')');
            }
            foreach ($this->emails as $email) {
                $id = $this->value('SELECT id FROM users WHERE email = ?', [$email]);
                if ($id !== false) {
                    $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$id]);
                    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                }
            }
            foreach ($this->ips as $ip) {
                $pdo->prepare('DELETE FROM auth_attempts WHERE ip = ?')->execute([$ip]);
            }
            foreach ($this->webhookEvents as $eventId) {
                $pdo->prepare('DELETE FROM payment_webhook_events WHERE event_id = ?')->execute([$eventId]);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    protected function kernel(): Kernel
    {
        return Bootstrap::kernel(dirname(__DIR__, 2));
    }

    protected function tenant(string $sector, string $status = 'active'): int
    {
        $slug = self::PREFIX . bin2hex(random_bytes(4));
        $id = (new TenantRepository($this->pdo()))->insert([
            'slug' => $slug,
            'name' => 'Commerce ' . $slug,
            'sector' => $sector,
            'status' => $status,
            'display_name' => 'Commerce ' . $slug,
        ]);
        $this->tenantIds[] = $id;

        return $id;
    }

    /**
     * Commerce et compte créés par l'application elle-même (inscription) : supprimés après le test.
     */
    protected function track(int $tenantId, ?string $email = null): void
    {
        $this->tenantIds[] = $tenantId;
        if ($email !== null) {
            $this->emails[] = $email;
        }
    }

    protected function slug(int $tenantId): string
    {
        return (string) $this->value('SELECT slug FROM tenants WHERE id = ?', [$tenantId]);
    }

    /**
     * @param list<string> $roles
     */
    protected function user(array $roles, ?int $tenantId): string
    {
        $email = self::PREFIX . bin2hex(random_bytes(5)) . '@example.com';
        $this->emails[] = $email;
        $repository = new RoleRepository($this->pdo());
        $id = (new UserRepository($this->pdo()))->insert([
            'tenant_id' => $tenantId,
            'full_name' => 'Compte ' . self::PREFIX,
            'email' => $email,
            'password_hash' => password_hash(self::PASSWORD, PASSWORD_DEFAULT),
            'is_active' => 1,
        ]);
        $repository->replaceUserRoles($id, $repository->idsForSlugs($roles));

        return $email;
    }

    protected function userId(string $email): int
    {
        return (int) $this->value('SELECT id FROM users WHERE email = ?', [$email]);
    }

    /**
     * @param list<string> $roles
     */
    protected function loginAs(Kernel $kernel, array $roles, ?int $tenantId): string
    {
        $email = $this->user($roles, $tenantId);
        $response = $kernel->handle($this->post('/connexion', [
            '_csrf' => $this->csrf($kernel, '/connexion'),
            'email' => $email,
            'password' => self::PASSWORD,
        ]));
        $this->assertSame(302, $response->status(), 'Connexion refusée pour ' . implode(',', $roles));

        return $email;
    }

    protected function logout(Kernel $kernel): void
    {
        $kernel->handle($this->post('/deconnexion', ['_csrf' => $this->csrf($kernel, '/profil')]));
    }

    protected function csrf(Kernel $kernel, string $path): string
    {
        $page = $kernel->handle(new Request('GET', $path));
        preg_match('/name="_csrf" value="([^"]+)"/', $page->content(), $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'Pas de jeton CSRF sur ' . $path . ' (' . $page->status() . ')');

        return (string) $matches[1];
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function post(string $path, array $body, ?string $ip = null): Request
    {
        $ip ??= '198.51.100.' . random_int(1, 250);
        $this->ips[] = $ip;

        return new Request('POST', $path, [], $body, [], [], false, $ip);
    }

    /**
     * POST authentifié : le jeton vient de la session courante.
     *
     * @param array<string, mixed> $body
     */
    protected function submit(Kernel $kernel, string $path, array $body = [], string $tokenPage = '/profil'): Response
    {
        return $kernel->handle($this->post($path, ['_csrf' => $this->csrf($kernel, $tokenPage)] + $body));
    }

    protected function get(Kernel $kernel, string $path): Response
    {
        return $kernel->handle(new Request('GET', $path));
    }

    /**
     * @param array<string, int|string|null> $data
     */
    protected function insert(string $table, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $columns = $this->columns($table);
        foreach (['created_at', 'updated_at'] as $stamp) {
            if (in_array($stamp, $columns, true) && !array_key_exists($stamp, $data)) {
                $data[$stamp] = $now;
            }
        }
        $keys = array_keys($data);
        $this->pdo()->prepare(
            'INSERT INTO `' . $table . '` (' . implode(', ', $keys) . ') VALUES (:' . implode(', :', $keys) . ')'
        )->execute($data);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param list<int|string|null> $params
     */
    protected function value(string $sql, array $params = []): mixed
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn();
    }

    protected function client(int $tenantId, string $name = 'Client p20', ?string $phone = null): int
    {
        return $this->insert('clients', [
            'tenant_id' => $tenantId,
            'full_name' => $name,
            'phone' => $phone,
            'status' => 'active',
        ]);
    }

    protected function payment(int $tenantId, string $status = 'pending', int $amount = 5000): int
    {
        return $this->insert('payments', [
            'tenant_id' => $tenantId,
            'reference' => bin2hex(random_bytes(16)),
            'amount_minor' => $amount,
            'status' => $status,
            'gateway' => 'naboopay',
            'mode' => 'sandbox',
            'description' => 'Paiement ' . self::PREFIX,
            'paid_at' => $status === 'success' ? gmdate('Y-m-d H:i:s') : null,
        ]);
    }

    /**
     * @return array{db: array{host: string, port: string, name: string, user: string, password: string}, url: string}
     */
    protected function config(): array
    {
        Env::load(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env');

        return require dirname(__DIR__, 2) . '/config/app.php';
    }

    protected function pdo(): \PDO
    {
        return Connection::make($this->config()['db']);
    }

    /**
     * @return list<string>
     */
    private function columns(string $table): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $statement->execute([$table]);

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * @return list<string>
     */
    private function tenantTables(): array
    {
        $statement = $this->pdo()->query(
            "SELECT TABLE_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'tenant_id' AND TABLE_NAME <> 'users'"
        );

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }
}
