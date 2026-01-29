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

api_require_role(['operator', 'admin']);

$input = api_request('POST');

$meetingId = trim((string)($input['meeting_id'] ?? ''));
if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400);
}

$status = trim((string)($input['status'] ?? 'present'));
$validStatuses = ['present', 'absent', 'remote', 'proxy', 'excused'];
if (!in_array($status, $validStatuses, true)) {
    api_fail('invalid_status', 400, ['valid' => $validStatuses]);
}

$tenantId = api_current_tenant_id();

// Vérifier que la séance existe et n'est pas archivée
$meeting = db_one("
    SELECT id, status FROM meetings 
    WHERE tenant_id = ? AND id = ?
", [$tenantId, $meetingId]);

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
if ($memberIds === null || count($memberIds) === 0) {
    $members = db_all("
        SELECT id FROM members 
        WHERE tenant_id = ? AND is_active = true
    ", [$tenantId]);
    $memberIds = array_column($members, 'id');
}

if (count($memberIds) === 0) {
    api_fail('no_members', 400, ['detail' => 'Aucun membre à traiter']);
}

// Traitement en transaction
$pdo = db();
$pdo->beginTransaction();

try {
    $updated = 0;
    $created = 0;
    $now = date('c');

    foreach ($memberIds as $memberId) {
        if (!api_is_uuid($memberId)) {
            continue;
        }

        // Vérifier que le membre existe
        $member = db_one("SELECT id FROM members WHERE tenant_id = ? AND id = ?", [$tenantId, $memberId]);
        if (!$member) {
            continue;
        }

        // Upsert attendance
        $existing = db_one("
            SELECT id FROM attendances 
            WHERE meeting_id = ? AND member_id = ?
        ", [$meetingId, $memberId]);

        if ($existing) {
            db_exec("
                UPDATE attendances 
                SET status = ?, updated_at = ?
                WHERE id = ?
            ", [$status, $now, $existing['id']]);
            $updated++;
        } else {
            db_exec("
                INSERT INTO attendances (id, meeting_id, member_id, status, created_at, updated_at)
                VALUES (gen_random_uuid(), ?, ?, ?, ?, ?)
            ", [$meetingId, $memberId, $status, $now, $now]);
            $created++;
        }
    }

    $pdo->commit();

    // Audit log
    audit_log('attendances_bulk_update', 'attendance', $meetingId, [
        'status' => $status,
        'created' => $created,
        'updated' => $updated,
        'total' => $created + $updated,
    ], $meetingId);

    api_ok([
        'created' => $created,
        'updated' => $updated,
        'total' => $created + $updated,
        'status' => $status,
    ]);

} catch (\Throwable $e) {
    $pdo->rollBack();
    error_log("attendances_bulk error: " . $e->getMessage());
    api_fail('database_error', 500);
}
