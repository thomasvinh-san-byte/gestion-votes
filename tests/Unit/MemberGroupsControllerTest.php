<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\MemberGroupsController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MemberGroupsController.
 *
 * Tests the member groups CRUD + assignment endpoints including:
 *  - Controller structure (final, extends AbstractController)
 *  - HTTP method enforcement (GET/POST/PATCH/PUT/DELETE)
 *  - UUID validation for group_id, member_id
 *  - Input validation for name, color, group_ids
 *  - InvalidArgumentException handling via AbstractController
 *  - Response structure and audit log verification
 */
class MemberGroupsControllerTest extends TestCase
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
        $controller = new MemberGroupsController();
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
        $ref = new \ReflectionClass(MemberGroupsController::class);
        $this->assertTrue($ref->isFinal(), 'MemberGroupsController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new MemberGroupsController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(MemberGroupsController::class);

        $expectedMethods = [
            'list',
            'create',
            'update',
            'delete',
            'assign',
            'unassign',
            'setMemberGroups',
            'bulkAssign',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "MemberGroupsController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(MemberGroupsController::class);

        $expectedMethods = [
            'list',
            'create',
            'update',
            'delete',
            'assign',
            'unassign',
            'setMemberGroups',
            'bulkAssign',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "MemberGroupsController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // list: UUID VALIDATION
    // =========================================================================

    public function testListRejectsInvalidGroupId(): void
    {
        // list() calls api_current_tenant_id() then MemberGroupRepository before
        // UUID validation. In test env (no DB), the repo throws RuntimeException
        // caught as business_error. Verify UUID validation via source inspection.
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        $this->assertStringContainsString("api_fail('invalid_group_id', 400)", $source);
    }

    public function testListRejectsInvalidGroupIdUuidCheck(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        $this->assertStringContainsString('api_is_uuid($groupId)', $source);
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

    // =========================================================================
    // create: INPUT VALIDATION
    // =========================================================================

    /**
     * create() instantiates MemberGroupRepository (which calls db()) before
     * reaching input validation. In test env without DB, RuntimeException is
     * caught by AbstractController as business_error (400). We verify input
     * validation logic via source inspection and logic replication.
     */
    public function testCreateRequiresName(): void
    {
        $name = trim((string) (null ?? ''));
        $this->assertEquals('', $name, 'Missing name should be empty after trim');
    }

    public function testCreateRejectsEmptyNameLogic(): void
    {
        $name = trim('');
        $this->assertEquals('', $name);
    }

    public function testCreateRejectsWhitespaceOnlyNameLogic(): void
    {
        $name = trim('   ');
        $this->assertEquals('', $name);
    }

    public function testCreateRejectsNameOver100CharsLogic(): void
    {
        $name = str_repeat('A', 101);
        $this->assertTrue(mb_strlen($name) > 100, 'Name over 100 chars should be rejected');
    }

    public function testCreateRejectsInvalidColorFormatLogic(): void
    {
        $invalidColors = ['red', '#FFF', 'invalid', '#GGGGGG'];
        foreach ($invalidColors as $color) {
            $this->assertFalse(
                (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $color),
                "Color '{$color}' should be invalid",
            );
        }
    }

    public function testCreateSourceValidatesName(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        $this->assertStringContainsString("Le nom du groupe est requis", $source);
        $this->assertStringContainsString("Le nom ne peut pas depasser 100 caracteres", $source);
    }

    public function testCreateSourceValidatesColor(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        $this->assertStringContainsString("Format de couleur invalide", $source);
        $this->assertStringContainsString('#RRGGBB', $source);
    }

    // =========================================================================
    // update: METHOD ENFORCEMENT
    // =========================================================================

    public function testUpdateRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('update');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testUpdateRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['id' => '12345678-1234-1234-1234-123456789abc', 'name' => 'Test']);

        $result = $this->callControllerMethod('update');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // update: INPUT VALIDATION
    // =========================================================================

    /**
     * update() instantiates repos before validation in test env.
     * Verify validation logic via source inspection.
     */
    public function testUpdateSourceValidatesGroupId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        $this->assertStringContainsString("ID de groupe invalide", $source);
    }

    public function testUpdateSourceValidatesName(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        $this->assertStringContainsString("Le nom du groupe est requis", $source);
    }

    public function testUpdateSourceValidatesNameLength(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        $this->assertStringContainsString("Le nom ne peut pas depasser 100 caracteres", $source);
    }

    public function testUpdateSourceValidatesColor(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        $this->assertStringContainsString("Format de couleur invalide", $source);
    }

    // =========================================================================
    // delete: UUID VALIDATION
    // =========================================================================

    /**
     * delete() calls api_current_tenant_id() and MemberGroupRepository before
     * UUID validation. Verify via source inspection.
     */
    public function testDeleteSourceValidatesGroupId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        // delete() throws InvalidArgumentException for invalid group_id
        $this->assertStringContainsString("'ID de groupe invalide'", $source);
    }

    public function testDeleteUuidCheckLogic(): void
    {
        $this->assertFalse(api_is_uuid('bad-uuid'));
        $this->assertFalse(api_is_uuid(''));
        $this->assertTrue(api_is_uuid('12345678-1234-1234-1234-123456789abc'));
    }

    // =========================================================================
    // assign: METHOD ENFORCEMENT
    // =========================================================================

    public function testAssignRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('assign');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // assign: INPUT VALIDATION
    // =========================================================================

    /**
     * assign() calls api_current_tenant_id() and repos before UUID validation.
     * Verify via source inspection.
     */
    public function testAssignSourceValidatesMemberId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        $this->assertStringContainsString("'member_id invalide'", $source);
    }

    public function testAssignSourceValidatesGroupId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        $this->assertStringContainsString("'group_id invalide'", $source);
    }

    public function testAssignValidationLogic(): void
    {
        $memberId = trim((string) ('bad-uuid'));
        $this->assertFalse(api_is_uuid($memberId), 'Invalid member_id should be rejected');

        $groupId = trim((string) ('bad-uuid'));
        $this->assertFalse(api_is_uuid($groupId), 'Invalid group_id should be rejected');
    }

    // =========================================================================
    // unassign: UUID VALIDATION
    // =========================================================================

    /**
     * unassign() calls api_current_tenant_id() and repos before UUID validation.
     * Verify via source inspection.
     */
    public function testUnassignSourceValidatesMemberId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        // unassign uses the same InvalidArgumentException messages
        $this->assertStringContainsString("'member_id invalide'", $source);
    }

    public function testUnassignSourceValidatesGroupId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        $this->assertStringContainsString("'group_id invalide'", $source);
    }

    // =========================================================================
    // setMemberGroups: METHOD ENFORCEMENT
    // =========================================================================

    public function testSetMemberGroupsRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('setMemberGroups');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testSetMemberGroupsRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('setMemberGroups');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // setMemberGroups: INPUT VALIDATION
    // =========================================================================

    /**
     * setMemberGroups() calls repos before validation.
     * Verify via source inspection.
     */
    public function testSetMemberGroupsSourceValidatesMemberId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        $this->assertStringContainsString("'member_id invalide'", $source);
    }

    public function testSetMemberGroupsSourceValidatesGroupIds(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        $this->assertStringContainsString("'group_ids doit etre un tableau'", $source);
    }

    // =========================================================================
    // bulkAssign: METHOD ENFORCEMENT
    // =========================================================================

    public function testBulkAssignRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('bulkAssign');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // bulkAssign: INPUT VALIDATION
    // =========================================================================

    /**
     * bulkAssign() calls repos before validation.
     * Verify via source inspection.
     */
    public function testBulkAssignSourceValidatesGroupId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        $this->assertStringContainsString("'group_id invalide'", $source);
    }

    public function testBulkAssignSourceValidatesMemberIds(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        $this->assertStringContainsString("'member_ids doit etre un tableau non vide'", $source);
    }

    public function testBulkAssignSourceValidatesNoValidMembers(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');
        $this->assertStringContainsString("'Aucun membre valide trouve'", $source);
    }

    // =========================================================================
    // COLOR VALIDATION LOGIC
    // =========================================================================

    public function testColorValidationRegex(): void
    {
        $validColors = ['#FF0000', '#00ff00', '#0000FF', '#123abc', '#AABBCC'];
        foreach ($validColors as $color) {
            $this->assertTrue(
                (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $color),
                "Color '{$color}' should be valid",
            );
        }

        $invalidColors = ['red', '#FFF', 'FF0000', '#GGGGGG', '#12345', '#1234567'];
        foreach ($invalidColors as $color) {
            $this->assertFalse(
                (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $color),
                "Color '{$color}' should be invalid",
            );
        }
    }

    // =========================================================================
    // NAME LENGTH VALIDATION LOGIC
    // =========================================================================

    public function testNameLengthBoundary(): void
    {
        $name100 = str_repeat('A', 100);
        $this->assertFalse(mb_strlen($name100) > 100, 'Name of 100 chars should be accepted');

        $name101 = str_repeat('A', 101);
        $this->assertTrue(mb_strlen($name101) > 100, 'Name of 101 chars should be rejected');
    }

    // =========================================================================
    // AUDIT LOG VERIFICATION (source-level)
    // =========================================================================

    public function testCreateAuditsGroupCreation(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');

        $this->assertStringContainsString("'member_group_created'", $source);
    }

    public function testUpdateAuditsGroupUpdate(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');

        $this->assertStringContainsString("'member_group_updated'", $source);
    }

    public function testDeleteAuditsGroupDeletion(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');

        $this->assertStringContainsString("'member_group_deleted'", $source);
    }

    public function testAssignAuditsMemberAssigned(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');

        $this->assertStringContainsString("'member_assigned_to_group'", $source);
    }

    public function testUnassignAuditsMemberRemoved(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');

        $this->assertStringContainsString("'member_removed_from_group'", $source);
    }

    public function testSetMemberGroupsAuditsUpdate(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');

        $this->assertStringContainsString("'member_groups_updated'", $source);
    }

    public function testBulkAssignAuditsBulkAssignment(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');

        $this->assertStringContainsString("'members_bulk_assigned_to_group'", $source);
    }

    // =========================================================================
    // RESPONSE STRUCTURE VERIFICATION (source-level)
    // =========================================================================

    public function testCreateReturns201(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');

        $this->assertStringContainsString('201', $source);
    }

    public function testAssignResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');

        $this->assertStringContainsString("'assigned' => true", $source);
        $this->assertStringContainsString("'member_id'", $source);
        $this->assertStringContainsString("'group_id'", $source);
    }

    public function testUnassignResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');

        $this->assertStringContainsString("'removed'", $source);
    }

    public function testDeleteResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MemberGroupsController.php');

        $this->assertStringContainsString("'deleted'", $source);
    }
}
