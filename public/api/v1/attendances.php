<?php
// public/api/v1/attendances.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\AttendancesService;
use AgVote\Repository\MeetingRepository;

try {
    api_require_role(['operator', 'trust', 'admin']);
    api_request('GET');

    $meetingId = trim((string)($_GET['meeting_id'] ?? ''));
    if ($meetingId === '') {
        api_fail('invalid_request', 422, ['detail' => 'meeting_id est obligatoire']);
    }

    $tenantId = api_current_tenant_id();

    // SECURITY: Verify meeting belongs to current tenant
    $meetingRepo = new MeetingRepository();
    $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
    if (!$meeting) {
        api_fail('meeting_not_found', 404, ['detail' => 'Séance introuvable']);
    }

    $list = AttendancesService::listForMeeting($meetingId, $tenantId);
    $summary = AttendancesService::summaryForMeeting($meetingId, $tenantId);

    api_ok([
        'attendances' => $list,
        'summary' => $summary,
    ]);
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in attendances.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}