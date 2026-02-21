<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingStatsRepository;
use AgVote\Repository\MotionRepository;

/**
 * MeetingValidator
 *
 * Purpose: centralize "ready to validate / sign" logic.
 *
 * Rules (specification v1.0):
 * - no open motions
 * - all closed motions have usable results:
 *    - either a consistent manual count (manual_total > 0 and sum == total)
 *    - or at least one e-vote ballot
 * - president (name) is set
 * - (optional) consolidation done: official_source present on all closed motions
 */

final class MeetingValidator {
    private MeetingRepository $meetingRepo;
    private MotionRepository $motionRepo;
    private MeetingStatsRepository $statsRepo;

    public function __construct(
        ?MeetingRepository $meetingRepo = null,
        ?MotionRepository $motionRepo = null,
        ?MeetingStatsRepository $statsRepo = null,
    ) {
        $this->meetingRepo = $meetingRepo ?? new MeetingRepository();
        $this->motionRepo = $motionRepo ?? new MotionRepository();
        $this->statsRepo = $statsRepo ?? new MeetingStatsRepository();
    }

    public function canBeValidated(string $meetingId, string $tenantId): array {
        $meeting = $this->meetingRepo->findByIdForTenant($meetingId, $tenantId);

        if (!$meeting) {
            return [
                'can' => false,
                'reasons' => ['Séance introuvable.'],
                'metrics' => [],
            ];
        }

        $reasons = [];
        $codes = [];

        if (trim((string) ($meeting['president_name'] ?? '')) === '') {
            $reasons[] = 'Président non renseigné.';
            $codes[] = 'missing_president';
        }

        $open = $this->statsRepo->countOpenMotions($meetingId, $tenantId);
        if ($open > 0) {
            $reasons[] = "{$open} motion(s) encore ouverte(s).";
            $codes[] = 'open_motions';
        }

        $bad = $this->motionRepo->countBadClosedMotions($meetingId, $tenantId);
        if ($bad > 0) {
            $reasons[] = "{$bad} motion(s) fermée(s) sans résultat exploitable (manuel cohérent ou e-vote).";
            $codes[] = 'bad_closed_results';
        }

        $closed = $this->statsRepo->countClosedMotions($meetingId, $tenantId);
        $consolidated = $this->motionRepo->countConsolidatedMotions($meetingId, $tenantId);

        // Consolidation is required when there is at least one closed motion.
        $needsConsolidation = $closed > 0;
        $consolidationDone = (!$needsConsolidation) || ($consolidated >= $closed);
        if ($needsConsolidation && !$consolidationDone) {
            $reasons[] = 'Consolidation non effectuée (résultats officiels non persistés).';
            $codes[] = 'consolidation_missing';
        }

        return [
            'can' => count($reasons) === 0,
            'codes' => $codes,
            'reasons' => $reasons,
            'metrics' => [
                'open_motions' => $open,
                'bad_closed_motions' => $bad,
                'closed_motions' => $closed,
                'consolidated_motions' => $consolidated,
                'consolidation_done' => $consolidationDone,
            ],
        ];
    }
}
