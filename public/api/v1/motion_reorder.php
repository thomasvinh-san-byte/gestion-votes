<?php
// public/api/v1/motion_reorder.php
// Reorder motions for a meeting
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MotionRepository;
use AgVote\Repository\MeetingRepository;

api_require_role('operator');

try {
    $in = api_request('POST');

    $meetingId = trim((string)($in['meeting_id'] ?? ''));
    if ($meetingId === '' || !api_is_uuid($meetingId)) {
        api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id est obligatoire (uuid).']);
    }

    $motionIds = $in['motion_ids'] ?? [];
    if (!is_array($motionIds) || empty($motionIds)) {
        api_fail('missing_motion_ids', 422, ['detail' => 'motion_ids est obligatoire (tableau d\'uuids).']);
    }

    // Validate all UUIDs
    foreach ($motionIds as $mid) {
        if (!api_is_uuid($mid)) {
            api_fail('invalid_motion_id', 422, ['detail' => "motion_id invalide: $mid"]);
        }
    }

    $tenantId = api_current_tenant_id();

    // Verify meeting exists and belongs to tenant
    $meetingRepo = new MeetingRepository();
    $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
    if (!$meeting) {
        api_fail('meeting_not_found', 404);
    }

    // Don't allow reorder on live/closed/validated/archived meetings
    $status = $meeting['status'] ?? '';
    if (in_array($status, ['live', 'closed', 'validated', 'archived'], true)) {
        api_fail('meeting_locked', 409, ['detail' => 'Impossible de réordonner les résolutions d\'une séance en cours, clôturée, validée ou archivée.']);
    }

    // Reorder
    $motionRepo = new MotionRepository();
    $motionRepo->reorderAll($meetingId, $tenantId, $motionIds);

    audit_log('motions_reordered', 'meeting', $meetingId, [
        'motion_ids' => $motionIds
    ]);

    api_ok(['reordered' => true, 'count' => count($motionIds)]);

} catch (Throwable $e) {
    error_log('motion_reorder.php error: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
