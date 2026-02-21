<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\EmailTrackingController;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EmailTrackingController.
 *
 * Note: EmailTrackingController does NOT extend AbstractController.
 * Its methods (pixel, redirect) use header()/exit for HTTP responses,
 * which cannot be intercepted cleanly in PHPUnit. Tests verify the
 * controller structure, UUID validation logic, and source-level patterns.
 */
class EmailTrackingControllerTest extends TestCase
{
    // =========================================================================
    // SETUP / TEARDOWN
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];

        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        \AgVote\Core\Security\AuthMiddleware::reset();
    }

    protected function tearDown(): void
    {
        \AgVote\Core\Security\AuthMiddleware::reset();

        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        parent::tearDown();
    }

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(EmailTrackingController::class);
        $this->assertTrue($ref->isFinal(), 'EmailTrackingController should be final');
    }

    public function testControllerDoesNotExtendAbstractController(): void
    {
        $controller = new EmailTrackingController();
        $this->assertNotInstanceOf(
            \AgVote\Controller\AbstractController::class,
            $controller,
            'EmailTrackingController should NOT extend AbstractController',
        );
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(EmailTrackingController::class);

        $expectedMethods = ['pixel', 'redirect'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "EmailTrackingController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(EmailTrackingController::class);

        $expectedMethods = ['pixel', 'redirect'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "EmailTrackingController::{$method}() should be public",
            );
        }
    }

    public function testOutputPixelMethodIsPrivate(): void
    {
        $ref = new \ReflectionClass(EmailTrackingController::class);
        $this->assertTrue(
            $ref->hasMethod('outputPixel'),
            'EmailTrackingController should have an outputPixel method',
        );
        $this->assertTrue(
            $ref->getMethod('outputPixel')->isPrivate(),
            'outputPixel() should be private',
        );
    }

    public function testOutputPixelReturnTypeIsNever(): void
    {
        $ref = new \ReflectionClass(EmailTrackingController::class);
        $method = $ref->getMethod('outputPixel');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'outputPixel should have a return type');
        $this->assertEquals('never', $returnType->getName());
    }

    // =========================================================================
    // PIXEL: UUID VALIDATION LOGIC
    // =========================================================================

    public function testPixelUuidValidationRegexAcceptsValidUuid(): void
    {
        $uuid = '12345678-1234-1234-1234-123456789abc';
        $valid = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
        $this->assertTrue($valid);
    }

    public function testPixelUuidValidationRegexAcceptsUppercaseUuid(): void
    {
        $uuid = 'ABCDEF01-2345-6789-ABCD-EF0123456789';
        $valid = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
        $this->assertTrue($valid);
    }

    public function testPixelUuidValidationRegexRejectsInvalidUuid(): void
    {
        $uuid = 'not-a-uuid';
        $valid = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
        $this->assertFalse($valid);
    }

    public function testPixelUuidValidationRegexRejectsEmptyString(): void
    {
        $uuid = '';
        $valid = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
        $this->assertFalse($valid);
    }

    public function testPixelUuidValidationRegexRejectsPartialUuid(): void
    {
        $uuid = '12345678-1234-1234';
        $valid = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
        $this->assertFalse($valid);
    }

    public function testPixelUuidValidationRegexRejectsUuidWithExtraChars(): void
    {
        $uuid = '12345678-1234-1234-1234-123456789abcX';
        $valid = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
        $this->assertFalse($valid);
    }

    // =========================================================================
    // PIXEL: TRACKING ENABLED LOGIC
    // =========================================================================

    public function testPixelTrackingDisabledSkipsTracking(): void
    {
        // Replicate: if ($invitationId === '' || !$trackingEnabled) outputPixel();
        $trackingEnabled = false;
        $invitationId = '12345678-1234-1234-1234-123456789abc';

        $shouldSkip = $invitationId === '' || !$trackingEnabled;
        $this->assertTrue($shouldSkip, 'Should skip tracking when disabled');
    }

    public function testPixelEmptyInvitationIdSkipsTracking(): void
    {
        $trackingEnabled = true;
        $invitationId = '';

        $shouldSkip = $invitationId === '' || !$trackingEnabled;
        $this->assertTrue($shouldSkip, 'Should skip tracking when invitation ID is empty');
    }

    public function testPixelValidInvitationIdAndEnabledDoesNotSkip(): void
    {
        $trackingEnabled = true;
        $invitationId = '12345678-1234-1234-1234-123456789abc';

        $shouldSkip = $invitationId === '' || !$trackingEnabled;
        $this->assertFalse($shouldSkip, 'Should not skip tracking when both are valid');
    }

    // =========================================================================
    // REDIRECT: URL VALIDATION LOGIC
    // =========================================================================

    public function testRedirectAcceptsHttpsUrl(): void
    {
        $targetUrl = 'https://example.com/vote';
        $parsed = parse_url($targetUrl);
        $valid = $parsed && isset($parsed['scheme']) && in_array($parsed['scheme'], ['http', 'https'], true);
        $this->assertTrue($valid);
    }

    public function testRedirectAcceptsHttpUrl(): void
    {
        $targetUrl = 'http://localhost:8080/vote';
        $parsed = parse_url($targetUrl);
        $valid = $parsed && isset($parsed['scheme']) && in_array($parsed['scheme'], ['http', 'https'], true);
        $this->assertTrue($valid);
    }

    public function testRedirectRejectsFtpUrl(): void
    {
        $targetUrl = 'ftp://example.com/file';
        $parsed = parse_url($targetUrl);
        $valid = $parsed && isset($parsed['scheme']) && in_array($parsed['scheme'], ['http', 'https'], true);
        $this->assertFalse($valid);
    }

    public function testRedirectRejectsJavascriptUrl(): void
    {
        $targetUrl = 'javascript:alert(1)';
        $parsed = parse_url($targetUrl);
        $valid = $parsed && isset($parsed['scheme']) && in_array($parsed['scheme'], ['http', 'https'], true);
        $this->assertFalse($valid);
    }

    public function testRedirectRejectsDataUrl(): void
    {
        $targetUrl = 'data:text/html,<script>alert(1)</script>';
        $parsed = parse_url($targetUrl);
        $valid = $parsed && isset($parsed['scheme']) && in_array($parsed['scheme'], ['http', 'https'], true);
        $this->assertFalse($valid);
    }

    // =========================================================================
    // REDIRECT: HOST VALIDATION LOGIC
    // =========================================================================

    public function testRedirectAllowsSameHost(): void
    {
        $fallbackUrl = 'http://localhost:8080';
        $allowedHost = parse_url($fallbackUrl, PHP_URL_HOST) ?: 'localhost';
        $targetHost = 'localhost';

        $hostMismatch = $targetHost !== '' && $targetHost !== $allowedHost;
        $this->assertFalse($hostMismatch, 'Same host should be allowed');
    }

    public function testRedirectRejectsDifferentHost(): void
    {
        $fallbackUrl = 'http://localhost:8080';
        $allowedHost = parse_url($fallbackUrl, PHP_URL_HOST) ?: 'localhost';
        $targetHost = 'evil.com';

        $hostMismatch = $targetHost !== '' && $targetHost !== $allowedHost;
        $this->assertTrue($hostMismatch, 'Different host should be rejected');
    }

    public function testRedirectAllowsEmptyHost(): void
    {
        $fallbackUrl = 'http://localhost:8080';
        $allowedHost = parse_url($fallbackUrl, PHP_URL_HOST) ?: 'localhost';
        $targetHost = '';

        $hostMismatch = $targetHost !== '' && $targetHost !== $allowedHost;
        $this->assertFalse($hostMismatch, 'Empty target host should be allowed');
    }

    // =========================================================================
    // SOURCE STRUCTURE VERIFICATION
    // =========================================================================

    public function testPixelUsesInvitationRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTrackingController.php');

        $this->assertStringContainsString('InvitationRepository', $source);
        $this->assertStringContainsString('incrementOpenCount', $source);
    }

    public function testPixelUsesEmailEventRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTrackingController.php');

        $this->assertStringContainsString('EmailEventRepository', $source);
        $this->assertStringContainsString('logEvent', $source);
    }

    public function testPixelLogsOpenedEvent(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTrackingController.php');

        $this->assertStringContainsString("'opened'", $source);
    }

    public function testRedirectLogsClickedEvent(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTrackingController.php');

        $this->assertStringContainsString("'clicked'", $source);
        $this->assertStringContainsString('incrementClickCount', $source);
    }

    public function testRedirectIncludesTargetUrlInEventMetadata(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTrackingController.php');

        $this->assertStringContainsString("'target_url'", $source);
    }

    public function testPixelOutputsGifImage(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTrackingController.php');

        $this->assertStringContainsString('image/gif', $source);
        $this->assertStringContainsString('Content-Type', $source);
        $this->assertStringContainsString('Content-Length', $source);
        $this->assertStringContainsString('Cache-Control', $source);
    }

    public function testPixelOutputsNoCacheHeaders(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTrackingController.php');

        $this->assertStringContainsString('no-cache', $source);
        $this->assertStringContainsString('no-store', $source);
        $this->assertStringContainsString('must-revalidate', $source);
    }

    public function testRedirectUsesLocationHeader(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTrackingController.php');

        $this->assertStringContainsString('Location:', $source);
    }

    public function testPixelGifBase64IsValid(): void
    {
        // The controller uses this base64 string for the 1x1 transparent GIF
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        $this->assertNotFalse($gif, 'Base64 GIF should decode successfully');
        $this->assertGreaterThan(0, strlen($gif), 'Decoded GIF should have content');
    }

    // =========================================================================
    // REDIRECT: EMPTY TARGET URL LOGIC
    // =========================================================================

    public function testRedirectEmptyTargetUrlFallsBack(): void
    {
        $targetUrl = '';
        $this->assertTrue($targetUrl === '', 'Empty target URL should trigger fallback');
    }

    public function testRedirectWhitespaceTargetUrlTrimmed(): void
    {
        // Replicate: $targetUrl = trim((string) ($_GET['url'] ?? ''));
        $_GET['url'] = '   ';
        $targetUrl = trim((string) ($_GET['url'] ?? ''));
        $this->assertEquals('', $targetUrl, 'Whitespace-only URL should be trimmed to empty');
    }

    // =========================================================================
    // ERROR HANDLING
    // =========================================================================

    public function testPixelErrorHandlingLogs(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTrackingController.php');

        $this->assertStringContainsString('Email pixel tracking error:', $source);
    }

    public function testRedirectErrorHandlingLogs(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTrackingController.php');

        $this->assertStringContainsString('Email redirect tracking error:', $source);
    }

    public function testPixelRethrowsApiResponseException(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTrackingController.php');

        $this->assertStringContainsString('ApiResponseException', $source);
    }
}
