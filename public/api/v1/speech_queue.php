<?php
// public/api/v1/speech_queue.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\SpeechService;

try {
    api_require_role('public');
    $q = api_request('GET');
    $meetingId = trim((string)($q['meeting_id'] ?? ''));
    if ($meetingId === '') throw new InvalidArgumentException('meeting_id requis');
    $out = SpeechService::getQueue($meetingId);
    // Return just the queue array with frontend-expected field aliases
    $queue = $out['queue'] ?? [];
    foreach ($queue as &$item) {
        $item['member_name'] = $item['full_name'] ?? $item['member_name'] ?? '';
        $item['requested_at'] = $item['created_at'] ?? null;
    }
    unset($item);
    api_ok($queue);
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in speech_queue.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
