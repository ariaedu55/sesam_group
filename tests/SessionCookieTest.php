<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Session\Session;

final class SessionCookieTest extends TestCase
{
    public function testCookieIsHttpOnlyAndSecureOnlyOnHttps(): void
    {
        $local = Session::cookieOptions(false);
        $secure = Session::cookieOptions(true);

        $this->assertTrue($local['httponly']);
        $this->assertFalse($local['secure']);
        $this->assertSame('Lax', $local['samesite']);
        $this->assertTrue($secure['secure']);
    }
}
