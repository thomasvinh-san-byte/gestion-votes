<?php

declare(strict_types=1);

namespace AgVote\Controller;

use DateTimeImmutable;

/**
 * Consolidates vote_tokens_generate.php.
 */
final class VoteTokenController extends AbstractController {
    public function generate(): void {
        $in = api_request('POST');

        $meetingId = trim((string) ($in['meeting_id'] ?? api_query('meeting_id')));
        $motionId = trim((string) ($in['motion_id'] ?? api_query('motion_id')));

        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('invalid_meeting_id', 400);
        }
        if ($motionId === '' || !api_is_uuid($motionId)) {
            api_fail('invalid_motion_id', 400);
        }

        api_guard_meeting_not_validated($meetingId);

        $tenant = api_current_tenant_id();

        $meeting = $this->repo()->meeting()->findByIdForTenant($meetingId, $tenant);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        $motion = $this->repo()->motion()->findByIdAndMeetingWithDates($motionId, $meetingId, $tenant);
        if (!$motion) {
            api_fail('motion_not_found', 404);
        }

        if ($motion['closed_at'] !== null) {
            api_fail('motion_closed', 409);
        }

        $voters = $this->repo()->attendance()->listEligibleVotersWithName($tenant, $meetingId);

        $ttlMinutes = (int) ($in['ttl_minutes'] ?? 0);
        if ($ttlMinutes <= 0) {
            $ttlMinutes = 180;
        }
        $expiresAt = (new DateTimeImmutable('+' . $ttlMinutes . ' minutes'))->format('Y-m-d H:i:sP');

        $generated = [];
        $createdCount = 0;
        $voteTokenRepo = $this->repo()->voteToken();

        // PERF-V27-02: pre-compute all hashes, then batch DELETE + batch INSERT
        // (was 2*N round-trips: one delete + one insert per voter).
        api_transaction(function () use ($voters, $voteTokenRepo, $meetingId, $motionId, $tenant, $expiresAt, &$generated, &$createdCount) {
            $rowsToInsert = [];
            $memberIds = [];
            foreach ($voters as $v) {
                $raw = api_uuid4();
                $hash = hash_hmac('sha256', $raw, APP_SECRET);
                $memberIds[] = $v['member_id'];
                $rowsToInsert[] = [
                    'token_hash' => $hash,
                    'tenant_id'  => $tenant,
                    'meeting_id' => $meetingId,
                    'member_id'  => $v['member_id'],
                    'motion_id'  => $motionId,
                    'expires_at' => $expiresAt,
                ];
                $generated[] = [
                    'member_id' => $v['member_id'],
                    'member_name' => $v['member_name'],
                    'token' => $raw,
                    'url' => '/vote.php?token=' . $raw,
                ];
                $createdCount++;
            }

            $voteTokenRepo->deleteUnusedByMotionAndMembers($meetingId, $motionId, $memberIds, $tenant);
            $voteTokenRepo->insertMany($rowsToInsert);
        });

        audit_log('vote_tokens_generated', 'motion', $motionId, [
            'meeting_id' => $meetingId,
            'motion_id' => $motionId,
            'count' => $createdCount,
            'ttl_minutes' => $ttlMinutes,
        ]);

        api_ok([
            'count' => $createdCount,
            'expires_in' => $ttlMinutes,
            'tokens' => $generated,
        ]);
    }
}
