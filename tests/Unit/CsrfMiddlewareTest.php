<?php

declare(strict_types=1);

use AgVote\Core\Security\CsrfMiddleware;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/Core/Security/CsrfMiddleware.php';

/**
 * Unit tests for CsrfMiddleware.
 */
class CsrfMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
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

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    public function testGetTokenGeneratesToken(): void
    {
        $token = CsrfMiddleware::getToken();
        
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    public function testGetTokenReturnsSameToken(): void
    {
        $token1 = CsrfMiddleware::getToken();
        $token2 = CsrfMiddleware::getToken();
        
        $this->assertEquals($token1, $token2);
    }

    public function testRegenerateTokenCreatesNewToken(): void
    {
        $token1 = CsrfMiddleware::getToken();
        $token2 = CsrfMiddleware::regenerateToken();
        
        $this->assertNotEquals($token1, $token2);
    }

    public function testFieldGeneratesHiddenInput(): void
    {
        $field = CsrfMiddleware::field();
        
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString('value="', $field);
    }

    public function testMetaTagGeneratesMetaElement(): void
    {
        $meta = CsrfMiddleware::metaTag();
        
        $this->assertStringContainsString('<meta', $meta);
        $this->assertStringContainsString('name="csrf-token"', $meta);
        $this->assertStringContainsString('content="', $meta);
    }

    public function testJsSnippetContainsToken(): void
    {
        $token = CsrfMiddleware::getToken();
        $snippet = CsrfMiddleware::jsSnippet();
        
        $this->assertStringContainsString($token, $snippet);
        $this->assertStringContainsString('X-CSRF-Token', $snippet);
        $this->assertStringContainsString('htmx:configRequest', $snippet);
    }

    public function testValidatePassesForGetRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $result = CsrfMiddleware::validate(false);
        
        $this->assertTrue($result);
    }

    public function testValidatePassesForOptionsRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        
        $result = CsrfMiddleware::validate(false);
        
        $this->assertTrue($result);
    }

    public function testValidateFailsWithoutTokenForPost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        CsrfMiddleware::getToken(); // Génère un token
        
        $result = CsrfMiddleware::validate(false);
        
        $this->assertFalse($result);
    }

    public function testValidatePassesWithCorrectTokenInPost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $token = CsrfMiddleware::getToken();
        $_POST['csrf_token'] = $token;
        
        $result = CsrfMiddleware::validate(false);
        
        $this->assertTrue($result);
    }

    public function testValidateFailsWithWrongToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        CsrfMiddleware::getToken();
        $_POST['csrf_token'] = 'wrong-token';
        
        $result = CsrfMiddleware::validate(false);
        
        $this->assertFalse($result);
    }

    public function testValidatePassesWithCorrectTokenInHeader(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $token = CsrfMiddleware::getToken();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
        
        $result = CsrfMiddleware::validate(false);
        
        $this->assertTrue($result);
    }

    public function testGetTokenNameReturnsCorrectName(): void
    {
        $name = CsrfMiddleware::getTokenName();
        
        $this->assertEquals('csrf_token', $name);
    }

    public function testGetHeaderNameReturnsCorrectName(): void
    {
        $name = CsrfMiddleware::getHeaderName();
        
        $this->assertEquals('X-CSRF-Token', $name);
    }

    public function testTokenIsHtmlEscaped(): void
    {
        $field = CsrfMiddleware::field();
        
        // Le token ne devrait contenir que des caractères hex
        $this->assertDoesNotMatchRegularExpression('/<script/i', $field);
    }
}
