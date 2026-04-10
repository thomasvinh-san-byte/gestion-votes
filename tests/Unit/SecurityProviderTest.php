<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Providers\SecurityProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SecurityProvider nonce generation.
 */
final class SecurityProviderTest extends TestCase {
    protected function setUp(): void {
        SecurityProvider::resetNonce();
    }

    protected function tearDown(): void {
        SecurityProvider::resetNonce();
    }

    public function testNonceReturns32CharHexString(): void {
        $nonce = SecurityProvider::nonce();
        $this->assertSame(32, strlen($nonce), 'Nonce must be 32 characters (16 bytes hex-encoded)');
        $this->assertTrue(ctype_xdigit($nonce), 'Nonce must be a valid hex string');
    }

    public function testNonceIsSameWithinRequest(): void {
        $first = SecurityProvider::nonce();
        $second = SecurityProvider::nonce();
        $this->assertSame($first, $second, 'Nonce must be identical within the same request');
    }

    public function testNonceChangesAfterReset(): void {
        $first = SecurityProvider::nonce();
        SecurityProvider::resetNonce();
        $second = SecurityProvider::nonce();
        $this->assertNotSame($first, $second, 'Nonce must differ after resetNonce()');
    }

    public function testResetNonceClearsStaticProperty(): void {
        SecurityProvider::nonce();
        SecurityProvider::resetNonce();
        // After reset, a new call should generate a fresh nonce
        $nonce = SecurityProvider::nonce();
        $this->assertSame(32, strlen($nonce));
        $this->assertTrue(ctype_xdigit($nonce));
    }
}
