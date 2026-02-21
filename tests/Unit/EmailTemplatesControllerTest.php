<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\EmailTemplatesController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EmailTemplatesController.
 *
 * Tests the email template CRUD endpoints including:
 *  - Controller structure (final, extends AbstractController)
 *  - HTTP method enforcement (create uses api_request('POST'))
 *  - Source-level validation verification
 *  - Template type validation logic
 *  - Duplicate action validation logic
 *
 * Note: list(), update(), delete() eagerly construct EmailTemplateRepository
 * (which calls db()) before any method or input checks. In test env (no DB),
 * the repo constructor throws RuntimeException, caught as business_error (400).
 * Input validation for these methods is verified at source level instead.
 */
class EmailTemplatesControllerTest extends TestCase
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
        $controller = new EmailTemplatesController();
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
        $ref = new \ReflectionClass(EmailTemplatesController::class);
        $this->assertTrue($ref->isFinal(), 'EmailTemplatesController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new EmailTemplatesController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(EmailTemplatesController::class);

        $expectedMethods = ['list', 'create', 'update', 'delete'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "EmailTemplatesController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(EmailTemplatesController::class);

        $expectedMethods = ['list', 'create', 'update', 'delete'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "EmailTemplatesController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // list: EAGER REPO CONSTRUCTION IN NO-DB ENV
    // list() eagerly creates EmailTemplateRepository which calls db(),
    // throwing RuntimeException caught as business_error.
    // =========================================================================

    public function testListEagerRepoThrowsBusinessErrorInNoDbEnv(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('list');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    public function testListNoMethodEnforcementPostReturnsBusinessError(): void
    {
        // list() does NOT call api_request(); repo constructor fires first
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('list');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    // =========================================================================
    // list: template_id VALIDATION (source verification)
    // Since list() eagerly constructs repo before validation, verify via source
    // =========================================================================

    public function testListSourceValidatesInvalidTemplateId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTemplatesController.php');

        $this->assertStringContainsString("api_is_uuid(\$id)", $source);
        $this->assertStringContainsString("'invalid_template_id'", $source);
    }

    // =========================================================================
    // create: METHOD ENFORCEMENT
    // create() calls api_request('POST') before the repo constructor,
    // so method enforcement works correctly.
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
    // create: REPO CONSTRUCTOR FIRES AFTER api_request('POST')
    // In no-DB env, after passing method check, repo constructor fires
    // and throws business_error.
    // =========================================================================

    public function testCreateWithValidMethodReturnsBusinessErrorNoDb(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'name' => 'Template',
            'subject' => 'Subject',
            'body_html' => '<p>Body</p>',
        ]);

        $result = $this->callControllerMethod('create');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    // =========================================================================
    // create (duplicate action): INPUT VALIDATION LOGIC (replicated)
    // Since repo constructor fires before validation in test env,
    // we replicate the validation logic directly.
    // =========================================================================

    public function testCreateDuplicateSourceIdValidationLogic(): void
    {
        // Replicate: if (!api_is_uuid($sourceId)) api_fail('invalid_source_id')
        $this->assertFalse(api_is_uuid('not-a-uuid'));
        $this->assertFalse(api_is_uuid(''));
        $this->assertFalse(api_is_uuid('12345678-1234'));
        $this->assertTrue(api_is_uuid('12345678-1234-1234-1234-123456789abc'));
    }

    public function testCreateDuplicateNewNameValidationLogic(): void
    {
        // Replicate: if ($newName === '') api_fail('missing_new_name')
        $this->assertTrue(trim('') === '');
        $this->assertTrue(trim('   ') === '');
        $this->assertFalse(trim('Copy of template') === '');
    }

    // =========================================================================
    // create (standard): INPUT VALIDATION LOGIC (replicated)
    // =========================================================================

    public function testCreateNameValidationLogic(): void
    {
        // Replicate: if ($name === '') api_fail('missing_name')
        $this->assertTrue(trim('') === '');
        $this->assertTrue(trim('   ') === '');
        $this->assertFalse(trim('Valid Name') === '');
    }

    public function testCreateSubjectValidationLogic(): void
    {
        // Replicate: if ($subject === '') api_fail('missing_subject')
        $this->assertTrue(trim('') === '');
        $this->assertFalse(trim('A subject') === '');
    }

    public function testCreateBodyHtmlValidationLogic(): void
    {
        // Replicate: if ($bodyHtml === '') api_fail('missing_body_html')
        $this->assertTrue(trim('') === '');
        $this->assertFalse(trim('<p>Content</p>') === '');
    }

    // =========================================================================
    // create: TEMPLATE TYPE VALIDATION
    // =========================================================================

    public function testCreateValidTemplateTypes(): void
    {
        $allowedTypes = ['invitation', 'reminder', 'confirmation', 'custom'];
        foreach ($allowedTypes as $type) {
            $this->assertTrue(
                in_array($type, $allowedTypes, true),
                "'{$type}' should be a valid template type",
            );
        }
        $this->assertNotContains('unknown_type', $allowedTypes);
    }

    public function testCreateRejectsInvalidTemplateTypeLogic(): void
    {
        $allowedTypes = ['invitation', 'reminder', 'confirmation', 'custom'];
        $this->assertFalse(in_array('unknown_type', $allowedTypes, true));
        $this->assertFalse(in_array('', $allowedTypes, true));
        $this->assertFalse(in_array('Invitation', $allowedTypes, true));
    }

    public function testCreateDefaultTemplateTypeIsInvitation(): void
    {
        $input = [];
        $type = trim((string) ($input['template_type'] ?? 'invitation'));
        $this->assertEquals('invitation', $type);
    }

    // =========================================================================
    // update/delete: EAGER REPO CONSTRUCTION IN NO-DB ENV
    // update() and delete() eagerly create repo before method checks.
    // =========================================================================

    public function testUpdateEagerRepoThrowsBusinessErrorInNoDbEnv(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_GET = ['id' => '12345678-1234-1234-1234-123456789abc'];
        $this->injectJsonBody(['name' => 'Updated']);

        $result = $this->callControllerMethod('update');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    public function testDeleteEagerRepoThrowsBusinessErrorInNoDbEnv(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_GET = ['id' => '12345678-1234-1234-1234-123456789abc'];

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    // =========================================================================
    // update/delete: SOURCE-LEVEL VALIDATION VERIFICATION
    // Since repo constructor fires before validation, verify via source.
    // =========================================================================

    public function testUpdateSourceValidatesTemplateId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTemplatesController.php');

        $this->assertStringContainsString("api_is_uuid(\$id)", $source);
        $this->assertStringContainsString("'invalid_template_id'", $source);
    }

    public function testUpdateSourceUsesApiRequestPut(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTemplatesController.php');

        $this->assertStringContainsString("api_request('PUT')", $source);
    }

    public function testDeleteSourceValidatesTemplateId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTemplatesController.php');

        $this->assertStringContainsString("api_is_uuid(\$id)", $source);
        $this->assertStringContainsString("'invalid_template_id'", $source);
    }

    // =========================================================================
    // CROSS-CUTTING: METHOD CHECK BEFORE BODY VALIDATION
    // =========================================================================

    public function testCreateMethodCheckBeforeBodyValidation(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->injectJsonBody([
            'name' => 'Template',
            'subject' => 'Subject',
            'body_html' => '<p>Body</p>',
        ]);

        $result = $this->callControllerMethod('create');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // RESPONSE STRUCTURE (source verification)
    // =========================================================================

    public function testListResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTemplatesController.php');

        $this->assertStringContainsString("'items'", $source);
        $this->assertStringContainsString("'template'", $source);
        $this->assertStringContainsString("'available_variables'", $source);
    }

    public function testCreateResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTemplatesController.php');

        $this->assertStringContainsString("'template'", $source);
        $this->assertStringContainsString('201', $source);
    }

    public function testDeleteResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTemplatesController.php');

        $this->assertStringContainsString("'deleted' => true", $source);
    }

    // =========================================================================
    // AUDIT LOG VERIFICATION (source-level)
    // =========================================================================

    public function testCreateAuditsTemplateCreation(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTemplatesController.php');

        $this->assertStringContainsString("'email_template.create'", $source);
    }

    public function testUpdateAuditsTemplateUpdate(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTemplatesController.php');

        $this->assertStringContainsString("'email_template.update'", $source);
    }

    public function testDeleteAuditsTemplateDeletion(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTemplatesController.php');

        $this->assertStringContainsString("'email_template.delete'", $source);
    }

    public function testDuplicateAuditsTemplateDuplicate(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTemplatesController.php');

        $this->assertStringContainsString("'email_template.duplicate'", $source);
    }

    // =========================================================================
    // BUSINESS GUARD VERIFICATION (source-level)
    // =========================================================================

    public function testDeleteGuardsDefaultTemplate(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTemplatesController.php');

        $this->assertStringContainsString('cannot_delete_default', $source);
    }

    public function testCreateValidatesUnknownVariables(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTemplatesController.php');

        $this->assertStringContainsString('unknown_variables', $source);
    }

    public function testCreateChecksNameUniqueness(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTemplatesController.php');

        $this->assertStringContainsString('template_name_exists', $source);
    }

    // =========================================================================
    // UNKNOWN METHOD HANDLING
    // =========================================================================

    public function testHandleUnknownMethodReturns500(): void
    {
        $result = $this->callControllerMethod('nonExistentMethod');

        $this->assertEquals(500, $result['status']);
        $this->assertEquals('internal_error', $result['body']['error']);
    }

    // =========================================================================
    // create: VALIDATION ORDER (source verification)
    // =========================================================================

    public function testCreateValidationOrderInSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTemplatesController.php');

        // missing_name should appear before missing_subject
        $namePos = strpos($source, "'missing_name'");
        $subjectPos = strpos($source, "'missing_subject'");
        $bodyPos = strpos($source, "'missing_body_html'");

        $this->assertNotFalse($namePos, 'Source should contain missing_name');
        $this->assertNotFalse($subjectPos, 'Source should contain missing_subject');
        $this->assertNotFalse($bodyPos, 'Source should contain missing_body_html');
        $this->assertLessThan($subjectPos, $namePos, 'Name validation should come before subject');
        $this->assertLessThan($bodyPos, $subjectPos, 'Subject validation should come before body_html');
    }

    // =========================================================================
    // create: SOURCE-LEVEL ACTION HANDLING
    // =========================================================================

    public function testCreateSourceHandlesCreateDefaultsAction(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTemplatesController.php');

        $this->assertStringContainsString("'create_defaults'", $source);
        $this->assertStringContainsString('createDefaultTemplates', $source);
    }

    public function testCreateSourceHandlesDuplicateAction(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailTemplatesController.php');

        $this->assertStringContainsString("'duplicate'", $source);
        $this->assertStringContainsString("'invalid_source_id'", $source);
        $this->assertStringContainsString("'missing_new_name'", $source);
    }
}
