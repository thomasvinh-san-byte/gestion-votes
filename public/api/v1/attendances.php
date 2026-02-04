<?php
// public/api/v1/attendances.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\AttendancesService;
use AgVote\Repository\MemberRepository;
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

    // Debug: if no attendances, check if members exist at all
    $debug = null;
    if (empty($list)) {
        $memberRepo = new MemberRepository();
        $allMembers = $memberRepo->listByTenant($tenantId);
        $debug = [
            'tenant_id' => $tenantId,
            'meeting_id' => $meetingId,
            'members_count' => count($allMembers),
            'hint' => count($allMembers) > 0
                ? 'Members exist but query returned empty - check meeting_id validity'
                : 'No members found for this tenant_id'
        ];
    }

    api_ok([
        'attendances' => $list,
        'summary' => $summary,
        'debug' => $debug,
    ]);
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in attendances.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}