<?php
// public/api/v1/speech_request.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\SpeechService;

try {
    api_require_role('public');
    $data = api_request('POST');
    $meetingId = api_require_uuid($data, 'meeting_id');
    $memberId = api_require_uuid($data, 'member_id');
    $tenantId = api_current_tenant_id();
    SpeechService::toggleRequest($meetingId,$memberId,$tenantId);
    // Return full status with position info
    $out = SpeechService::getMyStatus($meetingId,$memberId,$tenantId);

    audit_log('speech.requested', 'meeting', $meetingId, [
        'member_id' => $memberId,
    ], $meetingId);

    api_ok($out);
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in speech_request.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
