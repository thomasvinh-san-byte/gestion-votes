<?php
declare(strict_types=1);

/**
 * speech_next.php - Passer au prochain orateur dans la file
 * 
 * POST /api/v1/speech_next.php
 * Body: { "meeting_id": "uuid" }
 * 
 * Termine l'orateur actuel et donne la parole au prochain dans la file.
 */

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/SpeechService.php';

try {
    api_require_role(['operator', 'president', 'admin']);
    
    $input = api_request('POST');
    
    $meetingId = trim((string)($input['meeting_id'] ?? ''));
    if ($meetingId === '') {
        throw new InvalidArgumentException('meeting_id requis');
    }
    
    // Terminer l'orateur courant et passer au suivant
    // La méthode grant() sans memberId prend automatiquement le prochain
    $result = SpeechService::grant($meetingId);
    
    api_ok($result);
    
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in speech_next.php: ' . $e->getMessage());
    api_fail('internal_error', 500);
}
