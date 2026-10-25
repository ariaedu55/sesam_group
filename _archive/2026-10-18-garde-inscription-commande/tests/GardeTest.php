<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Auth\Role;
use Sesam\Core\Bootstrap;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Core\Garde\ExpiryStatus;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\UserRepository;

final class GardeTest extends TestCase
{
    /** @var list<string> */
    private array $emails = [];

    /** @var list<int> */
    private array $tenantIds = [];

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        foreach ($this->tenantIds as $id) {
            $pdo->prepare('DELETE FROM garde_movements WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM garde_batches WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM garde_products WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM garde_shifts WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM garde_staff WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM notifications WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM tenant_settings WHERE tenant_id = :id')->execute(['id' => $id]);
        }
        foreach ($this->emails as $email) {
            $pdo->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => $email]);
        }
        foreach ($this->tenantIds as $id) {
            $pdo->prepare('DELETE FROM tenants WHERE id = :id')->execute(['id' => $id]);
            $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'garde' . DIRECTORY_SEPARATOR . $id;
            if (is_dir($uploadDir)) {
                foreach (glob($uploadDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($uploadDir);
            }
        }
    }

    public function testInscriptionSectorCannotAccessGarde(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant(Sector::INSCRIPTION);
        $email = $this->email('ins');
        $this->createUser(Role::TENANT_OWNER, $tenant, $email);
        $this->login($kernel, $email);

        $this->assertSame(403, $kernel->handle(new Request('GET', '/garde/calendrier'))->status());
    }

    public function testShiftAndLotStayIsolatedBetweenTenants(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenantA = $this->createTenant(Sector::GARDE);
        $tenantB = $this->createTenant(Sector::GARDE);
        $emailA = $this->email('ga');
        $emailB = $this->email('gb');
        $this->createUser(Role::TENANT_OWNER, $tenantA, $emailA);
        $this->createUser(Role::TENANT_OWNER, $tenantB, $emailB);

        $this->login($kernel, $emailA);
        $staffA = $this->createStaff($kernel, 'Fatou Ndiaye');
        $planned = $kernel->handle($this->post('/garde/calendrier', [
            '_csrf' => $this->csrf($kernel, '/garde/calendrier/nouveau'),
            'staff_id' => (string) $staffA,
            'shift_date' => gmdate('Y-m-d', strtotime('+3 days') ?: time()),
            'note' => '',
        ]));
        $this->assertSame(302, $planned->status());
        $productA = $this->createProduct($kernel, 'Paracétamol');
        $this->createLot($kernel, $productA, 'LOT-A', 10, gmdate('Y-m-d', strtotime('+10 days') ?: time()));
        $shiftA = (int) $this->pdo()->query(
            'SELECT id FROM garde_shifts WHERE tenant_id = ' . $tenantA->id() . ' ORDER BY id DESC LIMIT 1'
        )->fetchColumn();

        $kernel->handle($this->post('/deconnexion', ['_csrf' => $this->csrf($kernel, '/espace')]));
        $this->login($kernel, $emailB);
        $this->createStaff($kernel, 'Moussa Fall');
        $people = $kernel->handle(new Request('GET', '/garde/personnel'));
        $this->assertSame(200, $people->status());
        $this->assertStringContainsString('Moussa Fall', $people->content());
        $this->assertStringNotContainsString('Fatou Ndiaye', $people->content());
        $this->assertSame(404, $kernel->handle(new Request('GET', '/garde/produits/' . $productA))->status());
        $this->assertSame(404, $kernel->handle(new Request('GET', '/garde/produits/' . $productA . '/image'))->status());
        if ($shiftA > 0) {
            $this->assertSame(404, $kernel->handle($this->post('/garde/calendrier/' . $shiftA . '/retirer', [
                '_csrf' => $this->csrf($kernel, '/garde/calendrier/nouveau'),
            ]))->status());
        }
    }

    public function testDuplicateShiftIsRejectedAndStockCannotGoNegative(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant(Sector::GARDE);
        $email = $this->email('own');
        $this->createUser(Role::TENANT_OWNER, $tenant, $email);
        $this->login($kernel, $email);

        $staffId = $this->createStaff($kernel, 'Awa Diop');
        $date = gmdate('Y-m-d', strtotime('+2 days') ?: time());
        $first = $kernel->handle($this->post('/garde/calendrier', [
            '_csrf' => $this->csrf($kernel, '/garde/calendrier/nouveau'),
            'staff_id' => (string) $staffId,
            'shift_date' => $date,
            'note' => 'Nuit',
        ]));
        $this->assertSame(302, $first->status());
        $duplicate = $kernel->handle($this->post('/garde/calendrier', [
            '_csrf' => $this->csrf($kernel, '/garde/calendrier/nouveau'),
            'staff_id' => (string) $staffId,
            'shift_date' => $date,
            'note' => '',
        ]));
        $this->assertSame(200, $duplicate->status());
        $this->assertStringContainsString('déjà de garde', $duplicate->content());

        $productId = $this->createProduct($kernel, 'Amoxicilline');
        $this->createLot($kernel, $productId, 'LOT-B', 2, gmdate('Y-m-d', strtotime('+5 days') ?: time()));
        $batchId = (int) $this->pdo()->query(
            'SELECT id FROM garde_batches WHERE tenant_id = ' . $tenant->id() . ' ORDER BY id DESC LIMIT 1'
        )->fetchColumn();
        $denied = $kernel->handle($this->post('/garde/mouvements', [
            '_csrf' => $this->csrf($kernel, '/garde/mouvements/nouveau'),
            'batch_id' => (string) $batchId,
            'type' => 'out',
            'quantity' => '5',
            'reason' => 'Vente',
        ]));
        $this->assertSame(200, $denied->status());
        $this->assertStringContainsString('Stock insuffisant', $denied->content());

        $ok = $kernel->handle($this->post('/garde/mouvements', [
            '_csrf' => $this->csrf($kernel, '/garde/mouvements/nouveau'),
            'batch_id' => (string) $batchId,
            'type' => 'out',
            'quantity' => '1',
            'reason' => 'Vente',
        ]));
        $this->assertSame(302, $ok->status());

        $alerts = $kernel->handle(new Request('GET', '/garde/alertes', ['etat' => 'soon']));
        $this->assertSame(200, $alerts->status());
        $this->assertStringContainsString('Amoxicilline', $alerts->content());
        $this->assertStringContainsString(ExpiryStatus::label(ExpiryStatus::SOON), $alerts->content());

        $home = $kernel->handle(new Request('GET', '/espace'));
        $this->assertSame(200, $home->status());
        $this->assertStringContainsString('Gardes du jour', $home->content());
        $this->assertStringContainsString('/garde/calendrier', $home->content());
    }

    public function testStaffWithoutStaffPermissionCannotCreatePersonnel(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant(Sector::GARDE);
        $email = $this->email('stf');
        $this->createUser(Role::STAFF, $tenant, $email);
        $this->login($kernel, $email);

        $this->assertSame(200, $kernel->handle(new Request('GET', '/garde/calendrier'))->status());
        $this->assertSame(403, $kernel->handle(new Request('GET', '/garde/personnel/nouveau'))->status());
    }

    public function testProductPhotoIsServedToOwnerOnlyAndConverted(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenantA = $this->createTenant(Sector::GARDE);
        $tenantB = $this->createTenant(Sector::GARDE);
        $emailA = $this->email('imga');
        $emailB = $this->email('imgb');
        $this->createUser(Role::TENANT_OWNER, $tenantA, $emailA);
        $this->createUser(Role::TENANT_OWNER, $tenantB, $emailB);
        $this->login($kernel, $emailA);

        $png = $this->pngFile(320, 240);
        $created = $kernel->handle($this->post('/garde/produits', [
            '_csrf' => $this->csrf($kernel, '/garde/produits/nouveau'),
            'name' => 'Sirop toux',
            'unit' => 'flacon',
            'min_quantity' => '1',
        ], '198.51.100.42', [
            'image' => [
                'name' => 'sirop.png',
                'type' => 'image/png',
                'tmp_name' => $png,
                'error' => UPLOAD_ERR_OK,
                'size' => (int) filesize($png),
            ],
        ]));
        $this->assertSame(302, $created->status());
        preg_match('#/garde/produits/(\d+)#', (string) ($created->headers()['Location'] ?? ''), $matches);
        $productId = (int) ($matches[1] ?? 0);
        $this->assertGreaterThan(0, $productId);

        $image = $kernel->handle(new Request('GET', '/garde/produits/' . $productId . '/image'));
        $this->assertSame(200, $image->status());
        $this->assertSame('image/webp', $image->headers()['Content-Type'] ?? '');
        $this->assertStringStartsWith('RIFF', $image->content());
        $list = $kernel->handle(new Request('GET', '/garde/produits'));
        $this->assertStringContainsString('/garde/produits/' . $productId . '/image', $list->content());

        $kernel->handle($this->post('/deconnexion', ['_csrf' => $this->csrf($kernel, '/espace')]));
        $this->login($kernel, $emailB);
        $this->assertSame(404, $kernel->handle(new Request('GET', '/garde/produits/' . $productId . '/image'))->status());
        unlink($png);
    }

    private function createStaff(\Sesam\Core\Kernel $kernel, string $name): int
    {
        $response = $kernel->handle($this->post('/garde/personnel', [
            '_csrf' => $this->csrf($kernel, '/garde/personnel/nouveau'),
            'name' => $name,
            'role_label' => 'Pharmacien',
        ]));
        $this->assertSame(302, $response->status());
        $statement = $this->pdo()->query('SELECT id FROM garde_staff ORDER BY id DESC LIMIT 1');

        return (int) $statement->fetchColumn();
    }

    private function createProduct(\Sesam\Core\Kernel $kernel, string $name): int
    {
        $response = $kernel->handle($this->post('/garde/produits', [
            '_csrf' => $this->csrf($kernel, '/garde/produits/nouveau'),
            'name' => $name,
            'unit' => 'boîte',
            'min_quantity' => '2',
        ]));
        $this->assertSame(302, $response->status());
        preg_match('#/garde/produits/(\d+)#', (string) ($response->headers()['Location'] ?? ''), $matches);

        return (int) ($matches[1] ?? 0);
    }

    private function createLot(\Sesam\Core\Kernel $kernel, int $productId, string $lot, int $qty, string $expires): void
    {
        $response = $kernel->handle($this->post('/garde/lots', [
            '_csrf' => $this->csrf($kernel, '/garde/lots/nouveau'),
            'product_id' => (string) $productId,
            'lot_code' => $lot,
            'quantity' => (string) $qty,
            'expires_on' => $expires,
        ]));
        $this->assertSame(302, $response->status());
    }

    private function createTenant(string $sector): \Sesam\Models\Tenant
    {
        $slug = 'p14-' . bin2hex(random_bytes(3));
        $id = (new TenantRepository($this->pdo()))->insert([
            'slug' => $slug,
            'name' => 'Garde test',
            'sector' => $sector,
            'status' => 'active',
            'display_name' => 'Pharmacie ' . $slug,
        ]);
        $this->tenantIds[] = $id;
        $tenant = (new TenantRepository($this->pdo()))->findById($id);
        $this->assertInstanceOf(\Sesam\Models\Tenant::class, $tenant);

        return $tenant;
    }

    private function createUser(string $role, \Sesam\Models\Tenant $tenant, string $email): void
    {
        $users = new UserRepository($this->pdo());
        $roles = new \Sesam\Repositories\RoleRepository($this->pdo());
        $id = $users->insert([
            'tenant_id' => $tenant->id(),
            'full_name' => 'Garde user',
            'email' => $email,
            'password_hash' => password_hash('ValidPass-1234', PASSWORD_DEFAULT),
            'is_active' => 1,
        ]);
        $roles->replaceUserRoles($id, $roles->idsForSlugs([$role]));
        $this->emails[] = $email;
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
     * @param array<string, mixed> $files
     */
    private function post(string $path, array $body, string $ip = '198.51.100.42', array $files = []): Request
    {
        return new Request('POST', $path, [], $body, [], [], false, $ip, '', $files);
    }

    private function pngFile(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $this->assertInstanceOf(\GdImage::class, $image);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 7, 26, 61));
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sesam-garde-' . bin2hex(random_bytes(3)) . '.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    private function email(string $prefix): string
    {
        return 'p14-' . $prefix . '-' . bin2hex(random_bytes(3)) . '@example.com';
    }

    private function pdo(): \PDO
    {
        Env::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

        return Connection::make((require dirname(__DIR__) . '/config/app.php')['db']);
    }
}
