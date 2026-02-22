<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Validation\InputValidator;
use AgVote\Repository\AgendaRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\ManualActionRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingStatsRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Service\NotificationsService;
use AgVote\Service\OfficialResultsService;
use AgVote\Service\VoteTokenService;
use AgVote\WebSocket\EventBroadcaster;
use Throwable;

/**
 * Consolidates 7 motion endpoints.
 *
 * Shared pattern: motion/meeting validation, MotionRepository + MeetingRepository.
 */
final class MotionsController extends AbstractController {
    public function createOrUpdate(): void {
        $in = api_request('POST');

        $v = InputValidator::schema()
            ->uuid('agenda_id')->required()
            ->uuid('motion_id')->optional()
            ->string('title')->required()->minLength(1)->maxLength(500)
            ->string('description')->optional()->maxLength(10000)
            ->boolean('secret')->default(false)
            ->uuid('vote_policy_id')->optional()
            ->uuid('quorum_policy_id')->optional()
            ->validate($in);
        $v->failIfInvalid();

        $agendaId = $v->get('agenda_id');
        $motionId = $v->get('motion_id', '');
        $title = $v->get('title');
        $description = $v->get('description', '');
        $secret = $v->get('secret', false);
        $votePolicyId = $v->get('vote_policy_id', '');
        $quorumPolicyId = $v->get('quorum_policy_id', '');

        $tenantId = api_current_tenant_id();
        $motionRepo = new MotionRepository();
        $meetingRepo = new MeetingRepository();

        $agenda = $motionRepo->findAgendaWithMeeting($agendaId, $tenantId);
        if (!$agenda) {
            api_fail('agenda_not_found', 404);
        }

        api_guard_meeting_not_validated((string) $agenda['meeting_id']);

        $policyRepo = new PolicyRepository();
        if ($votePolicyId !== '' && !$policyRepo->votePolicyExists($votePolicyId, $tenantId)) {
            api_fail('vote_policy_not_found', 404);
        }
        if ($quorumPolicyId !== '' && !$policyRepo->quorumPolicyExists($quorumPolicyId, $tenantId)) {
            api_fail('quorum_policy_not_found', 404);
        }

        if ($motionId === '') {
            $newMotionId = api_transaction(function () use ($motionRepo, $tenantId, $agenda, $agendaId, $title, $description, $secret, $votePolicyId, $quorumPolicyId) {
                $motionId = $motionRepo->generateUuid();
                $motionRepo->create(
                    $motionId,
                    $tenantId,
                    $agenda['meeting_id'],
                    $agendaId,
                    $title,
                    $description,
                    $secret,
                    $votePolicyId ?: null,
                    $quorumPolicyId ?: null,
                );
                audit_log('motion_created', 'motion', (string) $motionId, [
                    'meeting_id' => (string) $agenda['meeting_id'],
                    'agenda_id' => $agendaId,
                    'title' => $title,
                    'secret' => $secret,
                    'vote_policy_id' => $votePolicyId ?: null,
                    'quorum_policy_id' => $quorumPolicyId ?: null,
                ]);
                return (string) $motionId;
            });
            api_ok(['motion_id' => $newMotionId, 'created' => true]);
        }

        api_transaction(function () use ($motionRepo, $motionId, $tenantId, $agendaId, $title, $description, $secret, $votePolicyId, $quorumPolicyId) {
            $motion = $motionRepo->findByIdForTenant($motionId, $tenantId);
            if (!$motion) {
                api_fail('motion_not_found', 404);
            }
            if ((string) $motion['agenda_id'] !== $agendaId) {
                api_fail('agenda_mismatch', 409, ['detail' => 'La motion appartient à un autre agenda.']);
            }
            if (!empty($motion['opened_at']) && empty($motion['closed_at'])) {
                api_fail('motion_active_locked', 409, ['detail' => 'Motion active : édition interdite pendant le vote.']);
            }
            if (!empty($motion['closed_at'])) {
                api_fail('motion_closed_locked', 409, ['detail' => 'Motion clôturée : édition interdite.']);
            }

            $motionRepo->update(
                $motionId,
                $tenantId,
                $title,
                $description,
                $secret,
                $votePolicyId ?: null,
                $quorumPolicyId ?: null,
            );
            audit_log('motion_updated', 'motion', (string) $motionId, [
                'meeting_id' => (string) $motion['meeting_id'],
                'agenda_id' => $agendaId,
                'title' => $title,
                'secret' => $secret,
                'vote_policy_id' => $votePolicyId ?: null,
                'quorum_policy_id' => $quorumPolicyId ?: null,
            ]);
        });
        api_ok(['motion_id' => (string) $motionId, 'created' => false]);
    }

    public function listForMeeting(): void {
        $q = api_request('GET');
        $meetingId = api_require_uuid($q, 'meeting_id');

        $tenantId = api_current_tenant_id();
        $meetingRepo = new MeetingRepository();
        $motionRepo = new MotionRepository();

        if (!$meetingRepo->existsForTenant($meetingId, $tenantId)) {
            api_fail('meeting_not_found', 404);
        }

        $row = $motionRepo->listForMeetingJson($meetingId, $tenantId);
        $motions = [];
        if ($row && isset($row['motions']) && $row['motions'] !== null) {
            if (is_string($row['motions'])) {
                $decoded = json_decode($row['motions'], true);
                if (is_array($decoded)) {
                    $motions = $decoded;
                }
            } elseif (is_array($row['motions'])) {
                $motions = $row['motions'];
            }
        }

        $stats = $motionRepo->listStatsForMeeting($meetingId, $tenantId);
        $statsMap = [];
        foreach ($stats as $s) {
            $statsMap[(string) $s['motion_id']] = $s;
        }

        $policyRepo = new PolicyRepository();
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
                $policyNameCache['v_' . $vpId] = $policyRepo->findVotePolicyName($tenantId, $vpId);
            }
            $m['vote_policy_name'] = $policyNameCache['v_' . $vpId] ?? null;

            $qpId = (string) ($m['quorum_policy_id'] ?? '');
            if ($qpId !== '' && !isset($policyNameCache['q_' . $qpId])) {
                $policyNameCache['q_' . $qpId] = $policyRepo->findQuorumPolicyName($tenantId, $qpId);
            }
            $m['quorum_policy_name'] = $policyNameCache['q_' . $qpId] ?? null;
        }
        unset($m);

        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
        $currentMotionId = $meeting['current_motion_id'] ?? null;

        api_ok([
            'meeting_id' => $meetingId,
            'current_motion_id' => $currentMotionId,
            'items' => $motions,
        ]);
    }

    public function createSimple(): void {
        $in = api_request('POST');

        $meetingId = trim((string) ($in['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id est obligatoire (uuid).']);
        }

        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '') {
            api_fail('missing_title', 422, ['detail' => 'title est obligatoire.']);
        }
        if (mb_strlen($title) > 200) {
            api_fail('title_too_long', 422, ['detail' => 'title doit faire ≤ 200 caractères.']);
        }

        $description = trim((string) ($in['description'] ?? ''));
        if (mb_strlen($description) > 10000) {
            api_fail('description_too_long', 422, ['detail' => 'description trop longue (max 10000).']);
        }

        $secret = (bool) ($in['secret'] ?? false);
        $tenantId = api_current_tenant_id();

        $meetingRepo = new MeetingRepository();
        if (!$meetingRepo->existsForTenant($meetingId, $tenantId)) {
            api_fail('meeting_not_found', 404, ['detail' => 'Séance non trouvée.']);
        }

        api_guard_meeting_not_validated($meetingId);

        $result = api_transaction(function () use ($meetingId, $tenantId, $title, $description, $secret) {
            $agendaRepo = new AgendaRepository();
            $agendas = $agendaRepo->listForMeetingCompact($meetingId, $tenantId);

            $agendaId = null;
            if (empty($agendas)) {
                $agendaId = $agendaRepo->generateUuid();
                $agendaRepo->create($agendaId, $tenantId, $meetingId, 1, 'Ordre du jour');
                audit_log('agenda_created', 'agenda', $agendaId, [
                    'meeting_id' => $meetingId,
                    'title' => 'Ordre du jour',
                    'auto_created' => true,
                ]);
            } else {
                $agendaId = $agendas[0]['agenda_id'];
            }

            $motionRepo = new MotionRepository();
            $motionId = $motionRepo->generateUuid();
            $motionRepo->create($motionId, $tenantId, $meetingId, $agendaId, $title, $description, $secret, null, null);

            audit_log('motion_created', 'motion', $motionId, [
                'meeting_id' => $meetingId,
                'agenda_id' => $agendaId,
                'title' => $title,
                'secret' => $secret,
                'created_via' => 'simple_endpoint',
            ]);

            return ['motion_id' => $motionId, 'agenda_id' => $agendaId];
        });

        api_ok(['motion_id' => $result['motion_id'], 'agenda_id' => $result['agenda_id'], 'created' => true]);
    }

    public function deleteMotion(): void {
        $in = api_request('POST');
        $motionId = api_require_uuid($in, 'motion_id');

        $tenantId = api_current_tenant_id();
        $repo = new MotionRepository();
        $motion = $repo->findByIdForTenant($motionId, $tenantId);
        if (!$motion) {
            api_fail('motion_not_found', 404);
        }

        api_guard_meeting_not_validated((string) $motion['meeting_id']);

        if (!empty($motion['opened_at']) && empty($motion['closed_at'])) {
            api_fail('motion_open_locked', 409, ['detail' => 'Motion ouverte : suppression interdite.']);
        }
        if (!empty($motion['closed_at'])) {
            api_fail('motion_closed_locked', 409, ['detail' => 'Motion clôturée : suppression interdite.']);
        }

        $repo->delete($motionId, $tenantId);

        audit_log('motion_deleted', 'motion', (string) $motionId, [
            'meeting_id' => (string) $motion['meeting_id'],
            'agenda_id' => (string) $motion['agenda_id'],
        ]);

        api_ok(['motion_id' => (string) $motionId]);
    }

    public function reorder(): void {
        $in = api_request('POST');

        $meetingId = trim((string) ($in['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id est obligatoire (uuid).']);
        }

        $motionIds = $in['motion_ids'] ?? [];
        if (!is_array($motionIds) || empty($motionIds)) {
            api_fail('missing_motion_ids', 422, ['detail' => 'motion_ids est obligatoire (tableau d\'uuids).']);
        }

        foreach ($motionIds as $mid) {
            if (!api_is_uuid($mid)) {
                api_fail('invalid_motion_id', 422, ['detail' => "motion_id invalide: {$mid}"]);
            }
        }

        $tenantId = api_current_tenant_id();
        $meetingRepo = new MeetingRepository();
        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        $status = $meeting['status'] ?? '';
        if (in_array($status, ['live', 'closed', 'validated', 'archived'], true)) {
            api_fail('meeting_locked', 409, [
                'detail' => 'Impossible de réordonner les résolutions d\'une séance en cours, clôturée, validée ou archivée.',
            ]);
        }

        (new MotionRepository())->reorderAll($meetingId, $tenantId, $motionIds);

        audit_log('motions_reordered', 'meeting', $meetingId, ['motion_ids' => $motionIds]);
        api_ok(['reordered' => true, 'count' => count($motionIds)]);
    }

    public function tally(): void {
        $in = api_request('GET');

        $motionId = trim((string) ($in['motion_id'] ?? api_query('motion_id')));
        if ($motionId === '' || !api_is_uuid($motionId)) {
            api_fail('invalid_motion_id', 400);
        }

        $repo = new MotionRepository();
        $motion = $repo->findByIdForTenant($motionId, api_current_tenant_id());
        if (!$motion) {
            api_fail('motion_not_found', 404);
        }

        $rows = $repo->getTally($motionId, api_current_tenant_id());
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

        api_ok([
            'motion_id' => $motionId,
            'closed' => $motion['closed_at'] !== null,
            'tally' => $result,
        ]);
    }

    public function current(): void {
        api_request('GET');

        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('invalid_request', 422);
        }

        $tenantId = api_current_tenant_id();
        $motionRepo = new MotionRepository();
        $motion = $motionRepo->findCurrentOpen($meetingId, $tenantId);

        $totalMotions = $motionRepo->countForMeeting($meetingId, $tenantId);
        $eligibleCount = (new MeetingStatsRepository())->countActiveMembers($tenantId);

        $ballotsCast = 0;
        if ($motion) {
            $ballotsCast = (new BallotRepository())->countByMotionId((string) $motion['id'], $tenantId);
        }

        $meetingStatus = null;
        $meeting = (new MeetingRepository())->findByIdForTenant($meetingId, $tenantId);
        if ($meeting) {
            $meetingStatus = $meeting['status'] ?? null;
        }

        api_ok([
            'motion' => $motion,
            'total_motions' => $totalMotions,
            'eligible_count' => $eligibleCount,
            'ballots_cast' => $ballotsCast,
            'meeting_status' => $meetingStatus,
        ]);
    }

    public function open(): void {
        $input = api_request('POST');

        $motionId = trim((string) ($input['motion_id'] ?? ''));
        if ($motionId === '' || !api_is_uuid($motionId)) {
            api_fail('invalid_motion_id', 422);
        }

        $tenantId = api_current_tenant_id();
        $repo = new MotionRepository();
        $meetingRepo = new MeetingRepository();
        $policyRepo = new PolicyRepository();

        $txResult = api_transaction(function () use ($repo, $meetingRepo, $policyRepo, $motionId, $tenantId) {
            $motion = $repo->findByIdForTenantForUpdate($motionId, $tenantId);
            if (!$motion) {
                api_fail('motion_not_found', 404);
            }

            $meetingId = (string) $motion['meeting_id'];
            api_guard_meeting_not_validated($meetingId);

            if (!empty($motion['opened_at'])) {
                api_fail('motion_already_opened', 409);
            }

            // Policy resolution: motion → meeting → tenant default
            $votePolicyId = $motion['vote_policy_id'] ?? null;
            if (!$votePolicyId) {
                $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
                $votePolicyId = $meeting['vote_policy_id'] ?? null;
            }
            if (!$votePolicyId) {
                $defaults = $policyRepo->listVotePolicies($tenantId);
                if (!empty($defaults)) {
                    $votePolicyId = $defaults[0]['id'];
                }
            }

            $quorumPolicyId = $motion['quorum_policy_id'] ?? null;
            if (!$quorumPolicyId) {
                $meeting ??= $meetingRepo->findByIdForTenant($meetingId, $tenantId);
                $quorumPolicyId = $meeting['quorum_policy_id'] ?? null;
            }
            if (!$quorumPolicyId) {
                $defaults = $policyRepo->listQuorumPolicies($tenantId);
                if (!empty($defaults)) {
                    $quorumPolicyId = $defaults[0]['id'];
                }
            }

            $repo->markOpened($motionId, $tenantId, $votePolicyId, $quorumPolicyId);
            $meetingRepo->setCurrentMotion($meetingId, $tenantId, $motionId);

            return [
                'meetingId' => $meetingId,
                'votePolicyId' => $votePolicyId,
                'quorumPolicyId' => $quorumPolicyId,
                'title' => (string) $motion['title'],
                'secret' => (bool) $motion['secret'],
            ];
        });

        $meetingId = $txResult['meetingId'];
        $votePolicyId = $txResult['votePolicyId'];
        $quorumPolicyId = $txResult['quorumPolicyId'];

        try {
            audit_log('motion_opened', 'motion', $motionId, [
                'meeting_id' => $meetingId,
                'vote_policy_id' => $votePolicyId,
                'quorum_policy_id' => $quorumPolicyId,
            ]);
        } catch (Throwable $e) {
            if ($e instanceof \AgVote\Core\Http\ApiResponseException) {
                throw $e;
            }
            error_log('[motions_open] audit_log failed: ' . $e->getMessage());
        }

        try {
            EventBroadcaster::motionOpened($meetingId, $motionId, [
                'title' => $txResult['title'],
                'secret' => $txResult['secret'],
            ]);
        } catch (Throwable $e) {
            if ($e instanceof \AgVote\Core\Http\ApiResponseException) {
                throw $e;
            }
            error_log('[motions_open] EventBroadcaster failed: ' . $e->getMessage());
        }

        api_ok([
            'meeting_id' => $meetingId,
            'opened_motion_id' => $motionId,
            'vote_policy_id' => $votePolicyId,
            'quorum_policy_id' => $quorumPolicyId,
        ]);
    }

    public function close(): void {
        $input = api_request('POST');

        $motionId = trim((string) ($input['motion_id'] ?? ''));
        if ($motionId === '' || !api_is_uuid($motionId)) {
            api_fail('invalid_motion_id', 422);
        }

        $repo = new MotionRepository();

        $txResult = api_transaction(function () use ($repo, $motionId) {
            $motion = $repo->findByIdForTenantForUpdate($motionId, api_current_tenant_id());
            if (!$motion) {
                api_fail('motion_not_found', 404);
            }

            if (empty($motion['opened_at'])) {
                api_fail('motion_not_open', 409);
            }
            if (!empty($motion['closed_at'])) {
                api_fail('motion_already_closed', 409);
            }

            $repo->markClosed($motionId, api_current_tenant_id());

            $o = (new OfficialResultsService())->computeOfficialTallies((string) $motionId, api_current_tenant_id());
            $repo->updateOfficialResults(
                (string) $motionId,
                $o['source'],
                $o['for'],
                $o['against'],
                $o['abstain'],
                $o['total'],
                $o['decision'],
                $o['reason'],
                api_current_tenant_id(),
            );

            return ['motion' => $motion, 'o' => $o];
        });

        $motion = $txResult['motion'];
        $o = $txResult['o'];

        try {
            (new VoteTokenService())->revokeForMotion($motionId, api_current_tenant_id());
        } catch (Throwable $tokenErr) {
            if ($tokenErr instanceof \AgVote\Core\Http\ApiResponseException) {
                throw $tokenErr;
            }
            error_log('[motions_close] token revocation failed after commit: ' . $tokenErr->getMessage());
        }

        try {
            audit_log('motion_closed', 'motion', $motionId, [
                'meeting_id' => (string) $motion['meeting_id'],
            ]);
        } catch (Throwable $auditErr) {
            if ($auditErr instanceof \AgVote\Core\Http\ApiResponseException) {
                throw $auditErr;
            }
            error_log('[motions_close] audit_log failed after commit: ' . $auditErr->getMessage());
        }

        try {
            EventBroadcaster::motionClosed((string) $motion['meeting_id'], $motionId, [
                'for' => $o['for'] ?? 0,
                'against' => $o['against'] ?? 0,
                'abstain' => $o['abstain'] ?? 0,
                'total' => $o['total'] ?? 0,
                'decision' => $o['decision'] ?? 'unknown',
                'reason' => $o['reason'] ?? null,
            ]);
        } catch (Throwable $wsErr) {
            if ($wsErr instanceof \AgVote\Core\Http\ApiResponseException) {
                throw $wsErr;
            }
            error_log('[motions_close] EventBroadcaster failed after commit: ' . $wsErr->getMessage());
        }

        $eligibleCount = 0;
        try {
            $attendanceRepo = new AttendanceRepository();
            $eligibleCount = $attendanceRepo->countByModes(
                (string) $motion['meeting_id'],
                api_current_tenant_id(),
                ['present', 'remote'],
            );
        } catch (Throwable $e) {
            if ($e instanceof \AgVote\Core\Http\ApiResponseException) {
                throw $e;
            }
            /* non-critical */
        }

        api_ok([
            'meeting_id' => (string) $motion['meeting_id'],
            'closed_motion_id' => $motionId,
            'results' => [
                'for' => $o['for'] ?? 0,
                'against' => $o['against'] ?? 0,
                'abstain' => $o['abstain'] ?? 0,
                'total' => $o['total'] ?? 0,
                'decision' => $o['decision'] ?? 'unknown',
                'reason' => $o['reason'] ?? null,
            ],
            'eligible_count' => $eligibleCount,
            'votes_cast' => $o['total'] ?? 0,
        ]);
    }

    public function degradedTally(): void {
        $input = api_request('POST');

        $motionId = trim((string) ($input['motion_id'] ?? ''));
        if ($motionId === '') {
            api_fail('missing_motion_id', 422);
        }

        $tenantId = api_current_tenant_id();
        $row = (new MotionRepository())->findWithMeetingTenant($motionId, $tenantId);
        if (!$row) {
            api_fail('motion_not_found', 404);
        }

        $meetingId = (string) $row['meeting_id'];

        $total = isset($input['manual_total']) ? (int) $input['manual_total'] : 0;
        $for = isset($input['manual_for']) ? (int) $input['manual_for'] : 0;
        $against = isset($input['manual_against']) ? (int) $input['manual_against'] : 0;
        $abstain = isset($input['manual_abstain']) ? (int) $input['manual_abstain'] : 0;

        $justification = trim((string) ($input['justification'] ?? ''));
        if ($justification === '') {
            api_fail('missing_justification', 422, ['detail' => 'Une justification est obligatoire en mode dégradé.']);
        }

        if ($total <= 0) {
            api_fail('invalid_total', 422, ['detail' => 'Le nombre total de votants doit être strictement positif.']);
        }
        if ($for < 0 || $against < 0 || $abstain < 0) {
            api_fail('invalid_numbers', 422, ['detail' => 'Les nombres de votes doivent être positifs.']);
        }
        if ($for > $total || $against > $total || $abstain > $total) {
            api_fail('vote_exceeds_total', 422, ['detail' => 'Aucune catégorie ne peut dépasser le total.', 'total' => $total, 'for' => $for, 'against' => $against, 'abstain' => $abstain]);
        }
        $sum = $for + $against + $abstain;
        if ($sum !== $total) {
            api_fail('inconsistent_tally', 422, ['detail' => 'Pour + Contre + Abstentions doit être égal au total.', 'total' => $total, 'sum' => $sum]);
        }

        api_transaction(function () use ($motionId, $total, $for, $against, $abstain, $tenantId, $meetingId, $justification) {
            (new MotionRepository())->updateManualTally($motionId, $total, $for, $against, $abstain, $tenantId);

            (new ManualActionRepository())->createManualTally(
                $tenantId,
                $meetingId,
                $motionId,
                json_encode(['total' => $total, 'for' => $for, 'against' => $against, 'abstain' => $abstain], JSON_UNESCAPED_UNICODE),
                $justification,
            );
        });

        audit_log('manual_tally_set', 'motion', $motionId, [
            'meeting_id' => $meetingId,
            'tally' => ['total' => $total, 'for' => $for, 'against' => $against, 'abstain' => $abstain],
            'justification' => $justification,
        ]);

        (new NotificationsService())->emit(
            $meetingId,
            'warn',
            'degraded_manual_tally',
            'Mode dégradé: comptage manuel saisi pour "' . ((string) $row['motion_title']) . '".',
            ['operator', 'trust'],
            ['motion_id' => $motionId],
        );

        api_ok([
            'meeting_id' => $meetingId,
            'motion_id' => $motionId,
            'manual_total' => $total,
            'manual_for' => $for,
            'manual_against' => $against,
            'manual_abstain' => $abstain,
        ]);
    }
}
