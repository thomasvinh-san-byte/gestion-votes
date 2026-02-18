<?php
declare(strict_types=1);

/**
 * POST /api/v1/ballots_cancel.php
 *
 * Annule un bulletin de vote manuel (supprime le ballot).
 * Uniquement pour les votes manuels (source = 'manual') sur des motions non clôturées.
 *
 * Paramètres :
 *   motion_id  (uuid)  - La résolution concernée
 *   member_id  (uuid)  - Le membre dont on annule le vote
 *   reason     (string) - Justification obligatoire
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\BallotRepository;
use AgVote\Repository\MotionRepository;
use AgVote\WebSocket\EventBroadcaster;

api_require_role(['operator', 'admin']);

$in = api_request('POST');

$motionId = api_require_uuid($in, 'motion_id');
$memberId = api_require_uuid($in, 'member_id');
$reason   = trim((string)($in['reason'] ?? ''));

if ($reason === '') {
    api_fail('missing_reason', 400, ['detail' => 'Une justification est requise pour annuler un vote.']);
}

$tenantId  = api_current_tenant_id();
$motionRepo = new MotionRepository();
$ballotRepo = new BallotRepository();

db()->beginTransaction();
try {
    // Verify motion exists and belongs to tenant
    $motion = $motionRepo->findByIdForTenantForUpdate($motionId, $tenantId);
    if (!$motion) {
        db()->rollBack();
        api_fail('motion_not_found', 404);
    }

    // Guard: check validated status BEFORE continuing transaction
    // api_guard calls api_fail() which exits, so check manually
    $meetingId = $motion['meeting_id'];
    $mt = db()->prepare("SELECT validated_at FROM meetings WHERE tenant_id = :tid AND id = :mid");
    $mt->execute([':tid' => $tenantId, ':mid' => $meetingId]);
    $mtRow = $mt->fetch(\PDO::FETCH_ASSOC);
    if ($mtRow && !empty($mtRow['validated_at'])) {
        db()->rollBack();
        api_fail('meeting_validated', 409, [
            'detail' => 'Séance validée : modification interdite (séance figée).'
        ]);
    }

    // Guard: cannot cancel on a closed motion (results are already computed)
    if (!empty($motion['closed_at'])) {
        db()->rollBack();
        api_fail('motion_closed', 409, [
            'detail' => 'Impossible d\'annuler un vote sur une résolution déjà clôturée.',
        ]);
    }

    // Find the ballot
    $ballot = $ballotRepo->findByMotionAndMember($motionId, $memberId);
    if (!$ballot) {
        db()->rollBack();
        api_fail('ballot_not_found', 404, [
            'detail' => 'Aucun bulletin trouvé pour ce membre sur cette résolution.',
        ]);
    }

    // Guard: only manual votes can be cancelled (electronic votes are cast by the voter)
    $source = $ballot['source'] ?? 'tablet';
    if ($source !== 'manual') {
        db()->rollBack();
        api_fail('not_manual_vote', 422, [
            'detail' => 'Seuls les votes manuels (source=manual) peuvent être annulés par l\'opérateur.',
        ]);
    }

    // Delete the ballot
    $stmt = db()->prepare(
        "DELETE FROM ballots WHERE motion_id = :mid AND member_id = :mem"
    );
    $stmt->execute([':mid' => $motionId, ':mem' => $memberId]);

    db()->commit();

    // Post-commit: audit + broadcast
    audit_log('ballot_cancelled', 'ballot', $motionId, [
        'member_id'    => $memberId,
        'value'        => $ballot['value'] ?? null,
        'weight'       => $ballot['weight'] ?? null,
        'reason'       => $reason,
        'motion_title' => $motion['title'] ?? '',
    ], $motion['meeting_id']);

    try {
        EventBroadcaster::motionUpdated($motion['meeting_id'], $motionId, [
            'ballot_cancelled' => true,
            'member_id' => $memberId,
        ]);
    } catch (\Throwable $e) {
        // Don't fail if broadcast fails
    }

    api_ok([
        'cancelled'  => true,
        'motion_id'  => $motionId,
        'member_id'  => $memberId,
    ]);

} catch (\Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    api_fail('cancel_failed', 500, ['detail' => 'Échec de l\'annulation du vote.']);
}
