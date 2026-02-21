<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\MemberGroupRepository;
use AgVote\Repository\MemberRepository;

/**
 * Consolidates member_groups.php and member_group_assignments.php.
 */
final class MemberGroupsController extends AbstractController
{
    // ── member_groups CRUD ──

    public function list(): void
    {
        $tenantId = api_current_tenant_id();
        $repo = new MemberGroupRepository();
        $groupId = trim((string)($_GET['id'] ?? ''));
        $includeInactive = ($_GET['include_inactive'] ?? '') === '1';

        if ($groupId !== '') {
            if (!api_is_uuid($groupId)) {
                api_fail('invalid_group_id', 400);
            }

            $group = $repo->findById($groupId, $tenantId);
            if (!$group) {
                api_fail('group_not_found', 404);
            }

            $members = $repo->listMembersInGroup($groupId, $tenantId);
            $group['members'] = $members;

            api_ok(['group' => $group]);
        }

        $groups = $repo->listForTenant($tenantId, !$includeInactive);
        api_ok(['groups' => $groups, 'total' => count($groups)]);
    }

    public function create(): void
    {
        $input = api_request('POST');
        $tenantId = api_current_tenant_id();
        $repo = new MemberGroupRepository();

        $name = trim((string)($input['name'] ?? ''));
        $description = trim((string)($input['description'] ?? '')) ?: null;
        $color = trim((string)($input['color'] ?? '')) ?: null;
        $sortOrder = isset($input['sort_order']) ? (int)$input['sort_order'] : null;

        if ($name === '') {
            throw new \InvalidArgumentException('Le nom du groupe est requis');
        }
        if (mb_strlen($name) > 100) {
            throw new \InvalidArgumentException('Le nom ne peut pas depasser 100 caracteres');
        }
        if ($color !== null && !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            throw new \InvalidArgumentException('Format de couleur invalide (attendu: #RRGGBB)');
        }
        if ($repo->nameExists($name, $tenantId)) {
            throw new \InvalidArgumentException('Un groupe avec ce nom existe deja');
        }

        $group = $repo->create($tenantId, $name, $description, $color, $sortOrder);

        audit_log('member_group_created', 'member_group', $group['id'], ['name' => $name]);

        api_ok(['group' => $group], 201);
    }

    public function update(): void
    {
        $input = api_request('PATCH');
        $tenantId = api_current_tenant_id();
        $repo = new MemberGroupRepository();

        $groupId = trim((string)($input['id'] ?? ''));
        $name = trim((string)($input['name'] ?? ''));
        $description = isset($input['description']) ? trim((string)$input['description']) : null;
        $color = isset($input['color']) ? trim((string)$input['color']) : null;
        $sortOrder = isset($input['sort_order']) ? (int)$input['sort_order'] : null;
        $isActive = isset($input['is_active']) ? (bool)$input['is_active'] : null;

        if (!api_is_uuid($groupId)) {
            throw new \InvalidArgumentException('ID de groupe invalide');
        }
        if ($name === '') {
            throw new \InvalidArgumentException('Le nom du groupe est requis');
        }
        if (mb_strlen($name) > 100) {
            throw new \InvalidArgumentException('Le nom ne peut pas depasser 100 caracteres');
        }
        if ($color !== null && $color !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            throw new \InvalidArgumentException('Format de couleur invalide (attendu: #RRGGBB)');
        }

        $existing = $repo->findById($groupId, $tenantId);
        if (!$existing) {
            api_fail('group_not_found', 404);
        }
        if ($repo->nameExists($name, $tenantId, $groupId)) {
            throw new \InvalidArgumentException('Un groupe avec ce nom existe deja');
        }

        $group = $repo->update($groupId, $tenantId, $name, $description, $color ?: null, $sortOrder, $isActive);

        audit_log('member_group_updated', 'member_group', $groupId, ['name' => $name, 'is_active' => $isActive]);

        api_ok(['group' => $group]);
    }

    public function delete(): void
    {
        $tenantId = api_current_tenant_id();
        $repo = new MemberGroupRepository();
        $groupId = trim((string)($_GET['id'] ?? ''));

        if (!api_is_uuid($groupId)) {
            throw new \InvalidArgumentException('ID de groupe invalide');
        }

        $existing = $repo->findById($groupId, $tenantId);
        if (!$existing) {
            api_fail('group_not_found', 404);
        }

        $deleted = $repo->delete($groupId, $tenantId);

        if ($deleted) {
            audit_log('member_group_deleted', 'member_group', $groupId, [
                'name' => $existing['name'],
                'had_members' => (int)$existing['member_count'],
            ]);
        }

        api_ok(['deleted' => $deleted]);
    }

    // ── member_group_assignments ──

    public function assign(): void
    {
        $input = api_request('POST');
        $tenantId = api_current_tenant_id();
        $userId = api_current_user_id();

        $groupRepo = new MemberGroupRepository();
        $memberRepo = new MemberRepository();

        $memberId = trim((string)($input['member_id'] ?? ''));
        $groupId = trim((string)($input['group_id'] ?? ''));

        if (!api_is_uuid($memberId)) {
            throw new \InvalidArgumentException('member_id invalide');
        }
        if (!api_is_uuid($groupId)) {
            throw new \InvalidArgumentException('group_id invalide');
        }

        $member = $memberRepo->findByIdForTenant($memberId, $tenantId);
        if (!$member) {
            api_fail('member_not_found', 404);
        }

        $group = $groupRepo->findById($groupId, $tenantId);
        if (!$group) {
            api_fail('group_not_found', 404);
        }

        $groupRepo->assignMember($memberId, $groupId, $userId);

        audit_log('member_assigned_to_group', 'member', $memberId, [
            'group_id' => $groupId,
            'group_name' => $group['name'],
            'member_name' => $member['full_name'],
        ]);

        api_ok(['assigned' => true, 'member_id' => $memberId, 'group_id' => $groupId]);
    }

    public function unassign(): void
    {
        $tenantId = api_current_tenant_id();
        $groupRepo = new MemberGroupRepository();
        $memberRepo = new MemberRepository();

        $memberId = trim((string)($_GET['member_id'] ?? ''));
        $groupId = trim((string)($_GET['group_id'] ?? ''));

        if (!api_is_uuid($memberId)) {
            throw new \InvalidArgumentException('member_id invalide');
        }
        if (!api_is_uuid($groupId)) {
            throw new \InvalidArgumentException('group_id invalide');
        }

        $member = $memberRepo->findByIdForTenant($memberId, $tenantId);
        if (!$member) {
            api_fail('member_not_found', 404);
        }

        $group = $groupRepo->findById($groupId, $tenantId);
        if (!$group) {
            api_fail('group_not_found', 404);
        }

        $removed = $groupRepo->unassignMember($memberId, $groupId);

        if ($removed) {
            audit_log('member_removed_from_group', 'member', $memberId, [
                'group_id' => $groupId,
                'group_name' => $group['name'],
                'member_name' => $member['full_name'],
            ]);
        }

        api_ok(['removed' => $removed, 'member_id' => $memberId, 'group_id' => $groupId]);
    }

    public function setMemberGroups(): void
    {
        $input = api_request('PUT');
        $tenantId = api_current_tenant_id();
        $userId = api_current_user_id();
        $groupRepo = new MemberGroupRepository();
        $memberRepo = new MemberRepository();

        $memberId = trim((string)($input['member_id'] ?? ''));
        $groupIds = $input['group_ids'] ?? [];

        if (!api_is_uuid($memberId)) {
            throw new \InvalidArgumentException('member_id invalide');
        }
        if (!is_array($groupIds)) {
            throw new \InvalidArgumentException('group_ids doit etre un tableau');
        }

        $member = $memberRepo->findByIdForTenant($memberId, $tenantId);
        if (!$member) {
            api_fail('member_not_found', 404);
        }

        $validGroupIds = [];
        foreach ($groupIds as $groupId) {
            $groupId = trim((string)$groupId);
            if (!api_is_uuid($groupId)) {
                throw new \InvalidArgumentException("group_id invalide: {$groupId}");
            }
            $group = $groupRepo->findById($groupId, $tenantId);
            if (!$group) {
                throw new \InvalidArgumentException("Groupe introuvable: {$groupId}");
            }
            $validGroupIds[] = $groupId;
        }

        $groupRepo->setMemberGroups($memberId, $validGroupIds, $userId);

        audit_log('member_groups_updated', 'member', $memberId, [
            'group_ids' => $validGroupIds,
            'group_count' => count($validGroupIds),
            'member_name' => $member['full_name'],
        ]);

        $groups = $groupRepo->listGroupsForMember($memberId, $tenantId);
        api_ok(['member_id' => $memberId, 'groups' => $groups, 'total' => count($groups)]);
    }

    public function bulkAssign(): void
    {
        $input = api_request('POST');
        $tenantId = api_current_tenant_id();
        $userId = api_current_user_id();
        $groupRepo = new MemberGroupRepository();
        $memberRepo = new MemberRepository();

        $groupId = trim((string)($input['group_id'] ?? ''));
        $memberIds = $input['member_ids'] ?? [];

        if (!api_is_uuid($groupId)) {
            throw new \InvalidArgumentException('group_id invalide');
        }
        if (!is_array($memberIds) || empty($memberIds)) {
            throw new \InvalidArgumentException('member_ids doit etre un tableau non vide');
        }

        $group = $groupRepo->findById($groupId, $tenantId);
        if (!$group) {
            api_fail('group_not_found', 404);
        }

        $validMemberIds = [];
        foreach ($memberIds as $memberId) {
            $memberId = trim((string)$memberId);
            if (!api_is_uuid($memberId)) {
                continue;
            }
            $member = $memberRepo->findByIdForTenant($memberId, $tenantId);
            if ($member) {
                $validMemberIds[] = $memberId;
            }
        }

        if (empty($validMemberIds)) {
            throw new \InvalidArgumentException('Aucun membre valide trouve');
        }

        $count = $groupRepo->bulkAssignToGroup($groupId, $validMemberIds, $userId);

        audit_log('members_bulk_assigned_to_group', 'member_group', $groupId, [
            'group_name' => $group['name'],
            'member_count' => $count,
        ]);

        api_ok(['group_id' => $groupId, 'assigned_count' => $count, 'member_ids' => $validMemberIds]);
    }
}
