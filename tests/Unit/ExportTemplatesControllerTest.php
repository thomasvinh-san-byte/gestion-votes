<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\ExportTemplatesController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ExportTemplatesController.
 *
 * Tests the export template CRUD endpoints including:
 *  - Controller structure (final, extends AbstractController)
 *  - HTTP method enforcement (create uses api_request('POST'))
 *  - Source-level validation verification
 *  - Name length validation logic
 *  - Export type validation logic
 *
 * Note: list(), update(), delete() eagerly construct ExportTemplateRepository
 * (which calls db()) before any method or input checks. In test env (no DB),
 * the repo constructor throws RuntimeException, caught as business_error (400).
 * Input validation for these methods is verified at source level instead.
 */
class ExportTemplatesControllerTest extends TestCase
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
        $controller = new ExportTemplatesController();
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
        $ref = new \ReflectionClass(ExportTemplatesController::class);
        $this->assertTrue($ref->isFinal(), 'ExportTemplatesController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new ExportTemplatesController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(ExportTemplatesController::class);

        $expectedMethods = ['list', 'create', 'update', 'delete'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "ExportTemplatesController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(ExportTemplatesController::class);

        $expectedMethods = ['list', 'create', 'update', 'delete'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "ExportTemplatesController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // list: EAGER REPO CONSTRUCTION IN NO-DB ENV
    // list() eagerly creates ExportTemplateRepository which calls db(),
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
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('list');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    // =========================================================================
    // list: VALIDATION (source verification)
    // =========================================================================

    public function testListSourceValidatesInvalidTemplateId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportTemplatesController.php');

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
            'name' => 'Template Name',
            'export_type' => 'members',
        ]);

        $result = $this->callControllerMethod('create');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    // =========================================================================
    // create (duplicate): INPUT VALIDATION LOGIC (replicated)
    // =========================================================================

    public function testCreateDuplicateSourceIdValidationLogic(): void
    {
        $this->assertFalse(api_is_uuid('not-a-uuid'));
        $this->assertFalse(api_is_uuid(''));
        $this->assertFalse(api_is_uuid('12345678-1234'));
        $this->assertTrue(api_is_uuid('12345678-1234-1234-1234-123456789abc'));
    }

    public function testCreateDuplicateNewNameValidationLogic(): void
    {
        $check = function (string $newName): bool {
            return $newName === '' || mb_strlen($newName) < 2 || mb_strlen($newName) > 100;
        };

        $this->assertTrue($check(''));
        $this->assertTrue($check('X'));
        $this->assertTrue($check(str_repeat('A', 101)));
        $this->assertFalse($check('AB'));
        $this->assertFalse($check(str_repeat('A', 100)));
        $this->assertFalse($check('Copy of Template'));
    }

    // =========================================================================
    // create (standard): NAME VALIDATION LOGIC (replicated)
    // =========================================================================

    public function testCreateNameLengthBoundary(): void
    {
        $check = function (string $name): bool {
            return $name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100;
        };

        $this->assertTrue($check(''));
        $this->assertTrue($check('X'));
        $this->assertTrue($check(str_repeat('A', 101)));
        $this->assertFalse($check('AB'));
        $this->assertFalse($check(str_repeat('A', 100)));
        $this->assertFalse($check('Valid Name'));
    }

    public function testCreateNameExactly2CharsPassesValidation(): void
    {
        $name = 'AB';
        $invalid = $name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100;
        $this->assertFalse($invalid);
    }

    public function testCreateNameExactly100CharsPassesValidation(): void
    {
        $name = str_repeat('C', 100);
        $invalid = $name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100;
        $this->assertFalse($invalid);
    }

    // =========================================================================
    // create: EXPORT TYPE VALIDATION LOGIC (replicated)
    // =========================================================================

    public function testCreateExportTypeValidationLogic(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportTemplatesController.php');

        $this->assertStringContainsString("'invalid_export_type'", $source);
        $this->assertStringContainsString('ExportTemplateRepository::TYPES', $source);
    }

    // =========================================================================
    // update/delete: EAGER REPO CONSTRUCTION IN NO-DB ENV
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

    public function testUpdateNoMethodEnforcementGetReturnsBusinessError(): void
    {
        // update() eagerly constructs repo before api_request('PUT')
        $_SERVER['REQUEST_METHOD'] = 'GET';

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

    public function testDeleteNoMethodEnforcementGetReturnsBusinessError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    // =========================================================================
    // update/delete: SOURCE-LEVEL VALIDATION VERIFICATION
    // =========================================================================

    public function testUpdateSourceValidatesTemplateId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportTemplatesController.php');

        $this->assertStringContainsString("api_is_uuid(\$id)", $source);
        $this->assertStringContainsString("'invalid_template_id'", $source);
    }

    public function testUpdateSourceUsesApiRequestPut(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportTemplatesController.php');

        $this->assertStringContainsString("api_request('PUT')", $source);
    }

    public function testDeleteSourceValidatesTemplateId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportTemplatesController.php');

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
            'export_type' => 'members',
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
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportTemplatesController.php');

        $this->assertStringContainsString("'items'", $source);
        $this->assertStringContainsString("'template'", $source);
        $this->assertStringContainsString("'available_columns'", $source);
    }

    public function testCreateResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportTemplatesController.php');

        $this->assertStringContainsString("'template'", $source);
        $this->assertStringContainsString('201', $source);
    }

    public function testDeleteResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportTemplatesController.php');

        $this->assertStringContainsString("'deleted' => true", $source);
    }

    public function testListAvailableColumnsResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportTemplatesController.php');

        $this->assertStringContainsString("'types'", $source);
        $this->assertStringContainsString("'columns_by_type'", $source);
    }

    // =========================================================================
    // AUDIT LOG VERIFICATION (source-level)
    // =========================================================================

    public function testCreateAuditsTemplateCreation(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportTemplatesController.php');

        $this->assertStringContainsString("'export_template_create'", $source);
    }

    public function testUpdateAuditsTemplateUpdate(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportTemplatesController.php');

        $this->assertStringContainsString("'export_template_update'", $source);
    }

    public function testDeleteAuditsTemplateDeletion(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportTemplatesController.php');

        $this->assertStringContainsString("'export_template_delete'", $source);
    }

    public function testDuplicateAuditsTemplateDuplicate(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportTemplatesController.php');

        $this->assertStringContainsString("'export_template_duplicate'", $source);
    }

    // =========================================================================
    // BUSINESS GUARD VERIFICATION (source-level)
    // =========================================================================

    public function testCreateChecksNameUniqueness(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportTemplatesController.php');

        $this->assertStringContainsString('name_already_exists', $source);
        $this->assertStringContainsString('nameExists', $source);
    }

    public function testUpdateChecksNameUniqueness(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportTemplatesController.php');

        $this->assertStringContainsString('name_already_exists', $source);
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
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportTemplatesController.php');

        $namePos = strpos($source, "'invalid_name'");
        $typePos = strpos($source, "'invalid_export_type'");

        $this->assertNotFalse($namePos, 'Source should contain invalid_name');
        $this->assertNotFalse($typePos, 'Source should contain invalid_export_type');
        $this->assertLessThan($typePos, $namePos, 'Name validation should come before export_type');
    }

    // =========================================================================
    // create: DUPLICATE ACTION SOURCE VERIFICATION
    // =========================================================================

    public function testCreateSourceHandlesDuplicateAction(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportTemplatesController.php');

        $this->assertStringContainsString("'duplicate'", $source);
        $this->assertStringContainsString("'invalid_source_id'", $source);
        $this->assertStringContainsString("'invalid_name'", $source);
    }
}
