<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\MemberRepository;
use AgVote\Repository\MemberGroupRepository;
use AgVote\Core\Validation\Schemas\ValidationSchemas;

/**
 * Consolidates members CRUD endpoint.
 */
final class MembersController extends AbstractController
{
    public function index(): void
    {
        api_require_role('operator');
        $data = api_request('GET');

        $repo = new MemberRepository();
        $tenantId = api_current_tenant_id();
        $members = $repo->listAll($tenantId);

        $includeGroups = isset($data['include_groups']) && $data['include_groups'];
        if ($includeGroups) {
            $groupRepo = new MemberGroupRepository();
            foreach ($members as &$member) {
                $member['groups'] = $groupRepo->listGroupsForMember($member['id'], $tenantId);
            }
        }

        api_ok(['members' => $members]);
    }

    public function create(): void
    {
        api_require_role('operator');
        $input = api_request('POST');

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

        $repo = new MemberRepository();
        $tenantId = api_current_tenant_id();

        $repo->create($id, $tenantId, $full_name, $email, $voting_power, $is_active);

        audit_log('member_created', 'member', $id, ['full_name' => $full_name]);

        api_ok(['member_id' => $id, 'full_name' => $full_name], 201);
    }

    public function updateMember(): void
    {
        api_require_role('operator');
        $input = api_request('PATCH', 'PUT');

        $id = trim($input['id'] ?? $input['member_id'] ?? '');
        if ($id === '' || !api_is_uuid($id)) {
            api_fail('missing_member_id', 422, ['detail' => 'ID membre requis.']);
        }

        $repo = new MemberRepository();
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

        $full_name    = $v->get('full_name');
        $email        = $v->get('email', '');
        $voting_power = $v->get('voting_power', 1);
        $is_active    = $v->get('is_active', true);

        $repo->updateImport($id, $full_name, $email ?: null, $voting_power, $is_active, $tenantId);

        audit_log('member_updated', 'member', $id, ['full_name' => $full_name]);

        api_ok(['member_id' => $id, 'full_name' => $full_name]);
    }

    public function delete(): void
    {
        api_require_role('operator');
        $input = api_request('DELETE');

        $id = trim($input['id'] ?? $input['member_id'] ?? '');
        if ($id === '' || !api_is_uuid($id)) {
            api_fail('missing_member_id', 422, ['detail' => 'ID membre requis.']);
        }

        $repo = new MemberRepository();
        $tenantId = api_current_tenant_id();

        if (!$repo->existsForTenant($id, $tenantId)) {
            api_fail('member_not_found', 404, ['detail' => 'Membre introuvable.']);
        }

        $repo->softDelete($id, $tenantId);

        audit_log('member_deleted', 'member', $id);

        api_ok(['member_id' => $id, 'deleted' => true]);
    }
}
