<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Repository\AgendaRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\ManualActionRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use RuntimeException;
use Throwable;

/**
 * Business logic for motion lifecycle operations.
 *
 * Extracted from MotionsController to centralise CRUD + state-machine logic
 * (create, open, close, tally, degraded tally, override decision).
 * The controller remains a thin HTTP orchestration layer.
 */
final class MotionsService {
    private ?MotionRepository $_motionRepo;
    private ?MeetingRepository $_meetingRepo;
    private ?PolicyRepository $_policyRepo;
    private ?AgendaRepository $_agendaRepo;
    private ?ManualActionRepository $_manualActionRepo;
    private ?AttendanceRepository $_attendanceRepo;
    private ?OfficialResultsService $_resultsService;
    private ?VoteTokenService $_voteTokenService;
    private ?NotificationsService $_notificationsService;

    public function __construct(
        ?MotionRepository $motionRepo = null,
        ?MeetingRepository $meetingRepo = null,
        ?PolicyRepository $policyRepo = null,
        ?AgendaRepository $agendaRepo = null,
        ?ManualActionRepository $manualActionRepo = null,
        ?AttendanceRepository $attendanceRepo = null,
        ?OfficialResultsService $resultsService = null,
        ?VoteTokenService $voteTokenService = null,
        ?NotificationsService $notificationsService = null,
    ) {
        $this->_motionRepo = $motionRepo;
        $this->_meetingRepo = $meetingRepo;
        $this->_policyRepo = $policyRepo;
        $this->_agendaRepo = $agendaRepo;
        $this->_manualActionRepo = $manualActionRepo;
        $this->_attendanceRepo = $attendanceRepo;
        $this->_resultsService = $resultsService;
        $this->_voteTokenService = $voteTokenService;
        $this->_notificationsService = $notificationsService;
    }

    private function motionRepo(): MotionRepository {
        return $this->_motionRepo ??= RepositoryFactory::getInstance()->motion();
    }

    private function meetingRepo(): MeetingRepository {
        return $this->_meetingRepo ??= RepositoryFactory::getInstance()->meeting();
    }

    private function policyRepo(): PolicyRepository {
        return $this->_policyRepo ??= RepositoryFactory::getInstance()->policy();
    }

    private function agendaRepo(): AgendaRepository {
        return $this->_agendaRepo ??= RepositoryFactory::getInstance()->agenda();
    }

    private function manualActionRepo(): ManualActionRepository {
        return $this->_manualActionRepo ??= RepositoryFactory::getInstance()->manualAction();
    }

    private function attendanceRepo(): AttendanceRepository {
        return $this->_attendanceRepo ??= RepositoryFactory::getInstance()->attendance();
    }

    private function resultsService(): OfficialResultsService {
        return $this->_resultsService ??= new OfficialResultsService();
    }

    private function voteTokenService(): VoteTokenService {
        return $this->_voteTokenService ??= new VoteTokenService();
    }

    private function notificationsService(): NotificationsService {
        return $this->_notificationsService ??= new NotificationsService();
    }

    /**
     * Create or update a motion within an agenda.
     *
     * @param array<string,mixed> $input   Validated input (agenda_id, motion_id?, title, …)
     * @param string              $tenantId
     *
     * @return array{motion_id: string, created: bool}
     */
    public function createOrUpdate(array $input, string $tenantId): array {
        $agendaId = (string) $input['agenda_id'];
        $motionId = (string) ($input['motion_id'] ?? '');
        $title = (string) $input['title'];
        $description = (string) ($input['description'] ?? '');
        $secret = (bool) ($input['secret'] ?? false);
        $votePolicyId = (string) ($input['vote_policy_id'] ?? '');
        $quorumPolicyId = (string) ($input['quorum_policy_id'] ?? '');

        $agenda = $this->motionRepo()->findAgendaWithMeeting($agendaId, $tenantId);
        if (!$agenda) {
            throw new RuntimeException('agenda_not_found');
        }

        if ($votePolicyId !== '' && !$this->policyRepo()->votePolicyExists($votePolicyId, $tenantId)) {
            throw new RuntimeException('vote_policy_not_found');
        }
        if ($quorumPolicyId !== '' && !$this->policyRepo()->quorumPolicyExists($quorumPolicyId, $tenantId)) {
            throw new RuntimeException('quorum_policy_not_found');
        }

        if ($motionId === '') {
            $newId = api_transaction(function () use ($agendaId, $tenantId, $agenda, $title, $description, $secret, $votePolicyId, $quorumPolicyId) {
                $id = $this->motionRepo()->generateUuid();
                $this->motionRepo()->create(
                    $id,
                    $tenantId,
                    $agenda['meeting_id'],
                    $agendaId,
                    $title,
                    $description,
                    $secret,
                    $votePolicyId ?: null,
                    $quorumPolicyId ?: null,
                );
                audit_log('motion_created', 'motion', (string) $id, [
                    'meeting_id' => (string) $agenda['meeting_id'],
                    'agenda_id' => $agendaId,
                    'title' => $title,
                    'secret' => $secret,
                    'vote_policy_id' => $votePolicyId ?: null,
                    'quorum_policy_id' => $quorumPolicyId ?: null,
                ]);
                return (string) $id;
            });
            return ['motion_id' => $newId, 'created' => true];
        }

        api_transaction(function () use ($motionId, $tenantId, $agendaId, $title, $description, $secret, $votePolicyId, $quorumPolicyId) {
            $motion = $this->motionRepo()->findByIdForTenant($motionId, $tenantId);
            if (!$motion) {
                throw new RuntimeException('motion_not_found');
            }
            if ((string) $motion['agenda_id'] !== $agendaId) {
                throw new RuntimeException('agenda_mismatch');
            }
            if (!empty($motion['opened_at']) && empty($motion['closed_at'])) {
                throw new RuntimeException('motion_active_locked');
            }
            if (!empty($motion['closed_at'])) {
                throw new RuntimeException('motion_closed_locked');
            }

            $this->motionRepo()->update(
                $motionId,
                $tenantId,
                $title,
                $description,
                $secret,
                $votePolicyId ?: null,
                $quorumPolicyId ?: null,
            );
            audit_log('motion_updated', 'motion', (string) $motionId, [
                'agenda_id' => $agendaId,
                'title' => $title,
                'secret' => $secret,
                'vote_policy_id' => $votePolicyId ?: null,
                'quorum_policy_id' => $quorumPolicyId ?: null,
            ]);
        });

        return ['motion_id' => $motionId, 'created' => false];
    }

    /**
     * Create a motion under the first available agenda (or auto-create one).
     *
     * @param array<string,mixed> $input   title, description, secret, meeting_id
     * @param string              $tenantId
     *
     * @return array{motion_id: string, agenda_id: string}
     */
    public function createSimple(array $input, string $tenantId): array {
        $meetingId = (string) $input['meeting_id'];
        $title = (string) $input['title'];
        $description = (string) ($input['description'] ?? '');
        $secret = (bool) ($input['secret'] ?? false);

        return api_transaction(function () use ($meetingId, $tenantId, $title, $description, $secret) {
            $agendas = $this->agendaRepo()->listForMeetingCompact($meetingId, $tenantId);

            $agendaId = null;
            if (empty($agendas)) {
                $agendaId = $this->agendaRepo()->generateUuid();
                $this->agendaRepo()->create($agendaId, $tenantId, $meetingId, 1, 'Ordre du jour');
                audit_log('agenda_created', 'agenda', $agendaId, [
                    'meeting_id' => $meetingId,
                    'title' => 'Ordre du jour',
                    'auto_created' => true,
                ]);
            } else {
                $agendaId = $agendas[0]['agenda_id'];
            }

            $motionId = $this->motionRepo()->generateUuid();
            $this->motionRepo()->create($motionId, $tenantId, $meetingId, $agendaId, $title, $description, $secret, null, null);

            audit_log('motion_created', 'motion', $motionId, [
                'meeting_id' => $meetingId,
                'agenda_id' => $agendaId,
                'title' => $title,
                'secret' => $secret,
                'created_via' => 'simple_endpoint',
            ]);

            return ['motion_id' => $motionId, 'agenda_id' => $agendaId];
        });
    }

    /**
     * Delete a motion (guards: not open, not closed). Includes audit_log.
     */
    public function delete(string $motionId, string $tenantId): void {
        $motion = $this->motionRepo()->findByIdForTenant($motionId, $tenantId);
        if (!$motion) {
            throw new RuntimeException('motion_not_found');
        }

        if (!empty($motion['opened_at']) && empty($motion['closed_at'])) {
            throw new RuntimeException('motion_open_locked');
        }
        if (!empty($motion['closed_at'])) {
            throw new RuntimeException('motion_closed_locked');
        }

        $this->motionRepo()->delete($motionId, $tenantId);

        audit_log('motion_deleted', 'motion', (string) $motionId, [
            'meeting_id' => (string) $motion['meeting_id'],
            'agenda_id' => (string) $motion['agenda_id'],
        ]);
    }

    /**
     * Open a motion for voting. Resolves vote/quorum policies via cascade.
     *
     * @return array{meetingId: string, votePolicyId: ?string, quorumPolicyId: ?string, title: string, secret: bool}
     */
    public function open(string $motionId, string $tenantId): array {
        return api_transaction(function () use ($motionId, $tenantId) {
            $motion = $this->motionRepo()->findByIdForTenantForUpdate($motionId, $tenantId);
            if (!$motion) {
                throw new RuntimeException('motion_not_found');
            }

            $meetingId = (string) $motion['meeting_id'];

            if (!empty($motion['opened_at'])) {
                throw new RuntimeException('motion_already_opened');
            }

            // Policy resolution: motion → meeting → tenant default
            $votePolicyId = $motion['vote_policy_id'] ?? null;
            if (!$votePolicyId) {
                $meeting = $this->meetingRepo()->findByIdForTenant($meetingId, $tenantId);
                $votePolicyId = $meeting['vote_policy_id'] ?? null;
            }
            if (!$votePolicyId) {
                $defaults = $this->policyRepo()->listVotePolicies($tenantId);
                if (!empty($defaults)) {
                    $votePolicyId = $defaults[0]['id'];
                }
            }

            $quorumPolicyId = $motion['quorum_policy_id'] ?? null;
            if (!$quorumPolicyId) {
                $meeting ??= $this->meetingRepo()->findByIdForTenant($meetingId, $tenantId);
                $quorumPolicyId = $meeting['quorum_policy_id'] ?? null;
            }
            if (!$quorumPolicyId) {
                $defaults = $this->policyRepo()->listQuorumPolicies($tenantId);
                if (!empty($defaults)) {
                    $quorumPolicyId = $defaults[0]['id'];
                }
            }

            $this->motionRepo()->markOpened($motionId, $tenantId);
            $this->meetingRepo()->updateCurrentMotion($meetingId, $tenantId, $motionId);

            return [
                'meetingId' => $meetingId,
                'votePolicyId' => $votePolicyId,
                'quorumPolicyId' => $quorumPolicyId,
                'title' => (string) $motion['title'],
                'secret' => (bool) $motion['secret'],
            ];
        });
    }

    /**
     * Close a motion, compute official tallies, persist decision.
     *
     * Also attempts token revocation (non-critical, caught internally).
     *
     * @return array{motion: array<string,mixed>, o: array<string,mixed>}
     */
    public function close(string $motionId, string $tenantId): array {
        $txResult = api_transaction(function () use ($motionId, $tenantId) {
            $motion = $this->motionRepo()->findByIdForTenantForUpdate($motionId, $tenantId);
            if (!$motion) {
                throw new RuntimeException('motion_not_found');
            }

            if (empty($motion['opened_at'])) {
                throw new RuntimeException('motion_not_open');
            }
            if (!empty($motion['closed_at'])) {
                throw new RuntimeException('motion_already_closed');
            }

            $this->motionRepo()->markClosed($motionId, $tenantId);

            $o = $this->resultsService()->computeOfficialTallies($motionId, $tenantId);
            $this->motionRepo()->updateOfficialResults(
                $motionId,
                $o['source'],
                $o['for'],
                $o['against'],
                $o['abstain'],
                $o['total'],
                $o['decision'],
                $o['reason'],
                $tenantId,
            );

            return ['motion' => $motion, 'o' => $o];
        });

        try {
            $this->voteTokenService()->revokeForMotion($motionId, $tenantId);
        } catch (Throwable) {
            // Non-critical: token revocation failure is logged but doesn't block response
        }

        $eligibleCount = 0;
        try {
            $eligibleCount = $this->attendanceRepo()->countByModes(
                (string) $txResult['motion']['meeting_id'],
                $tenantId,
                ['present', 'remote'],
            );
        } catch (Throwable) {
            // Non-critical
        }

        return array_merge($txResult, ['eligible_count' => $eligibleCount]);
    }

    /**
     * Persist a manual (degraded-mode) tally and emit an operator notification.
     *
     * @param array<string,mixed> $input  motion_id, manual_total, manual_for, …, justification
     * @param string              $tenantId
     *
     * @return array{meeting_id: string, motion_id: string, motion_title: string,
     *               manual_total: int, manual_for: int, manual_against: int, manual_abstain: int}
     */
    public function degradedTally(array $input, string $tenantId): array {
        $motionId = (string) $input['motion_id'];
        $total = (int) ($input['manual_total'] ?? 0);
        $for = (int) ($input['manual_for'] ?? 0);
        $against = (int) ($input['manual_against'] ?? 0);
        $abstain = (int) ($input['manual_abstain'] ?? 0);
        $justification = trim((string) $input['justification']);

        // Arithmetic validation (can be called from service directly)
        if ($total <= 0) {
            throw new RuntimeException('invalid_total');
        }
        if ($for < 0 || $against < 0 || $abstain < 0) {
            throw new RuntimeException('invalid_numbers');
        }
        if ($for > $total || $against > $total || $abstain > $total) {
            throw new RuntimeException('vote_exceeds_total');
        }
        if ($for + $against + $abstain !== $total) {
            throw new RuntimeException('inconsistent_tally');
        }

        // F03: justification gate — operators MUST document why the manual tally
        // is being set (forensic value when reconciling vs ballots later).
        if (mb_strlen($justification) < 20) {
            throw new RuntimeException('justification_too_short');
        }

        $row = $this->motionRepo()->findWithMeetingTenant($motionId, $tenantId);
        if (!$row) {
            throw new RuntimeException('motion_not_found');
        }

        // F03: idempotence gate — a manual tally that already exists must NOT
        // be silently overwritten. Operators have to explicitly cancel the
        // previous tally first (separate flow). This prevents an attacker
        // (or a confused operator) from rewriting figures repeatedly with
        // only generic audit entries to clean up afterwards.
        $beforeTotal = $row['manual_total'] !== null ? (int) $row['manual_total'] : 0;
        if ($beforeTotal > 0) {
            throw new RuntimeException('manual_tally_already_set');
        }

        $beforeTally = [
            'total'   => $row['manual_total'] !== null ? (int) $row['manual_total'] : null,
            'for'     => $row['manual_for'] !== null ? (int) $row['manual_for'] : null,
            'against' => $row['manual_against'] !== null ? (int) $row['manual_against'] : null,
            'abstain' => $row['manual_abstain'] !== null ? (int) $row['manual_abstain'] : null,
        ];

        $meetingId = (string) $row['meeting_id'];

        api_transaction(function () use ($motionId, $total, $for, $against, $abstain, $tenantId, $meetingId, $justification) {
            $this->motionRepo()->updateManualTally($motionId, $total, $for, $against, $abstain, $tenantId);

            $this->manualActionRepo()->createManualTally(
                $tenantId,
                $meetingId,
                $motionId,
                json_encode(['total' => $total, 'for' => $for, 'against' => $against, 'abstain' => $abstain], JSON_UNESCAPED_UNICODE),
                $justification,
            );
        });

        audit_log('manual_tally_set', 'motion', $motionId, [
            'meeting_id' => $meetingId,
            'before' => $beforeTally,
            'after' => ['total' => $total, 'for' => $for, 'against' => $against, 'abstain' => $abstain],
            'justification' => $justification,
        ]);

        $this->notificationsService()->emit(
            $meetingId,
            'warn',
            'degraded_manual_tally',
            'Mode dégradé: comptage manuel saisi pour "' . ((string) $row['motion_title']) . '".',
            ['operator', 'trust'],
            ['motion_id' => $motionId],
            $tenantId,
        );

        return [
            'meeting_id' => $meetingId,
            'motion_id' => $motionId,
            'motion_title' => (string) $row['motion_title'],
            'manual_total' => $total,
            'manual_for' => $for,
            'manual_against' => $against,
            'manual_abstain' => $abstain,
        ];
    }

    /**
     * Reorder motions for a meeting.
     *
     * @param string[] $motionIds  Ordered list of motion UUIDs
     */
    public function reorder(string $meetingId, string $tenantId, array $motionIds): void {
        $meeting = $this->meetingRepo()->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            throw new RuntimeException('meeting_not_found');
        }
        $status = (string) ($meeting['status'] ?? '');
        if (in_array($status, ['live', 'closed', 'validated', 'archived'], true)) {
            throw new RuntimeException('meeting_locked');
        }
        $this->motionRepo()->reorderAll($meetingId, $tenantId, $motionIds);
        audit_log('motions_reordered', 'meeting', $meetingId, ['motion_ids' => $motionIds]);
    }

    /**
     * Build the enriched motion list for a meeting (stats + policy names merged).
     *
     * @return array{meeting_id: string, current_motion_id: ?string, items: array<int,mixed>}
     */
    public function listForMeeting(string $meetingId, string $tenantId): array {
        $row = $this->motionRepo()->listForMeetingJson($meetingId, $tenantId);
        $motions = [];
        if ($row && isset($row['motions'])) {
            if (is_string($row['motions'])) {
                $decoded = json_decode($row['motions'], true);
                if (is_array($decoded)) {
                    $motions = $decoded;
                }
            } elseif (is_array($row['motions'])) {
                $motions = $row['motions'];
            }
        }

        $stats = $this->motionRepo()->listStatsForMeeting($meetingId, $tenantId);
        $statsMap = [];
        foreach ($stats as $s) {
            $statsMap[(string) $s['motion_id']] = $s;
        }

        $policyNameCache = [];

        foreach ($motions as &$m) {
            $m['id'] = $m['motion_id'] ?? $m['id'] ?? null;
            $m['title'] = $m['motion_title'] ?? $m['title'] ?? '';
            $m['description'] = $m['motion_description'] ?? $m['description'] ?? '';
            $m['result'] = $m['decision'] ?? null;

            $mid = (string) $m['id'];
            if (isset($statsMap[$mid])) {
                $m['votes_for'] = (int) $statsMap[$mid]['ballots_for'];
                $m['votes_against'] = (int) $statsMap[$mid]['ballots_against'];
                $m['votes_abstain'] = (int) $statsMap[$mid]['ballots_abstain'];
                $m['votes_nsp'] = (int) $statsMap[$mid]['ballots_nsp'];
                $m['votes_count'] = (int) $statsMap[$mid]['ballots_total'];
            } else {
                $m['votes_for'] = 0;
                $m['votes_against'] = 0;
                $m['votes_abstain'] = 0;
                $m['votes_nsp'] = 0;
                $m['votes_count'] = 0;
            }

            $vpId = (string) ($m['vote_policy_id'] ?? '');
            if ($vpId !== '' && !isset($policyNameCache['v_' . $vpId])) {
                $policyNameCache['v_' . $vpId] = $this->policyRepo()->findVotePolicyName($tenantId, $vpId);
            }
            $m['vote_policy_name'] = $policyNameCache['v_' . $vpId] ?? null;

            $qpId = (string) ($m['quorum_policy_id'] ?? '');
            if ($qpId !== '' && !isset($policyNameCache['q_' . $qpId])) {
                $policyNameCache['q_' . $qpId] = $this->policyRepo()->findQuorumPolicyName($tenantId, $qpId);
            }
            $m['quorum_policy_name'] = $policyNameCache['q_' . $qpId] ?? null;
        }
        unset($m);

        $meeting = $this->meetingRepo()->findByIdForTenant($meetingId, $tenantId);
        $currentMotionId = $meeting['current_motion_id'] ?? null;

        return [
            'meeting_id' => $meetingId,
            'current_motion_id' => $currentMotionId,
            'items' => $motions,
        ];
    }

    /**
     * Compute per-value tally counts for a motion.
     *
     * @return array{motion_id: string, closed: bool, tally: array<string,array{count:int,weight:float}>}
     */
    public function tally(string $motionId, string $tenantId): array {
        $motion = $this->motionRepo()->findByIdForTenant($motionId, $tenantId);
        if (!$motion) {
            throw new RuntimeException('motion_not_found');
        }

        $rows = $this->motionRepo()->getTally($motionId, $tenantId);
        $result = [
            'for' => ['count' => 0, 'weight' => 0],
            'against' => ['count' => 0, 'weight' => 0],
            'abstain' => ['count' => 0, 'weight' => 0],
            'nsp' => ['count' => 0, 'weight' => 0],
        ];
        foreach ($rows as $r) {
            $v = $r['value'];
            if (!isset($result[$v])) {
                continue;
            }
            $result[$v]['count'] = (int) $r['c'];
            $result[$v]['weight'] = (float) $r['w'];
        }

        return [
            'motion_id' => $motionId,
            'closed' => $motion['closed_at'] !== null,
            'tally' => $result,
        ];
    }

    /**
     * Override the official decision on a closed motion.
     *
     * @return array{meeting_id: string, motion_title: string}  For the caller to use in broadcast.
     */
    public function overrideDecision(string $motionId, string $decision, string $justification, string $tenantId): array {
        $row = $this->motionRepo()->findWithMeetingTenant($motionId, $tenantId);
        if (!$row) {
            throw new RuntimeException('motion_not_found');
        }

        $meetingId = (string) $row['meeting_id'];

        if (empty($row['closed_at'])) {
            throw new RuntimeException('motion_not_closed');
        }

        api_transaction(function () use ($motionId, $decision, $justification, $tenantId) {
            $this->motionRepo()->overrideDecision($motionId, $decision, $justification, $tenantId);
        });

        audit_log('decision_override', 'motion', $motionId, [
            'decision' => $decision,
            'justification' => $justification,
            'meeting_id' => $meetingId,
        ]);

        return [
            'meeting_id' => $meetingId,
            'motion_title' => (string) ($row['motion_title'] ?? ''),
        ];
    }
}
