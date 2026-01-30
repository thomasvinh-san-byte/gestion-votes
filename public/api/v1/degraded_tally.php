<?php
declare(strict_types=1);

// Saisie manuelle d'un comptage (mode dégradé)
// - met à jour motions.manual_*
// - journalise dans manual_actions (append-only)
// - émet une notification (warn)

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/NotificationsService.php';

use AgVote\Repository\MotionRepository;
use AgVote\Repository\ManualActionRepository;

api_require_role(['operator','auditor']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    api_fail('method_not_allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$motionId = trim((string)($input['motion_id'] ?? ''));
if ($motionId === '') api_fail('missing_motion_id', 422);

// Charger motion + meeting + tenant
$row = (new MotionRepository())->findWithMeetingTenant($motionId);
if (!$row) api_fail('motion_not_found', 404);

$meetingId = (string)$row['meeting_id'];
$tenantId  = (string)$row['tenant_id'];

// Champs numériques
$total   = isset($input['manual_total'])   ? (int)$input['manual_total']   : 0;
$for     = isset($input['manual_for'])     ? (int)$input['manual_for']     : 0;
$against = isset($input['manual_against']) ? (int)$input['manual_against'] : 0;
$abstain = isset($input['manual_abstain']) ? (int)$input['manual_abstain'] : 0;

$justification = trim((string)($input['justification'] ?? ''));
if ($justification === '') {
    api_fail('missing_justification', 422, ['detail' => 'Une justification est obligatoire en mode dégradé.']);
}

// Validation métier (copiée/alignée avec motion_tally.php)
if ($total <= 0) api_fail('invalid_total', 422, ['detail' => 'Le nombre total de votants doit être strictement positif.']);
if ($for < 0 || $against < 0 || $abstain < 0) api_fail('invalid_numbers', 422, ['detail' => 'Les nombres de votes doivent être positifs.']);
if ($for > $total || $against > $total || $abstain > $total) {
    api_fail('vote_exceeds_total', 422, ['detail' => 'Aucune catégorie ne peut dépasser le total.', 'total'=>$total,'for'=>$for,'against'=>$against,'abstain'=>$abstain]);
}
$sum = $for + $against + $abstain;
if ($sum !== $total) {
    api_fail('inconsistent_tally', 422, ['detail' => 'Pour + Contre + Abstentions doit être égal au total.', 'total'=>$total,'sum'=>$sum]);
}

// Best-effort: créer table manual_actions si setup pas joué
(new ManualActionRepository())->ensureSchema();

try {
    db()->beginTransaction();

    (new MotionRepository())->updateManualTally($motionId, $total, $for, $against, $abstain);

    (new ManualActionRepository())->createManualTally(
        $tenantId,
        $meetingId,
        $motionId,
        json_encode(['total'=>$total,'for'=>$for,'against'=>$against,'abstain'=>$abstain], JSON_UNESCAPED_UNICODE),
        $justification
    );

    db()->commit();
} catch (Throwable $e) {
    db()->rollBack();
    api_fail('degraded_tally_failed', 500, ['detail' => $e->getMessage()]);
}

audit_log('manual_tally_set', 'motion', $motionId, [
    'meeting_id' => $meetingId,
    'tally' => ['total'=>$total,'for'=>$for,'against'=>$against,'abstain'=>$abstain],
    'justification' => $justification,
]);

NotificationsService::emit(
    $meetingId,
    'warn',
    'degraded_manual_tally',
    "Mode dégradé: comptage manuel saisi pour \"" . ((string)$row['motion_title']) . "\".",
    ['operator','trust'],
    ['motion_id' => $motionId]
);

api_ok([
    'meeting_id' => $meetingId,
    'motion_id' => $motionId,
    'manual_total' => $total,
    'manual_for' => $for,
    'manual_against' => $against,
    'manual_abstain' => $abstain,
]);
