<?php
declare(strict_types=1);

// Mode dégradé minimal : vote saisi manuellement par l'opérateur
// - insère un ballot source=manual (et respecte l'unicité motion_id+member_id)
// - écrit une trace append-only dans manual_actions avec justification
// - refuser si meeting validé (409 meeting_validated)
// - refuser si motion pas ouverte (409 motion_not_open)

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\ManualActionRepository;

api_require_role('operator');

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$raw = $GLOBALS['__ag_vote_raw_body'] ?? (file_get_contents('php://input') ?: '');
$data = [];
$ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

if (is_string($ct) && stripos($ct, 'application/json') !== false) {
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = [];
} else {
    // fallback form-encoded
    $data = $_POST ?? [];
}

// SECURITY: Always use current authenticated tenant - never from input
$tenantId  = api_current_tenant_id();
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

$meetingRepo = new MeetingRepository();
$motionRepo  = new MotionRepository();
$memberRepo  = new MemberRepository();
$ballotRepo  = new BallotRepository();
$manualRepo  = new ManualActionRepository();

// 1) Vérifs intégrité
$meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
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

$motion = $motionRepo->findForMeetingWithState($tenantId, $motionId, $meetingId);
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

$member = $memberRepo->findActiveWithWeight($tenantId, $memberId);
if (!$member) {
    http_response_code(404);
    echo json_encode(['error' => 'member_not_found']);
    exit;
}

$weight = (string)($member['vote_weight'] ?? '1.0');

// 2) Insert ballot + manual action atomiquement
db()->beginTransaction();
try {
    // Ballot : unique (motion_id, member_id) au niveau DB
    $ballotId = $ballotRepo->insertManual($tenantId, $meetingId, $motionId, $memberId, $value, $weight);

    // Trace audit append-only
    $val = [
        'ballot_id' => $ballotId,
        'motion_id' => $motionId,
        'member_id' => $memberId,
        'value'     => $value,
        'weight'    => $weight,
    ];

    $manualRepo->create(
        $tenantId,
        $meetingId,
        $motionId,
        $memberId,
        'manual_vote',
        json_encode($val, JSON_UNESCAPED_UNICODE),
        $justif
    );

    db()->commit();

    echo json_encode([
        'ok' => true,
        'ballot_id' => $ballotId,
        'value' => $value,
        'source' => 'manual',
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    db()->rollBack();

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
