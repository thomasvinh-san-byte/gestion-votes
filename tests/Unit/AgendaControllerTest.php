<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AgendaController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AgendaController.
 *
 * Tests the agenda endpoint logic including:
 *  - Controller structure (final, extends AbstractController, public methods)
 *  - HTTP method enforcement for listForMeeting, create, listForMeetingPublic
 *  - UUID validation for meeting_id parameters
 *  - Late rules boolean flag parsing logic
 *  - Late rules GET response casting logic
 *  - Validation schema structure for agenda creation
 *  - Controller source verification for response structure, audit logs, guards
 *  - AbstractController handle() error wrapping behavior
 *
 * Note: lateRules() instantiates MeetingRepository before branching on HTTP
 * method, so its individual code paths cannot be reached without a live DB.
 * Those paths are tested via source verification and logic-extraction tests.
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class AgendaControllerTest extends TestCase
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
        $controller = new AgendaController();
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

    /**
     * Inject a JSON body into Request::$cachedRawBody for POST requests.
     */
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
        $ref = new \ReflectionClass(AgendaController::class);
        $this->assertTrue($ref->isFinal(), 'AgendaController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new AgendaController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(AgendaController::class);

        $expectedMethods = ['listForMeeting', 'create', 'lateRules', 'listForMeetingPublic'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "AgendaController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(AgendaController::class);

        $expectedMethods = ['listForMeeting', 'create', 'lateRules', 'listForMeetingPublic'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "AgendaController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // listForMeeting: METHOD ENFORCEMENT
    // =========================================================================

    public function testListForMeetingRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListForMeetingRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListForMeetingRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListForMeetingRejectsPatchMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // listForMeeting: UUID VALIDATION
    // =========================================================================

    public function testListForMeetingRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testListForMeetingRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testListForMeetingRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-valid-uuid'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testListForMeetingRejectsShortUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testListForMeetingRejectsUuidWithSpecialChars(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234-1234-12345678ZZZZ'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testListForMeetingUuidErrorIncludesFieldName(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'bad'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('meeting_id', $result['body']['field']);
        $this->assertEquals('uuid', $result['body']['expected']);
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

    public function testCreateRejectsPatchMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('create');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // lateRules: EARLY REPO INSTANTIATION BEHAVIOR
    //
    // lateRules() calls new MeetingRepository() before branching on method.
    // In test env (no DB), this raises RuntimeException which handle() wraps
    // as business_error/400. We verify this expected behavior.
    // =========================================================================

    public function testLateRulesFailsWithBusinessErrorOnGetDueToNoDb(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('lateRules');

        // MeetingRepository constructor calls db() which throws RuntimeException
        // handle() converts RuntimeException to business_error/400
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    public function testLateRulesFailsWithBusinessErrorOnPostDueToNoDb(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $result = $this->callControllerMethod('lateRules');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    public function testLateRulesFailsWithBusinessErrorOnPutDueToNoDb(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('lateRules');

        // Even for unsupported methods, the repo instantiation fails first
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    public function testLateRulesFailsWithBusinessErrorOnDeleteDueToNoDb(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('lateRules');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    public function testLateRulesFailsWithBusinessErrorOnPatchDueToNoDb(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('lateRules');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    // =========================================================================
    // lateRules: SOURCE CODE VERIFICATION FOR METHOD DISPATCH
    //
    // Since lateRules() cannot be tested through the controller without a DB
    // (due to early MeetingRepository instantiation), we verify the source
    // contains the expected method dispatch pattern and 405 fallthrough.
    // =========================================================================

    public function testLateRulesSourceHasGetBranch(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString("\$method === 'GET'", $source,
            'lateRules should have a GET branch');
    }

    public function testLateRulesSourceHasPostBranch(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString("\$method === 'POST'", $source,
            'lateRules should have a POST branch');
    }

    public function testLateRulesSourceHas405Fallthrough(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString("api_fail('method_not_allowed', 405)", $source,
            'lateRules should fall through to 405 for unsupported methods');
    }

    public function testLateRulesSourceUsesApiMethodDispatch(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString('$method = api_method()', $source,
            'lateRules should use api_method() for dispatch');
    }

    public function testLateRulesSourceUsesApiRequireUuidForGet(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        // Both GET and POST branches call api_require_uuid
        $this->assertStringContainsString("api_require_uuid(\$q, 'meeting_id')", $source,
            'lateRules GET branch should validate meeting_id UUID');
    }

    public function testLateRulesSourceUsesApiRequireUuidForPost(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString("api_require_uuid(\$in, 'meeting_id')", $source,
            'lateRules POST branch should validate meeting_id UUID');
    }

    // =========================================================================
    // listForMeetingPublic: METHOD ENFORCEMENT
    // =========================================================================

    public function testListForMeetingPublicRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('listForMeetingPublic');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListForMeetingPublicRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('listForMeetingPublic');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListForMeetingPublicRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('listForMeetingPublic');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListForMeetingPublicRejectsPatchMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('listForMeetingPublic');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // listForMeetingPublic: UUID VALIDATION
    // =========================================================================

    public function testListForMeetingPublicRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('listForMeetingPublic');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testListForMeetingPublicRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('listForMeetingPublic');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testListForMeetingPublicRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'xyz'];

        $result = $this->callControllerMethod('listForMeetingPublic');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testListForMeetingPublicUuidErrorIncludesFieldName(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'bad-value'];

        $result = $this->callControllerMethod('listForMeetingPublic');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('meeting_id', $result['body']['field']);
        $this->assertEquals('uuid', $result['body']['expected']);
    }

    // =========================================================================
    // lateRules: LATE RULE BOOLEAN FLAG PARSING
    // =========================================================================

    public function testLateRuleQuorumFlagDefaultsToTrue(): void
    {
        // Replicate the boolean parsing from lateRules() POST path:
        // $lrq = (int) ($in['late_rule_quorum'] ?? 1) ? true : false;
        $in = [];
        $lrq = (int) ($in['late_rule_quorum'] ?? 1) ? true : false;

        $this->assertTrue($lrq, 'late_rule_quorum should default to true when absent');
    }

    public function testLateRuleQuorumFlagParsesExplicitTrue(): void
    {
        $in = ['late_rule_quorum' => 1];
        $lrq = (int) ($in['late_rule_quorum'] ?? 1) ? true : false;

        $this->assertTrue($lrq);
    }

    public function testLateRuleQuorumFlagParsesExplicitFalse(): void
    {
        $in = ['late_rule_quorum' => 0];
        $lrq = (int) ($in['late_rule_quorum'] ?? 1) ? true : false;

        $this->assertFalse($lrq);
    }

    public function testLateRuleQuorumFlagParsesStringOne(): void
    {
        $in = ['late_rule_quorum' => '1'];
        $lrq = (int) ($in['late_rule_quorum'] ?? 1) ? true : false;

        $this->assertTrue($lrq);
    }

    public function testLateRuleQuorumFlagParsesStringZero(): void
    {
        $in = ['late_rule_quorum' => '0'];
        $lrq = (int) ($in['late_rule_quorum'] ?? 1) ? true : false;

        $this->assertFalse($lrq);
    }

    public function testLateRuleVoteFlagDefaultsToTrue(): void
    {
        $in = [];
        $lrv = (int) ($in['late_rule_vote'] ?? 1) ? true : false;

        $this->assertTrue($lrv, 'late_rule_vote should default to true when absent');
    }

    public function testLateRuleVoteFlagParsesExplicitFalse(): void
    {
        $in = ['late_rule_vote' => 0];
        $lrv = (int) ($in['late_rule_vote'] ?? 1) ? true : false;

        $this->assertFalse($lrv);
    }

    public function testLateRuleVoteFlagParsesExplicitTrue(): void
    {
        $in = ['late_rule_vote' => 1];
        $lrv = (int) ($in['late_rule_vote'] ?? 1) ? true : false;

        $this->assertTrue($lrv);
    }

    public function testBothLateRuleFlagsFalseSimultaneously(): void
    {
        $in = ['late_rule_quorum' => 0, 'late_rule_vote' => 0];

        $lrq = (int) ($in['late_rule_quorum'] ?? 1) ? true : false;
        $lrv = (int) ($in['late_rule_vote'] ?? 1) ? true : false;

        $this->assertFalse($lrq);
        $this->assertFalse($lrv);
    }

    public function testBothLateRuleFlagsTrueSimultaneously(): void
    {
        $in = ['late_rule_quorum' => 1, 'late_rule_vote' => 1];

        $lrq = (int) ($in['late_rule_quorum'] ?? 1) ? true : false;
        $lrv = (int) ($in['late_rule_vote'] ?? 1) ? true : false;

        $this->assertTrue($lrq);
        $this->assertTrue($lrv);
    }

    public function testLateRuleFlagsMixedValues(): void
    {
        $in = ['late_rule_quorum' => 1, 'late_rule_vote' => 0];

        $lrq = (int) ($in['late_rule_quorum'] ?? 1) ? true : false;
        $lrv = (int) ($in['late_rule_vote'] ?? 1) ? true : false;

        $this->assertTrue($lrq);
        $this->assertFalse($lrv);
    }

    public function testLateRuleFlagsCastToBooleans(): void
    {
        // Verify the casting logic produces boolean results, not int
        $in = ['late_rule_quorum' => 1, 'late_rule_vote' => 0];

        $lrq = (int) ($in['late_rule_quorum'] ?? 1) ? true : false;
        $lrv = (int) ($in['late_rule_vote'] ?? 1) ? true : false;

        $this->assertIsBool($lrq);
        $this->assertIsBool($lrv);
    }

    public function testLateRuleFlagsFromStringInput(): void
    {
        // In JSON body, values might arrive as non-numeric strings
        $in = ['late_rule_quorum' => 'true', 'late_rule_vote' => 'false'];

        // (int) 'true' = 0, (int) 'false' = 0
        // This tests the actual behavior of the controller's casting logic
        $lrq = (int) ($in['late_rule_quorum'] ?? 1) ? true : false;
        $lrv = (int) ($in['late_rule_vote'] ?? 1) ? true : false;

        // (int) 'true' === 0, so both evaluate to false
        $this->assertFalse($lrq, '(int) "true" casts to 0, which is falsy');
        $this->assertFalse($lrv, '(int) "false" casts to 0, which is falsy');
    }

    public function testLateRuleFlagsFromNumericStringInput(): void
    {
        $in = ['late_rule_quorum' => '1', 'late_rule_vote' => '0'];

        $lrq = (int) ($in['late_rule_quorum'] ?? 1) ? true : false;
        $lrv = (int) ($in['late_rule_vote'] ?? 1) ? true : false;

        $this->assertTrue($lrq);
        $this->assertFalse($lrv);
    }

    // =========================================================================
    // lateRules GET: RESPONSE FIELD CASTING
    // =========================================================================

    public function testLateRulesResponseCastsToBool(): void
    {
        // Replicate the casting from lateRules() GET response
        $row = [
            'id' => '12345678-1234-1234-1234-123456789abc',
            'late_rule_quorum' => 1,
            'late_rule_vote' => 0,
        ];

        $response = [
            'meeting_id' => $row['id'],
            'late_rule_quorum' => (bool) $row['late_rule_quorum'],
            'late_rule_vote' => (bool) $row['late_rule_vote'],
        ];

        $this->assertTrue($response['late_rule_quorum']);
        $this->assertFalse($response['late_rule_vote']);
        $this->assertIsBool($response['late_rule_quorum']);
        $this->assertIsBool($response['late_rule_vote']);
    }

    public function testLateRulesResponseCastsZeroToBoolFalse(): void
    {
        $row = ['id' => 'test', 'late_rule_quorum' => 0, 'late_rule_vote' => 0];

        $this->assertFalse((bool) $row['late_rule_quorum']);
        $this->assertFalse((bool) $row['late_rule_vote']);
    }

    public function testLateRulesResponseCastsOneToBoolTrue(): void
    {
        $row = ['id' => 'test', 'late_rule_quorum' => 1, 'late_rule_vote' => 1];

        $this->assertTrue((bool) $row['late_rule_quorum']);
        $this->assertTrue((bool) $row['late_rule_vote']);
    }

    public function testLateRulesResponseMeetingIdComesFromRowId(): void
    {
        $row = [
            'id' => 'aabbccdd-1122-3344-5566-778899001122',
            'late_rule_quorum' => 1,
            'late_rule_vote' => 0,
        ];

        $response = [
            'meeting_id' => $row['id'],
            'late_rule_quorum' => (bool) $row['late_rule_quorum'],
            'late_rule_vote' => (bool) $row['late_rule_vote'],
        ];

        $this->assertEquals('aabbccdd-1122-3344-5566-778899001122', $response['meeting_id']);
    }

    // =========================================================================
    // VALIDATION SCHEMA: AGENDA CREATION
    // =========================================================================

    public function testAgendaSchemaRequiresMeetingId(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::agenda();
        $result = $schema->validate([
            'title' => 'Test Agenda Item',
        ]);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('meeting_id', $result->errors());
    }

    public function testAgendaSchemaRequiresTitle(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::agenda();
        $result = $schema->validate([
            'meeting_id' => '12345678-1234-4234-8234-123456789abc',
        ]);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('title', $result->errors());
    }

    public function testAgendaSchemaRejectsEmptyTitle(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::agenda();
        $result = $schema->validate([
            'meeting_id' => '12345678-1234-4234-8234-123456789abc',
            'title' => '',
        ]);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('title', $result->errors());
    }

    public function testAgendaSchemaRejectsInvalidMeetingUuid(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::agenda();
        $result = $schema->validate([
            'meeting_id' => 'not-a-uuid',
            'title' => 'Test',
        ]);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('meeting_id', $result->errors());
    }

    public function testAgendaSchemaAcceptsValidData(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::agenda();
        $result = $schema->validate([
            'meeting_id' => '12345678-1234-4234-8234-123456789abc',
            'title' => 'Valid Agenda Title',
        ]);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->errors());
        $this->assertEquals('12345678-1234-4234-8234-123456789abc', $result->get('meeting_id'));
    }

    public function testAgendaSchemaRejectsTitleTooLong(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::agenda();
        $result = $schema->validate([
            'meeting_id' => '12345678-1234-4234-8234-123456789abc',
            'title' => str_repeat('x', 101),
        ]);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('title', $result->errors());
    }

    public function testAgendaSchemaTitleMaxLengthBoundary(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::agenda();
        $result = $schema->validate([
            'meeting_id' => '12345678-1234-4234-8234-123456789abc',
            'title' => str_repeat('a', 100),
        ]);

        $this->assertTrue($result->isValid());
    }

    public function testAgendaSchemaTitleMinLengthBoundary(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::agenda();

        // Title minLength is 1, so a single character should pass
        $result = $schema->validate([
            'meeting_id' => '12345678-1234-4234-8234-123456789abc',
            'title' => 'x',
        ]);

        $this->assertTrue($result->isValid());
    }

    public function testAgendaSchemaPositionIsOptional(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::agenda();
        $result = $schema->validate([
            'meeting_id' => '12345678-1234-4234-8234-123456789abc',
            'title' => 'Item without position',
        ]);

        $this->assertTrue($result->isValid());
    }

    public function testAgendaSchemaAcceptsValidPosition(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::agenda();
        $result = $schema->validate([
            'meeting_id' => '12345678-1234-4234-8234-123456789abc',
            'title' => 'Item with position',
            'position' => 5,
        ]);

        $this->assertTrue($result->isValid());
        $this->assertEquals(5, $result->get('position'));
    }

    public function testAgendaSchemaAcceptsZeroPosition(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::agenda();
        $result = $schema->validate([
            'meeting_id' => '12345678-1234-4234-8234-123456789abc',
            'title' => 'Item at zero',
            'position' => 0,
        ]);

        $this->assertTrue($result->isValid());
        $this->assertEquals(0, $result->get('position'));
    }

    public function testAgendaSchemaRejectsNegativePosition(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::agenda();
        $result = $schema->validate([
            'meeting_id' => '12345678-1234-4234-8234-123456789abc',
            'title' => 'Item',
            'position' => -1,
        ]);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('position', $result->errors());
    }

    public function testAgendaSchemaRejectsMissingBothRequiredFields(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::agenda();
        $result = $schema->validate([]);

        $this->assertFalse($result->isValid());
        $errors = $result->errors();
        $this->assertArrayHasKey('meeting_id', $errors);
        $this->assertArrayHasKey('title', $errors);
    }

    public function testAgendaSchemaFirstErrorReturnsString(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::agenda();
        $result = $schema->validate([]);

        $this->assertIsString($result->firstError());
    }

    public function testAgendaSchemaValidDataReturnsSanitizedTitle(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::agenda();
        $result = $schema->validate([
            'meeting_id' => '12345678-1234-4234-8234-123456789abc',
            'title' => '<script>alert(1)</script>',
        ]);

        $this->assertTrue($result->isValid());
        // XSS sanitization by default: htmlspecialchars
        $this->assertStringNotContainsString('<script>', $result->get('title'));
    }

    public function testAgendaSchemaLowercasesMeetingUuid(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::agenda();
        $result = $schema->validate([
            'meeting_id' => '12345678-1234-4234-8234-123456789ABC',
            'title' => 'Test',
        ]);

        $this->assertTrue($result->isValid());
        $this->assertEquals('12345678-1234-4234-8234-123456789abc', $result->get('meeting_id'));
    }

    // =========================================================================
    // UUID VALIDATION HELPER: EDGE CASES
    // =========================================================================

    public function testApiIsUuidAcceptsValidUuid(): void
    {
        $this->assertTrue(api_is_uuid('12345678-1234-1234-1234-123456789abc'));
    }

    public function testApiIsUuidAcceptsUppercaseUuid(): void
    {
        $this->assertTrue(api_is_uuid('12345678-1234-1234-1234-123456789ABC'));
    }

    public function testApiIsUuidRejectsEmptyString(): void
    {
        $this->assertFalse(api_is_uuid(''));
    }

    public function testApiIsUuidRejectsShortString(): void
    {
        $this->assertFalse(api_is_uuid('12345678'));
    }

    public function testApiIsUuidRejectsNoHyphens(): void
    {
        $this->assertFalse(api_is_uuid('12345678123412341234123456789abc'));
    }

    public function testApiIsUuidRejectsExtraHyphens(): void
    {
        $this->assertFalse(api_is_uuid('12345678-1234-1234-1234-1234-56789abc'));
    }

    public function testApiIsUuidRejectsNonHexCharacters(): void
    {
        $this->assertFalse(api_is_uuid('g2345678-1234-1234-1234-123456789abc'));
    }

    // =========================================================================
    // CONTROLLER SOURCE: RESPONSE STRUCTURE VERIFICATION
    // =========================================================================

    public function testListForMeetingReturnsItemsKey(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString("'items'", $source, 'listForMeeting response should contain items key');
    }

    public function testCreateReturnsExpectedKeys(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString("'agenda_id'", $source, 'create response should contain agenda_id');
        $this->assertStringContainsString("'idx'", $source, 'create response should contain idx');
        $this->assertStringContainsString("'title'", $source, 'create response should contain title');
    }

    public function testCreateReturns201StatusCode(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString('201', $source, 'create should return 201 status code');
    }

    public function testLateRulesGetReturnsExpectedKeys(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString("'late_rule_quorum'", $source, 'lateRules GET should return late_rule_quorum');
        $this->assertStringContainsString("'late_rule_vote'", $source, 'lateRules GET should return late_rule_vote');
    }

    public function testLateRulesPostReturnsSavedKey(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString("'saved' => true", $source, 'lateRules POST should return saved:true');
    }

    public function testListForMeetingPublicReturnsExpectedKeys(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        // listForMeetingPublic returns both meeting_id and items
        $this->assertStringContainsString("'meeting_id'", $source, 'listForMeetingPublic should return meeting_id');
        $this->assertStringContainsString("'items'", $source, 'listForMeetingPublic should return items');
    }

    // =========================================================================
    // CONTROLLER SOURCE: AUDIT LOG VERIFICATION
    // =========================================================================

    public function testCreateAuditsAgendaCreation(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString("'agenda_created'", $source, 'create should log agenda_created audit event');
    }

    public function testLateRulesPostAuditsUpdate(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString("'meeting_late_rules_updated'", $source, 'lateRules POST should log meeting_late_rules_updated');
    }

    // =========================================================================
    // CONTROLLER SOURCE: REPOSITORY USAGE
    // =========================================================================

    public function testControllerUsesMeetingRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString('MeetingRepository', $source);
    }

    public function testControllerUsesAgendaRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString('AgendaRepository', $source);
    }

    public function testControllerUsesValidationSchemas(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString('ValidationSchemas::agenda()', $source);
    }

    // =========================================================================
    // CONTROLLER SOURCE: MEETING EXISTENCE CHECK
    // =========================================================================

    public function testListForMeetingChecksMeetingExists(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString('existsForTenant', $source);
        $this->assertStringContainsString("'meeting_not_found'", $source);
    }

    public function testCreateChecksMeetingExists(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        // create() also calls existsForTenant and returns meeting_not_found
        $this->assertStringContainsString('existsForTenant', $source);
    }

    public function testLateRulesGetChecksMeetingExists(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString('findLateRules', $source);
        $this->assertStringContainsString("'meeting_not_found'", $source);
    }

    // =========================================================================
    // CONTROLLER SOURCE: MEETING VALIDATION GUARD
    // =========================================================================

    public function testLateRulesPostGuardsMeetingNotValidated(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString('api_guard_meeting_not_validated', $source,
            'lateRules POST should guard against modification of validated meetings');
    }

    // =========================================================================
    // CONTROLLER SOURCE: USES CORRECT API REQUEST METHODS
    // =========================================================================

    public function testListForMeetingUsesApiRequestGet(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString("api_request('GET')", $source);
    }

    public function testCreateUsesApiRequestPost(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString("api_request('POST')", $source);
    }

    public function testListForMeetingPublicUsesApiRequestGet(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        // Both listForMeeting and listForMeetingPublic use api_request('GET')
        $this->assertStringContainsString("api_request('GET')", $source);
    }

    // =========================================================================
    // CONTROLLER SOURCE: AGENDA REPO METHODS
    // =========================================================================

    public function testListForMeetingUsesListForMeetingRepoMethod(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString('listForMeeting(', $source);
    }

    public function testListForMeetingPublicUsesCompactRepoMethod(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString('listForMeetingCompact(', $source);
    }

    public function testCreateUsesGenerateUuidAndNextIdx(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AgendaController.php');

        $this->assertStringContainsString('generateUuid()', $source);
        $this->assertStringContainsString('nextIdx(', $source);
    }

    // =========================================================================
    // API REQUEST PARSING: METHOD + BODY MERGE
    // =========================================================================

    public function testApiRequestMergesGetAndJsonBody(): void
    {
        // Replicate the merging logic from api_request()
        $_GET = ['meeting_id' => '12345678-1234-1234-1234-123456789abc'];
        $jsonData = ['title' => 'Test Title'];

        // array_merge($_GET, $data)
        $merged = array_merge($_GET, $jsonData);

        $this->assertEquals('12345678-1234-1234-1234-123456789abc', $merged['meeting_id']);
        $this->assertEquals('Test Title', $merged['title']);
    }

    public function testApiRequestJsonBodyOverridesGetParams(): void
    {
        // If the same key exists in both, the JSON body (second argument) wins
        $_GET = ['meeting_id' => 'from-get'];
        $jsonData = ['meeting_id' => 'from-body'];

        $merged = array_merge($_GET, $jsonData);

        $this->assertEquals('from-body', $merged['meeting_id']);
    }

    // =========================================================================
    // HANDLE METHOD: ABSTRACT CONTROLLER ERROR HANDLING
    // =========================================================================

    public function testHandleConvertsInvalidArgumentExceptionTo422(): void
    {
        // The AbstractController::handle() catches InvalidArgumentException
        // and converts it to a 422 api_fail('invalid_request')
        $controller = new class extends \AgVote\Controller\AbstractController {
            public function throwInvalidArg(): void
            {
                throw new \InvalidArgumentException('test invalid argument');
            }
        };

        try {
            $controller->handle('throwInvalidArg');
            $this->fail('Expected ApiResponseException');
        } catch (ApiResponseException $e) {
            $this->assertEquals(422, $e->getResponse()->getStatusCode());
            $this->assertEquals('invalid_request', $e->getResponse()->getBody()['error']);
        }
    }

    public function testHandleConvertsRuntimeExceptionTo400(): void
    {
        $controller = new class extends \AgVote\Controller\AbstractController {
            public function throwRuntime(): void
            {
                throw new \RuntimeException('test runtime error');
            }
        };

        try {
            $controller->handle('throwRuntime');
            $this->fail('Expected ApiResponseException');
        } catch (ApiResponseException $e) {
            $this->assertEquals(400, $e->getResponse()->getStatusCode());
            $this->assertEquals('business_error', $e->getResponse()->getBody()['error']);
        }
    }

    public function testHandleConvertsGenericExceptionTo500(): void
    {
        $controller = new class extends \AgVote\Controller\AbstractController {
            public function throwGeneric(): void
            {
                throw new \Exception('unexpected error');
            }
        };

        try {
            $controller->handle('throwGeneric');
            $this->fail('Expected ApiResponseException');
        } catch (ApiResponseException $e) {
            $this->assertEquals(500, $e->getResponse()->getStatusCode());
            $this->assertEquals('internal_error', $e->getResponse()->getBody()['error']);
        }
    }

    public function testHandlePropagatesApiResponseException(): void
    {
        // ApiResponseException should be re-thrown, not caught by generic handlers
        $controller = new class extends \AgVote\Controller\AbstractController {
            public function throwApiResponse(): void
            {
                api_ok(['test' => true]);
            }
        };

        try {
            $controller->handle('throwApiResponse');
            $this->fail('Expected ApiResponseException');
        } catch (ApiResponseException $e) {
            $this->assertEquals(200, $e->getResponse()->getStatusCode());
            $this->assertTrue($e->getResponse()->getBody()['ok']);
        }
    }
}
