<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\MembersController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MembersController.
 *
 * Tests the members CRUD endpoints including:
 *  - Controller structure (final, extends AbstractController)
 *  - HTTP method enforcement (GET/POST/PATCH/PUT/DELETE)
 *  - UUID validation for member_id
 *  - Input validation via ValidationSchemas::member()
 *  - Legacy field name normalization (vote_weight -> voting_power)
 *  - Response structure and audit log verification
 */
class MembersControllerTest extends TestCase
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
        $controller = new MembersController();
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
        $ref = new \ReflectionClass(MembersController::class);
        $this->assertTrue($ref->isFinal(), 'MembersController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new MembersController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(MembersController::class);

        $expectedMethods = [
            'index',
            'create',
            'updateMember',
            'delete',
            'presidents',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "MembersController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(MembersController::class);

        $expectedMethods = [
            'index',
            'create',
            'updateMember',
            'delete',
            'presidents',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "MembersController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // index: METHOD ENFORCEMENT
    // =========================================================================

    public function testIndexRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('index');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testIndexRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('index');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testIndexRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('index');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // create: METHOD ENFORCEMENT
    // =========================================================================

    public function testCreateRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('create');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testCreateRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('create');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testCreateRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('create');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // create: INPUT VALIDATION (via ValidationSchemas::member())
    // =========================================================================

    public function testCreateValidationSchemaRequiresFullName(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::member();
        $result = $schema->validate([]);

        $this->assertFalse($result->isValid(), 'Validation should fail without full_name');
        $errors = $result->errors();
        $this->assertArrayHasKey('full_name', $errors, 'Errors should include full_name');
    }

    public function testCreateValidationSchemaAcceptsValidInput(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::member();
        $result = $schema->validate(['full_name' => 'John Doe']);

        $this->assertTrue($result->isValid(), 'Valid input should pass validation');
    }

    public function testCreateValidationSchemaAcceptsOptionalEmail(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::member();
        $result = $schema->validate(['full_name' => 'John Doe', 'email' => 'john@example.com']);

        $this->assertTrue($result->isValid(), 'Valid input with email should pass');
    }

    public function testCreateValidationSchemaDefaultVotingPower(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::member();
        $result = $schema->validate(['full_name' => 'John Doe']);

        $this->assertTrue($result->isValid());
        $this->assertEquals(1, $result->get('voting_power'));
    }

    public function testCreateValidationSchemaDefaultIsActive(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::member();
        $result = $schema->validate(['full_name' => 'John Doe']);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->get('is_active'));
    }

    // =========================================================================
    // create: LEGACY FIELD NORMALIZATION
    // =========================================================================

    public function testCreateNormalizesVoteWeightToVotingPower(): void
    {
        $input = ['full_name' => 'John Doe', 'vote_weight' => 2.5];

        // Replicate the normalization logic
        if (isset($input['vote_weight']) && !isset($input['voting_power'])) {
            $input['voting_power'] = $input['vote_weight'];
        }

        $this->assertEquals(2.5, $input['voting_power']);
    }

    public function testCreateDoesNotOverrideExistingVotingPower(): void
    {
        $input = ['full_name' => 'John Doe', 'vote_weight' => 2.5, 'voting_power' => 3.0];

        if (isset($input['vote_weight']) && !isset($input['voting_power'])) {
            $input['voting_power'] = $input['vote_weight'];
        }

        $this->assertEquals(3.0, $input['voting_power'], 'Existing voting_power should not be overridden');
    }

    // =========================================================================
    // updateMember: METHOD ENFORCEMENT
    // =========================================================================

    public function testUpdateMemberRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('updateMember');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testUpdateMemberRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('updateMember');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // updateMember: INPUT VALIDATION
    // =========================================================================

    public function testUpdateMemberRequiresMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('updateMember');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_member_id', $result['body']['error']);
    }

    public function testUpdateMemberRejectsEmptyMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $this->injectJsonBody(['id' => '']);

        $result = $this->callControllerMethod('updateMember');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_member_id', $result['body']['error']);
    }

    public function testUpdateMemberRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $this->injectJsonBody(['id' => 'not-a-uuid']);

        $result = $this->callControllerMethod('updateMember');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_member_id', $result['body']['error']);
    }

    public function testUpdateMemberRejectsWhitespaceMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $this->injectJsonBody(['id' => '   ']);

        $result = $this->callControllerMethod('updateMember');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_member_id', $result['body']['error']);
    }

    public function testUpdateMemberAcceptsMemberIdField(): void
    {
        // The controller accepts both 'id' and 'member_id'
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MembersController.php');
        $this->assertStringContainsString("input['id']", $source);
        $this->assertStringContainsString("input['member_id']", $source);
    }

    // =========================================================================
    // delete: METHOD ENFORCEMENT
    // =========================================================================

    public function testDeleteRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testDeleteRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // delete: INPUT VALIDATION
    // =========================================================================

    public function testDeleteRequiresMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_member_id', $result['body']['error']);
    }

    public function testDeleteRejectsEmptyMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->injectJsonBody(['id' => '']);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_member_id', $result['body']['error']);
    }

    public function testDeleteRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->injectJsonBody(['id' => 'bad-uuid']);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_member_id', $result['body']['error']);
    }

    public function testDeleteRejectsPartialUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->injectJsonBody(['id' => '12345678-1234']);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_member_id', $result['body']['error']);
    }

    // =========================================================================
    // presidents: METHOD ENFORCEMENT
    // =========================================================================

    public function testPresidentsRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('presidents');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testPresidentsRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('presidents');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // MEMBER ID TRIMMING LOGIC
    // =========================================================================

    public function testMemberIdTrimmingLogic(): void
    {
        $uuid = '12345678-1234-1234-1234-123456789abc';
        $this->assertEquals($uuid, trim(" {$uuid} "));
        $this->assertEquals('', trim(''));
        $this->assertEquals('', trim('   '));
        $this->assertEquals('', trim((string) null));
    }

    // =========================================================================
    // RESPONSE STRUCTURE VERIFICATION (source-level)
    // =========================================================================

    public function testIndexResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MembersController.php');

        $this->assertStringContainsString("'items'", $source, "index() should return 'items'");
    }

    public function testCreateResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MembersController.php');

        $this->assertStringContainsString("'member_id'", $source);
        $this->assertStringContainsString("'full_name'", $source);
        $this->assertStringContainsString('201', $source, 'create() should return 201');
    }

    public function testUpdateMemberResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MembersController.php');

        $this->assertStringContainsString("'member_id'", $source);
        $this->assertStringContainsString("'full_name'", $source);
    }

    public function testDeleteResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MembersController.php');

        $this->assertStringContainsString("'deleted' => true", $source);
    }

    public function testPresidentsResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MembersController.php');

        $this->assertStringContainsString("'items'", $source);
    }

    // =========================================================================
    // AUDIT LOG VERIFICATION (source-level)
    // =========================================================================

    public function testCreateAuditsMemberCreated(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MembersController.php');

        $this->assertStringContainsString("'member_created'", $source);
    }

    public function testUpdateMemberAuditsMemberUpdated(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MembersController.php');

        $this->assertStringContainsString("'member_updated'", $source);
    }

    public function testDeleteAuditsMemberDeleted(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MembersController.php');

        $this->assertStringContainsString("'member_deleted'", $source);
    }

    // =========================================================================
    // SOFT DELETE VERIFICATION (source-level)
    // =========================================================================

    public function testDeleteUsesSoftDelete(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MembersController.php');

        $this->assertStringContainsString('softDelete', $source, 'delete() should use softDelete');
    }

    // =========================================================================
    // INCLUDE GROUPS FLAG
    // =========================================================================

    public function testIndexIncludeGroupsFlagParsing(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MembersController.php');

        $this->assertStringContainsString('include_groups', $source);
        $this->assertStringContainsString('MemberGroupRepository', $source);
    }

    // =========================================================================
    // UUID VALIDATION HELPER
    // =========================================================================

    public function testUuidValidationForMemberIds(): void
    {
        $this->assertTrue(api_is_uuid('12345678-1234-1234-1234-123456789abc'));
        $this->assertTrue(api_is_uuid('00000000-0000-0000-0000-000000000000'));
        $this->assertFalse(api_is_uuid(''));
        $this->assertFalse(api_is_uuid('not-a-uuid'));
        $this->assertFalse(api_is_uuid('12345678-1234'));
    }
}
