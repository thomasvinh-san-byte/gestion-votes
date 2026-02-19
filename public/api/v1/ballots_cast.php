<?php
// public/api/v1/ballots_cast.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\BallotsService;

try {
    api_require_role('public');
    $data = api_request('POST');

    // Idempotency: the underlying castBallot uses UPSERT (ON CONFLICT DO UPDATE)
    // so duplicate submissions for the same (motion_id, member_id) are safe —
    // the ballot is updated rather than duplicated.
    // The X-Idempotency-Key header is logged for audit traceability.
    $idempotencyKey = $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? null;
    if ($idempotencyKey) {
        $data['_idempotency_key'] = $idempotencyKey;
    }

    $ballot = BallotsService::castBallot($data);

    // Audit trail for vote cast (critical for compliance)
    $motionId = $data['motion_id'] ?? $ballot['motion_id'] ?? null;
    $meetingId = $ballot['meeting_id'] ?? $data['meeting_id'] ?? null;
    audit_log('ballot.cast', 'motion', $motionId, [
        'member_id' => $data['member_id'] ?? $ballot['member_id'] ?? null,
        'choice'    => $ballot['choice'] ?? $data['choice'] ?? null,
    ], $meetingId);

    api_ok(['ballot' => $ballot], 201);
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in ballots_cast.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
