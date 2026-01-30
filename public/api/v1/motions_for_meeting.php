<?php
// public/api/v1/motions_for_meeting.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_fail('method_not_allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

$meetingId = $_GET['meeting_id'] ?? ($input['meeting_id'] ?? '');
$meetingId = trim($meetingId);

if ($meetingId === '') {
    api_fail('missing_meeting_id', 400);
}

try {
    $meetingRepo = new MeetingRepository();
    $motionRepo = new MotionRepository();

    if (!$meetingRepo->existsForTenant($meetingId, api_current_tenant_id())) {
        api_fail('meeting_not_found', 404);
    }

    // Récupérer toutes les motions + agendas en une fois (JSON agrégé)
    $row = $motionRepo->listForMeetingJson($meetingId);

    $motions = [];

    if ($row && isset($row['motions']) && $row['motions'] !== null) {
        if (is_string($row['motions'])) {
            $decoded = json_decode($row['motions'], true);
            if (is_array($decoded)) {
                $motions = $decoded;
            }
        } elseif (is_array($row['motions'])) {
            $motions = $row['motions'];
        }
    }

    // Motion courante de la séance (peut être NULL)
    $meeting = $meetingRepo->findByIdForTenant($meetingId, api_current_tenant_id());
    $currentMotionId = $meeting['current_motion_id'] ?? null;

    api_ok([
        'meeting_id'        => $meetingId,
        'current_motion_id' => $currentMotionId,
        'motions'           => $motions,
    ]);
} catch (PDOException $e) {
    error_log("Database error in motions_for_meeting.php: " . $e->getMessage());
    api_fail('database_error', 500, ['detail' => $e->getMessage()]);
}
