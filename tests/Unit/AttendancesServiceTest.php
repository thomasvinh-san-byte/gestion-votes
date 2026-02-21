<?php

declare(strict_types=1);

namespace AgVote\Tests\Unit;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Service\AttendancesService;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for AttendancesService.
 *
 * All repository dependencies are mocked -- no database connection required.
 */
class AttendancesServiceTest extends TestCase {
    private const TENANT_ID = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const MEETING_ID = 'bbbbbbbb-1111-2222-3333-444444444444';
    private const MEMBER_ID = 'cccccccc-1111-2222-3333-444444444444';

    private AttendanceRepository&MockObject $attendanceRepo;
    private MeetingRepository&MockObject $meetingRepo;
    private MemberRepository&MockObject $memberRepo;
    private AttendancesService $service;

    protected function setUp(): void {
        $this->attendanceRepo = $this->createMock(AttendanceRepository::class);
        $this->meetingRepo = $this->createMock(MeetingRepository::class);
        $this->memberRepo = $this->createMock(MemberRepository::class);

        $this->service = new AttendancesService(
            $this->attendanceRepo,
            $this->meetingRepo,
            $this->memberRepo,
        );
    }

    // =========================================================================
    // isPresent()
    // =========================================================================

    public function testIsPresentReturnsTrueWhenMemberIsPresent(): void {
        $this->attendanceRepo->method('isPresent')
            ->with(self::MEETING_ID, self::MEMBER_ID, self::TENANT_ID)
            ->willReturn(true);

        $this->assertTrue($this->service->isPresent(self::MEETING_ID, self::MEMBER_ID, self::TENANT_ID));
    }

    public function testIsPresentReturnsFalseWhenMemberIsAbsent(): void {
        $this->attendanceRepo->method('isPresent')
            ->willReturn(false);

        $this->assertFalse($this->service->isPresent(self::MEETING_ID, self::MEMBER_ID, self::TENANT_ID));
    }

    public function testIsPresentReturnsFalseOnEmptyMeetingId(): void {
        $this->attendanceRepo->expects($this->never())->method('isPresent');

        $this->assertFalse($this->service->isPresent('', self::MEMBER_ID, self::TENANT_ID));
    }

    public function testIsPresentReturnsFalseOnEmptyMemberId(): void {
        $this->attendanceRepo->expects($this->never())->method('isPresent');

        $this->assertFalse($this->service->isPresent(self::MEETING_ID, '', self::TENANT_ID));
    }

    public function testIsPresentTrimsWhitespace(): void {
        $this->attendanceRepo->expects($this->never())->method('isPresent');

        // Whitespace-only strings should be treated as empty
        $this->assertFalse($this->service->isPresent('   ', self::MEMBER_ID, self::TENANT_ID));
    }

    // =========================================================================
    // isPresentDirect()
    // =========================================================================

    public function testIsPresentDirectReturnsTrueWhenDirectlyPresent(): void {
        $this->attendanceRepo->method('isPresentDirect')
            ->with(self::MEETING_ID, self::MEMBER_ID, self::TENANT_ID)
            ->willReturn(true);

        $this->assertTrue($this->service->isPresentDirect(self::MEETING_ID, self::MEMBER_ID, self::TENANT_ID));
    }

    public function testIsPresentDirectReturnsFalseWhenNotDirectlyPresent(): void {
        $this->attendanceRepo->method('isPresentDirect')
            ->willReturn(false);

        $this->assertFalse($this->service->isPresentDirect(self::MEETING_ID, self::MEMBER_ID, self::TENANT_ID));
    }

    public function testIsPresentDirectReturnsFalseOnEmptyMeetingId(): void {
        $this->attendanceRepo->expects($this->never())->method('isPresentDirect');

        $this->assertFalse($this->service->isPresentDirect('', self::MEMBER_ID, self::TENANT_ID));
    }

    public function testIsPresentDirectReturnsFalseOnEmptyMemberId(): void {
        $this->attendanceRepo->expects($this->never())->method('isPresentDirect');

        $this->assertFalse($this->service->isPresentDirect(self::MEETING_ID, '', self::TENANT_ID));
    }

    // =========================================================================
    // listForMeeting()
    // =========================================================================

    public function testListForMeetingReturnsArrayFromRepository(): void {
        $expectedRows = [
            ['member_id' => self::MEMBER_ID, 'mode' => 'present'],
        ];

        $this->attendanceRepo->method('listForMeeting')
            ->with(self::MEETING_ID, self::TENANT_ID)
            ->willReturn($expectedRows);

        $result = $this->service->listForMeeting(self::MEETING_ID, self::TENANT_ID);
        $this->assertSame($expectedRows, $result);
    }

    public function testListForMeetingThrowsOnEmptyMeetingId(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('meeting_id est obligatoire');
        $this->service->listForMeeting('', self::TENANT_ID);
    }

    public function testListForMeetingThrowsOnWhitespaceOnlyMeetingId(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->service->listForMeeting('   ', self::TENANT_ID);
    }

    // =========================================================================
    // summaryForMeeting()
    // =========================================================================

    public function testSummaryForMeetingReturnsArrayFromRepository(): void {
        $expectedSummary = ['present_count' => 5, 'present_weight' => 12.5];

        $this->attendanceRepo->method('summaryForMeeting')
            ->with(self::MEETING_ID, self::TENANT_ID)
            ->willReturn($expectedSummary);

        $result = $this->service->summaryForMeeting(self::MEETING_ID, self::TENANT_ID);
        $this->assertSame($expectedSummary, $result);
    }

    public function testSummaryForMeetingThrowsOnEmptyMeetingId(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('meeting_id est obligatoire');
        $this->service->summaryForMeeting('', self::TENANT_ID);
    }

    // =========================================================================
    // upsert() -- mode present (creates attendance)
    // =========================================================================

    public function testUpsertPresentCreatesAttendance(): void {
        $meeting = ['id' => self::MEETING_ID, 'status' => 'live', 'tenant_id' => self::TENANT_ID];
        $member = ['id' => self::MEMBER_ID, 'voting_power' => 2.5];
        $expectedRow = [
            'id' => 'attendance-id',
            'meeting_id' => self::MEETING_ID,
            'member_id' => self::MEMBER_ID,
            'mode' => 'present',
            'effective_power' => 2.5,
        ];

        $this->meetingRepo->method('findByIdForTenant')
            ->with(self::MEETING_ID, self::TENANT_ID)
            ->willReturn($meeting);

        $this->memberRepo->method('findByIdForTenant')
            ->with(self::MEMBER_ID, self::TENANT_ID)
            ->willReturn($member);

        $this->attendanceRepo->method('upsert')
            ->with(self::TENANT_ID, self::MEETING_ID, self::MEMBER_ID, 'present', 2.5, null)
            ->willReturn($expectedRow);

        // getStatsByMode may be called for broadcast; allow but don't require
        $this->attendanceRepo->method('getStatsByMode')->willReturn([]);

        $result = $this->service->upsert(self::MEETING_ID, self::MEMBER_ID, 'present', self::TENANT_ID);

        $this->assertSame($expectedRow, $result);
    }

    public function testUpsertRemoteModeCreatesAttendance(): void {
        $meeting = ['id' => self::MEETING_ID, 'status' => 'live'];
        $member = ['id' => self::MEMBER_ID, 'voting_power' => 1.0];
        $expectedRow = [
            'id' => 'attendance-id',
            'meeting_id' => self::MEETING_ID,
            'member_id' => self::MEMBER_ID,
            'mode' => 'remote',
        ];

        $this->meetingRepo->method('findByIdForTenant')->willReturn($meeting);
        $this->memberRepo->method('findByIdForTenant')->willReturn($member);
        $this->attendanceRepo->method('upsert')->willReturn($expectedRow);
        $this->attendanceRepo->method('getStatsByMode')->willReturn([]);

        $result = $this->service->upsert(self::MEETING_ID, self::MEMBER_ID, 'remote', self::TENANT_ID);

        $this->assertSame($expectedRow, $result);
    }

    public function testUpsertProxyModeCreatesAttendance(): void {
        $meeting = ['id' => self::MEETING_ID, 'status' => 'live'];
        $member = ['id' => self::MEMBER_ID, 'voting_power' => 1.0];
        $expectedRow = ['mode' => 'proxy'];

        $this->meetingRepo->method('findByIdForTenant')->willReturn($meeting);
        $this->memberRepo->method('findByIdForTenant')->willReturn($member);
        $this->attendanceRepo->method('upsert')->willReturn($expectedRow);
        $this->attendanceRepo->method('getStatsByMode')->willReturn([]);

        $result = $this->service->upsert(self::MEETING_ID, self::MEMBER_ID, 'proxy', self::TENANT_ID);

        $this->assertSame($expectedRow, $result);
    }

    public function testUpsertExcusedModeCreatesAttendance(): void {
        $meeting = ['id' => self::MEETING_ID, 'status' => 'live'];
        $member = ['id' => self::MEMBER_ID, 'voting_power' => 1.0];
        $expectedRow = ['mode' => 'excused'];

        $this->meetingRepo->method('findByIdForTenant')->willReturn($meeting);
        $this->memberRepo->method('findByIdForTenant')->willReturn($member);
        $this->attendanceRepo->method('upsert')->willReturn($expectedRow);
        $this->attendanceRepo->method('getStatsByMode')->willReturn([]);

        $result = $this->service->upsert(self::MEETING_ID, self::MEMBER_ID, 'excused', self::TENANT_ID);

        $this->assertSame($expectedRow, $result);
    }

    // =========================================================================
    // upsert() -- mode absent (deletes attendance)
    // =========================================================================

    public function testUpsertAbsentDeletesAttendance(): void {
        $meeting = ['id' => self::MEETING_ID, 'status' => 'live'];
        $member = ['id' => self::MEMBER_ID, 'voting_power' => 1.0];

        $this->meetingRepo->method('findByIdForTenant')->willReturn($meeting);
        $this->memberRepo->method('findByIdForTenant')->willReturn($member);

        $this->attendanceRepo->expects($this->once())
            ->method('deleteByMeetingAndMember')
            ->with(self::MEETING_ID, self::MEMBER_ID, self::TENANT_ID);

        $this->attendanceRepo->method('getStatsByMode')->willReturn([]);

        $result = $this->service->upsert(self::MEETING_ID, self::MEMBER_ID, 'absent', self::TENANT_ID);

        $this->assertTrue($result['deleted']);
        $this->assertSame(self::MEETING_ID, $result['meeting_id']);
        $this->assertSame(self::MEMBER_ID, $result['member_id']);
    }

    // =========================================================================
    // upsert() -- validation errors
    // =========================================================================

    public function testUpsertThrowsOnInvalidMode(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mode invalide');
        $this->service->upsert(self::MEETING_ID, self::MEMBER_ID, 'invalid_mode', self::TENANT_ID);
    }

    public function testUpsertThrowsOnEmptyMeetingId(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('meeting_id et member_id sont obligatoires');
        $this->service->upsert('', self::MEMBER_ID, 'present', self::TENANT_ID);
    }

    public function testUpsertThrowsOnEmptyMemberId(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('meeting_id et member_id sont obligatoires');
        $this->service->upsert(self::MEETING_ID, '', 'present', self::TENANT_ID);
    }

    public function testUpsertThrowsWhenMeetingNotFound(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('introuvable');
        $this->service->upsert(self::MEETING_ID, self::MEMBER_ID, 'present', self::TENANT_ID);
    }

    public function testUpsertThrowsWhenMeetingIsArchived(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn(['id' => self::MEETING_ID, 'status' => 'archived']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('archiv');
        $this->service->upsert(self::MEETING_ID, self::MEMBER_ID, 'present', self::TENANT_ID);
    }

    public function testUpsertThrowsWhenMemberNotFoundInTenant(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn(['id' => self::MEETING_ID, 'status' => 'live']);

        $this->memberRepo->method('findByIdForTenant')
            ->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Membre hors tenant');
        $this->service->upsert(self::MEETING_ID, self::MEMBER_ID, 'present', self::TENANT_ID);
    }

    public function testUpsertThrowsWhenUpsertReturnsNull(): void {
        $meeting = ['id' => self::MEETING_ID, 'status' => 'live'];
        $member = ['id' => self::MEMBER_ID, 'voting_power' => 1.0];

        $this->meetingRepo->method('findByIdForTenant')->willReturn($meeting);
        $this->memberRepo->method('findByIdForTenant')->willReturn($member);
        $this->attendanceRepo->method('upsert')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Erreur upsert');
        $this->service->upsert(self::MEETING_ID, self::MEMBER_ID, 'present', self::TENANT_ID);
    }

    // =========================================================================
    // upsert() -- voting_power edge cases
    // =========================================================================

    public function testUpsertUsesDefaultVotingPowerWhenNullInMember(): void {
        $meeting = ['id' => self::MEETING_ID, 'status' => 'live'];
        $member = ['id' => self::MEMBER_ID]; // no voting_power key

        $this->meetingRepo->method('findByIdForTenant')->willReturn($meeting);
        $this->memberRepo->method('findByIdForTenant')->willReturn($member);

        // effective_power should be 1.0 (default)
        $this->attendanceRepo->expects($this->once())
            ->method('upsert')
            ->with(self::TENANT_ID, self::MEETING_ID, self::MEMBER_ID, 'present', 1.0, null)
            ->willReturn(['id' => 'some-id', 'mode' => 'present']);

        $this->attendanceRepo->method('getStatsByMode')->willReturn([]);

        $this->service->upsert(self::MEETING_ID, self::MEMBER_ID, 'present', self::TENANT_ID);
    }

    public function testUpsertPassesNotesToRepository(): void {
        $meeting = ['id' => self::MEETING_ID, 'status' => 'live'];
        $member = ['id' => self::MEMBER_ID, 'voting_power' => 1.0];

        $this->meetingRepo->method('findByIdForTenant')->willReturn($meeting);
        $this->memberRepo->method('findByIdForTenant')->willReturn($member);

        $this->attendanceRepo->expects($this->once())
            ->method('upsert')
            ->with(self::TENANT_ID, self::MEETING_ID, self::MEMBER_ID, 'present', 1.0, 'test note')
            ->willReturn(['id' => 'some-id']);

        $this->attendanceRepo->method('getStatsByMode')->willReturn([]);

        $this->service->upsert(self::MEETING_ID, self::MEMBER_ID, 'present', self::TENANT_ID, 'test note');
    }
}
