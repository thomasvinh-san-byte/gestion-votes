<?php
declare(strict_types=1);

/**
 * Previsualisation de templates email.
 *
 * POST /email_templates_preview.php
 *   body_html: contenu HTML a previsualiser
 *   subject: sujet a previsualiser (optionnel)
 *   custom_variables: variables personnalisees (optionnel)
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\EmailTemplateService;

api_require_role(['operator', 'admin']);
$input = api_request('POST');

$bodyHtml = trim((string)($input['body_html'] ?? ''));
$subject = trim((string)($input['subject'] ?? ''));
$customVariables = isset($input['custom_variables']) && is_array($input['custom_variables'])
    ? $input['custom_variables']
    : null;

if ($bodyHtml === '') {
    api_fail('missing_body_html', 400);
}

$service = new EmailTemplateService($config ?? []);

// Valider les variables
$validation = $service->validate($bodyHtml);

// Previsualiser
$previewHtml = $service->preview($bodyHtml, $customVariables);
$previewSubject = $subject !== '' ? $service->preview($subject, $customVariables) : '';

api_ok([
    'preview_html' => $previewHtml,
    'preview_subject' => $previewSubject,
    'validation' => $validation,
    'available_variables' => $service->listAvailableVariables(),
]);
