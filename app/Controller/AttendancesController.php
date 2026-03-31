<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Service\AttendancesService;
use AgVote\Service\QuorumEngine;
use AgVote\SSE\EventBroadcaster;
use Throwable;

/**
 * Consolidates 4 attendance endpoints.
 *
 * Shared pattern: meeting_id validation + meeting not validated guard.
 */
final class AttendancesController extends AbstractController {
    public function listForMeeting(): void {
        api_request('GET');

        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('invalid_request', 422, ['detail' => 'meeting_id est obligatoire']);
        }

        $tenantId = api_current_tenant_id();
        $meeting = $this->repo()->meeting()->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            api_fail('meeting_not_found', 404, ['detail' => 'Séance introuvable']);
        }

        $svc = new AttendancesService();
        api_ok([
            'items' => $svc->listForMeeting($meetingId, $tenantId),
            'summary' => $svc->summaryForMeeting($meetingId, $tenantId),
        ]);
    }

    public function upsert(): void {
        $data = api_request('POST');

        $meetingId = trim((string) ($data['meeting_id'] ?? ''));
        $memberId = trim((string) ($data['member_id'] ?? ''));
        $mode = trim((string) ($data['mode'] ?? ''));
        $notes = isset($data['notes']) ? (string) $data['notes'] : null;

        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('invalid_meeting_id', 400);
        }
        if ($memberId === '' || !api_is_uuid($memberId)) {
            api_fail('invalid_member_id', 400);
        }

        api_guard_meeting_not_validated($meetingId);
        $tenantId = api_current_tenant_id();

        $row = (new AttendancesService())->upsert($meetingId, $memberId, $mode, $tenantId, $notes);

        audit_log('attendance.upsert', 'attendance', $memberId, [
            'meeting_id' => $meetingId,
            'mode' => $mode,
        ], $meetingId);

        // Broadcast quorum update after attendance change
        try {
            $quorumResult = (new QuorumEngine())->computeForMeeting($meetingId, $tenantId);
            EventBroadcaster::quorumUpdated($meetingId, $quorumResult);
        } catch (Throwable) {
            // Non-blocking — quorum broadcast failure doesn't affect the response
        }

        api_ok(['attendance' => $row]);
    }

    public function bulk(): void {
        $input = api_request('POST');

        $meetingId = trim((string) ($input['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        $mode = trim((string) ($input['mode'] ?? $input['status'] ?? 'present'));
        $validModes = ['present', 'absent', 'remote', 'proxy', 'excused'];
        if (!in_array($mode, $validModes, true)) {
            api_fail('invalid_mode', 400, ['valid' => $validModes]);
        }

        $tenantId = api_current_tenant_id();
        $meetingRepo = $this->repo()->meeting();
        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }
        if ($meeting['status'] === 'archived') {
            api_fail('meeting_archived', 409, ['detail' => 'Séance archivée, modification impossible']);
        }

        $memberIds = $input['member_ids'] ?? null;
        if ($memberIds !== null && !is_array($memberIds)) {
            api_fail('invalid_member_ids', 400, ['detail' => 'member_ids doit être un tableau']);
        }

        $memberRepo = $this->repo()->member();
        if ($memberIds === null || count($memberIds) === 0) {
            $members = $memberRepo->listActiveIds($tenantId);
            $memberIds = array_column($members, 'id');
        }
        if (count($memberIds) === 0) {
            api_fail('no_members', 400, ['detail' => 'Aucun membre à traiter']);
        }

        $updated = 0;
        $created = 0;
        $attendanceRepo = $this->repo()->attendance();

        // Filter valid UUIDs first, then batch-validate membership (avoids N+1)
        $validUuids = array_filter($memberIds, fn($id) => api_is_uuid($id));
        $existingIds = $memberRepo->filterExistingIds($validUuids, $tenantId);
        $existingSet = array_flip($existingIds);

        api_transaction(function () use ($existingIds, $attendanceRepo, $meetingId, $mode, $tenantId, &$created, &$updated) {
            foreach ($existingIds as $memberId) {
                $wasCreated = $attendanceRepo->upsertMode($meetingId, $memberId, $mode, $tenantId);
                if ($wasCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }
        });

        audit_log('attendances_bulk_update', 'attendance', $meetingId, [
            'mode' => $mode,
            'created' => $created,
            'updated' => $updated,
            'total' => $created + $updated,
        ], $meetingId);

        try {
            $stats = $attendanceRepo->getStatsByMode($meetingId, $tenantId);
            EventBroadcaster::attendanceUpdated($meetingId, $stats);

            // Recalculate quorum after bulk attendance change
            $quorumResult = (new QuorumEngine())->computeForMeeting($meetingId, $tenantId);
            EventBroadcaster::quorumUpdated($meetingId, $quorumResult);
        } catch (Throwable $e) {
            error_log('[SSE] Broadcast failed after attendance update: ' . $e->getMessage());
        }

        api_ok([
            'created' => $created,
            'updated' => $updated,
            'total' => $created + $updated,
            'mode' => $mode,
        ]);
    }

    public function setPresentFrom(): void {
        $in = api_request('POST');

        $meetingId = api_require_uuid($in, 'meeting_id');
        api_guard_meeting_not_validated($meetingId);
        $memberId = api_require_uuid($in, 'member_id');

        $presentFrom = trim((string) ($in['present_from_at'] ?? ''));

        $this->repo()->attendance()->updatePresentFrom($meetingId, $memberId, $presentFrom === '' ? null : $presentFrom, api_current_tenant_id());

        audit_log($presentFrom !== '' ? 'attendance_present_from_set' : 'attendance_present_from_cleared', 'meeting', $meetingId, [
            'member_id' => $memberId,
            'present_from_at' => $presentFrom !== '' ? $presentFrom : null,
        ]);

        api_ok(['saved' => true]);
    }
}
