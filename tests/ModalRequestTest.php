<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Core\Auth\Role;
use Sesam\Core\Kernel;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Tests\Support\IntegrationTestCase;

/**
 * Formulaires ouverts en modale : la redirection devient un en-tête, le message flash reste pour la page cible.
 */
final class ModalRequestTest extends IntegrationTestCase
{
    public function testModalPostReturnsLocationHeaderAndKeepsFlash(): void
    {
        $tenantId = $this->tenant(Sector::RESERVATION);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);

        $request = new Request('POST', '/clients', [], [
            '_csrf' => $this->csrf($kernel, '/clients/nouveau'),
            'full_name' => 'Client modale p20',
            'phone' => '+221770000020',
        ], [Kernel::MODAL_HEADER => '1'], [], false, '198.51.100.20');
        $response = $kernel->handle($request);

        $this->assertSame(200, $response->status());
        $this->assertSame('', $response->content());
        $location = (string) ($response->headers()[Kernel::MODAL_LOCATION] ?? '');
        $this->assertMatchesRegularExpression('#/clients/\d+$#', $location);
        $this->assertArrayNotHasKey('Location', $response->headers());

        $target = $this->get($kernel, (string) parse_url($location, PHP_URL_PATH));
        $this->assertStringContainsString('Le client a été enregistré.', $target->content());
    }

    public function testClassicPostStillRedirects(): void
    {
        $tenantId = $this->tenant(Sector::RESERVATION);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);

        $response = $this->submit($kernel, '/clients', [
            'full_name' => 'Client classique p20',
            'phone' => '+221770000021',
        ], '/clients/nouveau');

        $this->assertSame(302, $response->status());
        $this->assertArrayNotHasKey(Kernel::MODAL_LOCATION, $response->headers());
    }

    public function testModalGetReturnsThePageUnchanged(): void
    {
        $tenantId = $this->tenant(Sector::RESERVATION);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);

        $response = $kernel->handle(new Request('GET', '/clients/nouveau', [], [], [Kernel::MODAL_HEADER => '1']));

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('<main class="content tenant-content"', $response->content());
        $this->assertStringContainsString('name="full_name"', $response->content());
    }
}
