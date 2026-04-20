<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Security\IdempotencyGuard;
use AgVote\Core\Validation\InputValidator;
use AgVote\Service\MotionsService;
use AgVote\SSE\EventBroadcaster;
use RuntimeException;
use Throwable;

/** Thin HTTP orchestration layer for motion endpoints. Business logic lives in MotionsService. */
final class MotionsController extends AbstractController {
    private ?MotionsService $motionsService;

    public function __construct(?MotionsService $motionsService = null) {
        $this->motionsService = $motionsService;
    }
    private function motionsService(): MotionsService {
        return $this->motionsService ??= new MotionsService();
    }

    public function createOrUpdate(): void {
        $in = api_request('POST');
        $cached = IdempotencyGuard::check();
        if ($cached !== null) { api_ok($cached); }
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
        $tenantId = api_current_tenant_id();
        $agendaId = $v->get('agenda_id');
        $agenda = $this->repo()->motion()->findAgendaWithMeeting($agendaId, $tenantId)
            ?: api_fail('agenda_not_found', 404);
        api_guard_meeting_not_validated((string) $agenda['meeting_id']);
        try {
            $result = $this->motionsService()->createOrUpdate([
                'agenda_id' => $agendaId, 'motion_id' => $v->get('motion_id', ''),
                'title' => $v->get('title'), 'description' => $v->get('description', ''),
                'secret' => $v->get('secret', false), 'vote_policy_id' => $v->get('vote_policy_id', ''),
                'quorum_policy_id' => $v->get('quorum_policy_id', ''),
            ], $tenantId);
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            api_fail($code, match($code) {
                'agenda_not_found', 'vote_policy_not_found', 'quorum_policy_not_found', 'motion_not_found' => 404,
                'agenda_mismatch', 'motion_active_locked', 'motion_closed_locked' => 409,
                default => 400,
            });
        }
        $response = ['motion_id' => $result['motion_id'], 'created' => $result['created']];
        IdempotencyGuard::store($response);
        api_ok($response);
    }

    public function listForMeeting(): void {
        $q = api_request('GET');
        $meetingId = api_require_uuid($q, 'meeting_id');
        $tenantId = api_current_tenant_id();
        if (!$this->repo()->meeting()->existsForTenant($meetingId, $tenantId)) {
            api_fail('meeting_not_found', 404);
        }
        api_ok($this->motionsService()->listForMeeting($meetingId, $tenantId));
    }

    public function createSimple(): void {
        $in = api_request('POST');
        $cached = IdempotencyGuard::check();
        if ($cached !== null) { api_ok($cached); }
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
        $tenantId = api_current_tenant_id();
        if (!$this->repo()->meeting()->existsForTenant($meetingId, $tenantId)) {
            api_fail('meeting_not_found', 404, ['detail' => 'Séance non trouvée.']);
        }
        api_guard_meeting_not_validated($meetingId);
        $result = $this->motionsService()->createSimple(['meeting_id' => $meetingId, 'title' => $title, 'description' => $description, 'secret' => (bool) ($in['secret'] ?? false)], $tenantId);
        $response = ['motion_id' => $result['motion_id'], 'agenda_id' => $result['agenda_id'], 'created' => true];
        IdempotencyGuard::store($response);
        api_ok($response);
    }

    public function deleteMotion(): void {
        $in = api_request('POST');
        $motionId = api_require_uuid($in, 'motion_id');
        $tenantId = api_current_tenant_id();
        $motion = $this->repo()->motion()->findByIdForTenant($motionId, $tenantId);
        if (!$motion) {
            api_fail('motion_not_found', 404);
        }
        api_guard_meeting_not_validated((string) $motion['meeting_id']);
        try {
            $this->motionsService()->delete($motionId, $tenantId);
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            api_fail($code, match($code) {
                'motion_not_found' => 404,
                'motion_open_locked', 'motion_closed_locked' => 409,
                default => 400,
            });
        }
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
        try {
            $this->motionsService()->reorder($meetingId, api_current_tenant_id(), $motionIds);
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            api_fail($code, match($code) {
                'meeting_not_found' => 404, 'meeting_locked' => 409, default => 400,
            });
        }
        api_ok(['reordered' => true, 'count' => count($motionIds)]);
    }

    public function tally(): void {
        $in = api_request('GET');
        $motionId = trim((string) ($in['motion_id'] ?? api_query('motion_id')));
        if ($motionId === '' || !api_is_uuid($motionId)) {
            api_fail('invalid_motion_id', 400);
        }
        try {
            $result = $this->motionsService()->tally($motionId, api_current_tenant_id());
        } catch (RuntimeException $e) {
            api_fail($e->getMessage(), 404);
        }
        api_ok($result);
    }

    public function current(): void {
        api_request('GET');
        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('invalid_request', 422);
        }
        $tenantId = api_current_tenant_id();
        $motionRepo = $this->repo()->motion();
        $motion = $motionRepo->findCurrentOpen($meetingId, $tenantId);
        $ballotsCast = $motion ? $this->repo()->ballot()->countByMotionId((string) $motion['id'], $tenantId) : 0;
        $meeting = $this->repo()->meeting()->findByIdForTenant($meetingId, $tenantId);
        api_ok([
            'motion' => $motion,
            'total_motions' => $motionRepo->countForMeeting($meetingId, $tenantId),
            'eligible_count' => $this->repo()->meetingStats()->countActiveMembers($tenantId),
            'ballots_cast' => $ballotsCast,
            'meeting_status' => $meeting['status'] ?? null,
        ]);
    }

    public function open(): void {
        $input = api_request('POST');
        $motionId = trim((string) ($input['motion_id'] ?? ''));
        if ($motionId === '' || !api_is_uuid($motionId)) {
            api_fail('invalid_motion_id', 422);
        }
        $tenantId = api_current_tenant_id();
        try {
            $r = $this->motionsService()->open($motionId, $tenantId);
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            api_fail($code, match($code) {
                'motion_not_found' => 404,
                'motion_already_opened', 'meeting_validated' => 409,
                default => 400,
            });
        }
        audit_log('motion_opened', 'motion', $motionId, [
            'meeting_id' => $r['meetingId'],
            'vote_policy_id' => $r['votePolicyId'],
            'quorum_policy_id' => $r['quorumPolicyId'],
        ]);
        try {
            EventBroadcaster::motionOpened($r['meetingId'], $motionId, ['title' => $r['title'], 'secret' => $r['secret']]);
        } catch (Throwable) {
        }
        api_ok(['meeting_id' => $r['meetingId'], 'opened_motion_id' => $motionId, 'vote_policy_id' => $r['votePolicyId'], 'quorum_policy_id' => $r['quorumPolicyId']]);
    }

    public function close(): void {
        $input = api_request('POST');
        $motionId = trim((string) ($input['motion_id'] ?? ''));
        if ($motionId === '' || !api_is_uuid($motionId)) {
            api_fail('invalid_motion_id', 422);
        }
        $tenantId = api_current_tenant_id();
        try {
            $r = $this->motionsService()->close($motionId, $tenantId);
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            api_fail($code, match($code) {
                'motion_not_found' => 404,
                'motion_not_open', 'motion_already_closed' => 409,
                default => 400,
            });
        }
        $motion = $r['motion'];
        $o = $r['o'];
        $meetingId = (string) $motion['meeting_id'];
        audit_log('motion_closed', 'motion', $motionId, ['meeting_id' => $meetingId]);
        try {
            EventBroadcaster::motionClosed($meetingId, $motionId, [
                'for' => $o['for'] ?? 0, 'against' => $o['against'] ?? 0,
                'abstain' => $o['abstain'] ?? 0, 'total' => $o['total'] ?? 0,
                'decision' => $o['decision'] ?? 'unknown', 'reason' => $o['reason'] ?? null,
            ]);
        } catch (Throwable) {
        }
        api_ok([
            'meeting_id' => $meetingId,
            'closed_motion_id' => $motionId,
            'results' => ['for' => $o['for'] ?? 0, 'against' => $o['against'] ?? 0, 'abstain' => $o['abstain'] ?? 0, 'total' => $o['total'] ?? 0, 'decision' => $o['decision'] ?? 'unknown', 'reason' => $o['reason'] ?? null],
            'eligible_count' => $r['eligible_count'] ?? 0,
            'votes_cast' => $o['total'] ?? 0,
        ]);
    }

    public function degradedTally(): void {
        $input = api_request('POST');
        $motionId = trim((string) ($input['motion_id'] ?? ''));
        if ($motionId === '') {
            api_fail('missing_motion_id', 422);
        }
        $justification = trim((string) ($input['justification'] ?? ''));
        if ($justification === '') {
            api_fail('missing_justification', 422, ['detail' => 'Une justification est obligatoire en mode dégradé.']);
        }
        try {
            $result = $this->motionsService()->degradedTally([
                'motion_id' => $motionId,
                'manual_total' => isset($input['manual_total']) ? (int) $input['manual_total'] : 0,
                'manual_for' => isset($input['manual_for']) ? (int) $input['manual_for'] : 0,
                'manual_against' => isset($input['manual_against']) ? (int) $input['manual_against'] : 0,
                'manual_abstain' => isset($input['manual_abstain']) ? (int) $input['manual_abstain'] : 0,
                'justification' => $justification,
            ], api_current_tenant_id());
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            $statusMap = ['motion_not_found' => 404, 'invalid_total' => 422, 'invalid_numbers' => 422, 'vote_exceeds_total' => 422, 'inconsistent_tally' => 422];
            api_fail($code, $statusMap[$code] ?? 400);
        }
        api_ok(['meeting_id' => $result['meeting_id'], 'motion_id' => $result['motion_id'], 'manual_total' => $result['manual_total'], 'manual_for' => $result['manual_for'], 'manual_against' => $result['manual_against'], 'manual_abstain' => $result['manual_abstain']]);
    }

    public function overrideDecision(): void {
        $input = api_request('POST');
        $motionId = trim((string) ($input['motion_id'] ?? ''));
        if ($motionId === '') {
            api_fail('missing_motion_id', 422);
        }
        $decision = trim((string) ($input['decision'] ?? ''));
        if (!in_array($decision, ['adopted', 'rejected'], true)) {
            api_fail('invalid_decision', 422, ['detail' => 'La décision doit être adopted ou rejected.']);
        }
        $justification = trim((string) ($input['justification'] ?? ''));
        if ($justification === '') {
            api_fail('missing_justification', 422, ['detail' => 'Une justification est obligatoire pour modifier la décision.']);
        }
        $tenantId = api_current_tenant_id();
        $row = $this->repo()->motion()->findWithMeetingTenant($motionId, $tenantId);
        if (!$row) {
            api_fail('motion_not_found', 404);
        }
        $meetingId = (string) $row['meeting_id'];
        if (empty($row['closed_at'])) {
            api_fail('motion_not_closed', 409, ['detail' => 'La résolution doit être clôturée avant de modifier la décision.']);
        }
        api_transaction(fn () => $this->repo()->motion()->overrideDecision($motionId, $decision, $justification, $tenantId));
        audit_log('decision_override', 'motion', $motionId, ['decision' => $decision, 'justification' => $justification, 'meeting_id' => $meetingId]);
        EventBroadcaster::motionClosed($meetingId, $motionId, ['title' => (string) ($row['motion_title'] ?? ''), 'decision' => $decision]);
        api_ok(['decision' => $decision]);
    }
}
