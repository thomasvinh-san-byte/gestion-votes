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

$data = api_request('POST');

// SECURITY: Always use current authenticated tenant - never from input
$tenantId  = api_current_tenant_id();
$meetingId = trim((string)($data['meeting_id'] ?? ''));
$motionId  = trim((string)($data['motion_id'] ?? ''));
$memberId  = trim((string)($data['member_id'] ?? ''));
$voteUi    = trim((string)($data['vote'] ?? ''));
$justif    = trim((string)($data['justification'] ?? ''));

if ($meetingId === '' || $motionId === '' || $memberId === '') {
    api_fail('missing_fields', 400, ['required' => ['meeting_id','motion_id','member_id']]);
}
if ($justif === '') {
    api_fail('missing_justification', 400);
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
    api_fail('invalid_vote', 400);
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
    api_fail('meeting_not_found', 404);
}
if (!empty($meeting['validated_at'])) {
    api_fail('meeting_validated', 409);
}

$motion = $motionRepo->findForMeetingWithState($tenantId, $motionId, $meetingId);
if (!$motion) {
    api_fail('motion_not_found', 404);
}
if (empty($motion['opened_at']) || !empty($motion['closed_at'])) {
    api_fail('motion_not_open', 409);
}

$member = $memberRepo->findActiveWithWeight($tenantId, $memberId);
if (!$member) {
    api_fail('member_not_found', 404);
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

    api_ok([
        'ballot_id' => $ballotId,
        'value' => $value,
        'source' => 'manual',
    ]);

} catch (Throwable $e) {
    db()->rollBack();

    // Contrainte UNIQUE (motion_id, member_id) => déjà voté (tablet / manual / etc.)
    $msg = $e->getMessage();
    if (stripos($msg, 'unique') !== false || stripos($msg, 'ballots_motion_id_member_id') !== false) {
        api_fail('already_voted', 409);
    }

    api_fail('server_error', 500, ['detail' => 'manual_vote_failed']);
}
