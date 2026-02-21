<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\WebSocket\EventBroadcaster;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Attendance (check-in) management per meeting.
 *
 * MVP Goals:
 * - A member is "present" if an attendance row exists (mode present/remote/proxy)
 * - A member is "absent" if no row exists
 * - effective_power is frozen at check-in time (member's voting_power)
 */
final class AttendancesService {
    /**
     * Indicates if a member is "present" (mode present/remote/proxy, not checked_out) for a meeting.
     *
     * Used for vote eligibility (public).
     */
    public static function isPresent(string $meetingId, string $memberId, string $tenantId): bool {
        $meetingId = trim($meetingId);
        $memberId = trim($memberId);
        if ($meetingId === '' || $memberId === '') {
            return false;
        }

        $repo = new AttendanceRepository();
        return $repo->isPresent($meetingId, $memberId, $tenantId);
    }

    /**
     * Indicates if a member is present "directly" (present/remote only).
     *
     * Recommended for controlling vote eligibility to avoid proxy chains.
     */
    public static function isPresentDirect(string $meetingId, string $memberId, string $tenantId): bool {
        $meetingId = trim($meetingId);
        $memberId = trim($memberId);
        if ($meetingId === '' || $memberId === '') {
            return false;
        }

        $repo = new AttendanceRepository();
        return $repo->isPresentDirect($meetingId, $memberId, $tenantId);
    }

    /**
     * Lists attendance records for a meeting with member info.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function listForMeeting(string $meetingId, string $tenantId): array {
        $meetingId = trim($meetingId);
        if ($meetingId === '') {
            throw new InvalidArgumentException('meeting_id est obligatoire');
        }

        $repo = new AttendanceRepository();
        return $repo->listForMeeting($meetingId, $tenantId);
    }

    /**
     * Summary (count + weight) of meeting for present modes.
     *
     * @return array{present_count:int,present_weight:float}
     */
    public static function summaryForMeeting(string $meetingId, string $tenantId): array {
        $meetingId = trim($meetingId);
        if ($meetingId === '') {
            throw new InvalidArgumentException('meeting_id est obligatoire');
        }

        $repo = new AttendanceRepository();
        return $repo->summaryForMeeting($meetingId, $tenantId);
    }

    /**
     * Upsert attendance.
     * - mode: present|remote|proxy => active upsert (checked_out_at NULL)
     * - mode: absent => deletion (MVP)
     *
     * @return array<string,mixed> attendance row (or {deleted:true})
     */
    public static function upsert(string $meetingId, string $memberId, string $mode, string $tenantId, ?string $notes = null): array {
        $meetingId = trim($meetingId);
        $memberId = trim($memberId);
        $mode = trim($mode);

        if ($meetingId === '' || $memberId === '') {
            throw new InvalidArgumentException('meeting_id et member_id sont obligatoires');
        }

        $allowed = ['present', 'remote', 'proxy', 'excused', 'absent'];
        if (!in_array($mode, $allowed, true)) {
            throw new InvalidArgumentException('mode invalide (present/remote/proxy/excused/absent)');
        }

        // Verify meeting belongs to tenant
        $meetingRepo = new MeetingRepository();
        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            throw new RuntimeException('Séance introuvable');
        }
        if ((string) $meeting['status'] === 'archived') {
            throw new RuntimeException('Séance archivée : présence non modifiable');
        }

        // Load member + voting power
        $member = (new MemberRepository())->findByIdForTenant($memberId, $tenantId);
        if (!$member) {
            throw new RuntimeException('Membre hors tenant');
        }

        $attendanceRepo = new AttendanceRepository();

        if ($mode === 'absent') {
            $attendanceRepo->deleteByMeetingAndMember($meetingId, $memberId, $tenantId);
            self::broadcastAttendanceStats($attendanceRepo, $meetingId, $tenantId);
            return ['deleted' => true, 'meeting_id' => $meetingId, 'member_id' => $memberId];
        }

        $effective = (float) ($member['voting_power'] ?? 1.0);

        $row = $attendanceRepo->upsert($tenantId, $meetingId, $memberId, $mode, $effective, $notes);
        if (!$row) {
            throw new RuntimeException('Erreur upsert présence');
        }

        self::broadcastAttendanceStats($attendanceRepo, $meetingId, $tenantId);
        return $row;
    }

    /**
     * Broadcast attendance stats via WebSocket.
     */
    private static function broadcastAttendanceStats(AttendanceRepository $repo, string $meetingId, string $tenantId): void {
        try {
            $stats = $repo->getStatsByMode($meetingId, $tenantId);
            EventBroadcaster::attendanceUpdated($meetingId, $stats);
        } catch (Throwable $e) {
            // Don't fail if broadcast fails
        }
    }
}
