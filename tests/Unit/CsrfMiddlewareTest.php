<?php

declare(strict_types=1);

use AgVote\Core\Security\CsrfMiddleware;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/Core/Security/CsrfMiddleware.php';

/**
 * Unit tests for CsrfMiddleware.
 */
class CsrfMiddlewareTest extends TestCase {
    protected function setUp(): void {
        // Reset session pour chaque test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $_POST = [];
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '127.0.0.1',
            'REQUEST_URI' => '/test',
        ];
    }

    protected function tearDown(): void {
        $_SESSION = [];
        $_POST = [];
    }

    public function testGetTokenGeneratesToken(): void {
        $token = CsrfMiddleware::getToken();

        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    public function testGetTokenReturnsSameToken(): void {
        $token1 = CsrfMiddleware::getToken();
        $token2 = CsrfMiddleware::getToken();

        $this->assertEquals($token1, $token2);
    }

    public function testRegenerateTokenCreatesNewToken(): void {
        $token1 = CsrfMiddleware::getToken();
        $token2 = CsrfMiddleware::regenerateToken();

        $this->assertNotEquals($token1, $token2);
    }

    public function testFieldGeneratesHiddenInput(): void {
        $field = CsrfMiddleware::field();

        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString('value="', $field);
    }

    public function testMetaTagGeneratesMetaElement(): void {
        $meta = CsrfMiddleware::metaTag();

        $this->assertStringContainsString('<meta', $meta);
        $this->assertStringContainsString('name="csrf-token"', $meta);
        $this->assertStringContainsString('content="', $meta);
    }

    public function testJsSnippetContainsToken(): void {
        $token = CsrfMiddleware::getToken();
        $snippet = CsrfMiddleware::jsSnippet();

        $this->assertStringContainsString($token, $snippet);
        $this->assertStringContainsString('X-CSRF-Token', $snippet);
        $this->assertStringContainsString('htmx:configRequest', $snippet);
    }

    public function testValidatePassesForGetRequest(): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = CsrfMiddleware::validate(false);

        $this->assertTrue($result);
    }

    public function testValidatePassesForOptionsRequest(): void {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';

        $result = CsrfMiddleware::validate(false);

        $this->assertTrue($result);
    }

    public function testValidateFailsWithoutTokenForPost(): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        CsrfMiddleware::getToken(); // Génère un token

        $result = CsrfMiddleware::validate(false);

        $this->assertFalse($result);
    }

    public function testValidatePassesWithCorrectTokenInPost(): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $token = CsrfMiddleware::getToken();
        $_POST['csrf_token'] = $token;

        $result = CsrfMiddleware::validate(false);

        $this->assertTrue($result);
    }

    public function testValidateFailsWithWrongToken(): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        CsrfMiddleware::getToken();
        $_POST['csrf_token'] = 'wrong-token';

        $result = CsrfMiddleware::validate(false);

        $this->assertFalse($result);
    }

    public function testValidatePassesWithCorrectTokenInHeader(): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $token = CsrfMiddleware::getToken();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;

        $result = CsrfMiddleware::validate(false);

        $this->assertTrue($result);
    }

    public function testGetTokenNameReturnsCorrectName(): void {
        $name = CsrfMiddleware::getTokenName();

        $this->assertEquals('csrf_token', $name);
    }

    public function testGetHeaderNameReturnsCorrectName(): void {
        $name = CsrfMiddleware::getHeaderName();

        $this->assertEquals('X-CSRF-Token', $name);
    }

    // =========================================================================
    // F10: action-scoped CSRF tokens
    // =========================================================================

    public function testTokenForReturnsHmacScopedTokenLength(): void {
        $token = CsrfMiddleware::tokenFor('POST', '/api/v1/meetings');
        $this->assertSame(64, strlen($token), 'HMAC-SHA256 hex length must be 64');
    }

    public function testTokenForDiffersAcrossActions(): void {
        $a = CsrfMiddleware::tokenFor('POST', '/api/v1/meetings');
        $b = CsrfMiddleware::tokenFor('POST', '/api/v1/admin_settings');
        $this->assertNotSame($a, $b, 'F10: scoped tokens for distinct paths must differ');
    }

    public function testTokenForDiffersAcrossMethods(): void {
        $post = CsrfMiddleware::tokenFor('POST', '/api/v1/foo');
        $delete = CsrfMiddleware::tokenFor('DELETE', '/api/v1/foo');
        $this->assertNotSame($post, $delete, 'F10: scoped tokens for distinct methods must differ');
    }

    public function testTokenForIsStableAcrossCalls(): void {
        $a = CsrfMiddleware::tokenFor('POST', '/api/v1/foo');
        $b = CsrfMiddleware::tokenFor('POST', '/api/v1/foo');
        $this->assertSame($a, $b, 'Same (method, path) must produce the same token within a session');
    }

    public function testTokenForIgnoresQueryStringAndTrailingSlash(): void {
        $base = CsrfMiddleware::tokenFor('POST', '/api/v1/foo');
        $withQuery = CsrfMiddleware::tokenFor('POST', '/api/v1/foo?x=1');
        $withSlash = CsrfMiddleware::tokenFor('POST', '/api/v1/foo/');
        $this->assertSame($base, $withQuery);
        $this->assertSame($base, $withSlash);
    }

    public function testValidateAcceptsScopedTokenForCurrentRequest(): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/v1/meetings';

        $scoped = CsrfMiddleware::tokenFor('POST', '/api/v1/meetings');
        $_POST['csrf_token'] = $scoped;

        $this->assertTrue(CsrfMiddleware::validate(false));
    }

    /**
     * F10 critical: a scoped token minted for endpoint A must NOT validate
     * a request to endpoint B. This is the primary guarantee.
     */
    public function testValidateRejectsScopedTokenFromAnotherAction(): void {
        // Mint a token for endpoint A
        $tokenForA = CsrfMiddleware::tokenFor('POST', '/api/v1/meetings');

        // Replay it on endpoint B
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/v1/admin_settings';
        $_POST['csrf_token'] = $tokenForA;

        // Also clear any legacy session token so we test the scoped path only.
        unset($_SESSION['csrf_token']);

        $this->assertFalse(
            CsrfMiddleware::validate(false),
            'F10: a scoped token minted for /meetings must NOT validate /admin_settings',
        );
    }

    public function testValidateRejectsScopedTokenWithDifferentMethod(): void {
        $tokenForPost = CsrfMiddleware::tokenFor('POST', '/api/v1/foo');

        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI'] = '/api/v1/foo';
        $_POST['csrf_token'] = $tokenForPost;
        unset($_SESSION['csrf_token']);

        $this->assertFalse(CsrfMiddleware::validate(false));
    }

    public function testValidateStillAcceptsLegacySessionTokenAfterScopedSecretIssued(): void {
        // Mint a scoped token (creates the scoped secret as a side effect)
        CsrfMiddleware::tokenFor('POST', '/api/v1/foo');

        // The legacy session-wide token must still validate any POST.
        $legacy = CsrfMiddleware::getToken();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/anywhere';
        $_POST['csrf_token'] = $legacy;

        $this->assertTrue(CsrfMiddleware::validate(false));
    }

    public function testFieldForRendersScopedToken(): void {
        $field = CsrfMiddleware::fieldFor('POST', '/api/v1/foo');
        $expected = CsrfMiddleware::tokenFor('POST', '/api/v1/foo');

        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString('value="' . $expected . '"', $field);
    }

    public function testTokenIsHtmlEscaped(): void {
        $field = CsrfMiddleware::field();

        // Le token ne devrait contenir que des caractères hex
        $this->assertDoesNotMatchRegularExpression('/<script/i', $field);
    }
}
