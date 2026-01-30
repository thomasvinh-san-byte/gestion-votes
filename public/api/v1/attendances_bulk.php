<?php
declare(strict_types=1);

/**
 * attendances_bulk.php - Actions en masse sur les présences
 *
 * POST /api/v1/attendances_bulk.php
 * Body: { "meeting_id": "uuid", "status": "present|absent|remote", "member_ids": ["uuid", ...] }
 *
 * Si member_ids n'est pas fourni, applique à tous les membres actifs.
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\AttendanceRepository;

api_require_role(['operator', 'admin']);

$input = api_request('POST');

$meetingId = trim((string)($input['meeting_id'] ?? ''));
if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400);
}

$mode = trim((string)($input['mode'] ?? $input['status'] ?? 'present'));
$validModes = ['present', 'absent', 'remote', 'proxy', 'excused'];
if (!in_array($mode, $validModes, true)) {
    api_fail('invalid_mode', 400, ['valid' => $validModes]);
}

$tenantId = api_current_tenant_id();

// Vérifier que la séance existe et n'est pas archivée
$meetingRepo = new MeetingRepository();
$meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);

if (!$meeting) {
    api_fail('meeting_not_found', 404);
}

if ($meeting['status'] === 'archived') {
    api_fail('meeting_archived', 409, ['detail' => 'Séance archivée, modification impossible']);
}

// Récupérer les membres ciblés
$memberIds = $input['member_ids'] ?? null;

if ($memberIds !== null && !is_array($memberIds)) {
    api_fail('invalid_member_ids', 400, ['detail' => 'member_ids doit être un tableau']);
}

// Si pas de member_ids, prendre tous les membres actifs
$memberRepo = new MemberRepository();
if ($memberIds === null || count($memberIds) === 0) {
    $members = $memberRepo->listActiveIds($tenantId);
    $memberIds = array_column($members, 'id');
}

if (count($memberIds) === 0) {
    api_fail('no_members', 400, ['detail' => 'Aucun membre à traiter']);
}

// Traitement en transaction
db()->beginTransaction();

try {
    $updated = 0;
    $created = 0;

    $attendanceRepo = new AttendanceRepository();

    foreach ($memberIds as $memberId) {
        if (!api_is_uuid($memberId)) {
            continue;
        }

        // Vérifier que le membre existe
        if (!$memberRepo->existsForTenant($memberId, $tenantId)) {
            continue;
        }

        // Upsert attendance
        $wasCreated = $attendanceRepo->upsertMode($meetingId, $memberId, $mode, $tenantId);
        if ($wasCreated) {
            $created++;
        } else {
            $updated++;
        }
    }

    db()->commit();

    // Audit log
    audit_log('attendances_bulk_update', 'attendance', $meetingId, [
        'mode' => $mode,
        'created' => $created,
        'updated' => $updated,
        'total' => $created + $updated,
    ], $meetingId);

    api_ok([
        'created' => $created,
        'updated' => $updated,
        'total' => $created + $updated,
        'mode' => $mode,
    ]);

} catch (\Throwable $e) {
    db()->rollBack();
    error_log("attendances_bulk error: " . $e->getMessage());
    api_fail('database_error', 500);
}
