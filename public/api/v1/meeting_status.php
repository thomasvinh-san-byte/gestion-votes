<?php
// public/api/v1/meeting_status.php
require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/MeetingValidator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_err('method_not_allowed', 405);
}

try {
    // Séance "courante" pour le tenant :
    //  - NON archivée
    //  - statut live, closed ou draft
    //  - on privilégie live, puis closed, puis draft
    $meeting = db_select_one("
        SELECT
            m.id                AS meeting_id,
            m.title             AS meeting_title,
            m.status            AS meeting_status,
            m.started_at,
            m.ended_at,
            m.archived_at,
            m.president_member_id,
            m.president_name,
            m.president_source,
            m.ready_to_sign,
            m.validated_at,
            u.display_name      AS validated_by
        FROM meetings m
        LEFT JOIN users u ON u.id = m.validated_by_user_id
        WHERE m.tenant_id = :tenant_id
          AND m.status <> 'archived'
        ORDER BY
            CASE m.status
                WHEN 'live'   THEN 1
                WHEN 'closed' THEN 2
                WHEN 'draft'  THEN 3
                ELSE 4
            END,
            m.created_at DESC
        LIMIT 1
    ", ['tenant_id' => DEFAULT_TENANT_ID]);

    if (!$meeting) {
        json_err('no_live_meeting', 404);
    }

    // Compteurs de motions pour affichage et logique
    $counts = db_select_one("
        SELECT
          COUNT(*) AS total_motions,
          SUM(CASE WHEN mo.closed_at IS NULL THEN 1 ELSE 0 END) AS open_motions,
          SUM(CASE WHEN mo.closed_at IS NOT NULL THEN 1 ELSE 0 END) AS closed_motions,
          SUM(
            CASE
              WHEN mo.closed_at IS NOT NULL
               AND (mo.manual_total IS NULL OR mo.manual_total <= 0)
              THEN 1 ELSE 0
            END
          ) AS closed_without_tally
        FROM motions mo
        WHERE mo.meeting_id = :meeting_id
    ", ['meeting_id' => $meeting['meeting_id']]);

    $totalMotions        = (int)($counts['total_motions']        ?? 0);
    $openMotions         = (int)($counts['open_motions']         ?? 0);
    $closedWithoutTally  = (int)($counts['closed_without_tally'] ?? 0);

    // (Re)calculer ready_to_sign côté lecture, au cas où (inclut président + consolidation)
    $validation = MeetingValidator::canBeValidated((string)$meeting['meeting_id'], DEFAULT_TENANT_ID);
    $readyToSign = (bool)($validation['can'] ?? false);

    // Notifications readiness (sans spam)
    NotificationsService::emitReadinessTransitions((string)$meeting['meeting_id'], $validation);

    // Statut lisible pour /trust
    $signStatus = 'not_ready';
    $signMessage = "Séance en cours de traitement.";

    if ($meeting['status'] === 'archived') {
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
        // Pour l’instant : pas de RBAC fin, donc on laisse true
        'can_current_user_validate' => true,
    ]);

    json_ok($response);

} catch (PDOException $e) {
    error_log("Database error in meeting_status.php: " . $e->getMessage());
    json_err('database_error', 500, ['detail' => $e->getMessage()]);
}
