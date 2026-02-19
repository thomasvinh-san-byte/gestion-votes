<?php
// public/api/v1/members.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MemberRepository;
use AgVote\Repository\MemberGroupRepository;
use AgVote\Core\Validation\Schemas\ValidationSchemas;

api_require_role('operator');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$repo = new MemberRepository();
$tenantId = api_current_tenant_id();

try {
    if ($method === 'GET') {
        // Use listAll() to return both active and inactive members for the management page
        $members = $repo->listAll($tenantId);

        // Include groups if requested
        $includeGroups = isset($_GET['include_groups']) && $_GET['include_groups'];
        if ($includeGroups) {
            $groupRepo = new MemberGroupRepository();
            foreach ($members as &$member) {
                $member['groups'] = $groupRepo->listGroupsForMember($member['id'], $tenantId);
            }
        }

        api_ok(['members' => $members]);

    } elseif ($method === 'POST') {
        $input = json_decode($GLOBALS['__ag_vote_raw_body'] ?? file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_POST;

        // Normalize legacy field name
        if (isset($input['vote_weight']) && !isset($input['voting_power'])) {
            $input['voting_power'] = $input['vote_weight'];
        }

        $v = ValidationSchemas::member()->validate($input);
        $v->failIfInvalid();

        $full_name    = $v->get('full_name');
        $email        = $v->get('email', '');
        $voting_power = $v->get('voting_power', 1);
        $is_active    = $v->get('is_active', true);
        $id = api_uuid4();

        $repo->create($id, $tenantId, $full_name, $email, $voting_power, $is_active);

        audit_log('member_created', 'member', $id, ['full_name' => $full_name]);

        api_ok(['member_id' => $id, 'full_name' => $full_name], 201);

    } elseif ($method === 'PATCH' || $method === 'PUT') {
        $input = json_decode($GLOBALS['__ag_vote_raw_body'] ?? file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_POST;

        $id = trim($input['id'] ?? $input['member_id'] ?? '');
        if ($id === '' || !api_is_uuid($id)) {
            api_fail('missing_member_id', 422, ['detail' => 'ID membre requis.']);
        }

        // Vérifier que le membre appartient au tenant courant (isolation)
        if (!$repo->existsForTenant($id, $tenantId)) {
            api_fail('member_not_found', 404, ['detail' => 'Membre introuvable.']);
        }

        // Normalize legacy field name
        if (isset($input['vote_weight']) && !isset($input['voting_power'])) {
            $input['voting_power'] = $input['vote_weight'];
        }

        $v = ValidationSchemas::member()->validate($input);
        $v->failIfInvalid();

        $full_name    = $v->get('full_name');
        $email        = $v->get('email', '');
        $voting_power = $v->get('voting_power', 1);
        $is_active    = $v->get('is_active', true);

        $repo->updateImport($id, $full_name, $email ?: null, $voting_power, $is_active);

        audit_log('member_updated', 'member', $id, ['full_name' => $full_name]);

        api_ok(['member_id' => $id, 'full_name' => $full_name]);

    } elseif ($method === 'DELETE') {
        $input = json_decode($GLOBALS['__ag_vote_raw_body'] ?? file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_GET;

        $id = trim($input['id'] ?? $input['member_id'] ?? '');
        if ($id === '' || !api_is_uuid($id)) {
            api_fail('missing_member_id', 422, ['detail' => 'ID membre requis.']);
        }

        // Vérifier que le membre appartient au tenant courant (isolation)
        if (!$repo->existsForTenant($id, $tenantId)) {
            api_fail('member_not_found', 404, ['detail' => 'Membre introuvable.']);
        }

        $repo->softDelete($id);

        audit_log('member_deleted', 'member', $id);

        api_ok(['member_id' => $id, 'deleted' => true]);

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
    api_fail('internal_error', 500, ['detail' => $e->getMessage()]);
}
