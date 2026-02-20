<?php
// ADMIN: liste + upsert vote_policies (majorité)
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\PolicyRepository;
use AgVote\Core\Validation\Schemas\ValidationSchemas;

api_require_role('admin');

$method = api_method();

$repo = new PolicyRepository();

try {
    if ($method === 'GET') {
        api_request('GET');
        $rows = $repo->listVotePolicies(api_current_tenant_id());
        api_ok(['items' => $rows]);
    }

    if ($method === 'POST') {
        $in = api_request('POST');

        $action = trim((string)($in['action'] ?? ''));

        // ── Supprimer une politique de vote ──
        if ($action === 'delete') {
            $id = trim((string)($in['id'] ?? ''));
            if ($id === '' || !api_is_uuid($id)) api_fail('missing_id', 400);

            $repo->deleteVotePolicy($id, api_current_tenant_id());
            audit_log('admin_vote_policy_deleted', 'vote_policy', $id, []);
            api_ok(['deleted' => true, 'id' => $id]);
        }

        // ── Créer ou mettre à jour ──
        $id = trim((string)($in['id'] ?? ''));

        $v = ValidationSchemas::votePolicy()->validate($in);
        $v->failIfInvalid();

        $name      = $v->get('name');
        $desc      = $v->get('description');
        $base      = $v->get('base', 'expressed');
        $threshold = $v->get('threshold');
        $abstBool  = $v->get('abstention_as_against', false);

        if ($id !== '') {
            if (!api_is_uuid($id)) api_fail('invalid_id', 400);
            $repo->updateVotePolicy(
                $id, api_current_tenant_id(), $name, $desc,
                $base, $threshold, $abstBool
            );
        } else {
            $id = $repo->generateUuid();
            $repo->createVotePolicy(
                $id, api_current_tenant_id(), $name, $base, $threshold, $abstBool
            );
        }

        audit_log('admin_vote_policy_saved', 'vote_policy', $id, ['name' => $name, 'base' => $base]);
        api_ok(['saved' => true, 'id' => $id]);
    }

    api_fail('method_not_allowed', 405);

} catch (Throwable $e) {
    error_log('Error in admin_vote_policies.php: ' . $e->getMessage());
    api_fail('server_error', 500, ['detail' => $e->getMessage()]);
}
