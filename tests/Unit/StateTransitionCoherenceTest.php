<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use AgVote\Core\Security\Permissions;
use AgVote\Core\Security\AuthMiddleware;

/**
 * Tests that Permissions::TRANSITIONS and AuthMiddleware::STATE_TRANSITIONS
 * are in sync. Both define the same state machine — they must never diverge.
 */
class StateTransitionCoherenceTest extends TestCase
{
    /**
     * Get AuthMiddleware STATE_TRANSITIONS via reflection (it's private).
     */
    private function getAuthMiddlewareTransitions(): array
    {
        $ref = new \ReflectionClass(AuthMiddleware::class);
        $prop = $ref->getReflectionConstant('STATE_TRANSITIONS');
        return $prop->getValue();
    }

    public function testTransitionsAreIdentical(): void
    {
        $permissions = Permissions::TRANSITIONS;
        $authMiddleware = $this->getAuthMiddlewareTransitions();

        // Same set of source states
        $permKeys = array_keys($permissions);
        $authKeys = array_keys($authMiddleware);
        sort($permKeys);
        sort($authKeys);

        $this->assertSame(
            $permKeys,
            $authKeys,
            'Permissions::TRANSITIONS and AuthMiddleware::STATE_TRANSITIONS must define the same source states'
        );

        // Same transitions for each state
        foreach ($permissions as $from => $targets) {
            $this->assertSame(
                $targets,
                $authMiddleware[$from] ?? [],
                "Transition mismatch for state '{$from}'"
            );
        }
    }

    public function testArchivedIsTerminal(): void
    {
        $permissions = Permissions::TRANSITIONS;
        $authMiddleware = $this->getAuthMiddlewareTransitions();

        // archived must have no outgoing transitions in BOTH sources
        $this->assertArrayNotHasKey('archived', $permissions, 'archived must be terminal in Permissions');
        $this->assertArrayNotHasKey('archived', $authMiddleware, 'archived must be terminal in AuthMiddleware');
    }

    public function testLiveCanPause(): void
    {
        $permissions = Permissions::TRANSITIONS;
        $authMiddleware = $this->getAuthMiddlewareTransitions();

        $this->assertArrayHasKey('paused', $permissions['live'] ?? [], 'live → paused must exist in Permissions');
        $this->assertArrayHasKey('paused', $authMiddleware['live'] ?? [], 'live → paused must exist in AuthMiddleware');
    }

    public function testPausedCanResume(): void
    {
        $permissions = Permissions::TRANSITIONS;
        $authMiddleware = $this->getAuthMiddlewareTransitions();

        $this->assertArrayHasKey('live', $permissions['paused'] ?? [], 'paused → live must exist in Permissions');
        $this->assertArrayHasKey('live', $authMiddleware['paused'] ?? [], 'paused → live must exist in AuthMiddleware');
    }

    public function testAllLabelsHaveTransitions(): void
    {
        $labels = Permissions::LABELS['statuses'] ?? [];
        $transitions = Permissions::TRANSITIONS;

        foreach ($labels as $status => $label) {
            if ($status === 'archived') {
                // Terminal state — no outgoing transitions is expected
                $this->assertArrayNotHasKey($status, $transitions, "archived should not have outgoing transitions");
                continue;
            }
            $this->assertArrayHasKey(
                $status,
                $transitions,
                "Status '{$status}' ({$label}) has a label but no transition rules"
            );
        }
    }
}
