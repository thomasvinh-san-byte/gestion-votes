<?php

declare(strict_types=1);

use AgVote\Core\Security\AuthMiddleware;
use AgVote\Core\Security\Permissions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for relaxed role transitions (DU4).
 *
 * Uses AuthMiddleware::setCurrentUser() + AuthMiddleware::reset() test helpers.
 * No DB required — canTransition() only reads static user state + Permissions constants.
 */
class RelaxRoleTransitionsTest extends TestCase {

    protected function setUp(): void {
        AuthMiddleware::reset();
    }

    protected function tearDown(): void {
        AuthMiddleware::reset();
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function actAs(string $role): void {
        AuthMiddleware::setCurrentUser([
            'id'        => 'test-user',
            'role'      => $role,
            'tenant_id' => 'aaaaaaaa-1111-2222-3333-444444444444',
        ]);
    }

    // ── TRANSITIONS structure ─────────────────────────────────────────────

    public function test_transitions_values_are_arrays(): void {
        foreach (Permissions::TRANSITIONS as $from => $targets) {
            foreach ($targets as $to => $roles) {
                $this->assertIsArray($roles,
                    "TRANSITIONS[{$from}][{$to}] must be an array, got " . gettype($roles));
            }
        }
    }

    // ── Operator president-transitions ────────────────────────────────────

    /** @dataProvider operatorPresidentTransitions */
    public function test_operator_can_do_president_transitions(string $from, string $to): void {
        $this->actAs('operator');
        $this->assertTrue(
            AuthMiddleware::canTransition($from, $to),
            "operator must be able to transition {$from}→{$to}",
        );
    }

    public static function operatorPresidentTransitions(): array {
        return [
            'draft→frozen'         => ['draft',     'frozen'],
            'scheduled→frozen'     => ['scheduled', 'frozen'],
            'frozen→live'          => ['frozen',    'live'],
            'live→closed'          => ['live',      'closed'],
            'paused→closed'        => ['paused',    'closed'],
            'closed→validated'     => ['closed',    'validated'],
        ];
    }

    // ── Operator rollbacks ────────────────────────────────────────────────

    /** @dataProvider operatorRollbacks */
    public function test_operator_can_rollback(string $from, string $to): void {
        $this->actAs('operator');
        $this->assertTrue(
            AuthMiddleware::canTransition($from, $to),
            "operator must be able to rollback {$from}→{$to}",
        );
    }

    public static function operatorRollbacks(): array {
        return [
            'frozen→scheduled' => ['frozen',    'scheduled'],
            'scheduled→draft'  => ['scheduled', 'draft'],
        ];
    }

    // ── Operator-only transitions still work ──────────────────────────────

    public function test_operator_can_transition_live_to_paused(): void {
        $this->actAs('operator');
        $this->assertTrue(AuthMiddleware::canTransition('live', 'paused'));
    }

    public function test_operator_can_transition_paused_to_live(): void {
        $this->actAs('operator');
        $this->assertTrue(AuthMiddleware::canTransition('paused', 'live'));
    }

    // ── System-role president transitions ─────────────────────────────────

    /** @dataProvider presidentTransitions */
    public function test_system_president_can_do_president_transitions(string $from, string $to): void {
        $this->actAs('president');
        $this->assertTrue(
            AuthMiddleware::canTransition($from, $to),
            "system-role president must be able to transition {$from}→{$to}",
        );
    }

    public static function presidentTransitions(): array {
        return [
            'draft→frozen'     => ['draft',     'frozen'],
            'scheduled→frozen' => ['scheduled', 'frozen'],
            'frozen→live'      => ['frozen',    'live'],
            'live→closed'      => ['live',      'closed'],
            'paused→closed'    => ['paused',    'closed'],
            'closed→validated' => ['closed',    'validated'],
        ];
    }

    // ── SYSTEM_ROLES includes president ───────────────────────────────────

    public function test_president_is_a_system_role(): void {
        $this->assertTrue(AuthMiddleware::isSystemRole('president'));
    }

    // ── Admin can always transition ───────────────────────────────────────

    public function test_admin_always_permitted(): void {
        $this->actAs('admin');
        $this->assertTrue(AuthMiddleware::canTransition('validated', 'archived'));
        $this->assertTrue(AuthMiddleware::canTransition('draft', 'frozen'));
    }

    // ── Viewer cannot transition ──────────────────────────────────────────

    public function test_viewer_cannot_transition(): void {
        $this->actAs('viewer');
        $this->assertFalse(AuthMiddleware::canTransition('draft', 'scheduled'));
        $this->assertFalse(AuthMiddleware::canTransition('draft', 'frozen'));
    }

    // ── Invalid transition always false ──────────────────────────────────

    public function test_invalid_transition_returns_false(): void {
        $this->actAs('admin');
        $this->assertFalse(AuthMiddleware::canTransition('archived', 'draft'));
        $this->assertFalse(AuthMiddleware::canTransition('live', 'draft'));
    }
}
