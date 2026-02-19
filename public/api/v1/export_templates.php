<?php
declare(strict_types=1);

/**
 * API CRUD pour les templates d'export personnalisables.
 *
 * GET    /export_templates.php                    - Liste tous les templates
 * GET    /export_templates.php?id=X               - Detail d'un template
 * GET    /export_templates.php?type=X             - Liste par type (attendance, votes, etc.)
 * GET    /export_templates.php?available_columns  - Liste colonnes disponibles par type
 * POST   /export_templates.php                    - Creer un template
 * PUT    /export_templates.php?id=X               - Modifier un template
 * DELETE /export_templates.php?id=X               - Supprimer un template
 *
 * Types supportes: attendance, votes, members, motions, audit, proxies
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\ExportTemplateRepository;

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$repo = new ExportTemplateRepository();
$tenantId = api_current_tenant_id();

try {
    switch ($method) {
        case 'GET':
            api_require_role(['operator', 'admin']);
            handleGet($repo, $tenantId);
            break;

        case 'POST':
            api_require_role(['operator', 'admin']);
            handlePost($repo, $tenantId);
            break;

        case 'PUT':
            api_require_role(['operator', 'admin']);
            handlePut($repo, $tenantId);
            break;

        case 'DELETE':
            api_require_role(['operator', 'admin']);
            handleDelete($repo, $tenantId);
            break;

        default:
            api_fail('method_not_allowed', 405);
    }
} catch (Throwable $e) {
    error_log('Error in export_templates.php: ' . $e->getMessage());
    api_fail('server_error', 500, ['detail' => $e->getMessage()]);
}

function handleGet(ExportTemplateRepository $repo, string $tenantId): void
{
    $id = trim((string)($_GET['id'] ?? ''));
    $type = trim((string)($_GET['type'] ?? ''));
    $includeColumns = isset($_GET['include_columns']) || isset($_GET['available_columns']);

    // Retourner uniquement les colonnes disponibles
    if (isset($_GET['available_columns'])) {
        $result = [
            'types' => ExportTemplateRepository::TYPES,
            'columns_by_type' => [],
        ];

        foreach (ExportTemplateRepository::TYPES as $exportType) {
            $result['columns_by_type'][$exportType] = $repo->getAvailableColumns($exportType);
        }

        api_ok($result);
        return;
    }

    if ($id !== '') {
        // Detail d'un template
        if (!api_is_uuid($id)) {
            api_fail('invalid_template_id', 400);
        }

        $template = $repo->findById($id, $tenantId);
        if (!$template) {
            api_fail('template_not_found', 404);
        }

        $result = ['template' => $template];
        if ($includeColumns) {
            $result['available_columns'] = $repo->getAvailableColumns($template['export_type']);
        }

        api_ok($result);
        return;
    }

    // Liste des templates
    $templates = $repo->listForTenant($tenantId, $type !== '' ? $type : null);

    $result = ['templates' => $templates];
    if ($includeColumns && $type !== '') {
        $result['available_columns'] = $repo->getAvailableColumns($type);
    }

    api_ok($result);
}

function handlePost(ExportTemplateRepository $repo, string $tenantId): void
{
    $input = api_request('POST');

    $name = trim((string)($input['name'] ?? ''));
    $type = trim((string)($input['export_type'] ?? ''));
    $columns = $input['columns'] ?? null;
    $isDefault = (bool)($input['is_default'] ?? false);

    // Action speciale: dupliquer un template
    if (isset($input['action']) && $input['action'] === 'duplicate') {
        $sourceId = trim((string)($input['source_id'] ?? ''));
        $newName = trim((string)($input['new_name'] ?? ''));

        if (!api_is_uuid($sourceId)) {
            api_fail('invalid_source_id', 400);
        }

        if ($newName === '' || mb_strlen($newName) < 2 || mb_strlen($newName) > 100) {
            api_fail('invalid_name', 422, ['detail' => 'Le nom doit contenir entre 2 et 100 caractères.']);
        }

        $duplicated = $repo->duplicate($sourceId, $tenantId, $newName);
        if (!$duplicated) {
            api_fail('duplicate_failed', 500);
        }

        audit_log('export_template_duplicate', 'export_template', $duplicated['id'], [
            'source_id' => $sourceId,
            'new_name' => $newName,
        ]);

        api_ok(['template' => $duplicated], 201);
        return;
    }

    // Validation
    if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        api_fail('invalid_name', 422, ['detail' => 'Le nom doit contenir entre 2 et 100 caractères.']);
    }

    if (!in_array($type, ExportTemplateRepository::TYPES, true)) {
        api_fail('invalid_export_type', 422, [
            'detail' => 'Type invalide.',
            'valid_types' => ExportTemplateRepository::TYPES,
        ]);
    }

    // Si pas de colonnes specifiees, utiliser les colonnes par defaut
    if ($columns === null || !is_array($columns) || empty($columns)) {
        $columns = $repo->getDefaultColumns($type);
    }

    // Verifier unicite du nom
    if ($repo->nameExists($tenantId, $name, $type)) {
        api_fail('name_already_exists', 409, ['detail' => 'Un template avec ce nom existe déjà pour ce type.']);
    }

    $userId = api_current_user_id();
    $template = $repo->create($tenantId, $name, $type, $columns, $isDefault, $userId);

    if (!$template) {
        api_fail('creation_failed', 500);
    }

    audit_log('export_template_create', 'export_template', $template['id'], [
        'name' => $name,
        'export_type' => $type,
        'is_default' => $isDefault,
    ]);

    api_ok(['template' => $template], 201);
}

function handlePut(ExportTemplateRepository $repo, string $tenantId): void
{
    $id = trim((string)($_GET['id'] ?? ''));

    if (!api_is_uuid($id)) {
        api_fail('invalid_template_id', 400);
    }

    $existing = $repo->findById($id, $tenantId);
    if (!$existing) {
        api_fail('template_not_found', 404);
    }

    $input = api_request('PUT');

    $name = trim((string)($input['name'] ?? $existing['name']));
    $columns = $input['columns'] ?? $existing['columns'];
    $isDefault = isset($input['is_default']) ? (bool)$input['is_default'] : null;

    // Validation
    if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        api_fail('invalid_name', 422, ['detail' => 'Le nom doit contenir entre 2 et 100 caractères.']);
    }

    // Verifier unicite du nom (sauf pour ce template)
    if ($repo->nameExists($tenantId, $name, $existing['export_type'], $id)) {
        api_fail('name_already_exists', 409, ['detail' => 'Un template avec ce nom existe déjà pour ce type.']);
    }

    $template = $repo->update($id, $tenantId, $name, $columns, $isDefault);

    if (!$template) {
        api_fail('update_failed', 500);
    }

    audit_log('export_template_update', 'export_template', $id, [
        'name' => $name,
        'is_default' => $isDefault,
    ]);

    api_ok(['template' => $template]);
}

function handleDelete(ExportTemplateRepository $repo, string $tenantId): void
{
    $id = trim((string)($_GET['id'] ?? ''));

    if (!api_is_uuid($id)) {
        api_fail('invalid_template_id', 400);
    }

    $existing = $repo->findById($id, $tenantId);
    if (!$existing) {
        api_fail('template_not_found', 404);
    }

    $deleted = $repo->delete($id, $tenantId);

    if (!$deleted) {
        api_fail('delete_failed', 500);
    }

    audit_log('export_template_delete', 'export_template', $id, [
        'name' => $existing['name'],
        'export_type' => $existing['export_type'],
    ]);

    api_ok(['deleted' => true]);
}
