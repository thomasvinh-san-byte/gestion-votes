<?php
// public/api/v1/motions_for_meeting.php
require __DIR__ . '/../../../app/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_err('method_not_allowed', 405);
}

// On prend meeting_id depuis ?meeting_id=... ou éventuellement JSON (fallback)
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

$meetingId = $_GET['meeting_id'] ?? ($input['meeting_id'] ?? '');
$meetingId = trim($meetingId);

if ($meetingId === '') {
    json_err('missing_meeting_id', 400);
}

try {
    // Vérifier que la séance existe bien pour ce tenant
    $exists = db_scalar("
        SELECT COUNT(*)
        FROM meetings
        WHERE id = ?
          AND tenant_id = ?
    ", [$meetingId, DEFAULT_TENANT_ID]);

    if (!$exists) {
        json_err('meeting_not_found', 404);
    }

    // Récupérer toutes les motions + agendas en une fois,
    // sous forme de JSON agrégé que l'on re-décodera côté PHP.
    $row = db_select_one("
        SELECT json_agg(t) AS motions
        FROM (
            SELECT
                mo.id             AS motion_id,
                mo.title          AS motion_title,
                mo.description    AS motion_description,
                mo.opened_at,
                mo.closed_at,
                mo.secret,
                mo.tally_status,
                mo.decision,
                mo.decision_reason,
                mo.evote_results,
                mo.manual_tally,
                mo.vote_policy_id,
                mo.quorum_policy_id,
                a.id              AS agenda_id,
                a.title           AS agenda_title,
                a.idx             AS agenda_idx
            FROM motions mo
            LEFT JOIN agendas a ON a.id = mo.agenda_id
            WHERE mo.meeting_id = :meeting_id
            ORDER BY a.idx ASC, mo.created_at ASC
        ) AS t
    ", ['meeting_id' => $meetingId]);

    $motions = [];

    if ($row && isset($row['motions']) && $row['motions'] !== null) {
        // Avec PDO + PostgreSQL, json_agg/jsonb est retourné en string → on decode
        if (is_string($row['motions'])) {
            $decoded = json_decode($row['motions'], true);
            if (is_array($decoded)) {
                $motions = $decoded;
            }
        } elseif (is_array($row['motions'])) {
            // Si ton helper renvoie déjà un tableau PHP
            $motions = $row['motions'];
        }
    }

    // Motion courante de la séance (peut être NULL)
    $currentMotionId = db_scalar("
        SELECT current_motion_id, vote_policy_id AS meeting_vote_policy_id, quorum_policy_id AS meeting_quorum_policy_id
        FROM meetings
        WHERE id = ?
    ", [$meetingId]);

    json_ok([
        'meeting_id'        => $meetingId,
        'current_motion_id' => $currentMotionId,
        'motions'           => $motions,
    ]);
} catch (PDOException $e) {
    error_log("Database error in motions_for_meeting.php: " . $e->getMessage());
    json_err('database_error', 500, ['detail' => $e->getMessage()]);
}