<?php
// public/api/v1/speech_cancel.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\SpeechService;

try {
    api_require_role(['operator','trust','president','admin']);
    $data = api_request('POST');
    $meetingId = trim((string)($data['meeting_id'] ?? ''));
    $requestId = trim((string)($data['request_id'] ?? ''));
    if ($meetingId===''||$requestId==='') throw new InvalidArgumentException('meeting_id et request_id requis');

    $tenantId = api_current_tenant_id();
    $out = SpeechService::cancelRequest($meetingId, $requestId, $tenantId);
    api_ok($out);
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in speech_cancel.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
