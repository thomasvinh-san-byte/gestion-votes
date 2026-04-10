<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\PageController;
use AgVote\Core\Providers\SecurityProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PageController: CSP nonce injection and 404 handling.
 *
 * Uses @runInSeparateProcess because PageController calls header() and http_response_code().
 */
final class PageControllerTest extends TestCase {
    private array $originalServer;

    protected function setUp(): void {
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void {
        $_SERVER = $this->originalServer;
        SecurityProvider::resetNonce();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testServeFromUri_validPage_injectsNonce(): void {
        $_SERVER['REQUEST_URI'] = '/dashboard?foo=bar';

        SecurityProvider::resetNonce();
        $nonce = SecurityProvider::nonce();

        ob_start();
        PageController::serveFromUri();
        $output = ob_get_clean();

        $this->assertNotEmpty($output, 'Output should not be empty for valid page');
        $this->assertStringNotContainsString('%%CSP_NONCE%%', $output, 'Nonce placeholder should be replaced');
        $this->assertStringContainsString($nonce, $output, 'Actual nonce value should appear in output');
        $this->assertSame(200, http_response_code());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testServeFromUri_invalidPage_returns404(): void {
        $_SERVER['REQUEST_URI'] = '/totally-invalid-page-xyz';

        ob_start();
        PageController::serveFromUri();
        $output = ob_get_clean();

        $this->assertEmpty($output, 'Output should be empty for invalid page');
        $this->assertSame(404, http_response_code());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testServe_validPage_replacesNoncePlaceholder(): void {
        ob_start();
        PageController::serve('dashboard');
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        $this->assertStringNotContainsString('%%CSP_NONCE%%', $output, 'All nonce placeholders should be replaced');
        // Nonce is a 32-char hex string injected into nonce="..." attributes
        $this->assertMatchesRegularExpression('/nonce="[0-9a-f]{32}"/', $output, 'Nonce attributes should contain 32-char hex values');
        $this->assertSame(200, http_response_code());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testServe_nonexistentFile_returns404(): void {
        ob_start();
        PageController::serve('zzz-does-not-exist-page');
        $output = ob_get_clean();

        $this->assertEmpty($output, 'Output should be empty for nonexistent page file');
        $this->assertSame(404, http_response_code());
    }
}
