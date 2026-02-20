<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\UserRepository;

/**
 * MeetingWorkflowService
 *
 * Inspired by Helios `issues_before_freeze()` pattern.
 * Validates pre-conditions before allowing state transitions.
 *
 * Transition rules:
 * - draft → scheduled: requires motions
 * - scheduled → frozen: requires attendance
 * - frozen → live: quorum check (warning, not blocking)
 * - live → closed: no open motions
 * - closed → validated: all motions closed, consolidated
 */
final class MeetingWorkflowService
{
    /**
     * Check issues before allowing a transition.
     *
     * @param string $meetingId
     * @param string $tenantId
     * @param string $toStatus Target status
     * @return array ['issues' => [], 'warnings' => [], 'can_proceed' => bool]
     */
    public static function issuesBeforeTransition(string $meetingId, string $tenantId, string $toStatus, ?string $fromStatusOverride = null): array
    {
        $issues = [];
        $warnings = [];

        $meetingRepo = new MeetingRepository();
        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);

        if (!$meeting) {
            return [
                'issues' => [['code' => 'meeting_not_found', 'msg' => 'Séance introuvable']],
                'warnings' => [],
                'can_proceed' => false,
            ];
        }

        $fromStatus = $fromStatusOverride ?? $meeting['status'];

        // draft → scheduled
        if ($toStatus === 'scheduled' && $fromStatus === 'draft') {
            if (!self::hasMotions($meetingId)) {
                $issues[] = ['code' => 'no_motions', 'msg' => 'Aucune résolution créée'];
            }
        }

        // scheduled → frozen
        if ($toStatus === 'frozen' && $fromStatus === 'scheduled') {
            if (!self::hasAttendance($meetingId, $tenantId)) {
                $issues[] = ['code' => 'no_attendance', 'msg' => 'Aucune présence pointée'];
            }
            // President is optional but we warn
            if (!self::hasPresident($meetingId, $tenantId)) {
                $warnings[] = ['code' => 'no_president', 'msg' => 'Aucun président assigné (optionnel)'];
            }
        }

        // frozen → live
        if ($toStatus === 'live' && $fromStatus === 'frozen') {
            if (!self::quorumMet($meetingId, $tenantId)) {
                $warnings[] = ['code' => 'quorum_not_met', 'msg' => 'Quorum non atteint (vous pouvez continuer)'];
            }
        }

        // live → paused: block if a vote is actively open
        if ($toStatus === 'paused' && $fromStatus === 'live') {
            $openCount = self::countOpenMotions($meetingId);
            if ($openCount > 0) {
                $issues[] = ['code' => 'motion_open', 'msg' => "Impossible de mettre en pause : $openCount vote(s) en cours. Fermez le vote avant de mettre en pause."];
            }
        }

        // live → closed
        if ($toStatus === 'closed' && ($fromStatus === 'live' || $fromStatus === 'paused')) {
            $openCount = self::countOpenMotions($meetingId);
            if ($openCount > 0) {
                $issues[] = ['code' => 'motion_open', 'msg' => "$openCount résolution(s) encore ouverte(s)"];
            }
        }

        // closed → validated
        if ($toStatus === 'validated' && $fromStatus === 'closed') {
            $motionRepo = new MotionRepository();
            $closed = $meetingRepo->countClosedMotions($meetingId);
            $bad = $motionRepo->countBadClosedMotions($meetingId);

            if ($bad > 0) {
                $issues[] = ['code' => 'bad_results', 'msg' => "$bad résolution(s) sans résultat exploitable"];
            }

            $consolidated = $motionRepo->countConsolidatedMotions($meetingId);
            if ($closed > 0 && $consolidated < $closed) {
                $warnings[] = ['code' => 'not_consolidated', 'msg' => 'Résultats non consolidés (officialisation recommandée)'];
            }
        }

        // Block backward transition from archived — audit trail immutability
        if ($fromStatus === 'archived') {
            $issues[] = ['code' => 'archived_immutable', 'msg' => 'Séance archivée : toute modification est interdite pour garantir l\'intégrité de l\'audit.'];
        }

        return [
            'issues' => $issues,
            'warnings' => $warnings,
            'can_proceed' => count($issues) === 0,
        ];
    }

    /**
     * Check if meeting has any motions.
     */
    public static function hasMotions(string $meetingId): bool
    {
        $motionRepo = new MotionRepository();
        return $motionRepo->countForMeeting($meetingId) > 0;
    }

    /**
     * Check if meeting has any attendance records (present or remote).
     */
    public static function hasAttendance(string $meetingId, string $tenantId): bool
    {
        $attendanceRepo = new AttendanceRepository();
        $count = $attendanceRepo->countPresentOrRemote($meetingId, $tenantId);
        return $count > 0;
    }

    /**
     * Check if meeting has a president assigned.
     */
    public static function hasPresident(string $meetingId, string $tenantId): bool
    {
        if ($tenantId === '') return false;
        $userRepo = new UserRepository();
        return $userRepo->findExistingPresident($tenantId, $meetingId) !== null;
    }

    /**
     * Check if quorum is met.
     */
    public static function quorumMet(string $meetingId, string $tenantId): bool
    {
        try {
            $result = QuorumEngine::computeForMeeting($meetingId, $tenantId);
            return $result['met'] ?? false;
        } catch (\Throwable $e) {
            return true; // On error, don't block
        }
    }

    /**
     * Count open motions.
     */
    public static function countOpenMotions(string $meetingId): int
    {
        $meetingRepo = new MeetingRepository();
        return $meetingRepo->countOpenMotions($meetingId);
    }

    /**
     * Check if all motions are closed.
     */
    public static function allMotionsClosed(string $meetingId): bool
    {
        $meetingRepo = new MeetingRepository();
        return $meetingRepo->countOpenMotions($meetingId) === 0;
    }

    /**
     * Get a summary of meeting readiness for each possible transition.
     */
    public static function getTransitionReadiness(string $meetingId, string $tenantId): array
    {
        $meetingRepo = new MeetingRepository();
        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);

        if (!$meeting) {
            return ['error' => 'meeting_not_found'];
        }

        $currentStatus = $meeting['status'];

        // Possible next states from current state
        $possibleTransitions = [
            'draft' => ['scheduled'],
            'scheduled' => ['frozen', 'draft'],
            'frozen' => ['live', 'scheduled'],
            'live' => ['paused', 'closed'],
            'paused' => ['live', 'closed'],
            'closed' => ['validated'],
            'validated' => ['archived'],
            'archived' => [],
        ];

        $nextStates = $possibleTransitions[$currentStatus] ?? [];
        $result = [
            'current_status' => $currentStatus,
            'transitions' => [],
        ];

        foreach ($nextStates as $toStatus) {
            $check = self::issuesBeforeTransition($meetingId, $tenantId, $toStatus);
            $result['transitions'][$toStatus] = [
                'can_proceed' => $check['can_proceed'],
                'issues' => $check['issues'],
                'warnings' => $check['warnings'],
            ];
        }

        return $result;
    }
}
