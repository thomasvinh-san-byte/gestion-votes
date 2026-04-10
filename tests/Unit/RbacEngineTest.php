<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Security\Permissions;
use AgVote\Core\Security\RbacEngine;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RbacEngine extracted class.
 *
 * Proves RbacEngine is independently testable without AuthMiddleware.
 * Uses plain user arrays — no DB, no session, no middleware.
 */
final class RbacEngineTest extends TestCase {

    protected function setUp(): void {
        if (!defined('DEFAULT_TENANT_ID')) {
            define('DEFAULT_TENANT_ID', 'aaaaaaaa-1111-2222-3333-444444444444');
        }
        RbacEngine::reset();
    }

    protected function tearDown(): void {
        RbacEngine::reset();
    }

    // =========================================================================
    // Helper: build user arrays
    // =========================================================================

    private function buildUser(string $role, string $id = 'uuid-1'): array {
        return [
            'id' => $id,
            'role' => $role,
            'tenant_id' => 'tenant-1',
            'is_active' => true,
        ];
    }

    // =========================================================================
    // normalizeRole
    // =========================================================================

    /**
     * Test: normalizeRole resolves aliases ('trust' -> 'assessor', 'readonly' -> 'viewer').
     */
    public function testNormalizeRoleResolvesAliases(): void {
        $this->assertSame('assessor', RbacEngine::normalizeRole('trust'));
        $this->assertSame('viewer', RbacEngine::normalizeRole('readonly'));
    }

    /**
     * Test: normalizeRole passes through valid roles unchanged.
     */
    public function testNormalizeRolePassesThroughValidRoles(): void {
        $this->assertSame('admin', RbacEngine::normalizeRole('admin'));
        $this->assertSame('operator', RbacEngine::normalizeRole('operator'));
        $this->assertSame('voter', RbacEngine::normalizeRole('voter'));
    }

    /**
     * Test: normalizeRole handles case insensitive and trimming.
     */
    public function testNormalizeRoleHandlesCaseAndTrim(): void {
        $this->assertSame('admin', RbacEngine::normalizeRole(' Admin '));
        $this->assertSame('assessor', RbacEngine::normalizeRole('TRUST'));
    }

    // =========================================================================
    // isMeetingRole / isSystemRole
    // =========================================================================

    /**
     * Test: isMeetingRole returns true for president, assessor, voter.
     */
    public function testIsMeetingRoleReturnsTrueForMeetingRoles(): void {
        $this->assertTrue(RbacEngine::isMeetingRole('president'));
        $this->assertTrue(RbacEngine::isMeetingRole('assessor'));
        $this->assertTrue(RbacEngine::isMeetingRole('voter'));
        // Alias should also work
        $this->assertTrue(RbacEngine::isMeetingRole('trust'));
    }

    /**
     * Test: isSystemRole returns true for admin, operator, auditor, viewer.
     */
    public function testIsSystemRoleReturnsTrueForSystemRoles(): void {
        $this->assertTrue(RbacEngine::isSystemRole('admin'));
        $this->assertTrue(RbacEngine::isSystemRole('operator'));
        $this->assertTrue(RbacEngine::isSystemRole('auditor'));
        $this->assertTrue(RbacEngine::isSystemRole('viewer'));
        // Alias should also work
        $this->assertTrue(RbacEngine::isSystemRole('readonly'));
    }

    /**
     * Test: isMeetingRole returns false for system roles.
     */
    public function testIsMeetingRoleReturnsFalseForSystemRoles(): void {
        $this->assertFalse(RbacEngine::isMeetingRole('admin'));
        $this->assertFalse(RbacEngine::isMeetingRole('operator'));
    }

    // =========================================================================
    // checkRole
    // =========================================================================

    /**
     * Test: checkRole returns true when user role matches required role.
     */
    public function testCheckRoleReturnsTrueWhenRoleMatches(): void {
        $user = $this->buildUser('operator');

        $result = RbacEngine::checkRole($user, ['operator']);

        $this->assertTrue($result);
    }

    /**
     * Test: checkRole returns true when user has higher hierarchy role than required.
     */
    public function testCheckRoleReturnsTrueForHigherHierarchyRole(): void {
        $user = $this->buildUser('operator'); // level 80

        // viewer is level 5, operator (80) >= viewer (5)
        $result = RbacEngine::checkRole($user, ['viewer']);

        $this->assertTrue($result, 'Operator (80) should satisfy viewer (5) via hierarchy');
    }

    /**
     * Test: checkRole returns false when user has insufficient role.
     */
    public function testCheckRoleReturnsFalseForInsufficientRole(): void {
        $user = $this->buildUser('viewer'); // level 5

        // operator is level 80, viewer (5) < operator (80)
        $result = RbacEngine::checkRole($user, ['operator']);

        $this->assertFalse($result, 'Viewer should not satisfy operator requirement');
    }

    /**
     * Test: checkRole returns true for admin (admin always passes).
     */
    public function testCheckRoleAlwaysTrueForAdmin(): void {
        $user = $this->buildUser('admin');

        $result = RbacEngine::checkRole($user, ['operator']);

        $this->assertTrue($result, 'Admin should always pass any role check');
    }

    /**
     * Test: checkRole returns false for null user.
     */
    public function testCheckRoleReturnsFalseForNullUser(): void {
        $result = RbacEngine::checkRole(null, ['operator']);

        $this->assertFalse($result);
    }

    // =========================================================================
    // can (permissions)
    // =========================================================================

    /**
     * Test: can returns true for permission granted to user role.
     */
    public function testCanReturnsTrueForGrantedPermission(): void {
        $user = $this->buildUser('operator');

        // operator has meeting:create permission
        $result = RbacEngine::can($user, 'meeting:create');

        $this->assertTrue($result);
    }

    /**
     * Test: can returns false for permission not in user role.
     */
    public function testCanReturnsFalseForDeniedPermission(): void {
        $user = $this->buildUser('viewer');

        // viewer does NOT have meeting:create
        $result = RbacEngine::can($user, 'meeting:create');

        $this->assertFalse($result);
    }

    /**
     * Test: can returns true for admin regardless of permission.
     */
    public function testCanAlwaysTrueForAdmin(): void {
        $user = $this->buildUser('admin');

        $result = RbacEngine::can($user, 'admin:system');

        $this->assertTrue($result);
    }

    // =========================================================================
    // canTransition
    // =========================================================================

    /**
     * Test: canTransition returns true for allowed status transition.
     */
    public function testCanTransitionReturnsTrueForAllowedTransition(): void {
        $user = $this->buildUser('operator');

        // operator can transition draft -> scheduled
        $result = RbacEngine::canTransition($user, 'draft', 'scheduled');

        $this->assertTrue($result);
    }

    /**
     * Test: canTransition returns false for disallowed transition.
     */
    public function testCanTransitionReturnsFalseForDisallowedTransition(): void {
        $user = $this->buildUser('viewer');

        // viewer cannot transition anything
        $result = RbacEngine::canTransition($user, 'draft', 'scheduled');

        $this->assertFalse($result);
    }

    /**
     * Test: canTransition returns false for non-existent transition path.
     */
    public function testCanTransitionReturnsFalseForNonExistentPath(): void {
        $user = $this->buildUser('admin');

        // archived has no transitions (terminal state)
        $result = RbacEngine::canTransition($user, 'archived', 'draft');

        $this->assertFalse($result);
    }

    // =========================================================================
    // availableTransitions
    // =========================================================================

    /**
     * Test: availableTransitions returns correct list for given role and status.
     */
    public function testAvailableTransitionsReturnsCorrectList(): void {
        $user = $this->buildUser('operator');

        $transitions = RbacEngine::availableTransitions($user, 'draft');

        // operator can go: draft -> scheduled, draft -> frozen
        $toStates = array_column($transitions, 'to');
        $this->assertContains('scheduled', $toStates);
        $this->assertContains('frozen', $toStates);
    }

    /**
     * Test: availableTransitions returns empty for terminal state.
     */
    public function testAvailableTransitionsEmptyForTerminalState(): void {
        $user = $this->buildUser('admin');

        $transitions = RbacEngine::availableTransitions($user, 'archived');

        $this->assertEmpty($transitions, 'Archived is terminal, no transitions allowed');
    }

    // =========================================================================
    // getRoleLevel / isRoleAtLeast
    // =========================================================================

    /**
     * Test: getRoleLevel returns correct hierarchy value.
     */
    public function testGetRoleLevelReturnsCorrectValue(): void {
        $this->assertSame(100, RbacEngine::getRoleLevel('admin'));
        $this->assertSame(80, RbacEngine::getRoleLevel('operator'));
        $this->assertSame(5, RbacEngine::getRoleLevel('viewer'));
        $this->assertSame(0, RbacEngine::getRoleLevel('anonymous'));
    }

    /**
     * Test: getRoleLevel resolves aliases before lookup.
     */
    public function testGetRoleLevelResolvesAliases(): void {
        // 'trust' -> 'assessor' -> 60
        $this->assertSame(60, RbacEngine::getRoleLevel('trust'));
        // 'readonly' -> 'viewer' -> 5
        $this->assertSame(5, RbacEngine::getRoleLevel('readonly'));
    }

    /**
     * Test: isRoleAtLeast returns true when role >= minimum.
     */
    public function testIsRoleAtLeastReturnsTrueWhenSufficient(): void {
        $this->assertTrue(RbacEngine::isRoleAtLeast('admin', 'operator'));
        $this->assertTrue(RbacEngine::isRoleAtLeast('operator', 'operator'));
        $this->assertTrue(RbacEngine::isRoleAtLeast('operator', 'viewer'));
    }

    /**
     * Test: isRoleAtLeast returns false when role < minimum.
     */
    public function testIsRoleAtLeastReturnsFalseWhenInsufficient(): void {
        $this->assertFalse(RbacEngine::isRoleAtLeast('viewer', 'operator'));
        $this->assertFalse(RbacEngine::isRoleAtLeast('anonymous', 'viewer'));
    }

    // =========================================================================
    // reset
    // =========================================================================

    /**
     * Test: reset clears meeting context state.
     */
    public function testResetClearsMeetingContext(): void {
        RbacEngine::setMeetingContext('meeting-uuid-001');

        RbacEngine::reset();

        // After reset, getMeetingRoles with no context should return empty
        $user = $this->buildUser('operator');
        $roles = RbacEngine::getMeetingRoles($user);
        $this->assertEmpty($roles, 'Meeting roles should be empty after reset');
    }

    // =========================================================================
    // Label getters
    // =========================================================================

    /**
     * Test: getSystemRoleLabels returns labels for system roles only.
     */
    public function testGetSystemRoleLabelsReturnsCorrectLabels(): void {
        $labels = RbacEngine::getSystemRoleLabels();

        $this->assertArrayHasKey('admin', $labels);
        $this->assertArrayHasKey('operator', $labels);
        $this->assertArrayHasKey('auditor', $labels);
        $this->assertArrayHasKey('viewer', $labels);
        // president is both system and meeting role per SYSTEM_ROLES constant
        $this->assertArrayHasKey('president', $labels);
        // voter/assessor should NOT be in system labels
        $this->assertArrayNotHasKey('voter', $labels);
        $this->assertArrayNotHasKey('assessor', $labels);
    }

    /**
     * Test: getMeetingRoleLabels returns labels for meeting roles only.
     */
    public function testGetMeetingRoleLabelsReturnsCorrectLabels(): void {
        $labels = RbacEngine::getMeetingRoleLabels();

        $this->assertArrayHasKey('president', $labels);
        $this->assertArrayHasKey('assessor', $labels);
        $this->assertArrayHasKey('voter', $labels);
        // admin should NOT be in meeting labels
        $this->assertArrayNotHasKey('admin', $labels);
    }
}
