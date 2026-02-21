<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\ExportTemplateRepository;

/**
 * Consolidates export_templates.php CRUD operations.
 */
final class ExportTemplatesController extends AbstractController
{
    public function list(): void
    {
        $repo = new ExportTemplateRepository();
        $tenantId = api_current_tenant_id();

        $id = api_query('id');
        $type = api_query('type');
        $includeColumns = api_query('include_columns') !== '' || api_query('available_columns') !== '';

        if (api_query('available_columns') !== '') {
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

        $templates = $repo->listForTenant($tenantId, $type !== '' ? $type : null);
        $result = ['templates' => $templates];
        if ($includeColumns && $type !== '') {
            $result['available_columns'] = $repo->getAvailableColumns($type);
        }
        api_ok($result);
    }

    public function create(): void
    {
        $input = api_request('POST');
        $repo = new ExportTemplateRepository();
        $tenantId = api_current_tenant_id();

        // Special action: duplicate
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

        $name = trim((string)($input['name'] ?? ''));
        $type = trim((string)($input['export_type'] ?? ''));
        $columns = $input['columns'] ?? null;
        $isDefault = (bool)($input['is_default'] ?? false);

        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            api_fail('invalid_name', 422, ['detail' => 'Le nom doit contenir entre 2 et 100 caractères.']);
        }
        if (!in_array($type, ExportTemplateRepository::TYPES, true)) {
            api_fail('invalid_export_type', 422, ['detail' => 'Type invalide.', 'valid_types' => ExportTemplateRepository::TYPES]);
        }

        if ($columns === null || !is_array($columns) || empty($columns)) {
            $columns = $repo->getDefaultColumns($type);
        }

        if ($repo->nameExists($tenantId, $name, $type)) {
            api_fail('name_already_exists', 409, ['detail' => 'Un template avec ce nom existe déjà pour ce type.']);
        }

        $userId = api_current_user_id();
        $template = $repo->create($tenantId, $name, $type, $columns, $isDefault, $userId);
        if (!$template) {
            api_fail('creation_failed', 500);
        }

        audit_log('export_template_create', 'export_template', $template['id'], ['name' => $name, 'export_type' => $type, 'is_default' => $isDefault]);
        api_ok(['template' => $template], 201);
    }

    public function update(): void
    {
        $repo = new ExportTemplateRepository();
        $tenantId = api_current_tenant_id();
        $id = api_query('id');
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

        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            api_fail('invalid_name', 422, ['detail' => 'Le nom doit contenir entre 2 et 100 caractères.']);
        }
        if ($repo->nameExists($tenantId, $name, $existing['export_type'], $id)) {
            api_fail('name_already_exists', 409, ['detail' => 'Un template avec ce nom existe déjà pour ce type.']);
        }

        $template = $repo->update($id, $tenantId, $name, $columns, $isDefault);
        if (!$template) {
            api_fail('update_failed', 500);
        }

        audit_log('export_template_update', 'export_template', $id, ['name' => $name, 'is_default' => $isDefault]);
        api_ok(['template' => $template]);
    }

    public function delete(): void
    {
        $repo = new ExportTemplateRepository();
        $tenantId = api_current_tenant_id();
        $id = api_query('id');
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

        audit_log('export_template_delete', 'export_template', $id, ['name' => $existing['name'], 'export_type' => $existing['export_type']]);
        api_ok(['deleted' => true]);
    }
}
