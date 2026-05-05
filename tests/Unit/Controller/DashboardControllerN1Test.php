<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use AgVote\Controller\DashboardController;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingStatsRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\ProxyRepository;
use Tests\Unit\ControllerTestCase;

/**
 * PERF-V27-02 — regression test proving that DashboardController::index() does
 * NOT scale linearly with the number of closed motions.
 *
 * Before the fix: foreach ($closed as $mo) { $ballotRepo->countForMotion(...) }
 *   -> N closed motions = N countForMotion() calls (N+1 pattern).
 *
 * After the fix: $ballotRepo->countByMotionIds($closedIds, ...) called exactly once
 *   regardless of how many closed motions there are.
 */
final class DashboardControllerN1Test extends ControllerTestCase
{
    private const TENANT     = 'tenant-uuid-001';
    private const MEETING_ID = 'aaaaaaaa-1111-2222-3333-000000000001';
    private const USER_ID    = 'user-uuid-0001';

    private function buildClosedMotions(int $count): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'id'              => sprintf('bbbbbbbb-1111-2222-3333-%012d', $i),
                'title'           => "Motion {$i}",
                'manual_total'    => 0,
                'manual_for'      => 0,
                'manual_against'  => 0,
                'manual_abstain'  => 0,
            ];
        }
        return $rows;
    }

    private function buildBaseMocks(int $closedMotionsCount): array
    {
        $meetingData = [
            'id'                => self::MEETING_ID,
            'status'            => 'live',
            'current_motion_id' => '',
            'president_name'    => 'Alice',
        ];

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('listForDashboard')->willReturn([
            ['id' => self::MEETING_ID, 'status' => 'live'],
        ]);
        $meetingRepo->method('findByIdForTenant')->willReturn($meetingData);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countNotDeleted')->willReturn(10);
        $memberRepo->method('sumNotDeletedVoteWeight')->willReturn(10.0);

        $attRepo = $this->createMock(AttendanceRepository::class);
        $attRepo->method('dashboardSummary')->willReturn([
            'present_count' => 5, 'present_weight' => 5.0,
        ]);

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('getDashboardStats')->willReturn([
            'present_count' => 5, 'proxy_count' => 0, 'total_motions' => $closedMotionsCount,
            'closed_motions' => $closedMotionsCount, 'open_motions' => 0,
            'adopted_motions' => $closedMotionsCount, 'rejected_motions' => 0,
            'ballots_count' => 0, 'ballot_weight' => 0.0,
            'proxies_count' => 0, 'incidents_count' => 0, 'manual_votes_count' => 0,
        ]);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findCurrentOpen')->willReturn(null);
        $motionRepo->method('listOpenable')->willReturn([]);
        $motionRepo->method('listClosedWithManualTally')->willReturn(
            $this->buildClosedMotions($closedMotionsCount),
        );

        return [$meetingRepo, $statsRepo, $memberRepo, $attRepo, $motionRepo];
    }

    /**
     * Critical N+1 regression: ballotRepo->countByMotionIds() must be called
     * AT MOST ONCE regardless of how many closed motions exist, AND the deprecated
     * countForMotion() must NOT be called inside the foreach loop.
     */
    public function testIndexBatchesBallotCountsForClosedMotions(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        [$meetingRepo, $statsRepo, $memberRepo, $attRepo, $motionRepo] = $this->buildBaseMocks(10);

        $ballotRepo = $this->createMock(BallotRepository::class);
        // Expect the batch method called exactly once.
        $ballotRepo->expects($this->once())
            ->method('countByMotionIds')
            ->willReturn(array_fill_keys(
                array_map(fn($mo) => $mo['id'], $this->buildClosedMotions(10)),
                3,
            ));
        // The N+1 method must NEVER be called in the closed-motions loop.
        $ballotRepo->expects($this->never())
            ->method('countForMotion');

        $proxyRepo = $this->createMock(ProxyRepository::class);

        $this->injectRepos([
            MeetingRepository::class      => $meetingRepo,
            MeetingStatsRepository::class => $statsRepo,
            MemberRepository::class       => $memberRepo,
            AttendanceRepository::class   => $attRepo,
            MotionRepository::class       => $motionRepo,
            BallotRepository::class       => $ballotRepo,
            ProxyRepository::class        => $proxyRepo,
        ]);

        $res = $this->callController(DashboardController::class, 'index');
        $this->assertSame(200, $res['status']);
    }

    /**
     * Regression: response JSON shape must remain unchanged after the refactor.
     */
    public function testIndexReturnsSameJsonShapeAsBefore(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        [$meetingRepo, $statsRepo, $memberRepo, $attRepo, $motionRepo] = $this->buildBaseMocks(2);

        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('countByMotionIds')->willReturn([]);
        $ballotRepo->method('tally')->willReturn([
            'total_ballots' => 0, 'weight_for' => 0, 'weight_against' => 0, 'weight_abstain' => 0,
        ]);
        $proxyRepo = $this->createMock(ProxyRepository::class);

        $this->injectRepos([
            MeetingRepository::class      => $meetingRepo,
            MeetingStatsRepository::class => $statsRepo,
            MemberRepository::class       => $memberRepo,
            AttendanceRepository::class   => $attRepo,
            MotionRepository::class       => $motionRepo,
            BallotRepository::class       => $ballotRepo,
            ProxyRepository::class        => $proxyRepo,
        ]);

        $res = $this->callController(DashboardController::class, 'index');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];

        $expectedKeys = [
            'meetings', 'suggested_meeting_id', 'meeting', 'attendance',
            'proxies', 'current_motion', 'current_motion_votes', 'openable_motions',
            'ready_to_sign', 'stats',
        ];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $data, "Response must keep key '{$key}'");
        }
    }
}
