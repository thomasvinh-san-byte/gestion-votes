<?php
// public/api/v1/attendances_upsert.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/AttendancesService.php';

try {
    api_require_role('operator');
    $data = api_request('POST');

    $meetingId = trim((string)($data['meeting_id'] ?? ''));
    $memberId  = trim((string)($data['member_id'] ?? ''));
    $mode      = trim((string)($data['mode'] ?? ''));
    $notes     = isset($data['notes']) ? (string)$data['notes'] : null;

    $row = AttendancesService::upsert($meetingId, $memberId, $mode, $notes);

    api_ok(['attendance' => $row]);
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in attendances_upsert.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}