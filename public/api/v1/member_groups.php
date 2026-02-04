<?php
/**
 * member_groups.php - API CRUD pour les groupes de membres
 *
 * GET /api/v1/member_groups.php - Liste tous les groupes du tenant
 *   Query: ?include_inactive=1 pour inclure les groupes inactifs
 *
 * GET /api/v1/member_groups.php?id=UUID - Detail d'un groupe avec membres
 *
 * POST /api/v1/member_groups.php - Creer un groupe
 *   Body: { "name": "string", "description?": "string", "color?": "#RRGGBB", "sort_order?": int }
 *
 * PATCH /api/v1/member_groups.php - Modifier un groupe
 *   Body: { "id": "UUID", "name": "string", "description?": "string", "color?": "#RRGGBB", "sort_order?": int, "is_active?": bool }
 *
 * DELETE /api/v1/member_groups.php?id=UUID - Supprimer un groupe
 */
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MemberGroupRepository;

$method = $_SERVER['REQUEST_METHOD'];
$tenantId = api_current_tenant_id();
$repo = new MemberGroupRepository();

try {
    switch ($method) {
        case 'GET':
            handleGet($repo, $tenantId);
            break;

        case 'POST':
            api_require_any_role(['operator', 'admin']);
            handlePost($repo, $tenantId);
            break;

        case 'PATCH':
            api_require_any_role(['operator', 'admin']);
            handlePatch($repo, $tenantId);
            break;

        case 'DELETE':
            api_require_any_role(['operator', 'admin']);
            handleDelete($repo, $tenantId);
            break;

        default:
            api_fail('method_not_allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in member_groups.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => $e->getMessage()]);
}

function handleGet(MemberGroupRepository $repo, string $tenantId): void
{
    $groupId = trim((string) ($_GET['id'] ?? ''));
    $includeInactive = ($_GET['include_inactive'] ?? '') === '1';

    if ($groupId !== '') {
        // Detail d'un groupe avec ses membres
        if (!api_is_uuid($groupId)) {
            api_fail('invalid_group_id', 400);
        }

        $group = $repo->findById($groupId, $tenantId);
        if (!$group) {
            api_fail('group_not_found', 404);
        }

        // Charger les membres du groupe
        $members = $repo->listMembersInGroup($groupId, $tenantId);
        $group['members'] = $members;

        api_ok(['group' => $group]);
    } else {
        // Liste tous les groupes
        $groups = $repo->listForTenant($tenantId, !$includeInactive);

        api_ok([
            'groups' => $groups,
            'total' => count($groups),
        ]);
    }
}

function handlePost(MemberGroupRepository $repo, string $tenantId): void
{
    $input = api_request('POST');

    $name = trim((string) ($input['name'] ?? ''));
    $description = trim((string) ($input['description'] ?? '')) ?: null;
    $color = trim((string) ($input['color'] ?? '')) ?: null;
    $sortOrder = isset($input['sort_order']) ? (int) $input['sort_order'] : null;

    // Validation
    if ($name === '') {
        throw new InvalidArgumentException('Le nom du groupe est requis');
    }

    if (mb_strlen($name) > 100) {
        throw new InvalidArgumentException('Le nom ne peut pas depasser 100 caracteres');
    }

    if ($color !== null && !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        throw new InvalidArgumentException('Format de couleur invalide (attendu: #RRGGBB)');
    }

    // Verifier l'unicite du nom
    if ($repo->nameExists($name, $tenantId)) {
        throw new InvalidArgumentException('Un groupe avec ce nom existe deja');
    }

    // Creer le groupe
    $group = $repo->create($tenantId, $name, $description, $color, $sortOrder);

    if (function_exists('audit_log')) {
        audit_log('member_group_created', 'member_group', $group['id'], [
            'name' => $name,
        ]);
    }

    api_ok(['group' => $group], 201);
}

function handlePatch(MemberGroupRepository $repo, string $tenantId): void
{
    $input = api_request('PATCH');

    $groupId = trim((string) ($input['id'] ?? ''));
    $name = trim((string) ($input['name'] ?? ''));
    $description = isset($input['description']) ? trim((string) $input['description']) : null;
    $color = isset($input['color']) ? trim((string) $input['color']) : null;
    $sortOrder = isset($input['sort_order']) ? (int) $input['sort_order'] : null;
    $isActive = isset($input['is_active']) ? (bool) $input['is_active'] : null;

    // Validation
    if (!api_is_uuid($groupId)) {
        throw new InvalidArgumentException('ID de groupe invalide');
    }

    if ($name === '') {
        throw new InvalidArgumentException('Le nom du groupe est requis');
    }

    if (mb_strlen($name) > 100) {
        throw new InvalidArgumentException('Le nom ne peut pas depasser 100 caracteres');
    }

    if ($color !== null && $color !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        throw new InvalidArgumentException('Format de couleur invalide (attendu: #RRGGBB)');
    }

    // Verifier que le groupe existe
    $existing = $repo->findById($groupId, $tenantId);
    if (!$existing) {
        api_fail('group_not_found', 404);
    }

    // Verifier l'unicite du nom (excluant le groupe courant)
    if ($repo->nameExists($name, $tenantId, $groupId)) {
        throw new InvalidArgumentException('Un groupe avec ce nom existe deja');
    }

    // Mettre a jour
    $group = $repo->update(
        $groupId,
        $tenantId,
        $name,
        $description,
        $color ?: null,
        $sortOrder,
        $isActive
    );

    if (function_exists('audit_log')) {
        audit_log('member_group_updated', 'member_group', $groupId, [
            'name' => $name,
            'is_active' => $isActive,
        ]);
    }

    api_ok(['group' => $group]);
}

function handleDelete(MemberGroupRepository $repo, string $tenantId): void
{
    $groupId = trim((string) ($_GET['id'] ?? ''));

    if (!api_is_uuid($groupId)) {
        throw new InvalidArgumentException('ID de groupe invalide');
    }

    // Verifier que le groupe existe
    $existing = $repo->findById($groupId, $tenantId);
    if (!$existing) {
        api_fail('group_not_found', 404);
    }

    // Supprimer le groupe (les assignations sont supprimees par cascade)
    $deleted = $repo->delete($groupId, $tenantId);

    if ($deleted && function_exists('audit_log')) {
        audit_log('member_group_deleted', 'member_group', $groupId, [
            'name' => $existing['name'],
            'had_members' => (int) $existing['member_count'],
        ]);
    }

    api_ok(['deleted' => $deleted]);
}
