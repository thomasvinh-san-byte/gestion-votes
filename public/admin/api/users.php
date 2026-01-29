<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

AuthMiddleware::requireRole('admin');

$tenantId = AuthMiddleware::getCurrentTenantId();

$allowedRoles = ['admin', 'operator', 'president', 'trust', 'viewer'];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function json_response(array $payload, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_ok(array $data = []): never
{
    $payload = ['ok' => true];
    if (!empty($data)) {
        $payload['data'] = $data;
    }
    json_response($payload);
}

function json_error(string $message, int $code = 400): never
{
    json_response(['ok' => false, 'error' => $message], $code);
}

function is_valid_uuid(string $value): bool
{
    return (bool) preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        $value
    );
}

function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------------------------------------------------------------------------
// Determine request method and action
// ---------------------------------------------------------------------------

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    if ($method === 'GET') {
        // -----------------------------------------------------------------
        // GET: List users
        // -----------------------------------------------------------------
        $stmt = db()->prepare(
            'SELECT id, tenant_id, email, name, role, is_active, created_at, updated_at
             FROM users
             WHERE tenant_id = :tid
             ORDER BY created_at DESC'
        );
        $stmt->execute([':tid' => $tenantId]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cast is_active to boolean for JSON
        foreach ($users as &$u) {
            $u['is_active'] = (bool) $u['is_active'];
        }
        unset($u);

        json_ok(['users' => $users]);
    }

    if ($method !== 'POST') {
        json_error('method_not_allowed', 405);
    }

    // Read JSON body
    $rawBody = file_get_contents('php://input');
    $body = json_decode($rawBody, true);

    if (!is_array($body)) {
        json_error('invalid_json_body');
    }

    $action = sanitize((string) ($body['action'] ?? ''));

    // =====================================================================
    // POST action=create
    // =====================================================================
    if ($action === 'create') {
        // Validate name
        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 255) {
            json_error('Le nom est requis (2-255 caracteres).');
        }
        $name = sanitize($name);

        // Validate email (optional)
        $email = trim((string) ($body['email'] ?? ''));
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_error('Adresse email invalide.');
            }
            $email = mb_strtolower($email);
        } else {
            $email = null;
        }

        // Validate role
        $role = trim((string) ($body['role'] ?? ''));
        if (!in_array($role, $allowedRoles, true)) {
            json_error('Role invalide. Valeurs autorisees : ' . implode(', ', $allowedRoles));
        }

        // Generate API key
        $keyData = AuthMiddleware::generateApiKey();
        $rawKey = $keyData['key'];
        $keyHash = $keyData['hash'];

        // Generate UUID
        $newId = api_uuid4();

        $stmt = db()->prepare(
            'INSERT INTO users (id, tenant_id, email, name, role, api_key_hash, is_active, created_at, updated_at)
             VALUES (:id, :tid, :email, :name, :role, :hash, true, NOW(), NOW())'
        );
        $stmt->execute([
            ':id'    => $newId,
            ':tid'   => $tenantId,
            ':email' => $email,
            ':name'  => $name,
            ':role'  => $role,
            ':hash'  => $keyHash,
        ]);

        audit_log('admin:user_created', 'user', $newId, [
            'name'  => $name,
            'email' => $email,
            'role'  => $role,
        ]);

        json_ok([
            'user' => [
                'id'         => $newId,
                'tenant_id'  => $tenantId,
                'email'      => $email,
                'name'       => $name,
                'role'       => $role,
                'is_active'  => true,
            ],
            'api_key' => $rawKey,
        ]);
    }

    // =====================================================================
    // POST action=update
    // =====================================================================
    if ($action === 'update') {
        $id = trim((string) ($body['id'] ?? ''));
        if (!is_valid_uuid($id)) {
            json_error('ID utilisateur invalide.');
        }

        // Verify user exists in this tenant
        $existing = db_one(
            'SELECT id, tenant_id, email, name, role, is_active FROM users WHERE id = :id AND tenant_id = :tid',
            [':id' => $id, ':tid' => $tenantId]
        );
        if (!$existing) {
            json_error('Utilisateur introuvable.', 404);
        }

        // Validate name
        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 255) {
            json_error('Le nom est requis (2-255 caracteres).');
        }
        $name = sanitize($name);

        // Validate email (optional)
        $email = trim((string) ($body['email'] ?? ''));
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_error('Adresse email invalide.');
            }
            $email = mb_strtolower($email);
        } else {
            $email = null;
        }

        // Validate role
        $role = trim((string) ($body['role'] ?? ''));
        if (!in_array($role, $allowedRoles, true)) {
            json_error('Role invalide. Valeurs autorisees : ' . implode(', ', $allowedRoles));
        }

        // is_active
        $isActive = isset($body['is_active']) ? (bool) $body['is_active'] : (bool) $existing['is_active'];

        $stmt = db()->prepare(
            'UPDATE users
             SET name = :name, email = :email, role = :role, is_active = :active, updated_at = NOW()
             WHERE id = :id AND tenant_id = :tid'
        );
        $stmt->execute([
            ':name'   => $name,
            ':email'  => $email,
            ':role'   => $role,
            ':active' => $isActive ? 't' : 'f',
            ':id'     => $id,
            ':tid'    => $tenantId,
        ]);

        audit_log('admin:user_updated', 'user', $id, [
            'name'      => $name,
            'email'     => $email,
            'role'      => $role,
            'is_active' => $isActive,
        ]);

        json_ok([
            'user' => [
                'id'         => $id,
                'tenant_id'  => $tenantId,
                'email'      => $email,
                'name'       => $name,
                'role'       => $role,
                'is_active'  => $isActive,
            ],
        ]);
    }

    // =====================================================================
    // POST action=toggle_active
    // =====================================================================
    if ($action === 'toggle_active') {
        $id = trim((string) ($body['id'] ?? ''));
        if (!is_valid_uuid($id)) {
            json_error('ID utilisateur invalide.');
        }

        $existing = db_one(
            'SELECT id, is_active FROM users WHERE id = :id AND tenant_id = :tid',
            [':id' => $id, ':tid' => $tenantId]
        );
        if (!$existing) {
            json_error('Utilisateur introuvable.', 404);
        }

        $newState = !((bool) $existing['is_active']);

        $stmt = db()->prepare(
            'UPDATE users SET is_active = :active, updated_at = NOW() WHERE id = :id AND tenant_id = :tid'
        );
        $stmt->execute([
            ':active' => $newState ? 't' : 'f',
            ':id'     => $id,
            ':tid'    => $tenantId,
        ]);

        audit_log('admin:user_toggled', 'user', $id, [
            'is_active' => $newState,
        ]);

        json_ok();
    }

    // =====================================================================
    // POST action=regenerate_key
    // =====================================================================
    if ($action === 'regenerate_key') {
        $id = trim((string) ($body['id'] ?? ''));
        if (!is_valid_uuid($id)) {
            json_error('ID utilisateur invalide.');
        }

        $existing = db_one(
            'SELECT id FROM users WHERE id = :id AND tenant_id = :tid',
            [':id' => $id, ':tid' => $tenantId]
        );
        if (!$existing) {
            json_error('Utilisateur introuvable.', 404);
        }

        $keyData = AuthMiddleware::generateApiKey();
        $rawKey = $keyData['key'];
        $keyHash = $keyData['hash'];

        $stmt = db()->prepare(
            'UPDATE users SET api_key_hash = :hash, updated_at = NOW() WHERE id = :id AND tenant_id = :tid'
        );
        $stmt->execute([
            ':hash' => $keyHash,
            ':id'   => $id,
            ':tid'  => $tenantId,
        ]);

        audit_log('admin:apikey_regenerated', 'user', $id, [
            'regenerated' => true,
        ]);

        json_ok(['api_key' => $rawKey]);
    }

    // Unknown action
    json_error('Action inconnue : ' . $action);

} catch (\Throwable $e) {
    error_log('admin/api/users.php error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    json_response(['ok' => false, 'error' => 'internal_error'], 500);
}
