<?php
// public/api/v1/members.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MemberRepository;

api_require_role('operator');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$repo = new MemberRepository();

try {
    if ($method === 'GET') {
        // Use listActive() to match AttendanceRepository filter (is_active = true AND deleted_at IS NULL)
        $members = $repo->listActive(api_current_tenant_id());
        api_ok(['members' => $members]);

    } elseif ($method === 'POST') {
        $input = json_decode($GLOBALS['__ag_vote_raw_body'] ?? file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_POST;

        $full_name = trim($input['full_name'] ?? '');
        if ($full_name === '') {
            api_fail('missing_full_name', 422, ['detail' => 'Le nom complet est requis.']);
        }

        $email = trim($input['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            api_fail('invalid_email', 422, ['detail' => 'Format d\'email invalide.']);
        }

        $voting_power = (float)($input['voting_power'] ?? $input['vote_weight'] ?? 1);
        $is_active = ($input['is_active'] ?? true) !== false;
        $id = api_uuid4();

        $repo->create($id, api_current_tenant_id(), $full_name, $email, $voting_power, $is_active);

        audit_log('member_created', 'member', $id, ['full_name' => $full_name]);

        api_ok(['member_id' => $id, 'full_name' => $full_name], 201);

    } elseif ($method === 'PATCH' || $method === 'PUT') {
        $input = json_decode($GLOBALS['__ag_vote_raw_body'] ?? file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_POST;

        $id = trim($input['id'] ?? $input['member_id'] ?? '');
        if ($id === '' || !api_is_uuid($id)) {
            api_fail('missing_member_id', 422, ['detail' => 'ID membre requis.']);
        }

        $full_name = trim($input['full_name'] ?? '');
        if ($full_name === '') {
            api_fail('missing_full_name', 422, ['detail' => 'Le nom complet est requis.']);
        }

        $email = trim($input['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            api_fail('invalid_email', 422, ['detail' => 'Format d\'email invalide.']);
        }

        $voting_power = (float)($input['voting_power'] ?? $input['vote_weight'] ?? 1);
        $is_active = ($input['is_active'] ?? true) !== false;

        $repo->updateImport($id, $full_name, $email ?: null, $voting_power, $is_active);

        audit_log('member_updated', 'member', $id, ['full_name' => $full_name]);

        api_ok(['member_id' => $id, 'full_name' => $full_name]);

    } else {
        api_fail('method_not_allowed', 405);
    }
} catch (\PDOException $e) {
    error_log('DB error in members.php: ' . $e->getMessage());
    if (strpos($e->getMessage(), 'unique') !== false || strpos($e->getMessage(), 'duplicate') !== false) {
        api_fail('duplicate_member', 409, ['detail' => 'Un membre avec ce nom existe déjà.']);
    }
    api_fail('database_error', 500, ['detail' => 'Erreur de base de données']);
} catch (Throwable $e) {
    error_log('Error in members.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
