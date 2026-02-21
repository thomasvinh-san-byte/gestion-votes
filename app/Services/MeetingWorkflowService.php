<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingStatsRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\UserRepository;
use Throwable;

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
final class MeetingWorkflowService {
    private MeetingRepository $meetingRepo;
    private MotionRepository $motionRepo;
    private AttendanceRepository $attendanceRepo;
    private UserRepository $userRepo;
    private MeetingStatsRepository $statsRepo;

    public function __construct(
        ?MeetingRepository $meetingRepo = null,
        ?MotionRepository $motionRepo = null,
        ?AttendanceRepository $attendanceRepo = null,
        ?UserRepository $userRepo = null,
        ?MeetingStatsRepository $statsRepo = null,
    ) {
        $this->meetingRepo = $meetingRepo ?? new MeetingRepository();
        $this->motionRepo = $motionRepo ?? new MotionRepository();
        $this->attendanceRepo = $attendanceRepo ?? new AttendanceRepository();
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->statsRepo = $statsRepo ?? new MeetingStatsRepository();
    }

    /**
     * Check issues before allowing a transition.
     *
     * @param string $meetingId
     * @param string $tenantId
     * @param string $toStatus Target status
     *
     * @return array ['issues' => [], 'warnings' => [], 'can_proceed' => bool]
     */
    public function issuesBeforeTransition(string $meetingId, string $tenantId, string $toStatus, ?string $fromStatusOverride = null): array {
        $issues = [];
        $warnings = [];

        $meeting = $this->meetingRepo->findByIdForTenant($meetingId, $tenantId);

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
            if (!$this->hasMotions($meetingId, $tenantId)) {
                $issues[] = ['code' => 'no_motions', 'msg' => 'Aucune résolution créée'];
            }
        }

        // scheduled → frozen
        if ($toStatus === 'frozen' && $fromStatus === 'scheduled') {
            if (!$this->hasAttendance($meetingId, $tenantId)) {
                $issues[] = ['code' => 'no_attendance', 'msg' => 'Aucune présence pointée'];
            }
            // President is optional but we warn
            if (!$this->hasPresident($meetingId, $tenantId)) {
                $warnings[] = ['code' => 'no_president', 'msg' => 'Aucun président assigné (optionnel)'];
            }
        }

        // frozen → live
        if ($toStatus === 'live' && $fromStatus === 'frozen') {
            if (!$this->quorumMet($meetingId, $tenantId)) {
                $warnings[] = ['code' => 'quorum_not_met', 'msg' => 'Quorum non atteint (vous pouvez continuer)'];
            }
        }

        // live → paused: block if a vote is actively open
        if ($toStatus === 'paused' && $fromStatus === 'live') {
            $openCount = $this->countOpenMotions($meetingId, $tenantId);
            if ($openCount > 0) {
                $issues[] = ['code' => 'motion_open', 'msg' => "Impossible de mettre en pause : {$openCount} vote(s) en cours. Fermez le vote avant de mettre en pause."];
            }
        }

        // live → closed
        if ($toStatus === 'closed' && ($fromStatus === 'live' || $fromStatus === 'paused')) {
            $openCount = $this->countOpenMotions($meetingId, $tenantId);
            if ($openCount > 0) {
                $issues[] = ['code' => 'motion_open', 'msg' => "{$openCount} résolution(s) encore ouverte(s)"];
            }
        }

        // closed → validated
        if ($toStatus === 'validated' && $fromStatus === 'closed') {
            $closed = $this->statsRepo->countClosedMotions($meetingId, $tenantId);
            $bad = $this->motionRepo->countBadClosedMotions($meetingId, $tenantId);

            if ($bad > 0) {
                $issues[] = ['code' => 'bad_results', 'msg' => "{$bad} résolution(s) sans résultat exploitable"];
            }

            $consolidated = $this->motionRepo->countConsolidatedMotions($meetingId, $tenantId);
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
    public function hasMotions(string $meetingId, string $tenantId = ''): bool {
        if ($tenantId === '') {
            $tenantId = \AgVote\Core\Security\AuthMiddleware::getCurrentTenantId() ?? DEFAULT_TENANT_ID;
        }
        return $this->motionRepo->countForMeeting($meetingId, $tenantId) > 0;
    }

    /**
     * Check if meeting has any attendance records (present or remote).
     */
    public function hasAttendance(string $meetingId, string $tenantId): bool {
        $count = $this->attendanceRepo->countPresentOrRemote($meetingId, $tenantId);
        return $count > 0;
    }

    /**
     * Check if meeting has a president assigned.
     */
    public function hasPresident(string $meetingId, string $tenantId): bool {
        if ($tenantId === '') {
            return false;
        }
        return $this->userRepo->findExistingPresident($tenantId, $meetingId) !== null;
    }

    /**
     * Check if quorum is met.
     */
    public function quorumMet(string $meetingId, string $tenantId): bool {
        try {
            $result = (new QuorumEngine())->computeForMeeting($meetingId, $tenantId);
            return $result['met'] ?? false;
        } catch (Throwable $e) {
            return true; // On error, don't block
        }
    }

    /**
     * Count open motions.
     */
    public function countOpenMotions(string $meetingId, string $tenantId = ''): int {
        return $this->statsRepo->countOpenMotions($meetingId, $tenantId);
    }

    /**
     * Check if all motions are closed.
     */
    public function allMotionsClosed(string $meetingId, string $tenantId = ''): bool {
        return $this->statsRepo->countOpenMotions($meetingId, $tenantId) === 0;
    }

    /**
     * Get a summary of meeting readiness for each possible transition.
     */
    public function getTransitionReadiness(string $meetingId, string $tenantId): array {
        $meeting = $this->meetingRepo->findByIdForTenant($meetingId, $tenantId);

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
            $check = $this->issuesBeforeTransition($meetingId, $tenantId, $toStatus);
            $result['transitions'][$toStatus] = [
                'can_proceed' => $check['can_proceed'],
                'issues' => $check['issues'],
                'warnings' => $check['warnings'],
            ];
        }

        return $result;
    }
}
