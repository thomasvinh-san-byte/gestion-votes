<?php
declare(strict_types=1);

/**
 * API CRUD pour les templates email.
 *
 * GET    /email_templates.php              - Liste tous les templates
 * GET    /email_templates.php?id=X         - Detail d'un template
 * GET    /email_templates.php?type=X       - Liste par type (invitation, reminder, etc.)
 * POST   /email_templates.php              - Creer un template
 * PUT    /email_templates.php?id=X         - Modifier un template
 * DELETE /email_templates.php?id=X         - Supprimer un template
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\EmailTemplateRepository;
use AgVote\Service\EmailTemplateService;

$method = api_method();
$repo = new EmailTemplateRepository();
$service = new EmailTemplateService($config ?? []);
$tenantId = api_current_tenant_id();

switch ($method) {
    case 'GET':
        api_require_role(['operator', 'admin']);
        handleGet($repo, $service, $tenantId);
        break;

    case 'POST':
        api_require_role(['operator', 'admin']);
        handlePost($repo, $service, $tenantId);
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

function handleGet(EmailTemplateRepository $repo, EmailTemplateService $service, string $tenantId): void
{
    $id = trim((string)($_GET['id'] ?? ''));
    $type = trim((string)($_GET['type'] ?? ''));
    $includeVariables = isset($_GET['include_variables']);

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
        if ($includeVariables) {
            $result['available_variables'] = $service->listAvailableVariables();
        }

        api_ok($result);
    }

    // Liste des templates
    $templates = $repo->listForTenant($tenantId, $type !== '' ? $type : null);

    $result = ['templates' => $templates];
    if ($includeVariables) {
        $result['available_variables'] = $service->listAvailableVariables();
    }

    api_ok($result);
}

function handlePost(EmailTemplateRepository $repo, EmailTemplateService $service, string $tenantId): void
{
    $input = api_request('POST');

    $name = trim((string)($input['name'] ?? ''));
    $type = trim((string)($input['template_type'] ?? 'invitation'));
    $subject = trim((string)($input['subject'] ?? ''));
    $bodyHtml = trim((string)($input['body_html'] ?? ''));
    $bodyText = isset($input['body_text']) ? trim((string)$input['body_text']) : null;
    $isDefault = (bool)($input['is_default'] ?? false);

    // Action speciale: creer les templates par defaut
    if (isset($input['action']) && $input['action'] === 'create_defaults') {
        $userId = api_current_user_id();
        $created = $service->createDefaultTemplates($tenantId, $userId);
        api_ok(['created' => $created, 'count' => count($created)]);
    }

    // Action speciale: dupliquer
    if (isset($input['action']) && $input['action'] === 'duplicate') {
        $sourceId = trim((string)($input['source_id'] ?? ''));
        $newName = trim((string)($input['new_name'] ?? ''));

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

        api_ok(['template' => $duplicate]);
    }

    // Validation
    if ($name === '') {
        api_fail('missing_name', 400);
    }
    if ($subject === '') {
        api_fail('missing_subject', 400);
    }
    if ($bodyHtml === '') {
        api_fail('missing_body_html', 400);
    }

    // Valider le type
    $allowedTypes = ['invitation', 'reminder', 'confirmation', 'custom'];
    if (!in_array($type, $allowedTypes, true)) {
        api_fail('invalid_template_type', 400, ['allowed' => $allowedTypes]);
    }

    // Verifier unicite du nom
    if ($repo->nameExists($tenantId, $name)) {
        api_fail('template_name_exists', 400);
    }

    // Valider les variables utilisees
    $validation = $service->validate($bodyHtml);
    if (!empty($validation['unknown_variables'])) {
        api_fail('unknown_variables', 400, ['unknown' => $validation['unknown_variables']]);
    }

    $userId = api_current_user_id();
    $template = $repo->create(
        $tenantId,
        $name,
        $type,
        $subject,
        $bodyHtml,
        $bodyText,
        $isDefault,
        $userId
    );

    if (!$template) {
        api_fail('create_failed', 500);
    }

    api_ok(['template' => $template], 201);
}

function handlePut(EmailTemplateRepository $repo, string $tenantId): void
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

    $name = isset($input['name']) ? trim((string)$input['name']) : $existing['name'];
    $subject = isset($input['subject']) ? trim((string)$input['subject']) : $existing['subject'];
    $bodyHtml = isset($input['body_html']) ? trim((string)$input['body_html']) : $existing['body_html'];
    $bodyText = isset($input['body_text']) ? trim((string)$input['body_text']) : $existing['body_text'];
    $isDefault = isset($input['is_default']) ? (bool)$input['is_default'] : null;

    // Verifier unicite du nom si change
    if ($name !== $existing['name'] && $repo->nameExists($tenantId, $name, $id)) {
        api_fail('template_name_exists', 400);
    }

    // Valider les variables
    $service = new EmailTemplateService([]);
    $validation = $service->validate($bodyHtml);
    if (!empty($validation['unknown_variables'])) {
        api_fail('unknown_variables', 400, ['unknown' => $validation['unknown_variables']]);
    }

    $template = $repo->update($id, $tenantId, $name, $subject, $bodyHtml, $bodyText, $isDefault);
    if (!$template) {
        api_fail('update_failed', 500);
    }

    api_ok(['template' => $template]);
}

function handleDelete(EmailTemplateRepository $repo, string $tenantId): void
{
    $id = trim((string)($_GET['id'] ?? ''));
    if (!api_is_uuid($id)) {
        api_fail('invalid_template_id', 400);
    }

    $existing = $repo->findById($id, $tenantId);
    if (!$existing) {
        api_fail('template_not_found', 404);
    }

    if ($existing['is_default']) {
        api_fail('cannot_delete_default', 400, [
            'detail' => 'Impossible de supprimer le template par defaut. Definissez d\'abord un autre template comme defaut.'
        ]);
    }

    $deleted = $repo->delete($id, $tenantId);
    if (!$deleted) {
        api_fail('delete_failed', 500);
    }

    api_ok(['deleted' => true]);
}
