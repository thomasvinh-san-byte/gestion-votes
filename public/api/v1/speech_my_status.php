<?php
// public/api/v1/speech_my_status.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\SpeechService;

try {
    api_require_role('public');
    $q = api_request('GET');
    $meetingId = trim((string)($q['meeting_id'] ?? ''));
    $memberId = trim((string)($q['member_id'] ?? ''));
    if ($meetingId===''||$memberId==='') throw new InvalidArgumentException('meeting_id et member_id requis');
    $out = SpeechService::getMyStatus($meetingId,$memberId);
    api_ok($out);
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in speech_my_status.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
