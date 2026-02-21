<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Validation\Schemas\ValidationSchemas;
use AgVote\Repository\PolicyRepository;

/**
 * Consolidates 4 policy endpoints (quorum + vote, public list + admin CRUD).
 *
 * The two admin methods share the same GET/POST/delete/upsert skeleton.
 */
final class PoliciesController extends AbstractController {
    public function listQuorum(): void {
        api_request('GET');
        $rows = (new PolicyRepository())->listQuorumPolicies(api_current_tenant_id());
        api_ok(['items' => $rows]);
    }

    public function listVote(): void {
        api_request('GET');
        $rows = (new PolicyRepository())->listVotePolicies(api_current_tenant_id());
        api_ok(['items' => $rows]);
    }

    public function adminQuorum(): void {
        $method = api_method();
        $repo = new PolicyRepository();
        $tenantId = api_current_tenant_id();

        if ($method === 'GET') {
            api_request('GET');
            api_ok(['items' => $repo->listQuorumPolicies($tenantId)]);
        }

        if ($method === 'POST') {
            $in = api_request('POST');
            $action = trim((string) ($in['action'] ?? ''));

            if ($action === 'delete') {
                $id = trim((string) ($in['id'] ?? ''));
                if ($id === '' || !api_is_uuid($id)) {
                    api_fail('missing_id', 400);
                }
                $repo->deleteQuorumPolicy($id, $tenantId);
                audit_log('admin_quorum_policy_deleted', 'quorum_policy', $id, []);
                api_ok(['deleted' => true, 'id' => $id]);
            }

            $id = trim((string) ($in['id'] ?? ''));
            $v = ValidationSchemas::quorumPolicy()->validate($in);
            $v->failIfInvalid();

            $name = $v->get('name');
            $desc = $v->get('description');
            $mode = $v->get('mode', 'single');
            $den = $v->get('denominator', 'eligible_members');
            $threshold = $v->get('threshold');
            $thresholdCall2 = $v->get('threshold_call2');
            $den2 = $v->get('denominator2');
            $threshold2 = $v->get('threshold2');
            $includeProxies = $v->get('include_proxies', true);
            $countRemote = $v->get('count_remote', true);

            if ($id !== '') {
                if (!api_is_uuid($id)) {
                    api_fail('invalid_id', 400);
                }
                $repo->updateQuorumPolicy(
                    $id,
                    $tenantId,
                    $name,
                    $desc,
                    $mode,
                    $den,
                    $threshold,
                    $thresholdCall2,
                    $den2,
                    $threshold2,
                    $includeProxies,
                    $countRemote,
                );
            } else {
                $id = $repo->generateUuid();
                $repo->createQuorumPolicy(
                    $id,
                    $tenantId,
                    $name,
                    $mode,
                    $den,
                    $threshold,
                    $threshold2,
                    $den2,
                    $includeProxies,
                    $countRemote,
                    $thresholdCall2,
                    $desc,
                );
            }

            audit_log('admin_quorum_policy_saved', 'quorum_policy', $id, ['name' => $name, 'mode' => $mode]);
            api_ok(['saved' => true, 'id' => $id]);
        }

        api_fail('method_not_allowed', 405);
    }

    public function adminVote(): void {
        $method = api_method();
        $repo = new PolicyRepository();
        $tenantId = api_current_tenant_id();

        if ($method === 'GET') {
            api_request('GET');
            api_ok(['items' => $repo->listVotePolicies($tenantId)]);
        }

        if ($method === 'POST') {
            $in = api_request('POST');
            $action = trim((string) ($in['action'] ?? ''));

            if ($action === 'delete') {
                $id = trim((string) ($in['id'] ?? ''));
                if ($id === '' || !api_is_uuid($id)) {
                    api_fail('missing_id', 400);
                }
                $repo->deleteVotePolicy($id, $tenantId);
                audit_log('admin_vote_policy_deleted', 'vote_policy', $id, []);
                api_ok(['deleted' => true, 'id' => $id]);
            }

            $id = trim((string) ($in['id'] ?? ''));
            $v = ValidationSchemas::votePolicy()->validate($in);
            $v->failIfInvalid();

            $name = $v->get('name');
            $desc = $v->get('description');
            $base = $v->get('base', 'expressed');
            $threshold = $v->get('threshold');
            $abstBool = $v->get('abstention_as_against', false);

            if ($id !== '') {
                if (!api_is_uuid($id)) {
                    api_fail('invalid_id', 400);
                }
                $repo->updateVotePolicy($id, $tenantId, $name, $desc, $base, $threshold, $abstBool);
            } else {
                $id = $repo->generateUuid();
                $repo->createVotePolicy($id, $tenantId, $name, $base, $threshold, $abstBool);
            }

            audit_log('admin_vote_policy_saved', 'vote_policy', $id, ['name' => $name, 'base' => $base]);
            api_ok(['saved' => true, 'id' => $id]);
        }

        api_fail('method_not_allowed', 405);
    }
}
