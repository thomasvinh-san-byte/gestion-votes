<?php
// public/api/v1/meeting_status.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/MeetingValidator.php';
require __DIR__ . '/../../../app/services/NotificationsService.php';

use AgVote\Repository\MeetingRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_fail('method_not_allowed', 405);
}

try {
    $repo = new MeetingRepository();

    $meeting = $repo->findCurrentForTenant(api_current_tenant_id());

    if (!$meeting) {
        api_fail('no_live_meeting', 404);
    }

    $counts = $repo->countMotionStats((string)$meeting['meeting_id']);

    $totalMotions        = (int)($counts['total_motions']        ?? 0);
    $openMotions         = (int)($counts['open_motions']         ?? 0);
    $closedWithoutTally  = (int)($counts['closed_without_tally'] ?? 0);

    // (Re)calculer ready_to_sign côté lecture, au cas où (inclut président + consolidation)
    $validation = MeetingValidator::canBeValidated((string)$meeting['meeting_id'], api_current_tenant_id());
    $readyToSign = (bool)($validation['can'] ?? false);

    // Notifications readiness (sans spam)
    NotificationsService::emitReadinessTransitions((string)$meeting['meeting_id'], $validation);

    // Statut lisible pour /trust
    $signStatus = 'not_ready';
    $signMessage = "Séance en cours de traitement.";

    if ($meeting['meeting_status'] === 'archived') {
        $signStatus  = 'archived';
        $signMessage = "Séance archivée le " . ($meeting['archived_at'] ?? '—');
    } elseif ($readyToSign) {
        $signStatus  = 'ready';
        $signMessage = "Tout est prêt à être signé.";
    } elseif ($openMotions > 0) {
        $signStatus  = 'open_motions';
        $signMessage = "$openMotions résolution(s) encore ouverte(s).";
    } elseif ($closedWithoutTally > 0) {
        $signStatus  = 'missing_tally';
        $signMessage = "$closedWithoutTally résolution(s) clôturée(s) sans comptage complet.";
    }

    $response = array_merge($meeting, [
        'total_motions'        => $totalMotions,
        'open_motions'         => $openMotions,
        'closed_without_tally' => $closedWithoutTally,
        'ready_to_sign'        => $readyToSign,
        'sign_status'          => $signStatus,
        'sign_message'         => $signMessage,
        // Pour l'instant : pas de RBAC fin, donc on laisse true
        'can_current_user_validate' => true,
    ]);

    api_ok($response);

} catch (PDOException $e) {
    error_log("Database error in meeting_status.php: " . $e->getMessage());
    api_fail('database_error', 500, ['detail' => $e->getMessage()]);
}
