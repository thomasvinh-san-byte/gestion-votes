<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use AgVote\Core\Security\Permissions;

/**
 * Tests that Permissions::TRANSITIONS is the single source of truth
 * for the meeting state machine. AuthMiddleware now references
 * Permissions::TRANSITIONS directly (no duplication).
 */
class StateTransitionCoherenceTest extends TestCase
{
    public function testTransitionsExist(): void
    {
        $transitions = Permissions::TRANSITIONS;
        $this->assertNotEmpty($transitions);
    }

    public function testArchivedIsTerminal(): void
    {
        $transitions = Permissions::TRANSITIONS;
        $this->assertArrayNotHasKey('archived', $transitions, 'archived must be terminal');
    }

    public function testLiveCanPause(): void
    {
        $transitions = Permissions::TRANSITIONS;
        $this->assertArrayHasKey('paused', $transitions['live'] ?? [], 'live → paused must exist');
    }

    public function testPausedCanResume(): void
    {
        $transitions = Permissions::TRANSITIONS;
        $this->assertArrayHasKey('live', $transitions['paused'] ?? [], 'paused → live must exist');
    }

    public function testAllLabelsHaveTransitions(): void
    {
        $labels = Permissions::LABELS['statuses'] ?? [];
        $transitions = Permissions::TRANSITIONS;

        foreach ($labels as $status => $label) {
            if ($status === 'archived') {
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
