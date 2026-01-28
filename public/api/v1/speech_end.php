<?php
// public/api/v1/speech_end.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/SpeechService.php';

try {
    api_require_any_role(['operator','trust','admin']);
    $data = api_request('POST');
    $meetingId = trim((string)($data['meeting_id'] ?? ''));
    if ($meetingId==='') throw new InvalidArgumentException('meeting_id requis');
    $out = SpeechService::endCurrent($meetingId);
    api_ok($out);
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in speech_end.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
