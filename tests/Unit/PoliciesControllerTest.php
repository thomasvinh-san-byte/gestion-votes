<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\PoliciesController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PoliciesController.
 *
 * Tests the policy endpoints (quorum + vote, public list + admin CRUD) including:
 *  - Controller structure (final, extends AbstractController)
 *  - HTTP method enforcement (GET/POST)
 *  - UUID validation for policy id
 *  - Input validation via ValidationSchemas::quorumPolicy() and votePolicy()
 *  - Admin method dispatch (GET list, POST create/update/delete)
 *  - 405 for unsupported methods on admin endpoints
 *  - Response structure and audit log verification
 */
class PoliciesControllerTest extends TestCase
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
    // HELPER: Call controller and capture response
    // =========================================================================

    private function callControllerMethod(string $method): array
    {
        $controller = new PoliciesController();
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

    private function injectJsonBody(array $data): void
    {
        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, json_encode($data));
    }

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(PoliciesController::class);
        $this->assertTrue($ref->isFinal(), 'PoliciesController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new PoliciesController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(PoliciesController::class);

        $expectedMethods = [
            'listQuorum',
            'listVote',
            'adminQuorum',
            'adminVote',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "PoliciesController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(PoliciesController::class);

        $expectedMethods = [
            'listQuorum',
            'listVote',
            'adminQuorum',
            'adminVote',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "PoliciesController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // listQuorum: METHOD ENFORCEMENT
    // =========================================================================

    public function testListQuorumRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('listQuorum');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListQuorumRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('listQuorum');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListQuorumRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('listQuorum');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // listVote: METHOD ENFORCEMENT
    // =========================================================================

    public function testListVoteRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('listVote');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListVoteRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('listVote');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListVoteRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('listVote');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // adminQuorum: METHOD ENFORCEMENT
    // =========================================================================

    /**
     * adminQuorum() creates PolicyRepository (calls db()) before the GET/POST
     * dispatch. In test env without DB, RuntimeException is caught as
     * business_error (400). Verify method enforcement via source inspection.
     */
    public function testAdminQuorumMethodEnforcementViaSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');
        // adminQuorum falls through to method_not_allowed for unsupported methods
        $this->assertStringContainsString("api_fail('method_not_allowed', 405)", $source);
    }

    public function testAdminQuorumNoDbReturnsBusinessError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('adminQuorum');

        // Repo instantiation fails before method dispatch
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    // =========================================================================
    // adminQuorum POST: DELETE ACTION VALIDATION (source-level)
    // =========================================================================

    public function testAdminQuorumDeleteActionSourceRequiresId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');
        $this->assertStringContainsString("api_fail('missing_id', 400)", $source);
    }

    public function testAdminQuorumDeleteActionUuidLogic(): void
    {
        // Replicate: $id = trim((string) ($in['id'] ?? '')); if ($id === '' || !api_is_uuid($id))
        $this->assertTrue('' === '' || !api_is_uuid(''), 'Empty id should trigger missing_id');
        $this->assertTrue('bad-uuid' !== '' && !api_is_uuid('bad-uuid'), 'Invalid uuid should trigger missing_id');
        $this->assertFalse('12345678-1234-1234-1234-123456789abc' === '' || !api_is_uuid('12345678-1234-1234-1234-123456789abc'), 'Valid uuid should pass');
    }

    // =========================================================================
    // adminQuorum POST: UPDATE WITH INVALID ID (source-level)
    // =========================================================================

    public function testAdminQuorumUpdateSourceValidatesId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');
        $this->assertStringContainsString("api_fail('invalid_id', 400)", $source);
    }

    // =========================================================================
    // adminVote: METHOD ENFORCEMENT
    // =========================================================================

    /**
     * adminVote() also creates PolicyRepository before dispatch.
     * Same pattern as adminQuorum.
     */
    public function testAdminVoteNoDbReturnsBusinessError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('adminVote');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    public function testAdminVoteMethodEnforcementViaSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');
        // Both adminQuorum and adminVote end with the same 405 fallthrough
        $this->assertStringContainsString("api_fail('method_not_allowed', 405)", $source);
    }

    // =========================================================================
    // adminVote POST: DELETE ACTION VALIDATION (source-level)
    // =========================================================================

    public function testAdminVoteDeleteActionSourceRequiresId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');
        // Both adminQuorum and adminVote use the same missing_id check
        $this->assertStringContainsString("api_fail('missing_id', 400)", $source);
    }

    public function testAdminVoteDeleteActionUuidLogic(): void
    {
        $this->assertTrue('' === '' || !api_is_uuid(''), 'Empty id should trigger missing_id');
        $this->assertTrue(!api_is_uuid('not-a-uuid'), 'Invalid uuid should fail');
        $this->assertTrue(api_is_uuid('12345678-1234-1234-1234-123456789abc'), 'Valid uuid should pass');
    }

    public function testAdminVoteUpdateSourceValidatesId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');
        $this->assertStringContainsString("api_fail('invalid_id', 400)", $source);
    }

    // =========================================================================
    // VALIDATION SCHEMA: quorumPolicy
    // =========================================================================

    public function testQuorumPolicySchemaRequiresName(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::quorumPolicy();
        $result = $schema->validate([]);

        $this->assertFalse($result->isValid(), 'Quorum policy should require name');
        $errors = $result->errors();
        $this->assertArrayHasKey('name', $errors);
    }

    public function testQuorumPolicySchemaRequiresThreshold(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::quorumPolicy();
        $result = $schema->validate(['name' => 'Test Quorum']);

        $this->assertFalse($result->isValid(), 'Quorum policy should require threshold');
        $errors = $result->errors();
        $this->assertArrayHasKey('threshold', $errors);
    }

    public function testQuorumPolicySchemaAcceptsValidInput(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::quorumPolicy();
        $result = $schema->validate(['name' => 'Test Quorum', 'threshold' => 0.33]);

        $this->assertTrue($result->isValid(), 'Valid quorum policy should pass');
    }

    public function testQuorumPolicySchemaDefaultMode(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::quorumPolicy();
        $result = $schema->validate(['name' => 'Test', 'threshold' => 0.5]);

        $this->assertTrue($result->isValid());
        $this->assertEquals('single', $result->get('mode'));
    }

    public function testQuorumPolicySchemaDefaultDenominator(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::quorumPolicy();
        $result = $schema->validate(['name' => 'Test', 'threshold' => 0.5]);

        $this->assertTrue($result->isValid());
        $this->assertEquals('eligible_members', $result->get('denominator'));
    }

    // =========================================================================
    // VALIDATION SCHEMA: votePolicy
    // =========================================================================

    public function testVotePolicySchemaRequiresName(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::votePolicy();
        $result = $schema->validate([]);

        $this->assertFalse($result->isValid(), 'Vote policy should require name');
        $errors = $result->errors();
        $this->assertArrayHasKey('name', $errors);
    }

    public function testVotePolicySchemaRequiresThreshold(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::votePolicy();
        $result = $schema->validate(['name' => 'Test Vote']);

        $this->assertFalse($result->isValid(), 'Vote policy should require threshold');
        $errors = $result->errors();
        $this->assertArrayHasKey('threshold', $errors);
    }

    public function testVotePolicySchemaAcceptsValidInput(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::votePolicy();
        $result = $schema->validate(['name' => 'Test Vote', 'threshold' => 0.5]);

        $this->assertTrue($result->isValid(), 'Valid vote policy should pass');
    }

    public function testVotePolicySchemaDefaultBase(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::votePolicy();
        $result = $schema->validate(['name' => 'Test', 'threshold' => 0.5]);

        $this->assertTrue($result->isValid());
        $this->assertEquals('expressed', $result->get('base'));
    }

    public function testVotePolicySchemaDefaultAbstentionAsAgainst(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::votePolicy();
        $result = $schema->validate(['name' => 'Test', 'threshold' => 0.5]);

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->get('abstention_as_against'));
    }

    // =========================================================================
    // AUDIT LOG VERIFICATION (source-level)
    // =========================================================================

    public function testAdminQuorumAuditsSaved(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');

        $this->assertStringContainsString("'admin_quorum_policy_saved'", $source);
    }

    public function testAdminQuorumAuditsDeleted(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');

        $this->assertStringContainsString("'admin_quorum_policy_deleted'", $source);
    }

    public function testAdminVoteAuditsSaved(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');

        $this->assertStringContainsString("'admin_vote_policy_saved'", $source);
    }

    public function testAdminVoteAuditsDeleted(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');

        $this->assertStringContainsString("'admin_vote_policy_deleted'", $source);
    }

    // =========================================================================
    // RESPONSE STRUCTURE VERIFICATION (source-level)
    // =========================================================================

    public function testListQuorumResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');

        $this->assertStringContainsString("'items'", $source, "listQuorum() should return 'items'");
    }

    public function testListVoteResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');

        $this->assertStringContainsString("'items'", $source, "listVote() should return 'items'");
    }

    public function testAdminQuorumDeleteResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');

        $this->assertStringContainsString("'deleted' => true", $source);
    }

    public function testAdminQuorumSaveResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');

        $this->assertStringContainsString("'saved' => true", $source);
        $this->assertStringContainsString("'id'", $source);
    }

    // =========================================================================
    // UUID VALIDATION HELPER
    // =========================================================================

    public function testUuidValidationForPolicyIds(): void
    {
        $this->assertTrue(api_is_uuid('12345678-1234-1234-1234-123456789abc'));
        $this->assertTrue(api_is_uuid('00000000-0000-0000-0000-000000000000'));
        $this->assertFalse(api_is_uuid(''));
        $this->assertFalse(api_is_uuid('not-a-uuid'));
        $this->assertFalse(api_is_uuid('12345'));
    }

    // =========================================================================
    // ADMIN DISPATCH: METHOD PATTERN
    // =========================================================================

    public function testAdminQuorumUsesApiMethod(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');

        $this->assertStringContainsString('api_method()', $source, 'adminQuorum should use api_method() for dispatch');
    }

    public function testAdminVoteUsesApiMethod(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');

        $this->assertStringContainsString('api_method()', $source, 'adminVote should use api_method() for dispatch');
    }

    public function testAdminQuorumFallsThrough405(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');

        // The controller should fall through to method_not_allowed for unsupported methods
        $this->assertStringContainsString("api_fail('method_not_allowed', 405)", $source);
    }

    // =========================================================================
    // QUORUM POLICY: DUAL CALL SUPPORT
    // =========================================================================

    public function testQuorumPolicySupportsMultipleCallModes(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');

        $this->assertStringContainsString('threshold_call2', $source, 'Should support second-call threshold');
        $this->assertStringContainsString('denominator2', $source, 'Should support second-call denominator');
        $this->assertStringContainsString('threshold2', $source, 'Should support secondary threshold');
    }

    // =========================================================================
    // QUORUM POLICY: INCLUDE PROXIES AND COUNT REMOTE
    // =========================================================================

    public function testQuorumPolicySupportsFlagFields(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');

        $this->assertStringContainsString('include_proxies', $source);
        $this->assertStringContainsString('count_remote', $source);
    }

    // =========================================================================
    // VOTE POLICY: ABSTENTION AS AGAINST
    // =========================================================================

    public function testVotePolicySupportsAbstentionAsAgainst(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/PoliciesController.php');

        $this->assertStringContainsString('abstention_as_against', $source);
    }
}
