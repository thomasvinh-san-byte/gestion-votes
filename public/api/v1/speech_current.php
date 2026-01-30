<?php
// public/api/v1/speech_current.php
// Returns the current speaker for a meeting (subset of speech_queue)
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\SpeechService;

try {
    api_require_role('public');
    $q = api_request('GET');
    $meetingId = trim((string)($q['meeting_id'] ?? ''));
    if ($meetingId === '') throw new InvalidArgumentException('meeting_id requis');

    $out = SpeechService::getQueue($meetingId);
    $speaker = $out['speaker'] ?? null;

    if ($speaker) {
        api_ok([
            'member_name' => $speaker['full_name'] ?? null,
            'member_id'   => $speaker['member_id'] ?? null,
            'request_id'  => $speaker['id'] ?? null,
            'started_at'  => $speaker['updated_at'] ?? null,
        ]);
    } else {
        // No current speaker — return ok:true but no data key so frontend
        // sees body.data as falsy and shows "Personne n'a la parole"
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'data' => null], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in speech_current.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
