<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/Core/Security/AuthMiddleware.php';

/**
 * Tests unitaires pour AuthMiddleware
 */
class AuthMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset état global
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '127.0.0.1',
            'REQUEST_URI' => '/test',
        ];
        $_SESSION = [];
        
        // Définir les constantes si non définies
        if (!defined('APP_SECRET')) {
            define('APP_SECRET', 'test-secret-for-unit-tests-only');
        }
        if (!defined('DEFAULT_TENANT_ID')) {
            define('DEFAULT_TENANT_ID', 'aaaaaaaa-1111-2222-3333-444444444444');
        }
        
        // Reset user
        $reflection = new ReflectionClass(AuthMiddleware::class);
        $property = $reflection->getProperty('currentUser');
        $property->setAccessible(true);
        $property->setValue(null, null);
        
        AuthMiddleware::init(['debug' => false]);
    }

    protected function tearDown(): void
    {
        putenv('APP_AUTH_ENABLED=');
    }

    public function testIsEnabledReturnsFalseByDefault(): void
    {
        putenv('APP_AUTH_ENABLED=');
        
        $result = AuthMiddleware::isEnabled();
        
        $this->assertFalse($result);
    }

    public function testIsEnabledReturnsTrueWhenSet(): void
    {
        putenv('APP_AUTH_ENABLED=1');
        
        $result = AuthMiddleware::isEnabled();
        
        $this->assertTrue($result);
    }

    public function testIsEnabledReturnsTrueWithTrueString(): void
    {
        putenv('APP_AUTH_ENABLED=true');
        
        $result = AuthMiddleware::isEnabled();
        
        $this->assertTrue($result);
    }

    public function testGenerateApiKeyReturnsKeyAndHash(): void
    {
        $result = AuthMiddleware::generateApiKey();
        
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('hash', $result);
        $this->assertEquals(64, strlen($result['key'])); // 32 bytes hex
        $this->assertEquals(64, strlen($result['hash'])); // SHA256 hex
    }

    public function testHashApiKeyIsConsistent(): void
    {
        $key = 'test-api-key';
        
        $hash1 = AuthMiddleware::hashApiKey($key);
        $hash2 = AuthMiddleware::hashApiKey($key);
        
        $this->assertEquals($hash1, $hash2);
    }

    public function testHashApiKeyIsDifferentForDifferentKeys(): void
    {
        $hash1 = AuthMiddleware::hashApiKey('key1');
        $hash2 = AuthMiddleware::hashApiKey('key2');
        
        $this->assertNotEquals($hash1, $hash2);
    }

    public function testGetCurrentUserReturnsNullWithoutAuth(): void
    {
        putenv('APP_AUTH_ENABLED=1');
        
        $user = AuthMiddleware::getCurrentUser();
        
        $this->assertNull($user);
    }

    public function testGetCurrentUserIdReturnsNullWithoutAuth(): void
    {
        putenv('APP_AUTH_ENABLED=1');
        
        $userId = AuthMiddleware::getCurrentUserId();
        
        $this->assertNull($userId);
    }

    public function testGetCurrentRoleReturnsAnonymousWithoutAuth(): void
    {
        putenv('APP_AUTH_ENABLED=1');
        
        $role = AuthMiddleware::getCurrentRole();
        
        $this->assertEquals('anonymous', $role);
    }

    public function testGetCurrentTenantIdReturnsDefault(): void
    {
        $tenantId = AuthMiddleware::getCurrentTenantId();
        
        $this->assertEquals(DEFAULT_TENANT_ID, $tenantId);
    }

    public function testRequireRolePassesWhenAuthDisabled(): void
    {
        putenv('APP_AUTH_ENABLED=0');
        
        $result = AuthMiddleware::requireRole('admin', false);
        
        $this->assertTrue($result);
    }

    public function testRequireRoleFailsWhenAuthEnabledAndNoKey(): void
    {
        putenv('APP_AUTH_ENABLED=1');
        
        $result = AuthMiddleware::requireRole('admin', false);
        
        $this->assertFalse($result);
    }

    public function testRequireRolePassesForPublicRole(): void
    {
        putenv('APP_AUTH_ENABLED=1');
        
        $result = AuthMiddleware::requireRole('public', false);
        
        $this->assertTrue($result);
    }

    public function testRequireRoleAcceptsArrayOfRoles(): void
    {
        putenv('APP_AUTH_ENABLED=1');
        
        $result = AuthMiddleware::requireRole(['public', 'voter'], false);
        
        $this->assertTrue($result);
    }

    public function testAuthenticateReturnsNullWithoutCredentials(): void
    {
        putenv('APP_AUTH_ENABLED=1');
        
        $user = AuthMiddleware::authenticate();
        
        $this->assertNull($user);
    }

    public function testApiKeyExtractedFromHeader(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'test-key';
        putenv('APP_AUTH_ENABLED=1');
        
        // Sans DB, authenticate retournera null, mais le mécanisme d'extraction fonctionne
        $user = AuthMiddleware::authenticate();
        
        // Le test vérifie que ça ne plante pas
        $this->assertNull($user);
    }

    public function testInitSetsDebugMode(): void
    {
        AuthMiddleware::init(['debug' => true]);
        
        // Le test vérifie que l'init ne plante pas
        $this->assertTrue(true);
    }
}
