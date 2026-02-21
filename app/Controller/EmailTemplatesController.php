<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\EmailTemplateRepository;
use AgVote\Service\EmailTemplateService;

/**
 * Consolidates email_templates.php CRUD operations.
 */
final class EmailTemplatesController extends AbstractController {
    public function list(): void {
        $repo = new EmailTemplateRepository();
        global $config;
        $service = new EmailTemplateService($config ?? []);
        $tenantId = api_current_tenant_id();

        $id = api_query('id');
        $type = api_query('type');
        $includeVariables = api_query('include_variables') !== '';

        if ($id !== '') {
            if (!api_is_uuid($id)) {
                api_fail('invalid_template_id', 400);
            }
            $template = $repo->findById($id, $tenantId);
            if (!$template) {
                api_fail('template_not_found', 404);
            }
            $result = ['template' => $template];
            if ($includeVariables) {
                $result['available_variables'] = $service->listAvailableVariables();
            }
            api_ok($result);
        }

        $templates = $repo->listForTenant($tenantId, $type !== '' ? $type : null);
        $result = ['items' => $templates];
        if ($includeVariables) {
            $result['available_variables'] = $service->listAvailableVariables();
        }
        api_ok($result);
    }

    public function create(): void {
        $input = api_request('POST');
        $repo = new EmailTemplateRepository();
        global $config;
        $service = new EmailTemplateService($config ?? []);
        $tenantId = api_current_tenant_id();

        // Special action: create defaults
        if (isset($input['action']) && $input['action'] === 'create_defaults') {
            $userId = api_current_user_id();
            $created = $service->createDefaultTemplates($tenantId, $userId);
            api_ok(['created' => $created, 'count' => count($created)]);
        }

        // Special action: duplicate
        if (isset($input['action']) && $input['action'] === 'duplicate') {
            $sourceId = trim((string) ($input['source_id'] ?? ''));
            $newName = trim((string) ($input['new_name'] ?? ''));
            if (!api_is_uuid($sourceId)) {
                api_fail('invalid_source_id', 400);
            }
            if ($newName === '') {
                api_fail('missing_new_name', 400);
            }
            $duplicate = $repo->duplicate($sourceId, $tenantId, $newName);
            if (!$duplicate) {
                api_fail('duplicate_failed', 400);
            }
            audit_log('email_template.duplicate', 'email_template', $duplicate['id'] ?? $sourceId, [
                'source_id' => $sourceId,
                'new_name' => $newName,
            ]);
            api_ok(['template' => $duplicate]);
        }

        $name = trim((string) ($input['name'] ?? ''));
        $type = trim((string) ($input['template_type'] ?? 'invitation'));
        $subject = trim((string) ($input['subject'] ?? ''));
        $bodyHtml = trim((string) ($input['body_html'] ?? ''));
        $bodyText = isset($input['body_text']) ? trim((string) $input['body_text']) : null;
        $isDefault = (bool) ($input['is_default'] ?? false);

        if ($name === '') {
            api_fail('missing_name', 400);
        }
        if ($subject === '') {
            api_fail('missing_subject', 400);
        }
        if ($bodyHtml === '') {
            api_fail('missing_body_html', 400);
        }

        $allowedTypes = ['invitation', 'reminder', 'confirmation', 'custom'];
        if (!in_array($type, $allowedTypes, true)) {
            api_fail('invalid_template_type', 400, ['allowed' => $allowedTypes]);
        }

        if ($repo->nameExists($tenantId, $name)) {
            api_fail('template_name_exists', 400);
        }

        $validation = $service->validate($bodyHtml);
        if (!empty($validation['unknown_variables'])) {
            api_fail('unknown_variables', 400, ['unknown' => $validation['unknown_variables']]);
        }

        $userId = api_current_user_id();
        $template = $repo->create($tenantId, $name, $type, $subject, $bodyHtml, $bodyText, $isDefault, $userId);
        if (!$template) {
            api_fail('create_failed', 500);
        }

        audit_log('email_template.create', 'email_template', $template['id'] ?? '', ['name' => $name, 'type' => $type]);
        api_ok(['template' => $template], 201);
    }

    public function update(): void {
        $repo = new EmailTemplateRepository();
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

        $name = isset($input['name']) ? trim((string) $input['name']) : $existing['name'];
        $subject = isset($input['subject']) ? trim((string) $input['subject']) : $existing['subject'];
        $bodyHtml = isset($input['body_html']) ? trim((string) $input['body_html']) : $existing['body_html'];
        $bodyText = isset($input['body_text']) ? trim((string) $input['body_text']) : $existing['body_text'];
        $isDefault = isset($input['is_default']) ? (bool) $input['is_default'] : null;

        if ($name !== $existing['name'] && $repo->nameExists($tenantId, $name, $id)) {
            api_fail('template_name_exists', 400);
        }

        $service = new EmailTemplateService([]);
        $validation = $service->validate($bodyHtml);
        if (!empty($validation['unknown_variables'])) {
            api_fail('unknown_variables', 400, ['unknown' => $validation['unknown_variables']]);
        }

        $template = $repo->update($id, $tenantId, $name, $subject, $bodyHtml, $bodyText, $isDefault);
        if (!$template) {
            api_fail('update_failed', 500);
        }

        audit_log('email_template.update', 'email_template', $id, ['name' => $name]);
        api_ok(['template' => $template]);
    }

    public function delete(): void {
        $repo = new EmailTemplateRepository();
        $tenantId = api_current_tenant_id();
        $id = api_query('id');
        if (!api_is_uuid($id)) {
            api_fail('invalid_template_id', 400);
        }

        $existing = $repo->findById($id, $tenantId);
        if (!$existing) {
            api_fail('template_not_found', 404);
        }

        if ($existing['is_default']) {
            api_fail('cannot_delete_default', 400, [
                'detail' => 'Impossible de supprimer le template par defaut. Definissez d\'abord un autre template comme defaut.',
            ]);
        }

        $deleted = $repo->delete($id, $tenantId);
        if (!$deleted) {
            api_fail('delete_failed', 500);
        }

        audit_log('email_template.delete', 'email_template', $id, ['name' => $existing['name'] ?? '']);
        api_ok(['deleted' => true]);
    }
}
