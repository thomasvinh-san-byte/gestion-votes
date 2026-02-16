<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Service\MeetingReportService;

api_require_role(['president', 'admin']);

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  api_fail('method_not_allowed', 405);
  exit;
}

$input = json_decode($GLOBALS['__ag_vote_raw_body'] ?? file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$meetingId = trim((string)($input['meeting_id'] ?? ''));
if ($meetingId === '' || !api_is_uuid($meetingId)) api_fail('invalid_meeting_id', 400);

api_guard_meeting_not_validated($meetingId);

$presidentName = trim((string)($input['president_name'] ?? ''));
if ($presidentName === '') api_fail('missing_president_name', 400);

$tenant = api_current_tenant_id();
$repo = new MeetingRepository();

$meeting = $repo->findByIdForTenant($meetingId, $tenant);
if (!$meeting) api_fail('meeting_not_found', 404);

try {
  // Wrap report generation + validation + PV storage in a single transaction
  api_transaction(function () use ($repo, $meetingId, $tenant) {
    $pvHtml = MeetingReportService::renderHtml($meetingId, true);

    // Marque validée
    $repo->markValidated($meetingId, $tenant);

    // Stocke le PV HTML dans meeting_reports
    $repo->storePVHtml($meetingId, $pvHtml);
  });

  api_ok(['meeting_id'=>$meetingId, 'status'=>'validated']);
} catch (Throwable $e) {
  api_fail('validation_failed', 500, ['detail'=>$e->getMessage()]);
}
