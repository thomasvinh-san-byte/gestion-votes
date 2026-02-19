<?php
// ADMIN: liste + upsert quorum_policies (cahier: paramétrage global)
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\PolicyRepository;
use AgVote\Core\Validation\Schemas\ValidationSchemas;

api_require_role('admin');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$repo = new PolicyRepository();

try {
    if ($method === 'GET') {
        api_request('GET');
        $rows = $repo->listQuorumPolicies(api_current_tenant_id());
        api_ok(['items' => $rows]);
    }

    if ($method === 'POST') {
        $in = api_request('POST');

        $action = trim((string)($in['action'] ?? ''));

        // ── Supprimer une politique de quorum ──
        if ($action === 'delete') {
            $id = trim((string)($in['id'] ?? ''));
            if ($id === '' || !api_is_uuid($id)) api_fail('missing_id', 400);

            $repo->deleteQuorumPolicy($id, api_current_tenant_id());
            audit_log('admin_quorum_policy_deleted', 'quorum_policy', $id, []);
            api_ok(['deleted' => true, 'id' => $id]);
        }

        // ── Créer ou mettre à jour ──
        $id = trim((string)($in['id'] ?? ''));

        $v = ValidationSchemas::quorumPolicy()->validate($in);
        $v->failIfInvalid();

        $name           = $v->get('name');
        $desc           = $v->get('description');
        $mode           = $v->get('mode', 'single');
        $den            = $v->get('denominator', 'eligible_members');
        $threshold      = $v->get('threshold');
        $threshold_call2 = $v->get('threshold_call2');
        $den2           = $v->get('denominator2');
        $threshold2     = $v->get('threshold2');
        $includeProxies = $v->get('include_proxies', true);
        $countRemote    = $v->get('count_remote', true);

        if ($id !== '') {
            if (!api_is_uuid($id)) api_fail('invalid_id', 400);
            $repo->updateQuorumPolicy(
                $id, api_current_tenant_id(), $name, $desc,
                $mode, $den, $threshold, $threshold_call2,
                $den2, $threshold2, $includeProxies, $countRemote
            );
        } else {
            $id = $repo->generateUuid();
            $repo->createQuorumPolicy(
                $id, api_current_tenant_id(), $name, $mode, $den, $threshold,
                $threshold2, $den2,
                $includeProxies, $countRemote, $threshold_call2, $desc
            );
        }

        audit_log('admin_quorum_policy_saved', 'quorum_policy', $id, ['name' => $name, 'mode' => $mode]);
        api_ok(['saved' => true, 'id' => $id]);
    }

    api_fail('method_not_allowed', 405);

} catch (Throwable $e) {
    error_log('Error in admin_quorum_policies.php: ' . $e->getMessage());
    api_fail('server_error', 500);
}
