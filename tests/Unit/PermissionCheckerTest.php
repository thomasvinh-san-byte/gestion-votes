<?php

declare(strict_types=1);

use AgVote\Core\Security\PermissionChecker;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour PermissionChecker
 */
class PermissionCheckerTest extends TestCase {
    private PermissionChecker $checker;

    protected function setUp(): void {
        $this->checker = new PermissionChecker();
    }

    // =========================================================================
    // ADMIN TESTS
    // =========================================================================

    public function testAdminHasAllPermissions(): void {
        $admin = ['id' => '1', 'role' => 'admin', 'tenant_id' => 'test'];

        $this->assertTrue($this->checker->check($admin, 'meeting:create'));
        $this->assertTrue($this->checker->check($admin, 'meeting:delete'));
        $this->assertTrue($this->checker->check($admin, 'admin:users'));
        $this->assertTrue($this->checker->check($admin, 'admin:system'));
        $this->assertTrue($this->checker->check($admin, 'vote:cast'));
    }

    public function testAdminCanDoAllTransitions(): void {
        $admin = ['id' => '1', 'role' => 'admin', 'tenant_id' => 'test'];

        $this->assertTrue($this->checker->canTransition($admin, 'draft', 'scheduled'));
        $this->assertTrue($this->checker->canTransition($admin, 'draft', 'frozen'));
        $this->assertTrue($this->checker->canTransition($admin, 'frozen', 'live'));
        $this->assertTrue($this->checker->canTransition($admin, 'live', 'closed'));
        $this->assertTrue($this->checker->canTransition($admin, 'validated', 'archived'));
    }

    // =========================================================================
    // OPERATOR TESTS
    // =========================================================================

    public function testOperatorCanCreateMeeting(): void {
        $operator = ['id' => '2', 'role' => 'operator', 'tenant_id' => 'test'];

        $this->assertTrue($this->checker->check($operator, 'meeting:create'));
        $this->assertTrue($this->checker->check($operator, 'meeting:update'));
        $this->assertTrue($this->checker->check($operator, 'motion:create'));
        $this->assertTrue($this->checker->check($operator, 'member:create'));
    }

    public function testOperatorCannotDeleteMeeting(): void {
        $operator = ['id' => '2', 'role' => 'operator', 'tenant_id' => 'test'];

        $this->assertFalse($this->checker->check($operator, 'meeting:delete'));
        $this->assertFalse($this->checker->check($operator, 'admin:users'));
        $this->assertFalse($this->checker->check($operator, 'admin:system'));
    }

    public function testOperatorCanTransitionDraftToScheduled(): void {
        $operator = ['id' => '2', 'role' => 'operator', 'tenant_id' => 'test'];

        $this->assertTrue($this->checker->canTransition($operator, 'draft', 'scheduled'));
        $this->assertFalse($this->checker->canTransition($operator, 'draft', 'frozen')); // requires president
        $this->assertFalse($this->checker->canTransition($operator, 'frozen', 'live'));  // requires president
    }

    // =========================================================================
    // AUDITOR TESTS
    // =========================================================================

    public function testAuditorCanReadButNotModify(): void {
        $auditor = ['id' => '3', 'role' => 'auditor', 'tenant_id' => 'test'];

        // Can read
        $this->assertTrue($this->checker->check($auditor, 'meeting:read'));
        $this->assertTrue($this->checker->check($auditor, 'motion:read'));
        $this->assertTrue($this->checker->check($auditor, 'member:read'));
        $this->assertTrue($this->checker->check($auditor, 'audit:read'));
        $this->assertTrue($this->checker->check($auditor, 'audit:export'));

        // Cannot modify
        $this->assertFalse($this->checker->check($auditor, 'meeting:create'));
        $this->assertFalse($this->checker->check($auditor, 'meeting:update'));
        $this->assertFalse($this->checker->check($auditor, 'motion:create'));
    }

    // =========================================================================
    // VIEWER TESTS
    // =========================================================================

    public function testViewerCanOnlyRead(): void {
        $viewer = ['id' => '4', 'role' => 'viewer', 'tenant_id' => 'test'];

        // Can read basic info
        $this->assertTrue($this->checker->check($viewer, 'meeting:read'));
        $this->assertTrue($this->checker->check($viewer, 'motion:read'));
        $this->assertTrue($this->checker->check($viewer, 'report:read'));

        // Cannot read audit
        $this->assertFalse($this->checker->check($viewer, 'audit:read'));

        // Cannot modify anything
        $this->assertFalse($this->checker->check($viewer, 'meeting:create'));
        $this->assertFalse($this->checker->check($viewer, 'vote:cast'));
    }

    // =========================================================================
    // ROLE ALIASES TESTS
    // =========================================================================

    public function testRoleAliases(): void {
        $trust = ['id' => '5', 'role' => 'trust', 'tenant_id' => 'test'];

        // 'trust' is aliased to 'assessor'
        $this->assertTrue($this->checker->check($trust, 'audit:read'));
        $this->assertTrue($this->checker->check($trust, 'member:read'));

        $readonly = ['id' => '6', 'role' => 'readonly', 'tenant_id' => 'test'];

        // 'readonly' is aliased to 'viewer'
        $this->assertTrue($this->checker->check($readonly, 'meeting:read'));
        $this->assertFalse($this->checker->check($readonly, 'meeting:create'));
    }

    // =========================================================================
    // TRANSITION TESTS
    // =========================================================================

    public function testValidTransitions(): void {
        $president = ['id' => '7', 'role' => 'president', 'tenant_id' => 'test'];

        // President can do meeting state transitions
        $this->assertTrue($this->checker->canTransition($president, 'draft', 'frozen'));
        $this->assertTrue($this->checker->canTransition($president, 'scheduled', 'frozen'));
        $this->assertTrue($this->checker->canTransition($president, 'frozen', 'live'));
        $this->assertTrue($this->checker->canTransition($president, 'live', 'closed'));
        $this->assertTrue($this->checker->canTransition($president, 'closed', 'validated'));
    }

    public function testInvalidTransitions(): void {
        $admin = ['id' => '1', 'role' => 'admin', 'tenant_id' => 'test'];

        // Cannot skip states
        $this->assertFalse($this->checker->canTransition($admin, 'draft', 'live'));
        $this->assertFalse($this->checker->canTransition($admin, 'draft', 'closed'));
        $this->assertFalse($this->checker->canTransition($admin, 'scheduled', 'live'));

        // Cannot go backwards (except specific allowed)
        $this->assertFalse($this->checker->canTransition($admin, 'live', 'frozen'));
        $this->assertFalse($this->checker->canTransition($admin, 'closed', 'live'));
    }

    public function testAvailableTransitions(): void {
        $president = ['id' => '7', 'role' => 'president', 'tenant_id' => 'test'];

        $transitions = $this->checker->availableTransitions($president, 'draft');

        $this->assertIsArray($transitions);

        // President can go draft -> frozen
        $toStates = array_column($transitions, 'to');
        $this->assertContains('frozen', $toStates);
    }

    // =========================================================================
    // ANONYMOUS USER TESTS
    // =========================================================================

    public function testAnonymousHasNoPermissions(): void {
        $anonymous = ['id' => null, 'role' => 'anonymous', 'tenant_id' => 'test'];

        $this->assertFalse($this->checker->check($anonymous, 'meeting:read'));
        $this->assertFalse($this->checker->check($anonymous, 'vote:cast'));
        $this->assertFalse($this->checker->check($anonymous, 'admin:users'));
    }

    public function testMissingRoleDefaultsToAnonymous(): void {
        $noRole = ['id' => '8', 'tenant_id' => 'test'];

        $this->assertFalse($this->checker->check($noRole, 'meeting:read'));
    }

    // =========================================================================
    // hasRole TESTS
    // =========================================================================

    public function testHasRoleWithSingleRole(): void {
        $operator = ['id' => '2', 'role' => 'operator', 'tenant_id' => 'test'];

        $this->assertTrue($this->checker->hasRole($operator, ['operator']));
        $this->assertTrue($this->checker->hasRole($operator, ['operator', 'admin']));
        $this->assertFalse($this->checker->hasRole($operator, ['admin']));
    }

    public function testHasRoleWithHierarchy(): void {
        $admin = ['id' => '1', 'role' => 'admin', 'tenant_id' => 'test'];

        // Admin matches all system roles due to "always true"
        $this->assertTrue($this->checker->hasRole($admin, ['viewer']));
        $this->assertTrue($this->checker->hasRole($admin, ['operator']));
        $this->assertTrue($this->checker->hasRole($admin, ['auditor']));
    }

    // =========================================================================
    // getPermissions TESTS
    // =========================================================================

    public function testGetPermissionsForAdmin(): void {
        $admin = ['id' => '1', 'role' => 'admin', 'tenant_id' => 'test'];

        $permissions = $this->checker->getPermissions($admin);

        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);
        $this->assertContains('meeting:create', $permissions);
        $this->assertContains('admin:users', $permissions);
        $this->assertContains('vote:cast', $permissions);
    }

    public function testGetPermissionsForViewer(): void {
        $viewer = ['id' => '4', 'role' => 'viewer', 'tenant_id' => 'test'];

        $permissions = $this->checker->getPermissions($viewer);

        $this->assertContains('meeting:read', $permissions);
        $this->assertContains('motion:read', $permissions);
        $this->assertNotContains('meeting:create', $permissions);
        $this->assertNotContains('admin:users', $permissions);
    }

    // =========================================================================
    // CONFIG TESTS
    // =========================================================================

    public function testGetConfigReturnsStructure(): void {
        $config = $this->checker->getConfig();

        $this->assertArrayHasKey('permissions', $config);
        $this->assertArrayHasKey('transitions', $config);
        $this->assertArrayHasKey('hierarchy', $config);

        $this->assertIsArray($config['permissions']);
        $this->assertIsArray($config['transitions']);
        $this->assertIsArray($config['hierarchy']);
    }

    public function testPermissionsContainExpectedResources(): void {
        $config = $this->checker->getConfig();
        $permissions = $config['permissions'];

        // Meeting lifecycle
        $this->assertArrayHasKey('meeting:create', $permissions);
        $this->assertArrayHasKey('meeting:read', $permissions);
        $this->assertArrayHasKey('meeting:update', $permissions);
        $this->assertArrayHasKey('meeting:delete', $permissions);

        // Motions
        $this->assertArrayHasKey('motion:create', $permissions);
        $this->assertArrayHasKey('motion:read', $permissions);

        // Votes
        $this->assertArrayHasKey('vote:cast', $permissions);
        $this->assertArrayHasKey('vote:read', $permissions);

        // Admin
        $this->assertArrayHasKey('admin:users', $permissions);
        $this->assertArrayHasKey('admin:system', $permissions);
    }

    public function testTransitionsContainAllStates(): void {
        $config = $this->checker->getConfig();
        $transitions = $config['transitions'];

        $this->assertArrayHasKey('draft', $transitions);
        $this->assertArrayHasKey('scheduled', $transitions);
        $this->assertArrayHasKey('frozen', $transitions);
        $this->assertArrayHasKey('live', $transitions);
        $this->assertArrayHasKey('closed', $transitions);
        $this->assertArrayHasKey('validated', $transitions);
    }
}
