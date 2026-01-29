<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

AuthMiddleware::requireRole('admin');

$tenantId = AuthMiddleware::getCurrentTenantId();
$method   = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// ═══════════════════════════════════════════════════════════
// GET - List policies by type
// ═══════════════════════════════════════════════════════════
if ($method === 'GET') {
    $type = trim($_GET['type'] ?? '');

    if ($type === 'quorum') {
        $rows = db_all(
            'SELECT * FROM quorum_policies WHERE tenant_id = :tid ORDER BY name',
            [':tid' => $tenantId]
        );
        echo json_encode(['ok' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($type === 'vote') {
        $rows = db_all(
            'SELECT * FROM vote_policies WHERE tenant_id = :tid ORDER BY name',
            [':tid' => $tenantId]
        );
        echo json_encode(['ok' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parametre "type" requis (quorum|vote).'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════
// POST - Create / Update / Delete
// ═══════════════════════════════════════════════════════════
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Methode non autorisee.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Corps JSON invalide.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$type   = trim((string)($input['type'] ?? ''));
$action = trim((string)($input['action'] ?? ''));

if (!in_array($type, ['quorum', 'vote'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Le champ "type" doit etre "quorum" ou "vote".'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($action, ['create', 'update', 'delete'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Le champ "action" doit etre "create", "update" ou "delete".'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── UUID validation helper ───
function isValidUuid(string $val): bool {
    return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $val);
}

// ─── JSON response helpers ───
function jsonOk(array $extra = []): never {
    echo json_encode(array_merge(['ok' => true], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════
// QUORUM POLICIES
// ═══════════════════════════════════════════════════════════
if ($type === 'quorum') {

    // ─── DELETE ───
    if ($action === 'delete') {
        $id = trim((string)($input['id'] ?? ''));
        if (!isValidUuid($id)) {
            jsonError('ID invalide.');
        }

        // Check if used by any meeting
        $usedCount = (int)db_scalar(
            'SELECT COUNT(*) FROM meetings WHERE quorum_policy_id = :id AND tenant_id = :tid',
            [':id' => $id, ':tid' => $tenantId]
        );
        if ($usedCount > 0) {
            jsonError('Cette politique est utilisee par ' . $usedCount . ' seance(s). Supprimez ou modifiez les seances avant de supprimer cette politique.');
        }

        // Check if used by any motion
        $usedByMotion = (int)db_scalar(
            'SELECT COUNT(*) FROM motions WHERE quorum_policy_id = :id AND tenant_id = :tid',
            [':id' => $id, ':tid' => $tenantId]
        );
        if ($usedByMotion > 0) {
            jsonError('Cette politique est utilisee par ' . $usedByMotion . ' resolution(s). Modifiez-les avant de supprimer cette politique.');
        }

        // Verify it belongs to this tenant
        $exists = db_scalar(
            'SELECT COUNT(*) FROM quorum_policies WHERE id = :id AND tenant_id = :tid',
            [':id' => $id, ':tid' => $tenantId]
        );
        if (!$exists) {
            jsonError('Politique introuvable.', 404);
        }

        db_exec('DELETE FROM quorum_policies WHERE id = :id AND tenant_id = :tid', [':id' => $id, ':tid' => $tenantId]);

        audit_log('admin:quorum_policy_deleted', 'quorum_policy', $id, ['id' => $id]);

        jsonOk(['deleted' => $id]);
    }

    // ─── Validate common fields for create/update ───
    $schema = InputValidator::schema();
    $schema->string('name')->required()->minLength(2)->maxLength(255);
    $schema->string('description')->optional()->nullable()->maxLength(1000);
    $schema->enum('mode', ['single', 'evolving', 'double'])->required();
    $schema->enum('denominator', ['eligible_members', 'eligible_weight'])->required();
    $schema->number('threshold')->required()->min(0)->max(1);
    $schema->boolean('include_proxies')->optional()->default(true);
    $schema->boolean('count_remote')->optional()->default(true);
    $schema->number('threshold_call2')->optional()->nullable()->min(0)->max(1);
    $schema->string('denominator2')->optional()->nullable();
    $schema->number('threshold2')->optional()->nullable()->min(0)->max(1);

    $result = $schema->validate($input);
    if (!$result->isValid()) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'error' => 'validation_failed',
            'details' => $result->errors()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = $result->data();

    // Validate denominator2 if provided
    if (!empty($data['denominator2']) && !in_array($data['denominator2'], ['eligible_members', 'eligible_weight'], true)) {
        jsonError('Le denominateur2 doit etre "eligible_members" ou "eligible_weight".');
    }

    // ─── CREATE ───
    if ($action === 'create') {
        $id = api_uuid4();

        $sql = 'INSERT INTO quorum_policies
                (id, tenant_id, name, description, mode, denominator, threshold, threshold_call2, denominator2, threshold2, include_proxies, count_remote)
                VALUES
                (:id, :tid, :name, :desc, :mode, :den, :threshold, :tc2, :den2, :t2, :proxies, :remote)';

        db_exec($sql, [
            ':id'        => $id,
            ':tid'       => $tenantId,
            ':name'      => $data['name'],
            ':desc'      => $data['description'] ?? null,
            ':mode'      => $data['mode'],
            ':den'       => $data['denominator'],
            ':threshold' => $data['threshold'],
            ':tc2'       => $data['threshold_call2'] ?? null,
            ':den2'      => $data['denominator2'] ?? null,
            ':t2'        => $data['threshold2'] ?? null,
            ':proxies'   => $data['include_proxies'] ? 't' : 'f',
            ':remote'    => $data['count_remote'] ? 't' : 'f',
        ]);

        audit_log('admin:quorum_policy_created', 'quorum_policy', $id, [
            'name'      => $data['name'],
            'mode'      => $data['mode'],
            'threshold' => $data['threshold'],
        ]);

        jsonOk(['id' => $id]);
    }

    // ─── UPDATE ───
    if ($action === 'update') {
        $id = trim((string)($input['id'] ?? ''));
        if (!isValidUuid($id)) {
            jsonError('ID invalide.');
        }

        // Verify ownership
        $exists = db_scalar(
            'SELECT COUNT(*) FROM quorum_policies WHERE id = :id AND tenant_id = :tid',
            [':id' => $id, ':tid' => $tenantId]
        );
        if (!$exists) {
            jsonError('Politique introuvable.', 404);
        }

        $sql = 'UPDATE quorum_policies SET
                    name = :name,
                    description = :desc,
                    mode = :mode,
                    denominator = :den,
                    threshold = :threshold,
                    threshold_call2 = :tc2,
                    denominator2 = :den2,
                    threshold2 = :t2,
                    include_proxies = :proxies,
                    count_remote = :remote
                WHERE id = :id AND tenant_id = :tid';

        db_exec($sql, [
            ':id'        => $id,
            ':tid'       => $tenantId,
            ':name'      => $data['name'],
            ':desc'      => $data['description'] ?? null,
            ':mode'      => $data['mode'],
            ':den'       => $data['denominator'],
            ':threshold' => $data['threshold'],
            ':tc2'       => $data['threshold_call2'] ?? null,
            ':den2'      => $data['denominator2'] ?? null,
            ':t2'        => $data['threshold2'] ?? null,
            ':proxies'   => $data['include_proxies'] ? 't' : 'f',
            ':remote'    => $data['count_remote'] ? 't' : 'f',
        ]);

        audit_log('admin:quorum_policy_updated', 'quorum_policy', $id, [
            'name'      => $data['name'],
            'mode'      => $data['mode'],
            'threshold' => $data['threshold'],
        ]);

        jsonOk(['id' => $id]);
    }
}

// ═══════════════════════════════════════════════════════════
// VOTE POLICIES
// ═══════════════════════════════════════════════════════════
if ($type === 'vote') {

    // ─── DELETE ───
    if ($action === 'delete') {
        $id = trim((string)($input['id'] ?? ''));
        if (!isValidUuid($id)) {
            jsonError('ID invalide.');
        }

        // Check if used by any meeting
        $usedByMeeting = (int)db_scalar(
            'SELECT COUNT(*) FROM meetings WHERE vote_policy_id = :id AND tenant_id = :tid',
            [':id' => $id, ':tid' => $tenantId]
        );
        if ($usedByMeeting > 0) {
            jsonError('Cette politique est utilisee par ' . $usedByMeeting . ' seance(s). Modifiez-les avant de supprimer cette politique.');
        }

        // Check if used by any motion
        $usedByMotion = (int)db_scalar(
            'SELECT COUNT(*) FROM motions WHERE vote_policy_id = :id AND tenant_id = :tid',
            [':id' => $id, ':tid' => $tenantId]
        );
        if ($usedByMotion > 0) {
            jsonError('Cette politique est utilisee par ' . $usedByMotion . ' resolution(s). Modifiez-les avant de supprimer cette politique.');
        }

        // Verify ownership
        $exists = db_scalar(
            'SELECT COUNT(*) FROM vote_policies WHERE id = :id AND tenant_id = :tid',
            [':id' => $id, ':tid' => $tenantId]
        );
        if (!$exists) {
            jsonError('Politique introuvable.', 404);
        }

        db_exec('DELETE FROM vote_policies WHERE id = :id AND tenant_id = :tid', [':id' => $id, ':tid' => $tenantId]);

        audit_log('admin:vote_policy_deleted', 'vote_policy', $id, ['id' => $id]);

        jsonOk(['deleted' => $id]);
    }

    // ─── Validate common fields for create/update ───
    $schema = InputValidator::schema();
    $schema->string('name')->required()->minLength(2)->maxLength(255);
    $schema->string('description')->optional()->nullable()->maxLength(1000);
    $schema->enum('base', ['expressed', 'total_eligible'])->required();
    $schema->number('threshold')->required()->min(0)->max(1);
    $schema->boolean('abstention_as_against')->optional()->default(false);

    $result = $schema->validate($input);
    if (!$result->isValid()) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'error' => 'validation_failed',
            'details' => $result->errors()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = $result->data();

    // ─── CREATE ───
    if ($action === 'create') {
        $id = api_uuid4();

        $sql = 'INSERT INTO vote_policies
                (id, tenant_id, name, description, base, threshold, abstention_as_against)
                VALUES
                (:id, :tid, :name, :desc, :base, :threshold, :abs)';

        db_exec($sql, [
            ':id'        => $id,
            ':tid'       => $tenantId,
            ':name'      => $data['name'],
            ':desc'      => $data['description'] ?? null,
            ':base'      => $data['base'],
            ':threshold' => $data['threshold'],
            ':abs'       => $data['abstention_as_against'] ? 't' : 'f',
        ]);

        audit_log('admin:vote_policy_created', 'vote_policy', $id, [
            'name'      => $data['name'],
            'base'      => $data['base'],
            'threshold' => $data['threshold'],
        ]);

        jsonOk(['id' => $id]);
    }

    // ─── UPDATE ───
    if ($action === 'update') {
        $id = trim((string)($input['id'] ?? ''));
        if (!isValidUuid($id)) {
            jsonError('ID invalide.');
        }

        // Verify ownership
        $exists = db_scalar(
            'SELECT COUNT(*) FROM vote_policies WHERE id = :id AND tenant_id = :tid',
            [':id' => $id, ':tid' => $tenantId]
        );
        if (!$exists) {
            jsonError('Politique introuvable.', 404);
        }

        $sql = 'UPDATE vote_policies SET
                    name = :name,
                    description = :desc,
                    base = :base,
                    threshold = :threshold,
                    abstention_as_against = :abs
                WHERE id = :id AND tenant_id = :tid';

        db_exec($sql, [
            ':id'        => $id,
            ':tid'       => $tenantId,
            ':name'      => $data['name'],
            ':desc'      => $data['description'] ?? null,
            ':base'      => $data['base'],
            ':threshold' => $data['threshold'],
            ':abs'       => $data['abstention_as_against'] ? 't' : 'f',
        ]);

        audit_log('admin:vote_policy_updated', 'vote_policy', $id, [
            'name'      => $data['name'],
            'base'      => $data['base'],
            'threshold' => $data['threshold'],
        ]);

        jsonOk(['id' => $id]);
    }
}

// Fallthrough - should not happen
jsonError('Action non traitee.', 500);
