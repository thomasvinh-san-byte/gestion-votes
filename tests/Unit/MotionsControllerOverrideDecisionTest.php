<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\MotionsController;
use AgVote\Repository\MotionRepository;

/**
 * Execution tests for MotionsController::overrideDecision().
 *
 * Asserts the happy path returns {ok:true, decision:'adopted'} and
 * the error path (motion not closed) returns 409 motion_not_closed.
 *
 * EventBroadcaster calls are gracefully silenced (no Redis in test env —
 * EventBroadcaster::queue() catches Throwable and logs, never throws).
 */
class MotionsControllerOverrideDecisionTest extends ControllerTestCase
{
    private const TENANT_ID  = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const MOTION_ID  = 'cc000001-0000-4000-c000-000000000001';
    private const MEETING_ID = 'dd000002-0000-4000-d000-000000000002';
    private const USER_ID    = 'ee000003-0000-4000-e000-000000000003';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setAuth(self::USER_ID, 'admin', self::TENANT_ID);
    }

    // =========================================================================
    // VALIDATION — missing / invalid fields
    // =========================================================================

    public function testOverrideDecisionRequiresPostMethod(): void
    {
        $this->setHttpMethod('GET');
        $result = $this->callController(MotionsController::class, 'overrideDecision');
        $this->assertSame(405, $result['status']);
        $this->assertSame('method_not_allowed', $result['body']['error']);
    }

    public function testOverrideDecisionMissingMotionIdReturns422(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['decision' => 'adopted', 'justification' => 'Erreur manuelle']);
        $result = $this->callController(MotionsController::class, 'overrideDecision');
        $this->assertSame(422, $result['status']);
        $this->assertSame('missing_motion_id', $result['body']['error']);
    }

    public function testOverrideDecisionInvalidDecisionReturns422(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'motion_id'     => self::MOTION_ID,
            'decision'      => 'abstain',
            'justification' => 'Erreur comptage',
        ]);
        $result = $this->callController(MotionsController::class, 'overrideDecision');
        $this->assertSame(422, $result['status']);
        $this->assertSame('invalid_decision', $result['body']['error']);
    }

    public function testOverrideDecisionMissingJustificationReturns422(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'motion_id' => self::MOTION_ID,
            'decision'  => 'adopted',
        ]);
        $result = $this->callController(MotionsController::class, 'overrideDecision');
        $this->assertSame(422, $result['status']);
        $this->assertSame('missing_justification', $result['body']['error']);
    }

    public function testOverrideDecisionMotionNotFoundReturns404(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'motion_id'     => self::MOTION_ID,
            'decision'      => 'adopted',
            'justification' => 'Erreur comptage manuel',
        ]);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findWithMeetingTenant')->willReturn(null);

        $this->injectRepos([MotionRepository::class => $motionRepo]);

        $result = $this->callController(MotionsController::class, 'overrideDecision');
        $this->assertSame(404, $result['status']);
        $this->assertSame('motion_not_found', $result['body']['error']);
    }

    // =========================================================================
    // ERROR PATH — motion not closed
    // =========================================================================

    public function testOverrideDecisionRejectsOpenMotion(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'motion_id'     => self::MOTION_ID,
            'decision'      => 'adopted',
            'justification' => 'Erreur comptage manuel',
        ]);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findWithMeetingTenant')->willReturn([
            'id'           => self::MOTION_ID,
            'meeting_id'   => self::MEETING_ID,
            'motion_title' => 'Résolution 1',
            'tenant_id'    => self::TENANT_ID,
            'closed_at'    => null, // motion is still open
            'opened_at'    => '2026-04-07 12:00:00',
        ]);

        $this->injectRepos([MotionRepository::class => $motionRepo]);

        $result = $this->callController(MotionsController::class, 'overrideDecision');
        $this->assertSame(409, $result['status']);
        $this->assertSame('motion_not_closed', $result['body']['error']);
    }

    // =========================================================================
    // HAPPY PATH — asserts real payload
    // =========================================================================

    /**
     * Happy path: closed motion + valid decision → 200 {ok:true, decision:'adopted'}
     *
     * This test proves the full code path executes:
     *  1. Input validation passes
     *  2. findWithMeetingTenant returns a closed motion
     *  3. api_transaction callback invokes overrideDecision()
     *  4. EventBroadcaster::motionClosed() fires (silently, no Redis in CI)
     *  5. api_ok(['decision' => 'adopted']) is thrown and caught → 200 response
     */
    public function testOverrideDecisionHappyPathAdopted(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'motion_id'     => self::MOTION_ID,
            'decision'      => 'adopted',
            'justification' => 'Erreur comptage manuel',
        ]);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findWithMeetingTenant')->willReturn([
            'id'           => self::MOTION_ID,
            'meeting_id'   => self::MEETING_ID,
            'motion_title' => 'Résolution 1',
            'tenant_id'    => self::TENANT_ID,
            'closed_at'    => '2026-04-07 14:00:00', // motion is closed
            'opened_at'    => '2026-04-07 12:00:00',
        ]);
        $motionRepo->expects($this->once())
            ->method('overrideDecision')
            ->with(self::MOTION_ID, 'adopted', 'Erreur comptage manuel', self::TENANT_ID);

        $this->injectRepos([MotionRepository::class => $motionRepo]);

        $result = $this->callController(MotionsController::class, 'overrideDecision');

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        // api_ok() wraps data under 'data' key: {ok:true, data:{decision:'adopted'}}
        $this->assertSame('adopted', $result['body']['data']['decision']);
    }

    /**
     * Happy path: rejected decision → 200 {ok:true, data:{decision:'rejected'}}
     */
    public function testOverrideDecisionHappyPathRejected(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'motion_id'     => self::MOTION_ID,
            'decision'      => 'rejected',
            'justification' => 'Recompte confirme rejet',
        ]);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findWithMeetingTenant')->willReturn([
            'id'           => self::MOTION_ID,
            'meeting_id'   => self::MEETING_ID,
            'motion_title' => 'Résolution 2',
            'tenant_id'    => self::TENANT_ID,
            'closed_at'    => '2026-04-07 15:00:00',
            'opened_at'    => '2026-04-07 14:00:00',
        ]);
        // Note: overrideDecision() returns void — configure no return value

        $this->injectRepos([MotionRepository::class => $motionRepo]);

        $result = $this->callController(MotionsController::class, 'overrideDecision');

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertSame('rejected', $result['body']['data']['decision']);
    }
}
