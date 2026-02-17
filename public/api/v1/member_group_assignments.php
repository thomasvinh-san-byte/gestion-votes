<?php
/**
 * member_group_assignments.php - API pour gerer les assignations membres/groupes
 *
 * POST /api/v1/member_group_assignments.php - Assigner un membre a un groupe
 *   Body: { "member_id": "UUID", "group_id": "UUID" }
 *
 * DELETE /api/v1/member_group_assignments.php - Retirer un membre d'un groupe
 *   Query: ?member_id=UUID&group_id=UUID
 *
 * PUT /api/v1/member_group_assignments.php - Definir tous les groupes d'un membre
 *   Body: { "member_id": "UUID", "group_ids": ["UUID", "UUID", ...] }
 *
 * POST /api/v1/member_group_assignments.php?action=bulk - Assigner plusieurs membres a un groupe
 *   Body: { "group_id": "UUID", "member_ids": ["UUID", "UUID", ...] }
 */
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MemberGroupRepository;
use AgVote\Repository\MemberRepository;

api_require_any_role(['operator', 'admin']);

$method = $_SERVER['REQUEST_METHOD'];
$tenantId = api_current_tenant_id();
$action = trim((string) ($_GET['action'] ?? ''));
$userId = api_current_user_id();

$groupRepo = new MemberGroupRepository();
$memberRepo = new MemberRepository();

try {
    switch ($method) {
        case 'POST':
            if ($action === 'bulk') {
                handleBulkAssign($groupRepo, $memberRepo, $tenantId, $userId);
            } else {
                handleAssign($groupRepo, $memberRepo, $tenantId, $userId);
            }
            break;

        case 'PUT':
            handleSetMemberGroups($groupRepo, $memberRepo, $tenantId, $userId);
            break;

        case 'DELETE':
            handleUnassign($groupRepo, $memberRepo, $tenantId);
            break;

        default:
            api_fail('method_not_allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in member_group_assignments.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => $e->getMessage()]);
}

/**
 * Assigner un membre a un groupe.
 */
function handleAssign(
    MemberGroupRepository $groupRepo,
    MemberRepository $memberRepo,
    string $tenantId,
    ?string $userId
): void {
    $input = api_request('POST');

    $memberId = trim((string) ($input['member_id'] ?? ''));
    $groupId = trim((string) ($input['group_id'] ?? ''));

    if (!api_is_uuid($memberId)) {
        throw new InvalidArgumentException('member_id invalide');
    }

    if (!api_is_uuid($groupId)) {
        throw new InvalidArgumentException('group_id invalide');
    }

    // Verifier que le membre existe et appartient au tenant
    $member = $memberRepo->findByIdForTenant($memberId, $tenantId);
    if (!$member) {
        api_fail('member_not_found', 404);
    }

    // Verifier que le groupe existe et appartient au tenant
    $group = $groupRepo->findById($groupId, $tenantId);
    if (!$group) {
        api_fail('group_not_found', 404);
    }

    // Assigner
    $groupRepo->assignMember($memberId, $groupId, $userId);

    if (function_exists('audit_log')) {
        audit_log('member_assigned_to_group', 'member', $memberId, [
            'group_id' => $groupId,
            'group_name' => $group['name'],
            'member_name' => $member['full_name'],
        ]);
    }

    api_ok([
        'assigned' => true,
        'member_id' => $memberId,
        'group_id' => $groupId,
    ]);
}

/**
 * Retirer un membre d'un groupe.
 */
function handleUnassign(
    MemberGroupRepository $groupRepo,
    MemberRepository $memberRepo,
    string $tenantId
): void {
    $memberId = trim((string) ($_GET['member_id'] ?? ''));
    $groupId = trim((string) ($_GET['group_id'] ?? ''));

    if (!api_is_uuid($memberId)) {
        throw new InvalidArgumentException('member_id invalide');
    }

    if (!api_is_uuid($groupId)) {
        throw new InvalidArgumentException('group_id invalide');
    }

    // Verifier que le membre existe et appartient au tenant
    $member = $memberRepo->findByIdForTenant($memberId, $tenantId);
    if (!$member) {
        api_fail('member_not_found', 404);
    }

    // Verifier que le groupe existe et appartient au tenant
    $group = $groupRepo->findById($groupId, $tenantId);
    if (!$group) {
        api_fail('group_not_found', 404);
    }

    // Retirer
    $removed = $groupRepo->unassignMember($memberId, $groupId);

    if ($removed && function_exists('audit_log')) {
        audit_log('member_removed_from_group', 'member', $memberId, [
            'group_id' => $groupId,
            'group_name' => $group['name'],
            'member_name' => $member['full_name'],
        ]);
    }

    api_ok([
        'removed' => $removed,
        'member_id' => $memberId,
        'group_id' => $groupId,
    ]);
}

/**
 * Definir tous les groupes d'un membre (remplace les existants).
 */
function handleSetMemberGroups(
    MemberGroupRepository $groupRepo,
    MemberRepository $memberRepo,
    string $tenantId,
    ?string $userId
): void {
    $input = api_request('PUT');

    $memberId = trim((string) ($input['member_id'] ?? ''));
    $groupIds = $input['group_ids'] ?? [];

    if (!api_is_uuid($memberId)) {
        throw new InvalidArgumentException('member_id invalide');
    }

    if (!is_array($groupIds)) {
        throw new InvalidArgumentException('group_ids doit etre un tableau');
    }

    // Verifier que le membre existe et appartient au tenant
    $member = $memberRepo->findByIdForTenant($memberId, $tenantId);
    if (!$member) {
        api_fail('member_not_found', 404);
    }

    // Valider et filtrer les group_ids
    $validGroupIds = [];
    foreach ($groupIds as $groupId) {
        $groupId = trim((string) $groupId);
        if (!api_is_uuid($groupId)) {
            throw new InvalidArgumentException("group_id invalide: {$groupId}");
        }

        // Verifier que le groupe existe et appartient au tenant
        $group = $groupRepo->findById($groupId, $tenantId);
        if (!$group) {
            throw new InvalidArgumentException("Groupe introuvable: {$groupId}");
        }

        $validGroupIds[] = $groupId;
    }

    // Definir les groupes
    $groupRepo->setMemberGroups($memberId, $validGroupIds, $userId);

    if (function_exists('audit_log')) {
        audit_log('member_groups_updated', 'member', $memberId, [
            'group_ids' => $validGroupIds,
            'group_count' => count($validGroupIds),
            'member_name' => $member['full_name'],
        ]);
    }

    // Retourner la liste des groupes actuels
    $groups = $groupRepo->listGroupsForMember($memberId, $tenantId);

    api_ok([
        'member_id' => $memberId,
        'groups' => $groups,
        'total' => count($groups),
    ]);
}

/**
 * Assigner plusieurs membres a un groupe.
 */
function handleBulkAssign(
    MemberGroupRepository $groupRepo,
    MemberRepository $memberRepo,
    string $tenantId,
    ?string $userId
): void {
    $input = api_request('POST');

    $groupId = trim((string) ($input['group_id'] ?? ''));
    $memberIds = $input['member_ids'] ?? [];

    if (!api_is_uuid($groupId)) {
        throw new InvalidArgumentException('group_id invalide');
    }

    if (!is_array($memberIds) || empty($memberIds)) {
        throw new InvalidArgumentException('member_ids doit etre un tableau non vide');
    }

    // Verifier que le groupe existe et appartient au tenant
    $group = $groupRepo->findById($groupId, $tenantId);
    if (!$group) {
        api_fail('group_not_found', 404);
    }

    // Valider et filtrer les member_ids
    $validMemberIds = [];
    foreach ($memberIds as $memberId) {
        $memberId = trim((string) $memberId);
        if (!api_is_uuid($memberId)) {
            continue; // Ignorer les IDs invalides
        }

        $member = $memberRepo->findByIdForTenant($memberId, $tenantId);
        if ($member) {
            $validMemberIds[] = $memberId;
        }
    }

    if (empty($validMemberIds)) {
        throw new InvalidArgumentException('Aucun membre valide trouve');
    }

    // Assigner en masse
    $count = $groupRepo->bulkAssignToGroup($groupId, $validMemberIds, $userId);

    if (function_exists('audit_log')) {
        audit_log('members_bulk_assigned_to_group', 'member_group', $groupId, [
            'group_name' => $group['name'],
            'member_count' => $count,
        ]);
    }

    api_ok([
        'group_id' => $groupId,
        'assigned_count' => $count,
        'member_ids' => $validMemberIds,
    ]);
}
