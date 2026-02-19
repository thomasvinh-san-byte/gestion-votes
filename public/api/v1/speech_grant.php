<?php
// public/api/v1/speech_grant.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\SpeechService;

try {
    api_require_role(['operator','trust','president','admin']);
    $data = api_request('POST');
    $meetingId = api_require_uuid($data, 'meeting_id');
    $memberId = trim((string)($data['member_id'] ?? ''));
    $requestId = trim((string)($data['request_id'] ?? ''));
    if ($memberId !== '' && !api_is_uuid($memberId)) api_fail('invalid_uuid', 400, ['field' => 'member_id']);
    if ($requestId !== '' && !api_is_uuid($requestId)) api_fail('invalid_uuid', 400, ['field' => 'request_id']);

    $tenantId = api_current_tenant_id();

    // If request_id provided but no member_id, resolve member_id from the speech request
    if ($memberId === '' && $requestId !== '') {
        $repo = new \AgVote\Repository\SpeechRepository();
        $req = $repo->findById($requestId, $tenantId);
        if ($req) {
            $memberId = (string)$req['member_id'];
        }
    }

    // Pass tenant context for security validation
    $out = SpeechService::grant($meetingId, $memberId!=='' ? $memberId : null, $tenantId);

    audit_log('speech.granted', 'meeting', $meetingId, [
        'member_id' => $memberId ?: ($out['speaker']['member_id'] ?? null),
    ], $meetingId);

    api_ok($out);
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in speech_grant.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
