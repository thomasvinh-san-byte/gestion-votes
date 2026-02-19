<?php
// public/api/v1/preparation_convocations_generate.php
// POST: generate convocation entries for all members of a meeting
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

try {
    api_require_role('operator');
    $in = api_request('POST');

    $meetingId = api_require_uuid($in, 'meeting_id');

    // Verify meeting exists
    $meeting = db_one(
        "SELECT id FROM meetings WHERE tenant_id = :tid AND id = :mid",
        [':tid' => DEFAULT_TENANT_ID, ':mid' => $meetingId]
    );
    if (!$meeting) api_fail('meeting_not_found', 404);

    // Get all active members that don't already have a convocation for this meeting
    $members = db_all(
        "SELECT m.id AS member_id
         FROM members m
         WHERE m.tenant_id = :tid
           AND m.id NOT IN (
               SELECT c.member_id FROM preparation_convocations c
               WHERE c.tenant_id = :tid2 AND c.meeting_id = :mid
           )
         ORDER BY m.display_name",
        [':tid' => DEFAULT_TENANT_ID, ':tid2' => DEFAULT_TENANT_ID, ':mid' => $meetingId]
    );

    if (empty($members)) {
        api_ok(['generated' => 0, 'message' => 'Tous les membres ont deja une convocation.']);
        exit;
    }

    $method = trim((string)($in['method'] ?? 'email'));
    $validMethods = ['email', 'courrier', 'main_propre', 'affichage'];
    if (!in_array($method, $validMethods, true)) {
        $method = 'email';
    }

    $pdo = db();
    $pdo->beginTransaction();

    $count = 0;
    $stmt = $pdo->prepare(
        "INSERT INTO preparation_convocations
            (id, tenant_id, meeting_id, member_id, method, status)
         VALUES (:id, :tid, :mid, :member_id, :method, 'draft')
         ON CONFLICT (tenant_id, meeting_id, member_id) DO NOTHING"
    );

    foreach ($members as $member) {
        $stmt->execute([
            ':id' => api_uuid4(),
            ':tid' => DEFAULT_TENANT_ID,
            ':mid' => $meetingId,
            ':member_id' => $member['member_id'],
            ':method' => $method,
        ]);
        $count += $stmt->rowCount();
    }

    $pdo->commit();

    audit_log('convocations_generated', 'preparation_convocation', null, [
        'meeting_id' => $meetingId,
        'generated' => $count,
    ], $meetingId);

    api_ok(['generated' => $count]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('preparation_convocations_generate.php error: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
