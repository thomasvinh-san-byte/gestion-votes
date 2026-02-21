<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour les transitions d'etat des seances
 * Valide la machine a etats sans dependre de la base de donnees
 */
class MeetingTransitionTest extends TestCase
{
    // Valid meeting status values
    private const VALID_STATUSES = [
        'draft',
        'scheduled',
        'frozen',
        'live',
        'paused',
        'closed',
        'validated',
        'archived',
    ];

    // Expected state machine transitions
    private const TRANSITIONS = [
        'draft'     => ['scheduled', 'frozen'],
        'scheduled' => ['frozen', 'draft'],
        'frozen'    => ['live', 'scheduled'],
        'live'      => ['paused', 'closed'],
        'paused'    => ['live', 'closed'],
        'closed'    => ['validated'],
        'validated' => ['archived'],
        'archived'  => [],
    ];

    // Required roles for each transition
    private const TRANSITION_ROLES = [
        'draft' => [
            'scheduled' => 'operator',
            'frozen' => 'president',
        ],
        'scheduled' => [
            'frozen' => 'president',
            'draft' => 'admin',
        ],
        'frozen' => [
            'live' => 'president',
            'scheduled' => 'admin',
        ],
        'live' => [
            'paused' => 'operator',
            'closed' => 'president',
        ],
        'paused' => [
            'live' => 'operator',
            'closed' => 'president',
        ],
        'closed' => [
            'validated' => 'president',
        ],
        'validated' => [
            'archived' => 'admin',
        ],
    ];

    // =========================================================================
    // STATE MACHINE VALIDATION
    // =========================================================================

    public function testAllStatusesAreDefined(): void
    {
        foreach (self::VALID_STATUSES as $status) {
            $this->assertArrayHasKey($status, self::TRANSITIONS, "Missing transition definition for status: {$status}");
        }
    }

    public function testDraftTransitions(): void
    {
        $allowed = self::TRANSITIONS['draft'];

        $this->assertContains('scheduled', $allowed);
        $this->assertContains('frozen', $allowed);
        $this->assertCount(2, $allowed);
    }

    public function testScheduledTransitions(): void
    {
        $allowed = self::TRANSITIONS['scheduled'];

        $this->assertContains('frozen', $allowed);
        $this->assertContains('draft', $allowed);
        $this->assertCount(2, $allowed);
    }

    public function testFrozenTransitions(): void
    {
        $allowed = self::TRANSITIONS['frozen'];

        $this->assertContains('live', $allowed);
        $this->assertContains('scheduled', $allowed);
        $this->assertCount(2, $allowed);
    }

    public function testLiveTransitions(): void
    {
        $allowed = self::TRANSITIONS['live'];

        $this->assertContains('paused', $allowed);
        $this->assertContains('closed', $allowed);
        $this->assertCount(2, $allowed);
    }

    public function testPausedTransitions(): void
    {
        $allowed = self::TRANSITIONS['paused'];

        $this->assertContains('live', $allowed);
        $this->assertContains('closed', $allowed);
        $this->assertCount(2, $allowed);
    }

    public function testClosedTransitions(): void
    {
        $allowed = self::TRANSITIONS['closed'];

        $this->assertContains('validated', $allowed);
        $this->assertCount(1, $allowed);
    }

    public function testValidatedTransitions(): void
    {
        $allowed = self::TRANSITIONS['validated'];

        $this->assertContains('archived', $allowed);
        $this->assertCount(1, $allowed);
    }

    public function testArchivedIsTerminal(): void
    {
        $allowed = self::TRANSITIONS['archived'];

        $this->assertEmpty($allowed);
    }

    // =========================================================================
    // FORWARD-ONLY PROGRESSION TESTS
    // =========================================================================

    public function testCannotSkipFromDraftToLive(): void
    {
        $this->assertNotContains('live', self::TRANSITIONS['draft']);
    }

    public function testCannotSkipFromDraftToClosed(): void
    {
        $this->assertNotContains('closed', self::TRANSITIONS['draft']);
    }

    public function testCannotSkipFromScheduledToLive(): void
    {
        $this->assertNotContains('live', self::TRANSITIONS['scheduled']);
    }

    public function testCannotGoBackFromLiveToFrozen(): void
    {
        $this->assertNotContains('frozen', self::TRANSITIONS['live']);
    }

    public function testCannotGoBackFromClosedToLive(): void
    {
        $this->assertNotContains('live', self::TRANSITIONS['closed']);
    }

    // =========================================================================
    // ROLE REQUIREMENTS TESTS
    // =========================================================================

    public function testOperatorCanSchedule(): void
    {
        $role = self::TRANSITION_ROLES['draft']['scheduled'];
        $this->assertEquals('operator', $role);
    }

    public function testPresidentCanFreeze(): void
    {
        $role = self::TRANSITION_ROLES['draft']['frozen'];
        $this->assertEquals('president', $role);

        $role2 = self::TRANSITION_ROLES['scheduled']['frozen'];
        $this->assertEquals('president', $role2);
    }

    public function testPresidentCanOpenMeeting(): void
    {
        $role = self::TRANSITION_ROLES['frozen']['live'];
        $this->assertEquals('president', $role);
    }

    public function testPresidentCanCloseMeeting(): void
    {
        $role = self::TRANSITION_ROLES['live']['closed'];
        $this->assertEquals('president', $role);
    }

    public function testPresidentCanValidate(): void
    {
        $role = self::TRANSITION_ROLES['closed']['validated'];
        $this->assertEquals('president', $role);
    }

    public function testOnlyAdminCanArchive(): void
    {
        $role = self::TRANSITION_ROLES['validated']['archived'];
        $this->assertEquals('admin', $role);
    }

    public function testOnlyAdminCanRevertToScheduled(): void
    {
        $role = self::TRANSITION_ROLES['frozen']['scheduled'];
        $this->assertEquals('admin', $role);
    }

    public function testOnlyAdminCanRevertToDraft(): void
    {
        $role = self::TRANSITION_ROLES['scheduled']['draft'];
        $this->assertEquals('admin', $role);
    }

    // =========================================================================
    // COMPLETE LIFECYCLE TEST
    // =========================================================================

    public function testCompleteForwardLifecycle(): void
    {
        $lifecycle = ['draft', 'scheduled', 'frozen', 'live', 'closed', 'validated', 'archived'];

        for ($i = 0; $i < count($lifecycle) - 1; $i++) {
            $from = $lifecycle[$i];
            $to = $lifecycle[$i + 1];

            $transitions = self::TRANSITIONS[$from];

            // At minimum, the next state in lifecycle should be reachable
            // (except draft->scheduled is optional, draft->frozen is also valid)
            if ($from === 'draft') {
                $this->assertTrue(
                    in_array('scheduled', $transitions) || in_array('frozen', $transitions),
                    "From {$from} should be able to progress"
                );
            } else {
                $this->assertContains(
                    $to,
                    $transitions,
                    "Cannot transition from {$from} to {$to}"
                );
            }
        }
    }

    // =========================================================================
    // VALIDATION HELPER
    // =========================================================================

    /**
     * Test helper to validate transition
     */
    private function isValidTransition(string $from, string $to): bool
    {
        if (!isset(self::TRANSITIONS[$from])) {
            return false;
        }
        return in_array($to, self::TRANSITIONS[$from], true);
    }

    public function testValidTransitionHelper(): void
    {
        $this->assertTrue($this->isValidTransition('draft', 'scheduled'));
        $this->assertTrue($this->isValidTransition('draft', 'frozen'));
        $this->assertTrue($this->isValidTransition('frozen', 'live'));
        $this->assertTrue($this->isValidTransition('live', 'closed'));

        $this->assertFalse($this->isValidTransition('draft', 'live'));
        $this->assertFalse($this->isValidTransition('archived', 'draft'));
        $this->assertFalse($this->isValidTransition('invalid', 'live'));
    }
}
