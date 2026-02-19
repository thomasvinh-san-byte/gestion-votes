<?php
// public/api/v1/speech_current.php
// Returns the current speaker for a meeting with elapsed time
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\SpeechService;

try {
    api_require_role('public');
    $q = api_request('GET');
    $meetingId = api_require_uuid($q, 'meeting_id');

    $out = SpeechService::getQueue($meetingId);
    $speaker = $out['speaker'] ?? null;
    $queueCount = count($out['queue'] ?? []);

    if ($speaker) {
        // Calculer le temps ecoule depuis le debut de la parole
        $startedAt = $speaker['updated_at'] ?? null;
        $elapsedSeconds = 0;

        if ($startedAt) {
            $startTime = strtotime($startedAt);
            if ($startTime !== false) {
                $elapsedSeconds = max(0, time() - $startTime);
            }
        }

        // Formater le temps ecoule (mm:ss)
        $minutes = floor($elapsedSeconds / 60);
        $seconds = $elapsedSeconds % 60;
        $elapsedFormatted = sprintf('%02d:%02d', $minutes, $seconds);

        api_ok([
            'member_name' => $speaker['full_name'] ?? null,
            'member_id'   => $speaker['member_id'] ?? null,
            'request_id'  => $speaker['id'] ?? null,
            'started_at'  => $startedAt,
            'elapsed_seconds' => $elapsedSeconds,
            'elapsed_formatted' => $elapsedFormatted,
            'queue_count' => $queueCount,
        ]);
    } else {
        // No current speaker
        api_ok(['speaker' => null, 'queue_count' => $queueCount]);
    }
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in speech_current.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
