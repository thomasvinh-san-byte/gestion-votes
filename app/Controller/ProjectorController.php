<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;

/**
 * Consolidates projector_state.php.
 */
final class ProjectorController extends AbstractController
{
    public function state(): void
    {
        api_request('GET');

        $meetingRepo = new MeetingRepository();
        $motionRepo  = new MotionRepository();
        $tenantId    = api_current_tenant_id();

        $requestedId = trim((string)($_GET['meeting_id'] ?? ''));

        // --- Meeting resolution ---
        if ($requestedId !== '') {
            $meeting = $meetingRepo->findByIdForTenant($requestedId, $tenantId);
            if (!$meeting || ($meeting['status'] ?? '') === 'archived') {
                api_fail('meeting_not_found', 404);
            }
            $meetingId     = (string)$meeting['id'];
            $meetingTitle  = (string)$meeting['title'];
            $meetingStatus = (string)$meeting['status'];
        } else {
            $liveMeetings = $meetingRepo->listLiveForTenant($tenantId);

            if (count($liveMeetings) === 0) {
                api_fail('no_live_meeting', 404);
            }

            if (count($liveMeetings) > 1) {
                api_ok([
                    'choose'   => true,
                    'meetings' => array_map(fn($m) => [
                        'id'         => (string)$m['id'],
                        'title'      => (string)$m['title'],
                        'started_at' => (string)($m['started_at'] ?? ''),
                    ], $liveMeetings),
                ]);
            }

            $m = $liveMeetings[0];
            $meetingId     = (string)$m['id'];
            $meetingTitle  = (string)$m['title'];
            $meetingStatus = (string)($m['status'] ?? 'live');
        }

        // --- Motion state ---
        $open   = $motionRepo->findOpenForProjector($meetingId);
        $closed = $motionRepo->findLastClosedForProjector($meetingId);

        $phase  = 'idle';
        $motion = null;

        if ($open) {
            $phase = 'active';
            $motion = [
                'id'          => (string)$open['id'],
                'title'       => (string)$open['title'],
                'description' => (string)($open['description'] ?? ''),
                'body'        => (string)($open['body'] ?? ''),
                'secret'      => (bool)$open['secret'],
                'position'    => $open['position'] !== null ? (int)$open['position'] : null,
            ];
        } elseif ($closed) {
            $phase = 'closed';
            $motion = [
                'id'          => (string)$closed['id'],
                'title'       => (string)$closed['title'],
                'description' => (string)($closed['description'] ?? ''),
                'body'        => (string)($closed['body'] ?? ''),
                'secret'      => (bool)$closed['secret'],
                'position'    => $closed['position'] !== null ? (int)$closed['position'] : null,
            ];
        }

        $totalMotions  = $motionRepo->countForMeeting($meetingId);
        $eligibleCount = $meetingRepo->countActiveMembers($tenantId);

        api_ok([
            'meeting_id'     => $meetingId,
            'meeting_title'  => $meetingTitle,
            'meeting_status' => $meetingStatus,
            'phase'          => $phase,
            'motion'         => $motion,
            'total_motions'  => $totalMotions,
            'eligible_count' => $eligibleCount,
        ]);
    }
}
