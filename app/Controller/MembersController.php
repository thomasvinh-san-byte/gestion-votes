<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Security\IdempotencyGuard;
use AgVote\Core\Validation\Schemas\ValidationSchemas;

/**
 * Consolidates members CRUD endpoint.
 */
final class MembersController extends AbstractController {
    public function index(): void {
        $data = api_request('GET');

        $repo = $this->repo()->member();
        $tenantId = api_current_tenant_id();

        $page    = max(1, api_query_int('page', 1));
        $perPage = max(1, min(api_query_int('per_page', 50), 50));
        $offset  = ($page - 1) * $perPage;

        $search = trim((string) $this->request->query('search', ''));

        if ($search !== '') {
            $rows  = $repo->listPaginatedFiltered($tenantId, $perPage, $offset, $search);
            $total = $repo->countFiltered($tenantId, $search);
        } else {
            $rows  = $repo->listPaginated($tenantId, $perPage, $offset);
            $total = $repo->countAll($tenantId);
        }

        $includeGroups = isset($data['include_groups']) && $data['include_groups'];
        if ($includeGroups) {
            $groupRepo  = $this->repo()->memberGroup();
            $memberIds  = array_column($rows, 'id');
            $groupsMap  = $groupRepo->listGroupsForMembers($memberIds, $tenantId);
            foreach ($rows as &$member) {
                $member['groups'] = $groupsMap[$member['id']] ?? [];
            }
            unset($member);
        }

        api_ok([
            'items'      => $rows,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public function create(): void {
        $cached = IdempotencyGuard::check();
        if ($cached !== null) {
            api_ok($cached, 201);
        }

        $input = api_request('POST');

        // Normalize legacy field name
        if (isset($input['vote_weight']) && !isset($input['voting_power'])) {
            $input['voting_power'] = $input['vote_weight'];
        }

        $v = ValidationSchemas::member()->validate($input);
        $v->failIfInvalid();

        $full_name = $v->get('full_name');
        $email = $v->get('email', '');
        $voting_power = $v->get('voting_power', 1);
        $is_active = $v->get('is_active', true);
        $id = api_uuid4();

        $repo = $this->repo()->member();
        $tenantId = api_current_tenant_id();

        $repo->create($id, $tenantId, $full_name, $email, $voting_power, $is_active);

        audit_log('member_created', 'member', $id, ['full_name' => $full_name]);

        $result = ['member_id' => $id, 'full_name' => $full_name];
        IdempotencyGuard::store($result);
        api_ok($result, 201);
    }

    public function updateMember(): void {
        $input = api_request('PATCH', 'PUT');

        $id = trim($input['id'] ?? $input['member_id'] ?? '');
        if ($id === '' || !api_is_uuid($id)) {
            api_fail('missing_member_id', 422, ['detail' => 'ID membre requis.']);
        }

        $repo = $this->repo()->member();
        $tenantId = api_current_tenant_id();

        if (!$repo->existsForTenant($id, $tenantId)) {
            api_fail('member_not_found', 404, ['detail' => 'Membre introuvable.']);
        }

        // Normalize legacy field name
        if (isset($input['vote_weight']) && !isset($input['voting_power'])) {
            $input['voting_power'] = $input['vote_weight'];
        }

        $v = ValidationSchemas::member()->validate($input);
        $v->failIfInvalid();

        $full_name = $v->get('full_name');
        $email = $v->get('email', '');
        $voting_power = $v->get('voting_power', 1);
        $is_active = $v->get('is_active', true);

        $repo->updateImport($id, $full_name, $email ?: null, $voting_power, $is_active, $tenantId);

        audit_log('member_updated', 'member', $id, ['full_name' => $full_name]);

        api_ok(['member_id' => $id, 'full_name' => $full_name]);
    }

    public function delete(): void {
        api_request('DELETE');

        $id = api_query('member_id') ?: api_query('id');
        if ($id === '' || !api_is_uuid($id)) {
            api_fail('missing_member_id', 422, ['detail' => 'ID membre requis.']);
        }

        $repo = $this->repo()->member();
        $tenantId = api_current_tenant_id();

        if (!$repo->existsForTenant($id, $tenantId)) {
            api_fail('member_not_found', 404, ['detail' => 'Membre introuvable.']);
        }

        $repo->softDelete($id, $tenantId);

        audit_log('member_deleted', 'member', $id);

        api_ok(['member_id' => $id, 'deleted' => true]);
    }

    public function presidents(): void {
        api_request('GET');

        $repo = $this->repo()->member();
        $rows = $repo->listActiveForPresident(api_current_tenant_id());

        api_ok(['items' => $rows]);
    }

    public function bulk(): void {
        $input = api_request('POST');
        $cached = IdempotencyGuard::check();
        if ($cached !== null) { api_ok($cached); }

        $operation = trim($input['operation'] ?? '');
        if ($operation === '') {
            api_fail('missing_operation', 422, ['detail' => 'Le champ operation est requis.']);
        }

        $validOps = ['assign_group', 'update_voting_power'];
        if (!in_array($operation, $validOps, true)) {
            api_fail('invalid_operation', 422, ['detail' => 'Operation invalide. Valeurs acceptees : assign_group, update_voting_power.']);
        }

        $memberIds = $input['member_ids'] ?? null;
        if (!is_array($memberIds) || count($memberIds) === 0 || count($memberIds) > 200) {
            api_fail('invalid_member_ids', 422, ['detail' => 'member_ids doit etre un tableau de 1 a 200 elements.']);
        }

        foreach ($memberIds as $mid) {
            if (!is_string($mid) || !api_is_uuid($mid)) {
                api_fail('invalid_member_id', 422, ['detail' => 'Chaque element de member_ids doit etre un UUID valide.']);
            }
        }

        $tenantId  = api_current_tenant_id();
        $memberRepo = $this->repo()->member();
        $affected   = 0;

        if ($operation === 'assign_group') {
            $groupId = trim($input['group_id'] ?? '');
            if ($groupId === '' || !api_is_uuid($groupId)) {
                api_fail('missing_group_id', 422, ['detail' => 'group_id est requis pour assign_group.']);
            }

            $validIds = $memberRepo->filterExistingIds($memberIds, $tenantId);
            $groupRepo = $this->repo()->memberGroup();
            $affected  = $groupRepo->bulkAssignToGroup($groupId, $validIds);

            audit_log('members_bulk_assign_group', 'group', $groupId, [
                'member_count' => count($validIds),
            ]);
        } elseif ($operation === 'update_voting_power') {
            $power = isset($input['voting_power']) ? (float) $input['voting_power'] : null;
            if ($power === null || $power < 0.01 || $power > 100) {
                api_fail('invalid_voting_power', 422, ['detail' => 'voting_power doit etre entre 0.01 et 100.']);
            }

            $affected = $memberRepo->bulkUpdateVotingPower($memberIds, $tenantId, $power);

            audit_log('members_bulk_update_voting_power', 'tenant', $tenantId, [
                'member_count'  => count($memberIds),
                'voting_power'  => $power,
            ]);
        }

        $response = ['operation' => $operation, 'affected' => $affected];
        IdempotencyGuard::store($response);
        api_ok($response);
    }
}
