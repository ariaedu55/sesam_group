<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Auth\Role;
use Sesam\Core\Bootstrap;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Core\Kernel;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Repositories\RoleRepository;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\UserRepository;
use Sesam\Services\Agency\AgencyCatalog;

final class AgencyTest extends TestCase
{
    private const PREFIX = 'p17-';

    /** @var list<string> */
    private array $emails = [];

    /** @var list<int> */
    private array $tenantIds = [];

    /** @var list<int> */
    private array $requestIds = [];

    /** @var list<string> */
    private array $ips = [];

    private ?\PDO $connection = null;

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        $pdo->prepare('DELETE FROM agency_prospects WHERE full_name LIKE :prefix')->execute(['prefix' => self::PREFIX . '%']);
        foreach ($this->requestIds as $id) {
            $pdo->prepare('DELETE FROM agency_prospects WHERE contact_request_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM contact_requests WHERE id = :id')->execute(['id' => $id]);
        }
        foreach ($this->emails as $email) {
            $pdo->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => $email]);
        }
        foreach ($this->tenantIds as $id) {
            $pdo->prepare('DELETE FROM tenant_settings WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM tenants WHERE id = :id')->execute(['id' => $id]);
        }
        foreach ($this->ips as $ip) {
            $pdo->prepare('DELETE FROM auth_attempts WHERE ip = :ip')->execute(['ip' => $ip]);
        }
        $this->connection = null;
    }

    public function testAgencyIsReservedToSesamStaffWithTheRightPermission(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $guest = $kernel->handle(new Request('GET', '/agence'));
        $this->assertSame(302, $guest->status());

        $tenantId = $this->createTenant();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $this->assertSame(403, $kernel->handle(new Request('GET', '/agence'))->status());
        $this->assertStringNotContainsString('/agence"', $kernel->handle(new Request('GET', '/espace'))->content());
        $this->logout($kernel);

        $this->loginAs($kernel, [Role::COMMERCIAL], $tenantId);
        $this->assertSame(403, $kernel->handle(new Request('GET', '/agence/prospects'))->status());
        $this->logout($kernel);

        $this->loginAs($kernel, [Role::COMMERCIAL]);
        $dashboard = $kernel->handle(new Request('GET', '/agence'));
        $this->assertSame(200, $dashboard->status());
        $this->assertStringContainsString('Pipeline commercial', $dashboard->content());
        $this->assertStringContainsString('aria-current="page" title="Prospects &amp; projets"', $dashboard->content());
        $prospectId = $this->createProspect($kernel);
        $projectId = $this->acceptedProject($kernel, $prospectId, 'essentiel', '200000');
        $this->assertSame(403, $kernel->handle($this->post('/agence/projets/' . $projectId . '/statut', [
            '_csrf' => $this->csrf($kernel, '/agence'),
            'action' => 'start',
        ]))->status());
        $this->assertSame(403, $kernel->handle($this->post('/agence/projets/' . $projectId . '/factures', [
            '_csrf' => $this->csrf($kernel, '/agence'),
            'label' => 'Acompte',
            'amount' => '1000',
            'due_on' => gmdate('Y-m-d'),
        ]))->status());
        $this->logout($kernel);

        $this->loginAs($kernel, [Role::FINANCE]);
        $this->assertSame(200, $kernel->handle(new Request('GET', '/agence/factures'))->status());
        $this->assertSame(403, $kernel->handle($this->post('/agence/prospects', [
            '_csrf' => $this->csrf($kernel, '/agence'),
            'full_name' => self::PREFIX . 'interdit',
            'phone' => '770000000',
            'activity' => 'ecole',
        ]))->status());
        $this->assertSame(403, $kernel->handle($this->post('/agence/prospects/' . $prospectId . '/devis', [
            '_csrf' => $this->csrf($kernel, '/agence'),
            'offer' => 'essentiel',
            'site_path' => 'site-neuf',
            'amount' => '300000',
        ]))->status());
        $invoice = $kernel->handle($this->post('/agence/projets/' . $projectId . '/factures', [
            '_csrf' => $this->csrf($kernel, '/agence'),
            'label' => 'Acompte',
            'amount' => '100000',
            'due_on' => gmdate('Y-m-d'),
        ]));
        $this->assertSame(302, $invoice->status());
    }

    public function testQuoteProjectInvoiceAndPaymentFlow(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $this->loginAs($kernel, [Role::SUPER_ADMIN]);
        $prospectId = $this->createProspect($kernel);
        $path = '/agence/prospects/' . $prospectId;

        foreach ([
            ['offer' => 'essentiel', 'site_path' => 'site-neuf', 'amount' => '150000'],
            ['offer' => 'professionnel', 'site_path' => 'site-neuf', 'amount' => '449 999'],
            ['offer' => 'inconnue', 'site_path' => 'site-neuf', 'amount' => '500000'],
            ['offer' => 'essentiel', 'site_path' => 'ailleurs', 'amount' => '500000'],
            ['offer' => 'sur-mesure', 'site_path' => 'site-existant', 'amount' => '0'],
        ] as $body) {
            $response = $kernel->handle($this->post($path . '/devis', ['_csrf' => $this->csrf($kernel, $path)] + $body));
            $this->assertSame(422, $response->status(), json_encode($body) ?: '');
        }
        $this->assertSame(0, $this->number('SELECT COUNT(*) FROM agency_quotes WHERE prospect_id = ?', [$prospectId]));

        $created = $kernel->handle($this->post($path . '/devis', [
            '_csrf' => $this->csrf($kernel, $path),
            'offer' => 'professionnel',
            'site_path' => 'site-existant',
            'amount' => '450 000',
            'description' => 'Refonte 5 pages',
        ]));
        $this->assertSame(302, $created->status());
        $quote = $this->row('SELECT id, reference, status, amount FROM agency_quotes WHERE prospect_id = ?', [$prospectId]);
        $this->assertMatchesRegularExpression('/^DV-\d{4}-\d{4,}$/', (string) $quote['reference']);
        $this->assertSame('draft', $quote['status']);
        $this->assertSame(450000, (int) $quote['amount']);

        $this->quoteStatus($kernel, (int) $quote['id'], $prospectId, 'accepted');
        $this->assertSame('draft', $this->value('SELECT status FROM agency_quotes WHERE id = ?', [$quote['id']]));

        $this->quoteStatus($kernel, (int) $quote['id'], $prospectId, 'sent');
        $this->assertSame('devis-envoye', $this->stage($prospectId));
        $this->quoteStatus($kernel, (int) $quote['id'], $prospectId, 'accepted');
        $this->assertSame('gagne', $this->stage($prospectId));
        $projectId = (int) $this->value('SELECT id FROM agency_projects WHERE quote_id = ?', [$quote['id']]);
        $this->assertGreaterThan(0, $projectId);
        $this->assertSame(450000, (int) $this->value('SELECT amount FROM agency_projects WHERE id = ?', [$projectId]));
        $this->quoteStatus($kernel, (int) $quote['id'], $prospectId, 'refused');
        $this->assertSame('accepted', $this->value('SELECT status FROM agency_quotes WHERE id = ?', [$quote['id']]));

        $manual = $kernel->handle($this->post($path . '/etape', ['_csrf' => $this->csrf($kernel, $path), 'stage' => 'perdu']));
        $this->assertSame(422, $manual->status());
        $clients = $kernel->handle(new Request('GET', '/agence/prospects', ['vue' => 'clients']));
        $this->assertStringContainsString(self::PREFIX . 'contact', $clients->content());

        $projectPath = '/agence/projets/' . $projectId;
        $deliverEarly = $kernel->handle($this->post($projectPath . '/statut', ['_csrf' => $this->csrf($kernel, $projectPath), 'action' => 'deliver']));
        $this->assertSame(422, $deliverEarly->status());
        if ($this->startedThisMonth() < AgencyCatalog::MONTHLY_CAPACITY) {
            $start = $kernel->handle($this->post($projectPath . '/statut', ['_csrf' => $this->csrf($kernel, $projectPath), 'action' => 'start']));
            $this->assertSame(302, $start->status());
            $this->assertSame('in_progress', $this->value('SELECT status FROM agency_projects WHERE id = ?', [$projectId]));
            $this->assertSame(gmdate('Y-m'), $this->value('SELECT start_month FROM agency_projects WHERE id = ?', [$projectId]));
            $this->assertSame('projet-en-cours', $this->stage($prospectId));
        }

        $invoice = fn (string $amount, string $due = '2026-01-15'): Response => $kernel->handle($this->post($projectPath . '/factures', [
            '_csrf' => $this->csrf($kernel, $projectPath),
            'label' => 'Tranche',
            'amount' => $amount,
            'due_on' => $due,
        ]));
        $this->assertSame(422, $invoice('450001')->status());
        $this->assertSame(422, $invoice('1000', '2026-13-01')->status());
        $this->assertSame(302, $invoice('225000')->status());
        $this->assertSame(422, $invoice('225001')->status());
        $this->assertSame(302, $invoice('225000')->status());
        $this->assertSame(450000, $this->number('SELECT SUM(amount) FROM agency_invoices WHERE project_id = ?', [$projectId]));
        $first = $this->row('SELECT id, reference FROM agency_invoices WHERE project_id = ? ORDER BY id LIMIT 1', [$projectId]);
        $this->assertMatchesRegularExpression('/^FA-\d{4}-\d{4,}$/', (string) $first['reference']);

        $overdue = $kernel->handle(new Request('GET', '/agence/factures', ['statut' => 'overdue']));
        $this->assertStringContainsString((string) $first['reference'], $overdue->content());

        $pay = fn (array $body): Response => $kernel->handle($this->post('/agence/factures/' . $first['id'] . '/payer', ['_csrf' => $this->csrf($kernel, $projectPath)] + $body));
        $pay(['payment_method' => 'cheque']);
        $pay(['payment_method' => 'wave', 'paid_on' => '2999-01-01']);
        $this->assertSame('issued', $this->value('SELECT status FROM agency_invoices WHERE id = ?', [$first['id']]));
        $this->assertSame(302, $pay(['payment_method' => 'wave'])->status());
        $this->assertStringContainsString('Paiement enregistré pour ' . $first['reference'], $kernel->handle(new Request('GET', '/agence/factures'))->content());
        $paid = $this->row('SELECT status, payment_method, paid_on FROM agency_invoices WHERE id = ?', [$first['id']]);
        $this->assertSame(['paid', 'wave', gmdate('Y-m-d')], [$paid['status'], $paid['payment_method'], $paid['paid_on']]);
        $pay(['payment_method' => 'especes']);
        $this->assertSame('wave', $this->value('SELECT payment_method FROM agency_invoices WHERE id = ?', [$first['id']]));

        if ($this->value('SELECT status FROM agency_projects WHERE id = ?', [$projectId]) === 'in_progress') {
            $badUrl = $kernel->handle($this->post($projectPath . '/statut', ['_csrf' => $this->csrf($kernel, $projectPath), 'action' => 'deliver', 'site_url' => 'javascript:alert(1)']));
            $this->assertSame(422, $badUrl->status());
            $delivered = $kernel->handle($this->post($projectPath . '/statut', ['_csrf' => $this->csrf($kernel, $projectPath), 'action' => 'deliver', 'site_url' => 'https://exemple.sn']));
            $this->assertSame(302, $delivered->status());
            $this->assertSame('livre', $this->stage($prospectId));
        }

        $page = $kernel->handle(new Request('GET', $path));
        $this->assertSame(200, $page->status());
        $this->assertStringContainsString((string) $quote['reference'], $page->content());
        $this->assertStringContainsString('Refonte 5 pages', $page->content());
    }

    public function testSixthProjectOfTheMonthIsRefused(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $this->loginAs($kernel, [Role::ADMIN]);
        $room = AgencyCatalog::MONTHLY_CAPACITY - $this->startedThisMonth();
        $refused = null;
        for ($i = 0; $i <= max(0, $room); $i++) {
            $projectId = $this->acceptedProject($kernel, $this->createProspect($kernel), 'essentiel', '200000');
            $path = '/agence/projets/' . $projectId;
            $response = $kernel->handle($this->post($path . '/statut', ['_csrf' => $this->csrf($kernel, $path), 'action' => 'start']));
            if ($i < $room) {
                $this->assertSame(302, $response->status());
            } else {
                $refused = [$projectId, $response];
            }
        }
        $this->assertNotNull($refused);
        [$projectId, $response] = $refused;
        $this->assertSame(422, $response->status());
        $this->assertStringContainsString('Capacité atteinte', $response->content());
        $this->assertSame('planned', $this->value('SELECT status FROM agency_projects WHERE id = ?', [$projectId]));
        $this->assertSame(AgencyCatalog::MONTHLY_CAPACITY, $this->startedThisMonth());

        $projects = $kernel->handle(new Request('GET', '/agence/projets'));
        $this->assertStringContainsString('Complet', $projects->content());
    }

    public function testSiteRequestBecomesAProspectOnlyOnce(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $pdo = $this->pdo();
        $pdo->prepare(
            'INSERT INTO contact_requests (full_name, email, phone, activity, message, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([self::PREFIX . 'demande', 'demande@example.com', '771112233', 'salon-beaute', 'Je veux un site vitrine', gmdate('Y-m-d H:i:s')]);
        $requestId = (int) $pdo->lastInsertId();
        $this->requestIds[] = $requestId;

        $this->loginAs($kernel, [Role::COMMERCIAL]);
        $list = $kernel->handle(new Request('GET', '/agence/prospects'));
        $this->assertStringContainsString('/agence/demandes/' . $requestId . '/convertir', $list->content());

        $first = $kernel->handle($this->post('/agence/demandes/' . $requestId . '/convertir', ['_csrf' => $this->csrf($kernel, '/agence')]));
        $this->assertSame(302, $first->status());
        $prospect = $this->row('SELECT id, source, activity, stage, email FROM agency_prospects WHERE contact_request_id = ?', [$requestId]);
        $this->assertSame(['site', 'salon-beaute', 'nouveau', 'demande@example.com'], [$prospect['source'], $prospect['activity'], $prospect['stage'], $prospect['email']]);
        $this->assertSame('/agence/prospects/' . $prospect['id'], $first->headers()['Location'] ?? null);

        $second = $kernel->handle($this->post('/agence/demandes/' . $requestId . '/convertir', ['_csrf' => $this->csrf($kernel, '/agence')]));
        $this->assertSame(302, $second->status());
        $this->assertSame('/agence/prospects', $second->headers()['Location'] ?? null);
        $this->assertSame(1, $this->number('SELECT COUNT(*) FROM agency_prospects WHERE contact_request_id = ?', [$requestId]));
        $this->assertStringNotContainsString('/agence/demandes/' . $requestId . '/convertir', $kernel->handle(new Request('GET', '/agence/prospects'))->content());

        $this->assertSame(404, $kernel->handle($this->post('/agence/demandes/999999999/convertir', ['_csrf' => $this->csrf($kernel, '/agence')]))->status());

        $path = '/agence/prospects/' . $prospect['id'];
        $this->assertSame(422, $kernel->handle($this->post($path . '/etape', ['_csrf' => $this->csrf($kernel, $path), 'stage' => 'gagne']))->status());
        $this->assertSame(302, $kernel->handle($this->post($path . '/etape', ['_csrf' => $this->csrf($kernel, $path), 'stage' => 'perdu', 'note' => 'Budget']))->status());
        $this->assertSame('perdu', $this->stage((int) $prospect['id']));
        $this->assertSame(422, $kernel->handle($this->post($path . '/devis', [
            '_csrf' => $this->csrf($kernel, $path),
            'offer' => 'essentiel',
            'site_path' => 'site-neuf',
            'amount' => '200000',
        ]))->status());
    }

    public function testProspectFormValidatesContactAndActivity(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $this->loginAs($kernel, [Role::COMMERCIAL]);
        foreach ([
            ['full_name' => '', 'phone' => '770000000', 'activity' => 'ecole'],
            ['full_name' => self::PREFIX . 'x', 'activity' => 'ecole'],
            ['full_name' => self::PREFIX . 'x', 'phone' => '770000000', 'activity' => 'clinique'],
            ['full_name' => self::PREFIX . 'x', 'email' => 'pas-un-mail', 'activity' => 'ecole'],
            ['full_name' => self::PREFIX . 'x', 'phone' => '770000000', 'activity' => 'ecole', 'next_action_on' => '2026-02-31'],
        ] as $body) {
            $response = $kernel->handle($this->post('/agence/prospects', ['_csrf' => $this->csrf($kernel, '/agence/prospects/nouveau')] + $body));
            $this->assertSame(422, $response->status(), json_encode($body) ?: '');
        }
        $this->assertSame(0, $this->number('SELECT COUNT(*) FROM agency_prospects WHERE full_name = ?', [self::PREFIX . 'x']));

        $id = $this->createProspect($kernel);
        $path = '/agence/prospects/' . $id;
        $this->assertSame(422, $kernel->handle($this->post($path . '/note', ['_csrf' => $this->csrf($kernel, $path)]))->status());
        $this->assertSame(302, $kernel->handle($this->post($path . '/note', [
            '_csrf' => $this->csrf($kernel, $path),
            'note' => 'Rappel <script>',
            'next_action_on' => '2026-11-02',
        ]))->status());
        $this->assertSame('2026-11-02', $this->value('SELECT next_action_on FROM agency_prospects WHERE id = ?', [$id]));
        $page = $kernel->handle(new Request('GET', $path));
        $this->assertStringContainsString('Rappel &lt;script&gt;', $page->content());
        $this->assertSame(404, $kernel->handle(new Request('GET', '/agence/prospects/999999999'))->status());
    }

    private function createProspect(Kernel $kernel): int
    {
        $name = self::PREFIX . 'contact-' . bin2hex(random_bytes(3));
        $response = $kernel->handle($this->post('/agence/prospects', [
            '_csrf' => $this->csrf($kernel, '/agence'),
            'full_name' => $name,
            'business_name' => '',
            'phone' => '77 123 45 67',
            'email' => '',
            'activity' => 'salon-couture',
        ]));
        $this->assertSame(302, $response->status());

        return (int) $this->value('SELECT id FROM agency_prospects WHERE full_name = ?', [$name]);
    }

    private function acceptedProject(Kernel $kernel, int $prospectId, string $offer, string $amount): int
    {
        $path = '/agence/prospects/' . $prospectId;
        $kernel->handle($this->post($path . '/devis', [
            '_csrf' => $this->csrf($kernel, $path),
            'offer' => $offer,
            'site_path' => 'site-neuf',
            'amount' => $amount,
        ]));
        $quoteId = (int) $this->value('SELECT id FROM agency_quotes WHERE prospect_id = ? ORDER BY id DESC LIMIT 1', [$prospectId]);
        $this->quoteStatus($kernel, $quoteId, $prospectId, 'sent');
        $this->quoteStatus($kernel, $quoteId, $prospectId, 'accepted');
        $projectId = (int) $this->value('SELECT id FROM agency_projects WHERE quote_id = ?', [$quoteId]);
        $this->assertGreaterThan(0, $projectId);

        return $projectId;
    }

    private function quoteStatus(Kernel $kernel, int $quoteId, int $prospectId, string $status): void
    {
        $response = $kernel->handle($this->post('/agence/devis/' . $quoteId . '/statut', [
            '_csrf' => $this->csrf($kernel, '/agence/prospects/' . $prospectId),
            'prospect_id' => (string) $prospectId,
            'status' => $status,
        ]));
        $this->assertSame(302, $response->status());
    }

    private function startedThisMonth(): int
    {
        return $this->number('SELECT COUNT(*) FROM agency_projects WHERE start_month = ?', [gmdate('Y-m')]);
    }

    private function stage(int $prospectId): string
    {
        return (string) $this->value('SELECT stage FROM agency_prospects WHERE id = ?', [$prospectId]);
    }

    private function createTenant(): int
    {
        $id = (new TenantRepository($this->pdo()))->insert([
            'slug' => self::PREFIX . 'tenant-' . bin2hex(random_bytes(3)),
            'name' => 'Commerce agence',
            'sector' => 'reservation',
            'status' => 'active',
            'display_name' => 'Commerce agence',
        ]);
        $this->tenantIds[] = $id;

        return $id;
    }

    /**
     * @param list<string> $roleSlugs
     */
    private function loginAs(Kernel $kernel, array $roleSlugs, ?int $tenantId = null): void
    {
        $email = self::PREFIX . bin2hex(random_bytes(4)) . '@example.com';
        $this->emails[] = $email;
        $roles = new RoleRepository($this->pdo());
        $id = (new UserRepository($this->pdo()))->insert([
            'tenant_id' => $tenantId,
            'full_name' => 'Test agence',
            'email' => $email,
            'password_hash' => password_hash('ValidPass-1234', PASSWORD_DEFAULT),
            'is_active' => 1,
        ]);
        $roles->replaceUserRoles($id, $roles->idsForSlugs($roleSlugs));
        $login = $kernel->handle($this->post('/connexion', [
            '_csrf' => $this->csrf($kernel),
            'email' => $email,
            'password' => 'ValidPass-1234',
        ]));
        $this->assertSame(302, $login->status());
    }

    private function logout(Kernel $kernel): void
    {
        $kernel->handle($this->post('/deconnexion', ['_csrf' => $this->csrf($kernel, '/espace')]));
    }

    private function csrf(Kernel $kernel, string $path = '/connexion'): string
    {
        $page = $kernel->handle(new Request('GET', $path));
        preg_match('/name="_csrf" value="([^"]+)"/', $page->content(), $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'Pas de jeton sur ' . $path . ' (' . $page->status() . ')');

        return (string) $matches[1];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(string $path, array $body): Request
    {
        $ip = '198.51.100.' . random_int(1, 250);
        $this->ips[] = $ip;

        return new Request('POST', $path, [], $body, [], [], false, $ip);
    }

    /**
     * @param list<mixed> $params
     * @return array<string, mixed>
     */
    private function row(string $sql, array $params): array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();
        $this->assertIsArray($row);

        return $row;
    }

    /**
     * @param list<mixed> $params
     */
    private function value(string $sql, array $params): mixed
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn();
    }

    /**
     * @param list<mixed> $params
     */
    private function number(string $sql, array $params): int
    {
        return (int) $this->value($sql, $params);
    }

    private function pdo(): \PDO
    {
        if ($this->connection === null) {
            Env::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
            /** @var array{db: array{host: string, port: string, name: string, user: string, password: string}} $config */
            $config = require dirname(__DIR__) . '/config/app.php';
            $this->connection = Connection::make($config['db']);
        }

        return $this->connection;
    }
}
