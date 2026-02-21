<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\VoteTokenRepository;
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

        $meeting = (new MeetingRepository())->findByIdForTenant($meetingId, $tenant);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        $motion = (new MotionRepository())->findByIdAndMeetingWithDates($motionId, $meetingId);
        if (!$motion) {
            api_fail('motion_not_found', 404);
        }

        if ($motion['closed_at'] !== null) {
            api_fail('motion_closed', 409);
        }

        $voters = (new AttendanceRepository())->listEligibleVotersWithName($tenant, $meetingId);

        $ttlMinutes = (int) ($in['ttl_minutes'] ?? 0);
        if ($ttlMinutes <= 0) {
            $ttlMinutes = 180;
        }
        $expiresAt = (new DateTimeImmutable('+' . $ttlMinutes . ' minutes'))->format('Y-m-d H:i:sP');

        $generated = [];
        $createdCount = 0;
        $voteTokenRepo = new VoteTokenRepository();

        api_transaction(function () use ($voters, $voteTokenRepo, $meetingId, $motionId, $tenant, $expiresAt, &$generated, &$createdCount) {
            foreach ($voters as $v) {
                $raw = api_uuid4();
                $hash = hash_hmac('sha256', $raw, APP_SECRET);

                $voteTokenRepo->deleteUnusedByMotionAndMember($meetingId, $motionId, $v['member_id'], $tenant);
                $voteTokenRepo->insert($hash, $tenant, $meetingId, $v['member_id'], $motionId, $expiresAt);

                $createdCount++;

                $generated[] = [
                    'member_id' => $v['member_id'],
                    'member_name' => $v['member_name'],
                    'token' => $raw,
                    'url' => '/vote.php?token=' . $raw,
                ];
            }
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
