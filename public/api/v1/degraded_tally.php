<?php
declare(strict_types=1);

// Saisie manuelle d'un comptage (mode dégradé)
// - met à jour motions.manual_* 
// - journalise dans manual_actions (append-only)
// - émet une notification (warn)

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/NotificationsService.php';

require_any_role(['operator','trust']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_err('method_not_allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$motionId = trim((string)($input['motion_id'] ?? ''));
if ($motionId === '') json_err('missing_motion_id', 422);

// Charger motion + meeting + tenant
$row = db_select_one(
    "SELECT mo.id AS motion_id, mo.title AS motion_title, mo.meeting_id, m.tenant_id
     FROM motions mo
     JOIN meetings m ON m.id = mo.meeting_id
     WHERE mo.id = ?",
    [$motionId]
);
if (!$row) json_err('motion_not_found', 404);

$meetingId = (string)$row['meeting_id'];
$tenantId  = (string)$row['tenant_id'];

// Champs numériques
$total   = isset($input['manual_total'])   ? (int)$input['manual_total']   : 0;
$for     = isset($input['manual_for'])     ? (int)$input['manual_for']     : 0;
$against = isset($input['manual_against']) ? (int)$input['manual_against'] : 0;
$abstain = isset($input['manual_abstain']) ? (int)$input['manual_abstain'] : 0;

$justification = trim((string)($input['justification'] ?? ''));
if ($justification === '') {
    json_err('missing_justification', 422, ['detail' => 'Une justification est obligatoire en mode dégradé.']);
}

// Validation métier (copiée/alignée avec motion_tally.php)
if ($total <= 0) json_err('invalid_total', 422, ['detail' => 'Le nombre total de votants doit être strictement positif.']);
if ($for < 0 || $against < 0 || $abstain < 0) json_err('invalid_numbers', 422, ['detail' => 'Les nombres de votes doivent être positifs.']);
if ($for > $total || $against > $total || $abstain > $total) {
    json_err('vote_exceeds_total', 422, ['detail' => 'Aucune catégorie ne peut dépasser le total.', 'total'=>$total,'for'=>$for,'against'=>$against,'abstain'=>$abstain]);
}
$sum = $for + $against + $abstain;
if ($sum !== $total) {
    json_err('inconsistent_tally', 422, ['detail' => 'Pour + Contre + Abstentions doit être égal au total.', 'total'=>$total,'sum'=>$sum]);
}

// Best-effort: créer table manual_actions si setup pas joué
db_execute("CREATE TABLE IF NOT EXISTS manual_actions (
  id bigserial PRIMARY KEY,
  tenant_id uuid NOT NULL,
  meeting_id uuid NOT NULL,
  motion_id uuid,
  member_id uuid,
  action_type text NOT NULL,
  value jsonb NOT NULL DEFAULT '{}'::jsonb,
  justification text,
  operator_user_id uuid,
  signature_hash text,
  created_at timestamptz NOT NULL DEFAULT now()
)");
db_execute("CREATE INDEX IF NOT EXISTS idx_manual_actions_meeting ON manual_actions(meeting_id, created_at DESC)");

try {
    $pdo->beginTransaction();

    db_execute(
        "UPDATE motions SET manual_total = ?, manual_for = ?, manual_against = ?, manual_abstain = ? WHERE id = ?",
        [$total, $for, $against, $abstain, $motionId]
    );

    db_execute(
        "INSERT INTO manual_actions (tenant_id, meeting_id, motion_id, action_type, value, justification)
         VALUES (?, ?, ?, 'manual_tally', ?::jsonb, ?)",
        [$tenantId, $meetingId, $motionId, json_encode(['total'=>$total,'for'=>$for,'against'=>$against,'abstain'=>$abstain], JSON_UNESCAPED_UNICODE), $justification]
    );

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_err('degraded_tally_failed', 500, ['detail' => $e->getMessage()]);
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

json_ok([
    'meeting_id' => $meetingId,
    'motion_id' => $motionId,
    'manual_total' => $total,
    'manual_for' => $for,
    'manual_against' => $against,
    'manual_abstain' => $abstain,
]);
