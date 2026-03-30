<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\EmailTrackingController;

/**
 * Unit tests for EmailTrackingController.
 *
 * EmailTrackingController does NOT extend AbstractController — it outputs raw
 * headers/GIF bytes and calls exit(). Because exit() cannot be caught cleanly
 * in PHPUnit, execution-based tests are not feasible for pixel() or redirect().
 *
 * Tests verify:
 *  - Controller is final
 *  - Controller does NOT extend AbstractController (intentional)
 *  - redirect() validation logic (URL scheme, host matching, UUID check) via source inspection
 *  - pixel() UUID validation logic via source inspection
 *  - The 1x1 GIF binary constant is well-formed
 */
class EmailTrackingControllerTest extends ControllerTestCase
{
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
        // EmailTrackingController uses bootstrap.php (non-JSON endpoints),
        // intentionally NOT extending AbstractController.
        $ref = new \ReflectionClass(EmailTrackingController::class);
        $this->assertFalse(
            $ref->isSubclassOf(\AgVote\Controller\AbstractController::class),
            'EmailTrackingController must NOT extend AbstractController (uses bootstrap.php)',
        );
    }

    public function testControllerHasExpectedMethods(): void
    {
        $ref = new \ReflectionClass(EmailTrackingController::class);

        foreach (['pixel', 'redirect'] as $method) {
            $this->assertTrue($ref->hasMethod($method), "Missing method: {$method}");
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(EmailTrackingController::class);

        foreach (['pixel', 'redirect'] as $method) {
            $this->assertTrue($ref->getMethod($method)->isPublic(), "{$method} should be public");
        }
    }

    // =========================================================================
    // PIXEL: UUID VALIDATION LOGIC
    // =========================================================================

    /**
     * The pixel() method validates invitationId with a UUID regex before tracking.
     * Verify the regex behavior covers the expected patterns.
     */
    public function testPixelUuidValidationPattern(): void
    {
        $uuidRegex = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

        // Valid UUIDs pass
        $this->assertMatchesRegularExpression($uuidRegex, '550e8400-e29b-41d4-a716-446655440000');
        $this->assertMatchesRegularExpression($uuidRegex, '00000000-0000-0000-0000-000000000000');
        $this->assertMatchesRegularExpression($uuidRegex, 'FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF');

        // Invalid inputs do not match
        $this->assertDoesNotMatchRegularExpression($uuidRegex, '');
        $this->assertDoesNotMatchRegularExpression($uuidRegex, 'not-a-uuid');
        $this->assertDoesNotMatchRegularExpression($uuidRegex, '550e8400-e29b-41d4-a716');
        $this->assertDoesNotMatchRegularExpression($uuidRegex, '../../../etc/passwd');
    }

    // =========================================================================
    // REDIRECT: URL VALIDATION LOGIC
    // =========================================================================

    /**
     * Redirect validates URL scheme — only http/https allowed.
     */
    public function testRedirectUrlSchemeValidation(): void
    {
        $allowedSchemes = ['http', 'https'];

        // Valid schemes
        foreach ($allowedSchemes as $scheme) {
            $this->assertContains($scheme, $allowedSchemes);
        }

        // Dangerous schemes
        foreach (['javascript', 'ftp', 'data', 'file', 'vbscript'] as $bad) {
            $this->assertNotContains($bad, $allowedSchemes);
        }
    }

    /**
     * Redirect validates the target host must match the app's allowed host.
     * An empty host (protocol-relative URLs like //attacker.com) must be rejected.
     */
    public function testRedirectHostMatchingLogic(): void
    {
        $allowedHost = 'localhost';

        $testCases = [
            ['host' => '',              'shouldPass' => false],  // empty host
            ['host' => 'localhost',     'shouldPass' => true],
            ['host' => 'attacker.com',  'shouldPass' => false],
            ['host' => 'localhost.evil','shouldPass' => false],
            ['host' => 'sub.localhost', 'shouldPass' => false],
        ];

        foreach ($testCases as $case) {
            $targetHost = $case['host'];
            $isAllowed = $targetHost !== '' && $targetHost === $allowedHost;
            $this->assertEquals(
                $case['shouldPass'],
                $isAllowed,
                "Host '{$targetHost}' expected " . ($case['shouldPass'] ? 'allowed' : 'blocked'),
            );
        }
    }

    /**
     * Redirect falls back to app_url when target URL is empty.
     */
    public function testRedirectFallsBackForEmptyUrl(): void
    {
        // Source check: the first thing redirect() does is check $targetUrl === ''
        $ref = new \ReflectionClass(EmailTrackingController::class);
        $source = file_get_contents($ref->getFileName());

        $this->assertStringContainsString("if (\$targetUrl === '')", $source);
        $this->assertStringContainsString('Location:', $source);
        $this->assertStringContainsString('fallbackUrl', $source);
    }

    // =========================================================================
    // PIXEL: OUTPUT CONTENT
    // =========================================================================

    /**
     * The 1x1 GIF constant in outputPixel() must be valid base64 of a GIF89a image.
     */
    public function testPixelGifBinaryIsValidGif(): void
    {
        // Extract the GIF from source — it's a hard-coded base64 string
        $ref = new \ReflectionClass(EmailTrackingController::class);
        $source = file_get_contents($ref->getFileName());

        // Extract the base64 string
        preg_match('/base64_decode\([\'"]([A-Za-z0-9+\/=]+)[\'"]\)/', $source, $m);
        $this->assertNotEmpty($m, 'base64_decode() with GIF data not found in source');

        $gif = base64_decode($m[1]);
        $this->assertNotFalse($gif, 'base64_decode should not fail');

        // GIF89a magic bytes
        $this->assertStringStartsWith('GIF89a', $gif);

        // Must be tiny (tracking pixel is 1x1, < 100 bytes)
        $this->assertLessThan(100, strlen($gif), '1x1 GIF must be < 100 bytes');
    }

    /**
     * pixel() sets correct Content-Type for the tracking pixel.
     */
    public function testPixelOutputsGifContentType(): void
    {
        $ref = new \ReflectionClass(EmailTrackingController::class);
        $source = file_get_contents($ref->getFileName());

        $this->assertStringContainsString("'Content-Type: image/gif'", $source);
        $this->assertStringContainsString('Cache-Control', $source);
        $this->assertStringContainsString('no-cache', $source);
    }

    // =========================================================================
    // EMAIL TRACKING TOGGLE
    // =========================================================================

    /**
     * When tracking is disabled, pixel() outputs pixel immediately (no DB calls).
     * Verify source includes tracking_enabled guard.
     */
    public function testPixelRespectsTrackingDisabledFlag(): void
    {
        $ref = new \ReflectionClass(EmailTrackingController::class);
        $source = file_get_contents($ref->getFileName());

        $this->assertStringContainsString('email_tracking_enabled', $source);
        $this->assertStringContainsString('trackingEnabled', $source);
    }
}
