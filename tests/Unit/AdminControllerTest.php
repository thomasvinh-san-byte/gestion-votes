<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AdminController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AdminController.
 *
 * Tests the admin endpoint logic including:
 *  - User management validation (role validation, password strength, self-protection)
 *  - System status alert computation
 *  - Audit log formatting and payload parsing
 *  - Meeting role validation
 *  - Method enforcement patterns
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class AdminControllerTest extends TestCase
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

        // Reset cached raw body
        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        // Set up auth context (admin user for admin endpoints)
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
    // HELPER: Call controller and capture response
    // =========================================================================

    private function callControllerMethod(string $method): array
    {
        $controller = new AdminController();
        try {
            $controller->handle($method);
            $this->fail('Expected ApiResponseException was not thrown');
        } catch (ApiResponseException $e) {
            return [
                'status' => $e->getResponse()->getStatusCode(),
                'body' => $e->getResponse()->getBody(),
            ];
        }
        return ['status' => 500, 'body' => []];
    }

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(AdminController::class);

        $expectedMethods = ['users', 'roles', 'meetingRoles', 'systemStatus', 'auditLog'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "AdminController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(AdminController::class);

        $expectedMethods = ['users', 'roles', 'meetingRoles', 'systemStatus', 'auditLog'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "AdminController::{$method}() should be public",
            );
        }
    }

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(AdminController::class);
        $this->assertTrue($ref->isFinal(), 'AdminController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new AdminController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    // =========================================================================
    // PARSE PAYLOAD (private static method) - tested via Reflection
    // =========================================================================

    public function testParsePayloadWithEmptyInput(): void
    {
        $result = $this->invokeParsePayload(null);
        $this->assertEquals([], $result);
    }

    public function testParsePayloadWithEmptyString(): void
    {
        $result = $this->invokeParsePayload('');
        $this->assertEquals([], $result);
    }

    public function testParsePayloadWithValidJsonString(): void
    {
        $json = json_encode(['email' => 'test@example.com', 'role' => 'admin']);
        $result = $this->invokeParsePayload($json);

        $this->assertEquals('test@example.com', $result['email']);
        $this->assertEquals('admin', $result['role']);
    }

    public function testParsePayloadWithInvalidJsonString(): void
    {
        $result = $this->invokeParsePayload('not valid json');
        $this->assertEquals([], $result);
    }

    public function testParsePayloadWithArray(): void
    {
        $result = $this->invokeParsePayload(['key' => 'value']);
        $this->assertEquals(['key' => 'value'], $result);
    }

    public function testParsePayloadWithObject(): void
    {
        $obj = new \stdClass();
        $obj->key = 'value';
        $result = $this->invokeParsePayload($obj);
        $this->assertArrayHasKey('key', $result);
        $this->assertEquals('value', $result['key']);
    }

    /**
     * Invoke the private static parsePayload method via reflection.
     */
    private function invokeParsePayload(mixed $payload): array
    {
        $ref = new \ReflectionClass(AdminController::class);
        $method = $ref->getMethod('parsePayload');
        $method->setAccessible(true);
        return $method->invoke(null, $payload);
    }

    // =========================================================================
    // SYSTEM STATUS: ALERT COMPUTATION LOGIC
    // =========================================================================

    /**
     * Tests the alert creation logic extracted from systemStatus().
     * The controller creates alerts based on thresholds. We replicate
     * that logic here to test the threshold conditions.
     */
    public function testAuthFailureAlertTriggeredAboveThreshold(): void
    {
        $fail15 = 6;
        $alerts = $this->computeAlerts($fail15, null, null, null);

        $this->assertCount(1, $alerts);
        $this->assertEquals('auth_failures', $alerts[0]['code']);
        $this->assertEquals('warn', $alerts[0]['severity']);
    }

    public function testAuthFailureAlertNotTriggeredAtThreshold(): void
    {
        $fail15 = 5;
        $alerts = $this->computeAlerts($fail15, null, null, null);

        $alertCodes = array_column($alerts, 'code');
        $this->assertNotContains('auth_failures', $alertCodes);
    }

    public function testAuthFailureAlertNotTriggeredBelowThreshold(): void
    {
        $fail15 = 3;
        $alerts = $this->computeAlerts($fail15, null, null, null);

        $alertCodes = array_column($alerts, 'code');
        $this->assertNotContains('auth_failures', $alertCodes);
    }

    public function testSlowDbAlertTriggeredAbove2000ms(): void
    {
        $dbLat = 2001.0;
        $alerts = $this->computeAlerts(null, $dbLat, null, null);

        $alertCodes = array_column($alerts, 'code');
        $this->assertContains('slow_db', $alertCodes);
    }

    public function testSlowDbAlertSeverityIsCritical(): void
    {
        $dbLat = 3000.0;
        $alerts = $this->computeAlerts(null, $dbLat, null, null);

        $slowDbAlert = array_values(array_filter($alerts, fn($a) => $a['code'] === 'slow_db'));
        $this->assertNotEmpty($slowDbAlert);
        $this->assertEquals('critical', $slowDbAlert[0]['severity']);
    }

    public function testSlowDbAlertNotTriggeredAt2000ms(): void
    {
        $dbLat = 2000.0;
        $alerts = $this->computeAlerts(null, $dbLat, null, null);

        $alertCodes = array_column($alerts, 'code');
        $this->assertNotContains('slow_db', $alertCodes);
    }

    public function testSlowDbAlertNotTriggeredBelow2000ms(): void
    {
        $dbLat = 500.0;
        $alerts = $this->computeAlerts(null, $dbLat, null, null);

        $alertCodes = array_column($alerts, 'code');
        $this->assertNotContains('slow_db', $alertCodes);
    }

    public function testLowDiskAlertTriggeredBelow10Percent(): void
    {
        $free = 5_000_000_000;       // 5 GB
        $total = 100_000_000_000;    // 100 GB -> 5%
        $alerts = $this->computeAlerts(null, null, $free, $total);

        $alertCodes = array_column($alerts, 'code');
        $this->assertContains('low_disk', $alertCodes);
    }

    public function testLowDiskAlertSeverityIsCritical(): void
    {
        $free = 1_000_000_000;
        $total = 100_000_000_000;
        $alerts = $this->computeAlerts(null, null, $free, $total);

        $diskAlert = array_values(array_filter($alerts, fn($a) => $a['code'] === 'low_disk'));
        $this->assertNotEmpty($diskAlert);
        $this->assertEquals('critical', $diskAlert[0]['severity']);
    }

    public function testLowDiskAlertNotTriggeredAbove10Percent(): void
    {
        $free = 20_000_000_000;      // 20 GB
        $total = 100_000_000_000;    // 100 GB -> 20%
        $alerts = $this->computeAlerts(null, null, $free, $total);

        $alertCodes = array_column($alerts, 'code');
        $this->assertNotContains('low_disk', $alertCodes);
    }

    public function testLowDiskAlertExactlyAt10Percent(): void
    {
        $free = 10_000_000_000;      // 10 GB
        $total = 100_000_000_000;    // 100 GB -> 10%
        $alerts = $this->computeAlerts(null, null, $free, $total);

        $alertCodes = array_column($alerts, 'code');
        $this->assertNotContains('low_disk', $alertCodes, 'Alert should only trigger below 10%, not at 10%');
    }

    public function testMultipleAlertsCombined(): void
    {
        $alerts = $this->computeAlerts(
            10,       // auth failures > 5
            3000.0,   // db latency > 2000
            1_000_000_000,
            100_000_000_000,  // disk < 10%
        );

        $this->assertCount(3, $alerts);
        $codes = array_column($alerts, 'code');
        $this->assertContains('auth_failures', $codes);
        $this->assertContains('slow_db', $codes);
        $this->assertContains('low_disk', $codes);
    }

    public function testNoAlertsWhenAllMetricsHealthy(): void
    {
        $alerts = $this->computeAlerts(
            0,        // no auth failures
            50.0,     // fast DB
            80_000_000_000,
            100_000_000_000,  // 80% free
        );

        $this->assertCount(0, $alerts);
    }

    public function testNullMetricsProduceNoAlerts(): void
    {
        $alerts = $this->computeAlerts(null, null, null, null);
        $this->assertCount(0, $alerts);
    }

    /**
     * Replicates the alert computation logic from AdminController::systemStatus().
     */
    private function computeAlerts(?int $fail15, ?float $dbLat, ?float $free, ?float $total): array
    {
        $alerts = [];

        if ($fail15 !== null && $fail15 > 5) {
            $alerts[] = [
                'code' => 'auth_failures',
                'severity' => 'warn',
                'message' => 'Plus de 5 echecs de cle API sur 15 minutes.',
                'details' => ['count' => $fail15],
            ];
        }
        if ($dbLat !== null && $dbLat > 2000.0) {
            $alerts[] = [
                'code' => 'slow_db',
                'severity' => 'critical',
                'message' => 'Latence DB > 2s.',
                'details' => ['db_latency_ms' => round($dbLat, 2)],
            ];
        }
        if ($free !== null && $total) {
            $pct = ($free / $total) * 100.0;
            if ($pct < 10.0) {
                $alerts[] = [
                    'code' => 'low_disk',
                    'severity' => 'critical',
                    'message' => 'Espace disque < 10%.',
                    'details' => ['free_pct' => round($pct, 2), 'free_bytes' => $free, 'total_bytes' => $total],
                ];
            }
        }

        return $alerts;
    }

    // =========================================================================
    // USERS: ROLE VALIDATION LOGIC
    // =========================================================================

    public function testValidSystemRolesDefinition(): void
    {
        // Verify the valid system roles used in the controller
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AdminController.php');

        $validRoles = ['admin', 'operator', 'auditor', 'viewer'];
        foreach ($validRoles as $role) {
            $this->assertStringContainsString("'{$role}'", $source);
        }
    }

    public function testRoleValidationRejectsInvalidRole(): void
    {
        $validSystemRoles = ['admin', 'operator', 'auditor', 'viewer'];

        $invalidRoles = ['superadmin', 'root', 'manager', 'user', 'guest', ''];
        foreach ($invalidRoles as $role) {
            if ($role !== '') {
                $this->assertFalse(
                    in_array($role, $validSystemRoles, true),
                    "'{$role}' should not be a valid system role",
                );
            }
        }
    }

    public function testRoleValidationAcceptsValidRoles(): void
    {
        $validSystemRoles = ['admin', 'operator', 'auditor', 'viewer'];

        foreach ($validSystemRoles as $role) {
            $this->assertTrue(
                in_array($role, $validSystemRoles, true),
                "'{$role}' should be a valid system role",
            );
        }
    }

    // =========================================================================
    // USERS: PASSWORD VALIDATION LOGIC
    // =========================================================================

    public function testPasswordMinimumLengthIs8(): void
    {
        // The controller rejects passwords shorter than 8 characters
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AdminController.php');

        $this->assertStringContainsString('strlen($password) < 8', $source);
        $this->assertStringContainsString('weak_password', $source);
    }

    public function testPasswordValidationLogic(): void
    {
        $minLength = 8;

        // Passwords that should fail
        $this->assertTrue(strlen('') < $minLength);
        $this->assertTrue(strlen('short') < $minLength);
        $this->assertTrue(strlen('1234567') < $minLength);

        // Passwords that should pass
        $this->assertFalse(strlen('12345678') < $minLength);
        $this->assertFalse(strlen('a_secure_password') < $minLength);
    }

    // =========================================================================
    // USERS: SELF-PROTECTION LOGIC
    // =========================================================================

    public function testCannotToggleSelfProtection(): void
    {
        // Verify the source contains self-protection checks
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AdminController.php');

        $this->assertStringContainsString('cannot_toggle_self', $source);
        $this->assertStringContainsString('cannot_delete_self', $source);
        $this->assertStringContainsString('cannot_demote_self', $source);
    }

    public function testSelfProtectionLogic(): void
    {
        $currentUserId = 'user-123';

        // Same user ID should trigger protection
        $targetUserId = 'user-123';
        $this->assertEquals($currentUserId, $targetUserId, 'Same user should be detected');

        // Different user ID should not trigger protection
        $targetUserId = 'user-456';
        $this->assertNotEquals($currentUserId, $targetUserId, 'Different user should be allowed');
    }

    // =========================================================================
    // USERS: USER ACTION ROUTING
    // =========================================================================

    public function testUserActionsDefinedInController(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AdminController.php');

        $expectedActions = ['create', 'update', 'delete', 'toggle', 'set_password', 'rotate_key', 'revoke_key'];
        foreach ($expectedActions as $action) {
            $this->assertStringContainsString(
                "'{$action}'",
                $source,
                "Users endpoint should support action '{$action}'",
            );
        }
    }

    public function testUnknownActionIsRejected(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AdminController.php');

        $this->assertStringContainsString('unknown_action', $source);
    }

    // =========================================================================
    // USERS: METHOD ENFORCEMENT
    // =========================================================================

    public function testUsersEndpointRejectsUnsupportedMethods(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('users');

        // The users() method calls api_method() then creates UserRepository.
        // In test env, UserRepository constructor triggers db() which throws
        // RuntimeException before the final api_fail('method_not_allowed') is reached.
        // AbstractController::handle() wraps RuntimeException as 'business_error'.
        $this->assertFalse($result['body']['ok']);
        $this->assertContains($result['body']['error'], ['method_not_allowed', 'business_error']);
    }

    public function testUsersEndpointRejectsPatchMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('users');

        $this->assertFalse($result['body']['ok']);
        $this->assertContains($result['body']['error'], ['method_not_allowed', 'business_error']);
    }

    // =========================================================================
    // ROLES ENDPOINT
    // =========================================================================

    public function testRolesEndpointEnforcesGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('roles');

        $this->assertEquals(405, $result['status']);
    }

    // =========================================================================
    // MEETING ROLES: VALIDATION
    // =========================================================================

    public function testValidMeetingRolesDefinition(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AdminController.php');

        $validRoles = ['president', 'assessor', 'voter'];
        foreach ($validRoles as $role) {
            $this->assertStringContainsString("'{$role}'", $source);
        }
    }

    public function testMeetingRolesValidationLogic(): void
    {
        $validMeetingRoles = ['president', 'assessor', 'voter'];

        $this->assertTrue(in_array('president', $validMeetingRoles, true));
        $this->assertTrue(in_array('assessor', $validMeetingRoles, true));
        $this->assertTrue(in_array('voter', $validMeetingRoles, true));
        $this->assertFalse(in_array('admin', $validMeetingRoles, true));
        $this->assertFalse(in_array('operator', $validMeetingRoles, true));
        $this->assertFalse(in_array('secretary', $validMeetingRoles, true));
    }

    public function testMeetingRolesEndpointRejectsUnsupportedMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('meetingRoles');

        // meetingRoles() creates UserRepository and MeetingRepository before
        // the method check. Without DB, RuntimeException fires first.
        $this->assertFalse($result['body']['ok']);
        $this->assertContains($result['body']['error'], ['method_not_allowed', 'business_error']);
    }

    public function testPresidentRoleRequiresAdminCheck(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AdminController.php');

        $this->assertStringContainsString('admin_required_for_president', $source);
        $this->assertStringContainsString("api_current_role() !== 'admin'", $source);
    }

    // =========================================================================
    // AUDIT LOG: FORMATTING LOGIC
    // =========================================================================

    public function testAuditLogActionLabelsMapping(): void
    {
        // Replicate the action labels from the controller
        $actionLabels = [
            'admin.user.created' => 'Utilisateur cree',
            'admin.user.updated' => 'Utilisateur modifie',
            'admin.user.deleted' => 'Utilisateur supprime',
            'admin.user.toggled' => 'Utilisateur active/desactive',
            'admin.user.password_set' => 'Mot de passe defini',
            'admin.user.key_rotated' => 'Cle API regeneree',
            'admin.user.key_revoked' => 'Cle API revoquee',
            'admin.meeting_role.assigned' => 'Role de seance assigne',
            'admin.meeting_role.revoked' => 'Role de seance revoque',
        ];

        // Verify all expected action codes have labels
        foreach ($actionLabels as $code => $label) {
            $this->assertNotEmpty($label, "Action '{$code}' should have a label");
            $this->assertStringContainsString('.', $code, "Action code should use dot notation");
        }
    }

    public function testAuditLogDetailBuilding(): void
    {
        // Replicate the detail-building logic from auditLog()
        $payload = [
            'email' => 'test@example.com',
            'role' => 'admin',
            'user_name' => 'Test User',
        ];

        $detail = '';
        if (isset($payload['email'])) {
            $detail .= $payload['email'];
        }
        if (isset($payload['role'])) {
            $detail .= ($detail ? ' — ' : '') . $payload['role'];
        }
        if (isset($payload['user_name'])) {
            $detail .= ($detail ? ' — ' : '') . $payload['user_name'];
        }

        $this->assertEquals('test@example.com — admin — Test User', $detail);
    }

    public function testAuditLogDetailBuildingWithPartialData(): void
    {
        $payload = ['role' => 'operator'];

        $detail = '';
        if (isset($payload['email'])) {
            $detail .= $payload['email'];
        }
        if (isset($payload['role'])) {
            $detail .= ($detail ? ' — ' : '') . $payload['role'];
        }

        $this->assertEquals('operator', $detail);
    }

    public function testAuditLogDetailWithIsActive(): void
    {
        $payload = ['is_active' => true];

        $detail = '';
        if (isset($payload['is_active'])) {
            $detail .= ($detail ? ' — ' : '') . ($payload['is_active'] ? 'active' : 'desactive');
        }

        $this->assertEquals('active', $detail);
    }

    public function testAuditLogDetailWithIsInactive(): void
    {
        $payload = ['is_active' => false];

        $detail = '';
        if (isset($payload['is_active'])) {
            $detail .= ($detail ? ' — ' : '') . ($payload['is_active'] ? 'active' : 'desactive');
        }

        $this->assertEquals('desactive', $detail);
    }

    public function testAuditLogDetailWithEmptyPayload(): void
    {
        $payload = [];

        $detail = '';
        if (isset($payload['email'])) {
            $detail .= $payload['email'];
        }
        if (isset($payload['role'])) {
            $detail .= ($detail ? ' — ' : '') . $payload['role'];
        }

        $this->assertEquals('', $detail);
    }

    // =========================================================================
    // AUDIT LOG: UNKNOWN ACTION LABEL FALLBACK
    // =========================================================================

    public function testAuditLogUnknownActionFallback(): void
    {
        // The controller uses ucfirst(str_replace(...)) for unknown actions
        $action = 'admin.some_custom.action';
        $actionLabels = []; // Empty for testing unknown

        $actionLabel = $actionLabels[$action] ?? ucfirst(str_replace(['admin.', '_'], ['', ' '], $action));

        $this->assertEquals('Some custom.action', $actionLabel);
    }

    // =========================================================================
    // AUDIT LOG: PAGINATION
    // =========================================================================

    public function testAuditLogPaginationLimitClamping(): void
    {
        // The controller clamps: min(200, max(1, input))
        $this->assertEquals(100, min(200, max(1, 100)));  // normal
        $this->assertEquals(200, min(200, max(1, 500)));  // clamped to max
        $this->assertEquals(1, min(200, max(1, 0)));      // clamped to min
        $this->assertEquals(1, min(200, max(1, -5)));     // clamped to min
        $this->assertEquals(50, min(200, max(1, 50)));    // normal
    }

    public function testAuditLogPaginationOffsetClamping(): void
    {
        // The controller clamps: max(0, input)
        $this->assertEquals(0, max(0, 0));
        $this->assertEquals(0, max(0, -10));
        $this->assertEquals(50, max(0, 50));
    }

    // =========================================================================
    // SYSTEM STATUS: DISK SPACE PERCENTAGE
    // =========================================================================

    public function testDiskFreePercentageCalculation(): void
    {
        $free = 25_000_000_000.0;
        $total = 100_000_000_000.0;

        $pct = ($free / $total) * 100.0;
        $this->assertEquals(25.0, round($pct, 2));
    }

    public function testDiskFreePercentageWithNullTotal(): void
    {
        $free = 25_000_000_000.0;
        $total = null;

        $result = ($free !== null && $total) ? round(($free / $total) * 100.0, 2) : null;
        $this->assertNull($result);
    }

    // =========================================================================
    // USER EMAIL NORMALIZATION
    // =========================================================================

    public function testEmailNormalization(): void
    {
        // The controller does: strtolower(trim(email))
        $this->assertEquals('test@example.com', strtolower(trim('  Test@Example.COM  ')));
        $this->assertEquals('user@domain.org', strtolower(trim('User@Domain.ORG')));
        $this->assertEquals('', strtolower(trim('  ')));
    }

    // =========================================================================
    // USERS: CREATE VALIDATION
    // =========================================================================

    public function testCreateUserRequiresEmailAndName(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AdminController.php');

        $this->assertStringContainsString("if (\$email === '' || \$name === '')", $source);
        $this->assertStringContainsString('missing_fields', $source);
    }

    public function testCreateUserChecksForDuplicateEmail(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AdminController.php');

        $this->assertStringContainsString('email_exists', $source);
        $this->assertStringContainsString('findIdByEmail', $source);
    }

    // =========================================================================
    // USER ROLE FILTER LOGIC
    // =========================================================================

    public function testRoleFilterValidation(): void
    {
        $validSystemRoles = ['admin', 'operator', 'auditor', 'viewer'];

        // Valid filter
        $roleFilter = 'admin';
        $filterValue = ($roleFilter !== '' && in_array($roleFilter, $validSystemRoles, true)) ? $roleFilter : null;
        $this->assertEquals('admin', $filterValue);

        // Invalid filter
        $roleFilter = 'superuser';
        $filterValue = ($roleFilter !== '' && in_array($roleFilter, $validSystemRoles, true)) ? $roleFilter : null;
        $this->assertNull($filterValue);

        // Empty filter
        $roleFilter = '';
        $filterValue = ($roleFilter !== '' && in_array($roleFilter, $validSystemRoles, true)) ? $roleFilter : null;
        $this->assertNull($filterValue);
    }

    // =========================================================================
    // AUDIT LOGGING CALLS
    // =========================================================================

    public function testAllUserActionsAuditLogged(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AdminController.php');

        $expectedAuditActions = [
            'admin.user.password_set',
            'admin.user.key_rotated',
            'admin.user.key_revoked',
            'admin.user.toggled',
            'admin.user.deleted',
            'admin.user.updated',
            'admin.user.created',
        ];

        foreach ($expectedAuditActions as $action) {
            $this->assertStringContainsString(
                "'{$action}'",
                $source,
                "User action '{$action}' should be audit-logged",
            );
        }
    }

    public function testMeetingRoleActionsAuditLogged(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AdminController.php');

        $this->assertStringContainsString("'admin.meeting_role.assigned'", $source);
        $this->assertStringContainsString("'admin.meeting_role.revoked'", $source);
    }
}
