<?php
declare(strict_types=1);

// Mode dégradé minimal : vote saisi manuellement par l'opérateur
// - insère un ballot source=manual (et respecte l'unicité motion_id+member_id)
// - écrit une trace append-only dans manual_actions avec justification
// - refuser si meeting validé (409 meeting_validated)
// - refuser si motion pas ouverte (409 motion_not_open)

require __DIR__ . '/../../../app/api.php';

require_role('operator');

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$data = [];
$ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

if (is_string($ct) && stripos($ct, 'application/json') !== false) {
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = [];
} else {
    // fallback form-encoded
    $data = $_POST ?? [];
}

$tenantId  = (string)($data['tenant_id'] ?? DEFAULT_TENANT_ID);
$meetingId = trim((string)($data['meeting_id'] ?? ''));
$motionId  = trim((string)($data['motion_id'] ?? ''));
$memberId  = trim((string)($data['member_id'] ?? ''));
$voteUi    = trim((string)($data['vote'] ?? ''));
$justif    = trim((string)($data['justification'] ?? ''));

if ($meetingId === '' || $motionId === '' || $memberId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_fields', 'required' => ['meeting_id','motion_id','member_id']]);
    exit;
}
if ($justif === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_justification']);
    exit;
}

// Mapping identique à vote.php (UI FR -> ENUM DB)
$map = [
    'pour' => 'for',
    'contre' => 'against',
    'abstention' => 'abstain',
    'blanc' => 'nsp',
    // autoriser aussi l'ENUM direct si on l'envoie déjà
    'for' => 'for',
    'against' => 'against',
    'abstain' => 'abstain',
    'nsp' => 'nsp',
];
if (!isset($map[$voteUi])) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_vote']);
    exit;
}
$value = $map[$voteUi];

// 1) Vérifs intégrité
$meeting = db_select_one(
    "SELECT id, validated_at
     FROM meetings
     WHERE tenant_id = ? AND id = ?
     LIMIT 1",
    [$tenantId, $meetingId]
);
if (!$meeting) {
    http_response_code(404);
    echo json_encode(['error' => 'meeting_not_found']);
    exit;
}
if (!empty($meeting['validated_at'])) {
    http_response_code(409);
    echo json_encode(['error' => 'meeting_validated']);
    exit;
}

$motion = db_select_one(
    "SELECT id, opened_at, closed_at
     FROM motions
     WHERE tenant_id = ? AND id = ? AND meeting_id = ?
     LIMIT 1",
    [$tenantId, $motionId, $meetingId]
);
if (!$motion) {
    http_response_code(404);
    echo json_encode(['error' => 'motion_not_found']);
    exit;
}
if (empty($motion['opened_at']) || !empty($motion['closed_at'])) {
    http_response_code(409);
    echo json_encode(['error' => 'motion_not_open']);
    exit;
}

$member = db_select_one(
    "SELECT id, vote_weight
     FROM members
     WHERE tenant_id = ? AND id = ? AND is_active = true
     LIMIT 1",
    [$tenantId, $memberId]
);
if (!$member) {
    http_response_code(404);
    echo json_encode(['error' => 'member_not_found']);
    exit;
}

$weight = (string)($member['vote_weight'] ?? '1.0');

// 2) Insert ballot + manual action atomiquement
$pdo->beginTransaction();
try {
    // Ballot : unique (motion_id, member_id) au niveau DB
    $stmt = $pdo->prepare(
        "INSERT INTO ballots (tenant_id, meeting_id, motion_id, member_id, value, weight, cast_at, is_proxy_vote, source)
         VALUES (:tenant_id, :meeting_id, :motion_id, :member_id, :value, :weight, NOW(), false, 'manual')
         RETURNING id"
    );
    $stmt->execute([
        'tenant_id'  => $tenantId,
        'meeting_id' => $meetingId,
        'motion_id'  => $motionId,
        'member_id'  => $memberId,
        'value'      => $value,
        'weight'     => $weight,
    ]);
    $ballotId = (string)$stmt->fetchColumn();

    // Trace audit append-only
    $val = [
        'ballot_id' => $ballotId,
        'motion_id' => $motionId,
        'member_id' => $memberId,
        'value'     => $value,
        'weight'    => $weight,
    ];

    $stmt2 = $pdo->prepare(
        "INSERT INTO manual_actions (tenant_id, meeting_id, motion_id, member_id, action_type, value, justification, operator_user_id)
         VALUES (:tenant_id, :meeting_id, :motion_id, :member_id, 'manual_vote', :value::jsonb, :justification, NULL)"
    );
    $stmt2->execute([
        'tenant_id'      => $tenantId,
        'meeting_id'     => $meetingId,
        'motion_id'      => $motionId,
        'member_id'      => $memberId,
        'value'          => json_encode($val, JSON_UNESCAPED_UNICODE),
        'justification'  => $justif,
    ]);

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'ballot_id' => $ballotId,
        'value' => $value,
        'source' => 'manual',
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    $pdo->rollBack();

    // Contrainte UNIQUE (motion_id, member_id) => déjà voté (tablet / manual / etc.)
    $msg = $e->getMessage();
    if (stripos($msg, 'unique') !== false || stripos($msg, 'ballots_motion_id_member_id') !== false) {
        http_response_code(409);
        echo json_encode(['error' => 'already_voted'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'detail' => 'manual_vote_failed'], JSON_UNESCAPED_UNICODE);
    exit;
}
