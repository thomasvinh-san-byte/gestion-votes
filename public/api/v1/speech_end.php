<?php
// public/api/v1/speech_end.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\SpeechService;

try {
    api_require_role(['operator','trust','president','admin']);
    $data = api_request('POST');
    $meetingId = api_require_uuid($data, 'meeting_id');

    // Pass tenant context for security validation
    $tenantId = api_current_tenant_id();
    $out = SpeechService::endCurrent($meetingId, $tenantId);

    audit_log('speech.ended', 'meeting', $meetingId, [], $meetingId);

    api_ok($out);
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in speech_end.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
