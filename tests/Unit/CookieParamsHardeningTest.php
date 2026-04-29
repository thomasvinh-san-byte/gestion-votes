<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Security\SessionHelper;
use PHPUnit\Framework\TestCase;

/**
 * F18: cookie params hardening — SameSite default, env override.
 */
final class CookieParamsHardeningTest extends TestCase {
    /** @var string|null */
    private ?string $envBackup;

    protected function setUp(): void {
        $this->envBackup = getenv('SESSION_COOKIE_SAMESITE') !== false
            ? (string) getenv('SESSION_COOKIE_SAMESITE')
            : null;
    }

    protected function tearDown(): void {
        if ($this->envBackup === null) {
            putenv('SESSION_COOKIE_SAMESITE');
        } else {
            putenv('SESSION_COOKIE_SAMESITE=' . $this->envBackup);
        }
    }

    public function testSamesiteDefaultIsStrict(): void {
        putenv('SESSION_COOKIE_SAMESITE');
        $params = SessionHelper::cookieParams();
        $this->assertSame('Strict', $params['samesite']);
    }

    public function testSamesiteCanBeOverriddenToLax(): void {
        putenv('SESSION_COOKIE_SAMESITE=Lax');
        $params = SessionHelper::cookieParams();
        $this->assertSame('Lax', $params['samesite']);
    }

    public function testSamesiteCanBeOverriddenToNone(): void {
        // None requires Secure=true to be set by the browser, but the helper
        // accepts the literal — that's a deployer choice.
        putenv('SESSION_COOKIE_SAMESITE=None');
        $params = SessionHelper::cookieParams();
        $this->assertSame('None', $params['samesite']);
    }

    public function testInvalidSamesiteFallsBackToStrict(): void {
        // Garbage env value must not silently become anything dangerous.
        putenv('SESSION_COOKIE_SAMESITE=foo');
        $params = SessionHelper::cookieParams();
        $this->assertSame('Strict', $params['samesite']);
    }

    public function testHttpOnlyIsAlwaysOn(): void {
        putenv('SESSION_COOKIE_SAMESITE');
        $params = SessionHelper::cookieParams();
        $this->assertTrue($params['httponly']);
    }

    public function testCookieLifetimeIsZero(): void {
        // 0 → session cookie (deleted on browser close)
        putenv('SESSION_COOKIE_SAMESITE');
        $params = SessionHelper::cookieParams();
        $this->assertSame(0, $params['lifetime']);
    }
}
