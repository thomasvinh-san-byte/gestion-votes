<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/OfficialResultsService.php';

api_require_role(['president', 'admin']);

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  json_err('method_not_allowed', 405);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$meetingId = trim((string)($input['meeting_id'] ?? ''));

api_guard_meeting_not_validated($meetingId);

$presidentName = trim((string)($input['president_name'] ?? ''));

if ($meetingId === '') json_err('missing_meeting_id', 400);
if ($presidentName === '') json_err('missing_president_name', 400);

$tenant = DEFAULT_TENANT_ID;

$meeting = db_select_one(
  "SELECT id, status FROM meetings WHERE tenant_id = :tid AND id = :id",
  [':tid'=>$tenant, ':id'=>$meetingId]
);
if (!$meeting) json_err('meeting_not_found', 404);

try {
  $svc = new OfficialResultsService($pdo);
  $pvHtml = $svc->generatePVHtml($tenant, $meetingId, $presidentName);

  // Marque validée
  $pdo->prepare("UPDATE meetings SET status='validated', validated_at=NOW() WHERE tenant_id=:tid AND id=:id")
      ->execute([':tid'=>$tenant, ':id'=>$meetingId]);

  // Stocke le PV HTML dans meeting_reports
  try {
    $pdo->prepare("INSERT INTO meeting_reports(meeting_id, html, created_at, updated_at) VALUES (:mid,:html,NOW(),NOW())
                   ON CONFLICT (meeting_id) DO UPDATE SET html=EXCLUDED.html, updated_at=NOW()")
        ->execute([':mid'=>$meetingId, ':html'=>$pvHtml]);
  } catch (Throwable $e) {
    error_log("meeting_validate: could not store PV: " . $e->getMessage());
  }

  json_ok(['meeting_id'=>$meetingId, 'status'=>'validated']);
} catch (Throwable $e) {
  json_err('validation_failed', 500, ['detail'=>$e->getMessage()]);
}
