<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

api_require_role(['operator', 'admin']);

$in = api_request('POST');
$meetingId = api_require_uuid($in, 'meeting_id');

$confirm = (string)($in['confirm'] ?? '');
if ($confirm !== 'RESET') {
  api_fail('missing_confirm', 400, ['detail' => "Envoyez {confirm:"RESET"} pour éviter les resets accidentels."]);
}

// Refuse if validated (DB lockdown + conformité)
$mt = db_select_one("SELECT id, validated_at FROM meetings WHERE tenant_id = ? AND id = ? LIMIT 1", [api_current_tenant_id(), $meetingId]);
if (!$mt) api_fail('meeting_not_found', 404);
if (!empty($mt['validated_at'])) {
  api_fail('meeting_validated', 409, ['detail' => 'Séance validée : reset interdit (séance figée).']);
}

$pdo = db();
$pdo->beginTransaction();
try {
  // Delete dependents
  db_execute("DELETE FROM ballots WHERE meeting_id = :mid AND tenant_id = :tid", [':mid'=>$meetingId, ':tid'=>api_current_tenant_id()]);

  // vote_tokens are per motion; delete by meeting motions
  db_execute(
    "DELETE FROM vote_tokens vt
     USING motions mo
     WHERE vt.motion_id = mo.id
       AND mo.meeting_id = :mid
       AND mo.tenant_id = :tid",
    [':mid'=>$meetingId, ':tid'=>api_current_tenant_id()]
  );

  // manual actions / incidents / audit events (best-effort: tables may not exist)
  foreach (['manual_actions','audit_events'] as $t) {
    try {
      db_execute("DELETE FROM {$t} WHERE meeting_id = :mid AND tenant_id = :tid", [':mid'=>$meetingId, ':tid'=>api_current_tenant_id()]);
    } catch (Throwable $e) { /* ignore */ }
  }

  // reset motions state
  db_execute(
    "UPDATE motions
     SET opened_at = NULL, closed_at = NULL,
         manual_total = NULL, manual_for = NULL, manual_against = NULL, manual_abstain = NULL,
         updated_at = now()
     WHERE meeting_id = :mid AND tenant_id = :tid",
    [':mid'=>$meetingId, ':tid'=>api_current_tenant_id()]
  );

  // reset meeting live fields (but keep title etc.)
  db_execute(
    "UPDATE meetings
     SET current_motion_id = NULL,
         status = 'live',
         updated_at = now()
     WHERE id = :mid AND tenant_id = :tid",
    [':mid'=>$meetingId, ':tid'=>api_current_tenant_id()]
  );

  $pdo->commit();
  api_ok(['ok'=>true,'meeting_id'=>$meetingId]);

} catch (Throwable $e) {
  $pdo->rollBack();
  api_fail('reset_failed', 500, ['detail'=>'Reset demo échoué']);
}
