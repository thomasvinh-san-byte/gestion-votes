<?php
// public/api/v1/meetings.php

require __DIR__ . '/../../../app/api.php';

api_require_role('operator');


header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

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
        // LISTER LES SÉANCES POUR LE TENANT COURANT
        global $pdo;

        $stmt = $pdo->prepare(
            "SELECT
                id,
                tenant_id,
                title,
                description,
                status,
                scheduled_at,
                started_at,
                ended_at,
                location,
                quorum_policy_id,
                created_by,
                created_at,
                updated_at
             FROM meetings
             WHERE tenant_id = :tenant_id
             ORDER BY created_at DESC"
        );
        $stmt->execute([':tenant_id' => DEFAULT_TENANT_ID]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        send_json_ok(['meetings' => $rows]);

    } elseif ($method === 'POST') {
        // CRÉER UNE NOUVELLE SÉANCE
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $scheduledAt = $input['scheduled_at'] ?? null;
        $location    = trim($input['location'] ?? '');

        if ($title === '') {
            send_json_err('missing_title', 422, [
                'detail' => 'Le titre de la séance est obligatoire.',
            ]);
        }

        global $pdo;

        $id = db_scalar("SELECT gen_random_uuid()");

        $stmt = $pdo->prepare(
            "INSERT INTO meetings (
                id,
                tenant_id,
                title,
                description,
                status,
                scheduled_at,
                location,
                created_at,
                updated_at
             ) VALUES (
                :id,
                :tenant_id,
                :title,
                :description,
                'draft',
                :scheduled_at,
                :location,
                NOW(),
                NOW()
             )"
        );

        $stmt->execute([
            ':id'          => $id,
            ':tenant_id'   => DEFAULT_TENANT_ID,
            ':title'       => $title,
            ':description' => $description !== '' ? $description : null,
            ':scheduled_at'=> $scheduledAt ?: null,
            ':location'    => $location !== '' ? $location : null,
        ]);

        if (function_exists('audit_log')) {
            audit_log('meeting_created', 'meeting', $id, [
                'title'       => $title,
                'scheduled_at'=> $scheduledAt,
                'location'    => $location,
            ]);
        }

        send_json_ok([
            'meeting_id' => $id,
            'title'      => $title,
        ], 201);

    } else {
        // Toute autre méthode (PUT, DELETE, etc.)
        send_json_err('method_not_allowed', 405);
    }

} catch (PDOException $e) {
    error_log("Database error in meetings.php: " . $e->getMessage());
    send_json_err('database_error', 500, ['detail' => 'Erreur de base de données']);
} catch (Throwable $e) {
    error_log("Unexpected error in meetings.php: " . $e->getMessage());
    send_json_err('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
