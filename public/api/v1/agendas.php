<?php
// public/api/v1/agendas.php

require __DIR__ . '/../../../app/api.php';

api_require_role('operator');


header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/**
 * Helpers locaux pour rester compatibles avec json_ok/json_err.
 */
function send_json_ok(array $data = [], int $statusCode = 200): void {
    http_response_code($statusCode);
    json_ok($data);
    exit;
}

function send_json_err(string $error, int $statusCode = 400, array $extra = []): void {
    http_response_code($statusCode);
    json_err($error, $statusCode, $extra);
    exit;
}

try {
    if ($method === 'GET') {
        // --------------------------------------------------
        // LISTE DES POINTS D'ODJ POUR UNE SÉANCE (READ)
        // --------------------------------------------------
        $meetingId = trim($_GET['meeting_id'] ?? '');

        if ($meetingId === '') {
            send_json_err('missing_meeting_id', 422, [
                'detail' => 'meeting_id est obligatoire.'
            ]);
        }

        // Vérifier que la séance existe pour ce tenant
        $exists = db_scalar(
            "SELECT 1 FROM meetings
             WHERE id = ? AND tenant_id = ?",
            [$meetingId, DEFAULT_TENANT_ID]
        );

        if (!$exists) {
            send_json_err('meeting_not_found', 404);
        }

        // Renvoyer la liste des agendas triés par idx
        global $pdo;
        $stmt = $pdo->prepare(
            "SELECT id, meeting_id, idx, title, description, is_approved, created_at
             FROM agendas
             WHERE meeting_id = :meeting_id
             ORDER BY idx ASC"
        );
        $stmt->execute([':meeting_id' => $meetingId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        send_json_ok(['agendas' => $rows]);

    } elseif ($method === 'POST') {
        // --------------------------------------------------
        // CRÉATION D'UN POINT D'ODJ (CREATE)
        // --------------------------------------------------
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $meetingId = trim($input['meeting_id'] ?? '');
        if ($meetingId === '') {
            send_json_err('missing_meeting_id', 422, [
                'detail' => 'meeting_id est obligatoire.'
            ]);
        }

        // Vérifier que la séance existe pour ce tenant
        $exists = db_scalar(
            "SELECT 1 FROM meetings
             WHERE id = ? AND tenant_id = ?",
            [$meetingId, DEFAULT_TENANT_ID]
        );

        if (!$exists) {
            send_json_err('meeting_not_found', 404);
        }

        $title = trim($input['title'] ?? '');
        $len   = mb_strlen($title);

        if ($len === 0) {
            send_json_err('missing_title', 400, [
                'detail' => 'Le titre du point est obligatoire.'
            ]);
        }
        if ($len > 40) {
            send_json_err('title_too_long', 400, [
                'detail' => 'Le titre du point ne doit pas dépasser 40 caractères.'
            ]);
        }

        global $pdo;

        $id  = db_scalar("SELECT gen_random_uuid()");
        $idx = db_scalar(
            "SELECT COALESCE(MAX(idx), 0) + 1 FROM agendas WHERE meeting_id = ?",
            [$meetingId]
        );
        if ($idx === null) {
            $idx = 1;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO agendas (id, meeting_id, idx, title, description, is_approved, created_at)
             VALUES (:id, :meeting_id, :idx, :title, :description, false, NOW())"
        );

        $stmt->execute([
            'id'          => $id,
            'meeting_id'  => $meetingId,
            'idx'         => $idx,
            'title'       => $title,
            'description' => null,
        ]);

        if (function_exists('audit_log')) {
            audit_log('agenda_created', 'agenda', $id, [
                'meeting_id' => $meetingId,
                'idx'        => $idx,
                'title'      => $title,
            ]);
        }

        send_json_ok([
            'agenda_id' => $id,
            'idx'       => $idx,
            'title'     => $title,
        ], 201);

    } else {
        // Toute autre méthode (PUT, DELETE, etc.)
        send_json_err('method_not_allowed', 405);
    }

} catch (PDOException $e) {
    error_log("Database error in agendas.php: " . $e->getMessage());
    send_json_err('database_error', 500, ['detail' => 'Erreur de base de données']);
} catch (Throwable $e) {
    error_log("Unexpected error in agendas.php: " . $e->getMessage());
    send_json_err('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
