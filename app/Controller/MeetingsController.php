<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Service\MeetingValidator;
use AgVote\Service\NotificationsService;

/**
 * Consolidates 8 meeting CRUD + status endpoints.
 *
 * Shared pattern: MeetingRepository, tenant validation, meeting_id.
 */
final class MeetingsController extends AbstractController
{
    public function index(): void
    {
        api_require_role('viewer');
        api_request('GET');

        $limit = (int)($_GET['limit'] ?? 50);
        if ($limit <= 0 || $limit > 200) {
            $limit = 50;
        }

        $activeOnly = filter_var($_GET['active_only'] ?? '0', FILTER_VALIDATE_BOOLEAN);

        $repo = new MeetingRepository();
        if ($activeOnly) {
            $rows = $repo->listActiveByTenantCompact(api_current_tenant_id(), $limit);
        } else {
            $rows = $repo->listByTenantCompact(api_current_tenant_id(), $limit);
        }

        api_ok(['meetings' => $rows]);
    }

    public function update(): void
    {
        api_require_role('operator');
        $input = api_request('POST');

        $meetingId = trim((string)($input['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400, ['detail' => 'meeting_id est obligatoire (uuid).']);
        }

        api_guard_meeting_not_validated($meetingId);

        $title = array_key_exists('title', $input) ? trim((string)$input['title']) : null;
        $presidentName = array_key_exists('president_name', $input) ? trim((string)$input['president_name']) : null;
        $scheduledAt = array_key_exists('scheduled_at', $input) ? trim((string)$input['scheduled_at']) : null;
        $meetingType = array_key_exists('meeting_type', $input) ? trim((string)$input['meeting_type']) : null;

        if (array_key_exists('status', $input)) {
            api_fail('status_via_transition', 400, [
                'detail' => 'Les transitions de statut doivent passer par /api/v1/meeting_transition.php.',
            ]);
        }

        if ($title !== null) {
            $len = mb_strlen($title);
            if ($len === 0) {
                api_fail('missing_title', 400, ['detail' => 'Le titre de la séance est obligatoire.']);
            }
            if ($len > 120) {
                api_fail('title_too_long', 400, ['detail' => 'Titre trop long (120 max).']);
            }
        }

        if ($presidentName !== null && mb_strlen($presidentName) > 200) {
            api_fail('president_name_too_long', 400, ['detail' => 'Nom du président trop long (200 max).']);
        }

        $validMeetingTypes = ['ag_ordinaire', 'ag_extraordinaire', 'conseil', 'bureau', 'autre'];
        if ($meetingType !== null && !in_array($meetingType, $validMeetingTypes, true)) {
            api_fail('invalid_meeting_type', 400, ['detail' => 'Type de séance invalide.', 'valid_types' => $validMeetingTypes]);
        }

        $repo = new MeetingRepository();
        $current = $repo->findByIdForTenant($meetingId, api_current_tenant_id());
        if (!$current) {
            api_fail('meeting_not_found', 404);
        }

        if ((string)$current['status'] === 'archived') {
            api_fail('meeting_archived_locked', 409, ['detail' => 'Séance archivée : modification interdite.']);
        }

        $fields = [];
        if ($title !== null) {
            $fields['title'] = $title;
        }
        if ($presidentName !== null) {
            $fields['president_name'] = $presidentName;
        }
        if ($scheduledAt !== null) {
            $fields['scheduled_at'] = $scheduledAt ?: null;
        }
        if ($meetingType !== null) {
            $fields['meeting_type'] = $meetingType;
        }

        if (!$fields) {
            api_ok(['updated' => false, 'meeting_id' => $meetingId]);
        }

        $updated = $repo->updateFields($meetingId, api_current_tenant_id(), $fields);

        audit_log('meeting_updated', 'meeting', $meetingId, [
            'fields' => array_keys($fields),
        ]);

        api_ok(['updated' => $updated > 0, 'meeting_id' => $meetingId]);
    }

    public function archive(): void
    {
        api_require_role('operator');
        api_request('GET');

        $from = trim($_GET['from'] ?? '');
        $to = trim($_GET['to'] ?? '');

        $repo = new MeetingRepository();
        $rows = $repo->listArchived(api_current_tenant_id(), $from, $to);
        api_ok(['meetings' => $rows]);
    }

    public function archivesList(): void
    {
        api_require_role('viewer');
        api_request('GET');

        $repo = new MeetingRepository();
        $rows = $repo->listArchivedWithReports(api_current_tenant_id());
        api_ok(['items' => $rows]);
    }

    public function status(): void
    {
        api_require_role('operator');
        api_request('GET');

        $repo = new MeetingRepository();
        $meeting = $repo->findCurrentForTenant(api_current_tenant_id());

        if (!$meeting) {
            api_fail('no_live_meeting', 404);
        }

        $counts = $repo->countMotionStats((string)$meeting['meeting_id']);

        $totalMotions = (int)($counts['total_motions'] ?? 0);
        $openMotions = (int)($counts['open_motions'] ?? 0);
        $closedWithoutTally = (int)($counts['closed_without_tally'] ?? 0);

        $validation = MeetingValidator::canBeValidated((string)$meeting['meeting_id'], api_current_tenant_id());
        $readyToSign = (bool)($validation['can'] ?? false);

        NotificationsService::emitReadinessTransitions((string)$meeting['meeting_id'], $validation, api_current_tenant_id());

        $signStatus = 'not_ready';
        $signMessage = "Séance en cours de traitement.";

        if ($meeting['meeting_status'] === 'archived') {
            $signStatus = 'archived';
            $signMessage = "Séance archivée le " . ($meeting['archived_at'] ?? '—');
        } elseif ($readyToSign) {
            $signStatus = 'ready';
            $signMessage = "Tout est prêt à être signé.";
        } elseif ($openMotions > 0) {
            $signStatus = 'open_motions';
            $signMessage = "$openMotions résolution(s) encore ouverte(s).";
        } elseif ($closedWithoutTally > 0) {
            $signStatus = 'missing_tally';
            $signMessage = "$closedWithoutTally résolution(s) clôturée(s) sans comptage complet.";
        }

        $response = array_merge($meeting, [
            'total_motions' => $totalMotions,
            'open_motions' => $openMotions,
            'closed_without_tally' => $closedWithoutTally,
            'ready_to_sign' => $readyToSign,
            'sign_status' => $signStatus,
            'sign_message' => $signMessage,
            'can_current_user_validate' => in_array(api_current_role(), ['president', 'admin'], true),
        ]);

        api_ok($response);
    }

    public function statusForMeeting(): void
    {
        api_require_role('auditor');
        api_request('GET');

        $meetingId = trim((string)($_GET['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 422);
        }

        $repo = new MeetingRepository();
        $meeting = $repo->findStatusFields($meetingId, api_current_tenant_id());
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        $validation = MeetingValidator::canBeValidated((string)$meetingId, api_current_tenant_id());
        $readyToSign = (bool)($validation['can'] ?? false);

        NotificationsService::emitReadinessTransitions((string)$meetingId, $validation, api_current_tenant_id());

        $signStatus = 'not_ready';
        $signMessage = '';
        if (!empty($meeting['validated_at'])) {
            $signStatus = 'validated';
            $signMessage = 'Séance validée.';
        } elseif ($readyToSign) {
            $signStatus = 'ready';
            $signMessage = 'Tout est prêt à être signé.';
        } else {
            $signMessage = 'Préparation incomplète.';
        }

        api_ok([
            'meeting_id' => $meeting['meeting_id'],
            'meeting_title' => $meeting['meeting_title'],
            'meeting_status' => $meeting['meeting_status'],
            'started_at' => $meeting['started_at'],
            'ended_at' => $meeting['ended_at'],
            'archived_at' => $meeting['archived_at'],
            'validated_at' => $meeting['validated_at'],
            'president_name' => $meeting['president_name'],
            'ready_to_sign' => $readyToSign,
            'sign_status' => $signStatus,
            'sign_message' => $signMessage,
        ]);
    }

    public function summary(): void
    {
        api_require_role(['operator', 'president', 'admin', 'auditor']);

        $meetingId = trim((string)($_GET['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        $tenantId = api_current_tenant_id();
        $repo = new MeetingRepository();

        $meeting = $repo->findSummaryFields($meetingId, $tenantId);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        $totalMembers = $repo->countActiveMembers($tenantId);
        $presentCount = $repo->countPresent($meetingId);
        $proxyCount = $repo->countProxy($meetingId);
        $absentCount = $totalMembers - $presentCount - $proxyCount;

        $motionsCount = $repo->countMotions($meetingId);
        $closedMotionsCount = $repo->countClosedMotions($meetingId);
        $openMotionsCount = $repo->countOpenMotions($meetingId);

        $adoptedCount = $repo->countAdoptedMotions($meetingId);
        $rejectedCount = $repo->countRejectedMotions($meetingId);

        $ballotsCount = $repo->countBallots($meetingId);
        $totalVotedWeight = $repo->sumBallotWeight($meetingId);
        $proxiesCount = $repo->countProxies($meetingId);
        $incidentsCount = $repo->countIncidents($meetingId);
        $manualVotesCount = $repo->countManualVotes($meetingId);

        api_ok([
            'meeting_id' => $meetingId,
            'meeting_title' => $meeting['title'],
            'status' => $meeting['status'],
            'validated_at' => $meeting['validated_at'],
            'president_name' => $meeting['president_name'],
            'data' => [
                'total_members' => $totalMembers,
                'present_count' => $presentCount,
                'proxy_count' => $proxyCount,
                'absent_count' => $absentCount,
                'motions_count' => $motionsCount,
                'closed_motions_count' => $closedMotionsCount,
                'open_motions_count' => $openMotionsCount,
                'adopted_count' => $adoptedCount,
                'rejected_count' => $rejectedCount,
                'ballots_count' => $ballotsCount,
                'total_voted_weight' => round($totalVotedWeight, 2),
                'proxies_count' => $proxiesCount,
                'incidents_count' => $incidentsCount,
                'manual_votes_count' => $manualVotesCount,
            ],
        ]);
    }

    public function stats(): void
    {
        api_require_role('public');
        api_rate_limit('meeting_stats', 120, 60);
        api_request('GET');

        $meetingId = trim($_GET['meeting_id'] ?? '');
        if ($meetingId === '') {
            api_fail('missing_meeting_id', 422);
        }

        $meetingRepo = new MeetingRepository();
        $motionRepo = new MotionRepository();

        if (!$meetingRepo->existsForTenant($meetingId, api_current_tenant_id())) {
            api_fail('meeting_not_found', 404);
        }

        $motionsCount = $meetingRepo->countMotions($meetingId);
        $rows = $motionRepo->listStatsForMeeting($meetingId);

        $motions = [];
        $totalBallotsAllMotions = 0;

        foreach ($rows as $r) {
            $ballotsTotal = (int)($r['ballots_total'] ?? 0);

            if ($ballotsTotal > 0) {
                $source = 'ballots';
                $total = $ballotsTotal;
                $votes_for = (int)($r['ballots_for'] ?? 0);
                $votes_against = (int)($r['ballots_against'] ?? 0);
                $votes_abstain = (int)($r['ballots_abstain'] ?? 0);
                $votes_nsp = (int)($r['ballots_nsp'] ?? 0);
                $totalBallotsAllMotions += $ballotsTotal;
            } else {
                $source = 'manual';
                $total = (int)($r['manual_total'] ?? 0);
                $votes_for = (int)($r['manual_for'] ?? 0);
                $votes_against = (int)($r['manual_against'] ?? 0);
                $votes_abstain = (int)($r['manual_abstain'] ?? 0);
                $votes_nsp = max(0, $total - $votes_for - $votes_against - $votes_abstain);
            }

            $motions[] = [
                'motion_id' => $r['motion_id'],
                'title' => $r['title'],
                'total' => $total,
                'votes_for' => $votes_for,
                'votes_against' => $votes_against,
                'votes_abstain' => $votes_abstain,
                'votes_nsp' => $votes_nsp,
                'tally_source' => $source,
                'manual_total' => (int)($r['manual_total'] ?? 0),
                'manual_for' => (int)($r['manual_for'] ?? 0),
                'manual_against' => (int)($r['manual_against'] ?? 0),
                'manual_abstain' => (int)($r['manual_abstain'] ?? 0),
                'ballots_total' => $ballotsTotal,
            ];
        }

        $distinctVoters = 0;
        if ($totalBallotsAllMotions > 0) {
            $distinctVoters = $motionRepo->countDistinctVoters($meetingId);
        } else {
            $distinctVoters = $motionRepo->maxManualTotal($meetingId);
        }

        api_ok([
            'meeting_id' => $meetingId,
            'motions_count' => (int)$motionsCount,
            'distinct_voters' => (int)$distinctVoters,
            'motions' => $motions,
        ]);
    }
}
