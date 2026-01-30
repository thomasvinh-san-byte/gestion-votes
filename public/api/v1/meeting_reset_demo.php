<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\VoteTokenRepository;
use AgVote\Repository\ManualActionRepository;

api_require_role(['operator', 'admin']);

$in = api_request('POST');
$meetingId = api_require_uuid($in, 'meeting_id');

$confirm = (string)($in['confirm'] ?? '');
if ($confirm !== 'RESET') {
  api_fail('missing_confirm', 400, ['detail' => "Envoyez {confirm:\"RESET\"} pour éviter les resets accidentels."]);
}

// Refuse if validated (DB lockdown + conformité)
$mt = (new MeetingRepository())->findByIdForTenant($meetingId, api_current_tenant_id());
if (!$mt) api_fail('meeting_not_found', 404);
if (!empty($mt['validated_at'])) {
  api_fail('meeting_validated', 409, ['detail' => 'Séance validée : reset interdit (séance figée).']);
}

db()->beginTransaction();
try {
  // Delete dependents
  (new BallotRepository())->deleteByMeeting($meetingId, api_current_tenant_id());

  // vote_tokens are per motion; delete by meeting motions
  (new VoteTokenRepository())->deleteByMeetingMotions($meetingId, api_current_tenant_id());

  // manual actions / audit events (best-effort: tables may not exist)
  try { (new ManualActionRepository())->deleteByMeeting($meetingId, api_current_tenant_id()); } catch (\Throwable $e) { /* ignore */ }
  (new MeetingRepository())->deleteAuditEventsByMeeting($meetingId, api_current_tenant_id());

  // reset motions state
  (new MotionRepository())->resetStatesForMeeting($meetingId, api_current_tenant_id());

  // reset meeting live fields (but keep title etc.)
  (new MeetingRepository())->resetForDemo($meetingId, api_current_tenant_id());

  db()->commit();
  api_ok(['ok'=>true,'meeting_id'=>$meetingId]);

} catch (Throwable $e) {
  db()->rollBack();
  api_fail('reset_failed', 500, ['detail'=>'Reset demo échoué']);
}
