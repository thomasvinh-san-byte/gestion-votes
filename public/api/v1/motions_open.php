<?php
// public/api/v1/motions_open.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

api_require_role('operator');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_err('method_not_allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$motionId = trim((string)($input['motion_id'] ?? ''));
if ($motionId === '' || !api_is_uuid($motionId)) {
    json_err('invalid_motion_id', 422);
}

$expectedMeetingId = trim((string)($input['meeting_id'] ?? ''));
if ($expectedMeetingId !== '' && !api_is_uuid($expectedMeetingId)) {
    json_err('invalid_meeting_id', 422);
}

try {
    $row = db_select_one(
        "SELECT
            m.id AS motion_id,
            m.meeting_id,
            m.title,
            m.opened_at,
            m.closed_at,
            m.vote_policy_id,
            m.quorum_policy_id,
            mt.status AS meeting_status,
            mt.validated_at AS meeting_validated_at,
            mt.quorum_policy_id AS meeting_quorum_policy_id,
            mt.vote_policy_id   AS meeting_vote_policy_id
         FROM motions m
         JOIN meetings mt ON mt.id = m.meeting_id
         WHERE m.tenant_id = :tid AND m.id = :id",
        [':tid' => DEFAULT_TENANT_ID, ':id' => $motionId]
    );

    if (!$row) json_err('motion_not_found', 404);

    $meetingId = (string)$row['meeting_id'];
    if ($expectedMeetingId !== '' && $meetingId !== $expectedMeetingId) {
        json_err('meeting_mismatch', 409, ['detail' => "La motion n'appartient pas à cette séance."]);
    }

    $meetingStatus = (string)($row['meeting_status'] ?? '');
    if ($meetingStatus === 'archived') {
        json_err('meeting_archived_locked', 409, ['detail' => "Séance archivée : action interdite."]);
    }
    if ($meetingStatus !== 'live') {
        json_err('meeting_not_live', 409, ['detail' => "La séance doit être en cours (live) pour ouvrir une motion."]);
    }

    if (!empty($row['closed_at'])) {
        json_err('motion_closed_locked', 409, ['detail' => "Motion clôturée : ré-ouverture interdite."]);
    }

    $title = trim((string)($row['title'] ?? ''));
    if ($title === '') {
        json_err('motion_invalid_title', 422, ['detail' => "Titre vide : impossible d'ouvrir la motion."]);
    }

    $effectiveVotePolicyId = (string)($row['vote_policy_id'] ?? '');
    if ($effectiveVotePolicyId === '') {
        $effectiveVotePolicyId = (string)($row['meeting_vote_policy_id'] ?? '');
    }

    $effectiveQuorumPolicyId = (string)($row['quorum_policy_id'] ?? '');
    if ($effectiveQuorumPolicyId === '') {
        $effectiveQuorumPolicyId = (string)($row['meeting_quorum_policy_id'] ?? '');
    }

    if ($effectiveVotePolicyId === '' || !api_is_uuid($effectiveVotePolicyId)) {
        json_err('motion_missing_vote_policy', 422, ['detail' => "Policy de vote absente : impossible d'ouvrir la motion."]);
    }
    if ($effectiveQuorumPolicyId === '' || !api_is_uuid($effectiveQuorumPolicyId)) {
        json_err('motion_missing_quorum_policy', 422, ['detail' => "Policy de quorum absente : impossible d'ouvrir la motion."]);
    }

    $pdo = db();
    $pdo->beginTransaction();
// Lock meeting row to prevent concurrent opens
$mt = db_select_one(
    "SELECT id, validated_at, status FROM meetings WHERE tenant_id=:tid AND id=:id FOR UPDATE",
    [':tid' => DEFAULT_TENANT_ID, ':id' => $meetingId]
);
if (!$mt) {
    $pdo->rollBack();
    json_err('meeting_not_found', 404);
}
if (!empty($mt['validated_at'])) {
    $pdo->rollBack();
    json_err('meeting_validated', 409, ['detail' => "Séance validée : action interdite."]);
}


    $open = db_select_one(
        "SELECT id FROM motions
         WHERE tenant_id=:tid AND meeting_id=:mid
           AND opened_at IS NOT NULL AND closed_at IS NULL
         LIMIT 1
         FOR UPDATE",
        [':tid' => DEFAULT_TENANT_ID, ':mid' => $meetingId]
    );

    if ($open && (string)$open['id'] !== $motionId) {
        $pdo->rollBack();
        json_err('another_motion_active', 409, [
            'detail' => "Une motion est déjà ouverte : veuillez la clôturer avant d'en ouvrir une autre.",
            'open_motion_id' => (string)$open['id'],
        ]);
    }

    $updated = db_execute(
        "UPDATE motions
         SET opened_at = COALESCE(opened_at, now()),
             closed_at = NULL
         WHERE tenant_id=:tid AND id=:id AND closed_at IS NULL",
        [':tid' => DEFAULT_TENANT_ID, ':id' => $motionId]
    );

    if ($updated === 0) {
        $pdo->rollBack();
        json_err('motion_open_failed', 409, ['detail' => "Impossible d'ouvrir la motion."]);
    }

    db_execute(
        "UPDATE meetings
         SET current_motion_id = :mo, updated_at = now()
         WHERE tenant_id=:tid AND id=:mid",
        [':tid' => DEFAULT_TENANT_ID, ':mid' => $meetingId, ':mo' => $motionId]
    );

    $pdo->commit();

    audit_log('motion_opened', 'motion', $motionId, [
        'meeting_id' => $meetingId,
        'effective_vote_policy_id' => $effectiveVotePolicyId,
        'effective_quorum_policy_id' => $effectiveQuorumPolicyId,
    ]);

    json_ok([
        'meeting_id' => $meetingId,
        'current_motion_id' => $motionId,
    ]);
} catch (Throwable $e) {
    $pdo = db();
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_err('motion_open_failed', 500, ['detail' => $e->getMessage()]);
}
