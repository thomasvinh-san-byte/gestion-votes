<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Providers\SecurityProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SecurityProvider nonce generation and CSP header construction.
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

    // ── Report-only CSP header construction tests ──────────────────────

    public function testBuildReportOnlyCspContainsNonce(): void {
        $nonce = SecurityProvider::nonce();
        $csp = SecurityProvider::buildReportOnlyCsp();
        $this->assertStringContainsString("'nonce-{$nonce}'", $csp, 'Report-only CSP must contain the request nonce');
    }

    public function testBuildReportOnlyCspContainsStrictDynamic(): void {
        $csp = SecurityProvider::buildReportOnlyCsp();
        $this->assertStringContainsString("'strict-dynamic'", $csp, 'Report-only CSP must contain strict-dynamic');
    }

    public function testBuildReportOnlyCspScriptSrcNoUnsafeInline(): void {
        $csp = SecurityProvider::buildReportOnlyCsp();
        // Extract the script-src directive value (between "script-src " and the next ";")
        preg_match('/script-src\s+([^;]+)/', $csp, $matches);
        $this->assertNotEmpty($matches, 'CSP must have a script-src directive');
        $scriptSrc = $matches[1];
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc, 'script-src must NOT contain unsafe-inline');
    }

    public function testBuildReportOnlyCspStyleSrcHasNonce(): void {
        $nonce = SecurityProvider::nonce();
        $csp = SecurityProvider::buildReportOnlyCsp();
        // Extract style-src directive
        preg_match('/style-src\s+([^;]+)/', $csp, $matches);
        $this->assertNotEmpty($matches, 'CSP must have a style-src directive');
        $styleSrc = $matches[1];
        $this->assertStringContainsString("'nonce-{$nonce}'", $styleSrc, 'style-src must contain the request nonce');
    }

    public function testBuildReportOnlyCspScriptSrcNoSelf(): void {
        $csp = SecurityProvider::buildReportOnlyCsp();
        preg_match('/script-src\s+([^;]+)/', $csp, $matches);
        $scriptSrc = $matches[1];
        $this->assertStringNotContainsString("'self'", $scriptSrc, 'script-src in report-only must NOT contain self (strict-dynamic ignores it)');
    }

    public function testBuildReportOnlyCspUsesConsistentNonce(): void {
        // Calling buildReportOnlyCsp() twice should produce the same nonce
        $csp1 = SecurityProvider::buildReportOnlyCsp();
        $csp2 = SecurityProvider::buildReportOnlyCsp();
        $this->assertSame($csp1, $csp2, 'Same request should produce identical CSP strings');
    }
}
