<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\SpeechRepository;
use AgVote\Service\SpeechService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for SpeechService.
 *
 * Uses createMock() for SpeechRepository, MeetingRepository, MemberRepository.
 * Covers state transitions: waiting, speaking, finished, cancelled.
 */
class SpeechServiceTest extends TestCase
{
    private SpeechService $service;
    private $speechRepo;
    private $meetingRepo;
    private $memberRepo;

    private const TENANT  = 'tenant-001';
    private const MEETING = 'meeting-001';
    private const MEMBER  = 'member-001';

    protected function setUp(): void
    {
        $this->speechRepo  = $this->createMock(SpeechRepository::class);
        $this->meetingRepo = $this->createMock(MeetingRepository::class);
        $this->memberRepo  = $this->createMock(MemberRepository::class);

        // Default: meeting exists (resolveTenant succeeds)
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn(['id' => self::MEETING, 'tenant_id' => self::TENANT]);

        // Default: member has a name
        $this->memberRepo->method('findByIdForTenant')
            ->willReturn(['full_name' => 'Jean Dupont']);

        $this->service = new SpeechService(
            $this->speechRepo,
            $this->meetingRepo,
            $this->memberRepo,
        );
    }

    // =========================================================================
    // resolveTenant / meeting validation
    // =========================================================================

    public function testResolveTenantThrowsWhenMeetingNotFound(): void
    {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        $service = new SpeechService($this->speechRepo, $meetingRepo, $this->memberRepo);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('introuvable');
        $service->getQueue(self::MEETING, self::TENANT);
    }

    // =========================================================================
    // getQueue() tests
    // =========================================================================

    public function testGetQueueReturnsSpeakerAndQueue(): void
    {
        $speaker = ['id' => 'req-0', 'member_id' => 'member-000', 'full_name' => 'Alice'];
        $waiting = [
            ['id' => 'req-1', 'member_id' => 'member-001'],
            ['id' => 'req-2', 'member_id' => 'member-002'],
        ];

        $this->speechRepo->method('findCurrentSpeaker')->willReturn($speaker);
        $this->speechRepo->method('listWaiting')->willReturn($waiting);

        $result = $this->service->getQueue(self::MEETING, self::TENANT);

        $this->assertArrayHasKey('speaker', $result);
        $this->assertArrayHasKey('queue', $result);
        $this->assertSame($speaker, $result['speaker']);
        $this->assertCount(2, $result['queue']);
    }

    public function testGetQueueNullSpeakerWhenNone(): void
    {
        $this->speechRepo->method('findCurrentSpeaker')->willReturn(null);
        $this->speechRepo->method('listWaiting')->willReturn([]);

        $result = $this->service->getQueue(self::MEETING, self::TENANT);

        $this->assertNull($result['speaker']);
    }

    // =========================================================================
    // getMyStatus() tests
    // =========================================================================

    public function testGetMyStatusReturnsNoneWhenNoActiveRequest(): void
    {
        $this->speechRepo->method('findActive')->willReturn(null);
        $this->speechRepo->method('listWaiting')->willReturn([]);

        $result = $this->service->getMyStatus(self::MEETING, self::MEMBER, self::TENANT);

        $this->assertSame('none', $result['status']);
        $this->assertNull($result['request_id']);
        $this->assertNull($result['position']);
    }

    public function testGetMyStatusReturnsWaitingWithPosition(): void
    {
        $this->speechRepo->method('findActive')
            ->willReturn(['id' => 'req-1', 'status' => 'waiting']);
        $this->speechRepo->method('listWaiting')
            ->willReturn([['member_id' => self::MEMBER]]);

        $result = $this->service->getMyStatus(self::MEETING, self::MEMBER, self::TENANT);

        $this->assertSame('waiting', $result['status']);
        $this->assertSame(1, $result['position']);
    }

    public function testGetMyStatusReturnsSpeakingNoPosition(): void
    {
        $this->speechRepo->method('findActive')
            ->willReturn(['id' => 'req-1', 'status' => 'speaking']);
        $this->speechRepo->method('listWaiting')->willReturn([]);

        $result = $this->service->getMyStatus(self::MEETING, self::MEMBER, self::TENANT);

        $this->assertSame('speaking', $result['status']);
        $this->assertNull($result['position']);
    }

    // =========================================================================
    // toggleRequest() tests
    // =========================================================================

    public function testToggleRequestCreatesWaitingWhenNoExisting(): void
    {
        $this->speechRepo->method('findActive')->willReturn(null);
        $this->speechRepo->expects($this->once())
            ->method('insert')
            ->with($this->anything(), self::TENANT, self::MEETING, self::MEMBER, 'waiting');

        $result = $this->service->toggleRequest(self::MEETING, self::MEMBER, self::TENANT);

        $this->assertSame('waiting', $result['status']);
        $this->assertNotNull($result['request_id']);
    }

    public function testToggleRequestCancelsWhenWaiting(): void
    {
        $this->speechRepo->method('findActive')
            ->willReturn(['id' => 'req-1', 'status' => 'waiting']);
        $this->speechRepo->expects($this->once())
            ->method('updateStatus')
            ->with('req-1', self::TENANT, 'cancelled');

        $result = $this->service->toggleRequest(self::MEETING, self::MEMBER, self::TENANT);

        $this->assertSame('none', $result['status']);
        $this->assertNull($result['request_id']);
    }

    public function testToggleRequestFinishesWhenSpeaking(): void
    {
        $this->speechRepo->method('findActive')
            ->willReturn(['id' => 'req-1', 'status' => 'speaking']);
        $this->speechRepo->expects($this->once())
            ->method('updateStatus')
            ->with('req-1', self::TENANT, 'finished');

        $result = $this->service->toggleRequest(self::MEETING, self::MEMBER, self::TENANT);

        $this->assertSame('none', $result['status']);
        $this->assertNull($result['request_id']);
    }

    // =========================================================================
    // grant() tests
    // =========================================================================

    public function testGrantSpecificMemberFromQueue(): void
    {
        $req = ['id' => 'req-1'];
        $this->speechRepo->method('findWaitingForMember')->willReturn($req);
        $this->speechRepo->expects($this->once())
            ->method('finishAllSpeaking');
        $this->speechRepo->expects($this->once())
            ->method('updateStatus')
            ->with('req-1', self::TENANT, 'speaking');
        $this->speechRepo->method('findCurrentSpeaker')->willReturn(null);
        $this->speechRepo->method('listWaiting')->willReturn([]);

        $result = $this->service->grant(self::MEETING, self::MEMBER, self::TENANT);

        $this->assertArrayHasKey('queue', $result);
    }

    public function testGrantDirectSpeakingWhenNotInQueue(): void
    {
        $this->speechRepo->method('findWaitingForMember')->willReturn(null);
        $this->speechRepo->expects($this->once())
            ->method('finishAllSpeaking');
        $this->speechRepo->expects($this->once())
            ->method('insert')
            ->with($this->anything(), self::TENANT, self::MEETING, self::MEMBER, 'speaking');
        $this->speechRepo->method('findCurrentSpeaker')->willReturn(null);
        $this->speechRepo->method('listWaiting')->willReturn([]);

        $this->service->grant(self::MEETING, self::MEMBER, self::TENANT);
    }

    public function testGrantNextWaitingWhenNoMemberSpecified(): void
    {
        $next = ['id' => 'req-next', 'member_id' => 'member-999'];
        $this->speechRepo->method('findNextWaiting')->willReturn($next);
        $this->speechRepo->expects($this->once())
            ->method('finishAllSpeaking');
        $this->speechRepo->expects($this->once())
            ->method('updateStatus')
            ->with('req-next', self::TENANT, 'speaking');
        $this->speechRepo->method('findCurrentSpeaker')->willReturn(null);
        $this->speechRepo->method('listWaiting')->willReturn([]);

        $result = $this->service->grant(self::MEETING, null, self::TENANT);

        $this->assertArrayHasKey('queue', $result);
    }

    public function testGrantNullMemberAndEmptyQueue(): void
    {
        $this->speechRepo->method('findNextWaiting')->willReturn(null);
        $this->speechRepo->expects($this->once())
            ->method('finishAllSpeaking');
        $this->speechRepo->expects($this->never())
            ->method('updateStatus');
        $this->speechRepo->method('findCurrentSpeaker')->willReturn(null);
        $this->speechRepo->method('listWaiting')->willReturn([]);

        $result = $this->service->grant(self::MEETING, null, self::TENANT);

        $this->assertArrayHasKey('queue', $result);
        $this->assertEmpty($result['queue']);
    }

    // =========================================================================
    // endCurrent() tests
    // =========================================================================

    public function testEndCurrentFinishesAllSpeaking(): void
    {
        $this->speechRepo->method('findCurrentSpeaker')->willReturn(null);
        $this->speechRepo->expects($this->once())
            ->method('finishAllSpeaking')
            ->with(self::MEETING, self::TENANT);
        $this->speechRepo->method('listWaiting')->willReturn([]);

        $result = $this->service->endCurrent(self::MEETING, self::TENANT);

        $this->assertArrayHasKey('queue', $result);
    }

    // =========================================================================
    // cancelRequest() tests
    // =========================================================================

    public function testCancelRequestSucceedsForWaitingRequest(): void
    {
        $this->speechRepo->method('findById')
            ->willReturn(['id' => 'req-1', 'status' => 'waiting', 'meeting_id' => self::MEETING, 'member_id' => self::MEMBER]);
        $this->speechRepo->expects($this->once())
            ->method('updateStatus')
            ->with('req-1', self::TENANT, 'cancelled');
        $this->speechRepo->method('findCurrentSpeaker')->willReturn(null);
        $this->speechRepo->method('listWaiting')->willReturn([]);

        $result = $this->service->cancelRequest(self::MEETING, 'req-1', self::TENANT);

        $this->assertArrayHasKey('queue', $result);
    }

    public function testCancelRequestThrowsForNonWaitingStatus(): void
    {
        $this->speechRepo->method('findById')
            ->willReturn(['id' => 'req-1', 'status' => 'speaking', 'meeting_id' => self::MEETING, 'member_id' => self::MEMBER]);

        $this->expectException(RuntimeException::class);
        $this->service->cancelRequest(self::MEETING, 'req-1', self::TENANT);
    }

    public function testCancelRequestThrowsForWrongMeeting(): void
    {
        $this->speechRepo->method('findById')
            ->willReturn(['id' => 'req-1', 'status' => 'waiting', 'meeting_id' => 'other-meeting', 'member_id' => self::MEMBER]);

        $this->expectException(RuntimeException::class);
        $this->service->cancelRequest(self::MEETING, 'req-1', self::TENANT);
    }

    public function testCancelRequestThrowsWhenNotFound(): void
    {
        $this->speechRepo->method('findById')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->service->cancelRequest(self::MEETING, 'req-ghost', self::TENANT);
    }

    // =========================================================================
    // clearHistory() tests
    // =========================================================================

    public function testClearHistoryDeletesFinishedAndReturnsQueue(): void
    {
        $this->speechRepo->method('countFinished')->willReturn(3);
        $this->speechRepo->expects($this->once())
            ->method('deleteFinished')
            ->with(self::MEETING, self::TENANT);
        $this->speechRepo->method('findCurrentSpeaker')->willReturn(null);
        $this->speechRepo->method('listWaiting')->willReturn([]);

        $result = $this->service->clearHistory(self::MEETING, self::TENANT);

        $this->assertArrayHasKey('queue', $result);
    }

    // =========================================================================
    // endCurrent() with current speaker — covers L195-198
    // =========================================================================

    public function testEndCurrentWithActiveSpeakerLogsPayload(): void
    {
        // findCurrentSpeaker returns a current speaker → L194 branch (cur is non-null)
        // memberPayload is built with member_id + member_name (L195-198)
        $speaker = ['id' => 'req-spk', 'member_id' => self::MEMBER, 'status' => 'speaking'];

        $this->speechRepo->method('findCurrentSpeaker')->willReturn($speaker);
        $this->speechRepo->method('finishAllSpeaking');
        $this->speechRepo->method('listWaiting')->willReturn([]);

        $result = $this->service->endCurrent(self::MEETING, self::TENANT);

        $this->assertArrayHasKey('queue', $result);
    }

    // =========================================================================
    // memberLabel() returns null when member not found — covers L39
    // =========================================================================

    public function testEndCurrentWithSpeakerAndMemberNotFoundUsesNullLabel(): void
    {
        // memberRepo returns null → memberLabel returns null (L39)
        // endCurrent still works (payload member_name = null)
        $memberRepoNull = $this->createMock(MemberRepository::class);
        $memberRepoNull->method('findByIdForTenant')->willReturn(null);

        $service = new SpeechService(
            $this->speechRepo,
            $this->meetingRepo,
            $memberRepoNull,
        );

        $speaker = ['id' => 'req-spk2', 'member_id' => self::MEMBER, 'status' => 'speaking'];
        $this->speechRepo->method('findCurrentSpeaker')->willReturn($speaker);
        $this->speechRepo->method('finishAllSpeaking');
        $this->speechRepo->method('listWaiting')->willReturn([]);

        $result = $service->endCurrent(self::MEETING, self::TENANT);

        $this->assertArrayHasKey('queue', $result);
    }
}
