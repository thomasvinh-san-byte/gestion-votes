<?php
// public/api/v1/speech_grant.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\SpeechService;

try {
    api_require_any_role(['operator','trust','president','admin']);
    $data = api_request('POST');
    $meetingId = trim((string)($data['meeting_id'] ?? ''));
    $memberId = trim((string)($data['member_id'] ?? ''));
    $requestId = trim((string)($data['request_id'] ?? ''));
    if ($meetingId==='') throw new InvalidArgumentException('meeting_id requis');

    // If request_id provided but no member_id, resolve member_id from the speech request
    if ($memberId === '' && $requestId !== '') {
        $tenantId = api_current_tenant_id();
        $repo = new \AgVote\Repository\SpeechRepository();
        $req = $repo->findById($requestId, $tenantId);
        if ($req) {
            $memberId = (string)$req['member_id'];
        }
    }

    $out = SpeechService::grant($meetingId, $memberId!=='' ? $memberId : null);
    api_ok($out);
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in speech_grant.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
