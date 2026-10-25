<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Request\Request;

final class RequestPathTest extends TestCase
{
    public function testRootWhenDocumentRootIsPublic(): void
    {
        $this->assertSame('/', Request::normalizePath('/', '/index.php'));
    }

    public function testIndexPhpIsTheRoot(): void
    {
        $this->assertSame('/', Request::normalizePath('/index.php', '/index.php'));
    }

    public function testSubdirectoryFrontController(): void
    {
        $this->assertSame(
            '/introuvable',
            Request::normalizePath('/projet Sesam/introuvable', '/projet Sesam/public/index.php')
        );
    }

    public function testCaptureKeepsProviderAndCsrfHeaders(): void
    {
        $saved = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/webhooks/naboopay';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['HTTP_X_NABOO_SIGNATURE'] = 'abc123';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'jeton';
        try {
            $request = Request::capture();
        } finally {
            $_SERVER = $saved;
        }

        $this->assertSame('/webhooks/naboopay', $request->path());
        $this->assertSame('abc123', $request->header('X-Naboo-Signature'));
        $this->assertSame('jeton', $request->header('X-CSRF-Token'));
        $this->assertNull($request->header('X-Absent'));
    }

    public function testSubdirectoryHome(): void
    {
        $this->assertSame(
            '/',
            Request::normalizePath('/projet Sesam/', '/projet Sesam/public/index.php')
        );
    }
}
