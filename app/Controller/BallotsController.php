<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\BallotRepository;
use AgVote\Repository\ManualActionRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Service\BallotsService;
use AgVote\Service\VoteEngine;
use AgVote\WebSocket\EventBroadcaster;

/**
 * Consolidates 7 ballot/voting endpoints.
 *
 * Ranges from trivial (vote_incident: 5 lines) to complex
 * (manual_vote, ballots_cancel: transactions with multi-step validation).
 */
final class BallotsController extends AbstractController
{
    public function listForMotion(): void
    {
        api_require_role(['operator', 'admin', 'president']);
        api_request('GET');

        $motionId = trim((string)($_GET['motion_id'] ?? ''));
        if ($motionId === '' || !api_is_uuid($motionId)) {
            api_fail('missing_motion_id', 422, ['detail' => 'motion_id requis']);
        }

        $motionRepo = new MotionRepository();
        $motion = $motionRepo->findByIdForTenant($motionId, api_current_tenant_id());
        if (!$motion) {
            api_fail('motion_not_found', 404, ['detail' => 'Motion introuvable']);
        }

        $ballots = (new BallotRepository())->listForMotion($motionId, api_current_tenant_id());
        api_ok(['ballots' => $ballots]);
    }

    public function cast(): void
    {
        api_require_role('public');
        api_rate_limit('ballot_cast', 60, 60);
        $data = api_request('POST');

        $idempotencyKey = $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? null;
        if ($idempotencyKey) {
            $data['_idempotency_key'] = $idempotencyKey;
        }

        // Pass tenant_id for defense-in-depth tenant isolation in the query
        $data['_tenant_id'] = api_current_tenant_id();

        $ballot = BallotsService::castBallot($data);

        $motionId = $data['motion_id'] ?? $ballot['motion_id'] ?? null;
        $meetingId = $ballot['meeting_id'] ?? $data['meeting_id'] ?? null;
        audit_log('ballot.cast', 'motion', $motionId, [
            'member_id' => $data['member_id'] ?? $ballot['member_id'] ?? null,
            'choice' => $ballot['choice'] ?? $data['choice'] ?? null,
        ], $meetingId);

        api_ok(['ballot' => $ballot], 201);
    }

    public function cancel(): void
    {
        api_require_role(['operator', 'admin']);
        $in = api_request('POST');

        $motionId = api_require_uuid($in, 'motion_id');
        $memberId = api_require_uuid($in, 'member_id');
        $reason = trim((string)($in['reason'] ?? ''));

        if ($reason === '') {
            api_fail('missing_reason', 400, ['detail' => 'Une justification est requise pour annuler un vote.']);
        }

        $tenantId = api_current_tenant_id();
        $motionRepo = new MotionRepository();
        $ballotRepo = new BallotRepository();

        db()->beginTransaction();
        try {
            $motion = $motionRepo->findByIdForTenantForUpdate($motionId, $tenantId);
            if (!$motion) {
                db()->rollBack();
                api_fail('motion_not_found', 404);
            }

            $meetingId = $motion['meeting_id'];
            if ((new MeetingRepository())->isValidated($meetingId, $tenantId)) {
                db()->rollBack();
                api_fail('meeting_validated', 409, [
                    'detail' => 'Séance validée : modification interdite (séance figée).',
                ]);
            }

            if (!empty($motion['closed_at'])) {
                db()->rollBack();
                api_fail('motion_closed', 409, [
                    'detail' => 'Impossible d\'annuler un vote sur une résolution déjà clôturée.',
                ]);
            }

            $ballot = $ballotRepo->findByMotionAndMember($motionId, $memberId);
            if (!$ballot) {
                db()->rollBack();
                api_fail('ballot_not_found', 404, [
                    'detail' => 'Aucun bulletin trouvé pour ce membre sur cette résolution.',
                ]);
            }

            $source = $ballot['source'] ?? 'tablet';
            if ($source !== 'manual') {
                db()->rollBack();
                api_fail('not_manual_vote', 422, [
                    'detail' => 'Seuls les votes manuels (source=manual) peuvent être annulés par l\'opérateur.',
                ]);
            }

            $ballotRepo->deleteByMotionAndMember($motionId, $memberId, $tenantId);
            db()->commit();

            audit_log('ballot_cancelled', 'ballot', $motionId, [
                'member_id' => $memberId,
                'value' => $ballot['value'] ?? null,
                'weight' => $ballot['weight'] ?? null,
                'reason' => $reason,
                'motion_title' => $motion['title'] ?? '',
            ], $motion['meeting_id']);

            try {
                EventBroadcaster::motionUpdated($motion['meeting_id'], $motionId, [
                    'ballot_cancelled' => true,
                    'member_id' => $memberId,
                ]);
            } catch (\Throwable $e) {
                // Don't fail if broadcast fails
            }

            api_ok([
                'cancelled' => true,
                'motion_id' => $motionId,
                'member_id' => $memberId,
            ]);
        } catch (\Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            api_fail('cancel_failed', 500, ['detail' => 'Échec de l\'annulation du vote.']);
        }
    }

    public function result(): void
    {
        api_require_role('public');
        api_rate_limit('ballot_result', 120, 60);
        $params = api_request('GET');

        $motionId = trim((string)($params['motion_id'] ?? ''));
        if ($motionId === '') {
            api_fail('missing_motion_id', 422, ['detail' => 'Paramètre motion_id obligatoire']);
        }

        $result = VoteEngine::computeMotionResult($motionId);
        api_ok($result);
    }

    public function manualVote(): void
    {
        api_require_role('operator');
        $data = api_request('POST');

        $tenantId = api_current_tenant_id();
        $meetingId = trim((string)($data['meeting_id'] ?? ''));
        $motionId = trim((string)($data['motion_id'] ?? ''));
        $memberId = trim((string)($data['member_id'] ?? ''));
        $voteUi = trim((string)($data['vote'] ?? ''));
        $justif = trim((string)($data['justification'] ?? ''));

        if ($meetingId === '' || $motionId === '' || $memberId === '') {
            api_fail('missing_fields', 400, ['required' => ['meeting_id', 'motion_id', 'member_id']]);
        }
        if ($justif === '') {
            api_fail('missing_justification', 400);
        }

        $map = [
            'pour' => 'for', 'contre' => 'against', 'abstention' => 'abstain', 'blanc' => 'nsp',
            'for' => 'for', 'against' => 'against', 'abstain' => 'abstain', 'nsp' => 'nsp',
        ];
        if (!isset($map[$voteUi])) {
            api_fail('invalid_vote', 400);
        }
        $value = $map[$voteUi];

        $meetingRepo = new MeetingRepository();
        $motionRepo = new MotionRepository();
        $memberRepo = new MemberRepository();
        $ballotRepo = new BallotRepository();
        $manualRepo = new ManualActionRepository();

        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }
        if (!empty($meeting['validated_at'])) {
            api_fail('meeting_validated', 409);
        }

        $motion = $motionRepo->findForMeetingWithState($tenantId, $motionId, $meetingId);
        if (!$motion) {
            api_fail('motion_not_found', 404);
        }
        if (empty($motion['opened_at']) || !empty($motion['closed_at'])) {
            api_fail('motion_not_open', 409);
        }

        $member = $memberRepo->findActiveWithWeight($tenantId, $memberId);
        if (!$member) {
            api_fail('member_not_found', 404);
        }

        $weight = (string)($member['vote_weight'] ?? '1.0');

        db()->beginTransaction();
        try {
            $ballotId = $ballotRepo->insertManual($tenantId, $meetingId, $motionId, $memberId, $value, $weight);

            $val = [
                'ballot_id' => $ballotId,
                'motion_id' => $motionId,
                'member_id' => $memberId,
                'value' => $value,
                'weight' => $weight,
            ];

            $manualRepo->create(
                $tenantId, $meetingId, $motionId, $memberId,
                'manual_vote',
                json_encode($val, JSON_UNESCAPED_UNICODE),
                $justif
            );

            db()->commit();

            audit_log('ballot.manual_vote', 'ballot', $ballotId, [
                'motion_id' => $motionId,
                'member_id' => $memberId,
                'value' => $value,
            ], $meetingId);

            api_ok(['ballot_id' => $ballotId, 'value' => $value, 'source' => 'manual']);
        } catch (\Throwable $e) {
            db()->rollBack();
            $msg = $e->getMessage();
            if (stripos($msg, 'unique') !== false || stripos($msg, 'ballots_motion_id_member_id') !== false) {
                api_fail('already_voted', 409);
            }
            api_fail('server_error', 500, ['detail' => 'manual_vote_failed']);
        }
    }

    public function redeemPaperBallot(): void
    {
        api_require_role('operator');
        $in = api_request('POST');

        $code = trim((string)($in['code'] ?? ''));
        if ($code === '' || !api_is_uuid($code)) {
            api_fail('invalid_code', 400);
        }

        $vote = trim((string)($in['vote_value'] ?? ''));
        if (!in_array($vote, ['pour', 'contre', 'abstention', 'blanc'], true)) {
            api_fail('invalid_vote_value', 400);
        }

        $just = trim((string)($in['justification'] ?? 'vote papier (secours)'));
        if ($just === '') {
            api_fail('missing_justification', 400);
        }

        $hash = hash_hmac('sha256', $code, APP_SECRET);

        $ballotRepo = new BallotRepository();
        $manualRepo = new ManualActionRepository();

        $pb = $ballotRepo->findUnusedPaperBallotByHash($hash);
        if (!$pb) {
            api_fail('paper_ballot_not_found_or_used', 404);
        }

        db()->beginTransaction();
        try {
            $ballotRepo->markPaperBallotUsed($pb['id']);
            $manualRepo->createPaperBallotAction($pb['tenant_id'], $pb['meeting_id'], $pb['motion_id'], $vote, $just);
            db()->commit();
        } catch (\Throwable $e) {
            db()->rollBack();
            api_fail('paper_ballot_redeem_failed', 500, ['detail' => 'Erreur lors de l\'enregistrement du vote papier.']);
        }

        audit_log('paper_ballot_redeemed', 'motion', $pb['motion_id'], [
            'meeting_id' => $pb['meeting_id'],
            'vote_value' => $vote,
            'paper_ballot_id' => $pb['id'],
        ]);

        api_ok(['saved' => true]);
    }

    public function reportIncident(): void
    {
        api_require_role('public');
        api_rate_limit('vote_incident', 30, 60);
        $in = api_request('POST');

        $kind = trim((string)($in['kind'] ?? 'network'));
        $detail = trim((string)($in['detail'] ?? ''));
        $tokenHash = trim((string)($in['token_hash'] ?? ''));

        if ($kind === '') {
            api_fail('missing_kind', 400);
        }

        audit_log('vote_incident', 'vote', $tokenHash ?: null, [
            'kind' => $kind,
            'detail' => $detail,
        ]);

        api_ok(['saved' => true]);
    }
}
