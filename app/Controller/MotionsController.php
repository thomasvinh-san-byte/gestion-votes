<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Validation\InputValidator;
use AgVote\Repository\AgendaRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;

/**
 * Consolidates 7 motion endpoints.
 *
 * Shared pattern: motion/meeting validation, MotionRepository + MeetingRepository.
 */
final class MotionsController extends AbstractController
{
    public function createOrUpdate(): void
    {
        api_require_role('operator');
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

        api_guard_meeting_not_validated((string)$agenda['meeting_id']);

        if ($votePolicyId !== '' && !$meetingRepo->votePolicyExists($votePolicyId, $tenantId)) {
            api_fail('vote_policy_not_found', 404);
        }
        if ($quorumPolicyId !== '' && !$meetingRepo->quorumPolicyExists($quorumPolicyId, $tenantId)) {
            api_fail('quorum_policy_not_found', 404);
        }

        db()->beginTransaction();

        if ($motionId === '') {
            $motionId = $motionRepo->generateUuid();
            $motionRepo->create(
                $motionId, $tenantId, $agenda['meeting_id'], $agendaId,
                $title, $description, $secret,
                $votePolicyId ?: null, $quorumPolicyId ?: null
            );
            audit_log('motion_created', 'motion', (string)$motionId, [
                'meeting_id' => (string)$agenda['meeting_id'],
                'agenda_id' => $agendaId,
                'title' => $title,
                'secret' => $secret,
                'vote_policy_id' => $votePolicyId ?: null,
                'quorum_policy_id' => $quorumPolicyId ?: null,
            ]);
            db()->commit();
            api_ok(['motion_id' => (string)$motionId, 'created' => true]);
        }

        $motion = $motionRepo->findByIdForTenant($motionId, $tenantId);
        if (!$motion) {
            db()->rollBack();
            api_fail('motion_not_found', 404);
        }
        if ((string)$motion['agenda_id'] !== $agendaId) {
            db()->rollBack();
            api_fail('agenda_mismatch', 409, ['detail' => 'La motion appartient à un autre agenda.']);
        }
        if (!empty($motion['opened_at']) && empty($motion['closed_at'])) {
            db()->rollBack();
            api_fail('motion_active_locked', 409, ['detail' => 'Motion active : édition interdite pendant le vote.']);
        }
        if (!empty($motion['closed_at'])) {
            db()->rollBack();
            api_fail('motion_closed_locked', 409, ['detail' => 'Motion clôturée : édition interdite.']);
        }

        $motionRepo->update(
            $motionId, $tenantId, $title, $description, $secret,
            $votePolicyId ?: null, $quorumPolicyId ?: null
        );
        audit_log('motion_updated', 'motion', (string)$motionId, [
            'meeting_id' => (string)$motion['meeting_id'],
            'agenda_id' => $agendaId,
            'title' => $title,
            'secret' => $secret,
            'vote_policy_id' => $votePolicyId ?: null,
            'quorum_policy_id' => $quorumPolicyId ?: null,
        ]);
        db()->commit();
        api_ok(['motion_id' => (string)$motionId, 'created' => false]);
    }

    public function listForMeeting(): void
    {
        api_require_role('public');
        $q = api_request('GET');
        $meetingId = api_require_uuid($q, 'meeting_id');

        $tenantId = api_current_tenant_id();
        $meetingRepo = new MeetingRepository();
        $motionRepo = new MotionRepository();

        if (!$meetingRepo->existsForTenant($meetingId, $tenantId)) {
            api_fail('meeting_not_found', 404);
        }

        $row = $motionRepo->listForMeetingJson($meetingId);
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

        $stats = $motionRepo->listStatsForMeeting($meetingId);
        $statsMap = [];
        foreach ($stats as $s) {
            $statsMap[(string)$s['motion_id']] = $s;
        }

        $policyRepo = new PolicyRepository();
        $policyNameCache = [];

        foreach ($motions as &$m) {
            $m['id'] = $m['motion_id'] ?? $m['id'] ?? null;
            $m['title'] = $m['motion_title'] ?? $m['title'] ?? '';
            $m['description'] = $m['motion_description'] ?? $m['description'] ?? '';
            $m['result'] = $m['decision'] ?? null;

            $mid = (string)$m['id'];
            if (isset($statsMap[$mid])) {
                $m['votes_for'] = (int)$statsMap[$mid]['ballots_for'];
                $m['votes_against'] = (int)$statsMap[$mid]['ballots_against'];
                $m['votes_abstain'] = (int)$statsMap[$mid]['ballots_abstain'];
                $m['votes_nsp'] = (int)$statsMap[$mid]['ballots_nsp'];
                $m['votes_count'] = (int)$statsMap[$mid]['ballots_total'];
            } else {
                $m['votes_for'] = 0;
                $m['votes_against'] = 0;
                $m['votes_abstain'] = 0;
                $m['votes_nsp'] = 0;
                $m['votes_count'] = 0;
            }

            $vpId = (string)($m['vote_policy_id'] ?? '');
            if ($vpId !== '' && !isset($policyNameCache['v_' . $vpId])) {
                $policyNameCache['v_' . $vpId] = $policyRepo->findVotePolicyName($tenantId, $vpId);
            }
            $m['vote_policy_name'] = $policyNameCache['v_' . $vpId] ?? null;

            $qpId = (string)($m['quorum_policy_id'] ?? '');
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
            'motions' => $motions,
        ]);
    }

    public function createSimple(): void
    {
        api_require_role('operator');
        $in = api_request('POST');

        $meetingId = trim((string)($in['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id est obligatoire (uuid).']);
        }

        $title = trim((string)($in['title'] ?? ''));
        if ($title === '') {
            api_fail('missing_title', 422, ['detail' => 'title est obligatoire.']);
        }
        if (mb_strlen($title) > 200) {
            api_fail('title_too_long', 422, ['detail' => 'title doit faire ≤ 200 caractères.']);
        }

        $description = trim((string)($in['description'] ?? ''));
        if (mb_strlen($description) > 10000) {
            api_fail('description_too_long', 422, ['detail' => 'description trop longue (max 10000).']);
        }

        $secret = (bool)($in['secret'] ?? false);
        $tenantId = api_current_tenant_id();

        $meetingRepo = new MeetingRepository();
        if (!$meetingRepo->existsForTenant($meetingId, $tenantId)) {
            api_fail('meeting_not_found', 404, ['detail' => 'Séance non trouvée.']);
        }

        api_guard_meeting_not_validated($meetingId);

        $result = api_transaction(function () use ($meetingId, $tenantId, $title, $description, $secret) {
            $agendaRepo = new AgendaRepository();
            $agendas = $agendaRepo->listForMeetingCompact($meetingId);

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

    public function deleteMotion(): void
    {
        api_require_role('operator');
        $in = api_request('POST');
        $motionId = api_require_uuid($in, 'motion_id');

        $tenantId = api_current_tenant_id();
        $repo = new MotionRepository();
        $motion = $repo->findByIdForTenant($motionId, $tenantId);
        if (!$motion) {
            api_fail('motion_not_found', 404);
        }

        api_guard_meeting_not_validated((string)$motion['meeting_id']);

        if (!empty($motion['opened_at']) && empty($motion['closed_at'])) {
            api_fail('motion_open_locked', 409, ['detail' => 'Motion ouverte : suppression interdite.']);
        }
        if (!empty($motion['closed_at'])) {
            api_fail('motion_closed_locked', 409, ['detail' => 'Motion clôturée : suppression interdite.']);
        }

        $repo->delete($motionId, $tenantId);

        audit_log('motion_deleted', 'motion', (string)$motionId, [
            'meeting_id' => (string)$motion['meeting_id'],
            'agenda_id' => (string)$motion['agenda_id'],
        ]);

        api_ok(['motion_id' => (string)$motionId]);
    }

    public function reorder(): void
    {
        api_require_role('operator');
        $in = api_request('POST');

        $meetingId = trim((string)($in['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id est obligatoire (uuid).']);
        }

        $motionIds = $in['motion_ids'] ?? [];
        if (!is_array($motionIds) || empty($motionIds)) {
            api_fail('missing_motion_ids', 422, ['detail' => 'motion_ids est obligatoire (tableau d\'uuids).']);
        }

        foreach ($motionIds as $mid) {
            if (!api_is_uuid($mid)) {
                api_fail('invalid_motion_id', 422, ['detail' => "motion_id invalide: $mid"]);
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

    public function tally(): void
    {
        api_require_role('operator');
        $in = api_request('GET');

        $motionId = trim((string)($in['motion_id'] ?? ($_GET['motion_id'] ?? '')));
        if ($motionId === '' || !api_is_uuid($motionId)) {
            api_fail('invalid_motion_id', 400);
        }

        $repo = new MotionRepository();
        $motion = $repo->findByIdForTenant($motionId, api_current_tenant_id());
        if (!$motion) {
            api_fail('motion_not_found', 404);
        }

        $rows = $repo->getTally($motionId);
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
            $result[$v]['count'] = (int)$r['c'];
            $result[$v]['weight'] = (float)$r['w'];
        }

        api_ok([
            'motion_id' => $motionId,
            'closed' => $motion['closed_at'] !== null,
            'tally' => $result,
        ]);
    }

    public function current(): void
    {
        api_require_role('public');
        api_request('GET');

        $meetingId = trim((string)($_GET['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('invalid_request', 422);
        }

        $tenantId = api_current_tenant_id();
        $motionRepo = new MotionRepository();
        $motion = $motionRepo->findCurrentOpen($meetingId, $tenantId);

        $totalMotions = $motionRepo->countForMeeting($meetingId);
        $eligibleCount = (new MeetingRepository())->countActiveMembers($tenantId);

        $ballotsCast = 0;
        if ($motion) {
            $ballotsCast = (new BallotRepository())->countByMotionId((string)$motion['id']);
        }

        api_ok([
            'motion' => $motion,
            'total_motions' => $totalMotions,
            'eligible_count' => $eligibleCount,
            'ballots_cast' => $ballotsCast,
        ]);
    }
}
