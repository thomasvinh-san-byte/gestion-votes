<?php
declare(strict_types=1);

/**
 * Programmer l'envoi d'invitations.
 *
 * POST /invitations_schedule.php
 *   meeting_id: ID de la seance (requis)
 *   template_id: ID du template a utiliser (optionnel, utilise le defaut sinon)
 *   scheduled_at: Date/heure d'envoi (optionnel, envoi immediat sinon)
 *   only_unsent: N'envoyer qu'aux membres non encore invites (defaut: true)
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\EmailQueueService;

api_require_role('operator');

try {

$input = api_request('POST');

$meetingId = trim((string)($input['meeting_id'] ?? ''));
$templateId = isset($input['template_id']) ? trim((string)$input['template_id']) : null;
$scheduledAt = isset($input['scheduled_at']) ? trim((string)$input['scheduled_at']) : null;
$onlyUnsent = !isset($input['only_unsent']) || (bool)$input['only_unsent'];

if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400);
}

// Verifier que la seance existe et n'est pas validee
api_guard_meeting_not_validated($meetingId);

// Valider le template_id si fourni
if ($templateId !== null && $templateId !== '' && !api_is_uuid($templateId)) {
    api_fail('invalid_template_id', 400);
}

// Valider scheduled_at si fourni
if ($scheduledAt !== null && $scheduledAt !== '') {
    $dt = \DateTime::createFromFormat(\DateTime::ATOM, $scheduledAt);
    if (!$dt) {
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $scheduledAt);
    }
    if (!$dt) {
        $dt = \DateTime::createFromFormat('Y-m-d H:i', $scheduledAt);
    }
    if (!$dt) {
        api_fail('invalid_scheduled_at', 400, ['detail' => 'Format attendu: ISO 8601 ou Y-m-d H:i:s']);
    }
    $scheduledAt = $dt->format('c');
}

global $config;
$service = new EmailQueueService($config ?? []);
$tenantId = api_current_tenant_id();

$result = $service->scheduleInvitations(
    $tenantId,
    $meetingId,
    $templateId,
    $scheduledAt,
    $onlyUnsent
);

api_ok([
    'meeting_id' => $meetingId,
    'scheduled' => $result['scheduled'],
    'skipped' => $result['skipped'],
    'scheduled_at' => $scheduledAt ?? 'immediate',
    'errors' => $result['errors'],
]);

} catch (Throwable $e) {
    error_log('Error in invitations_schedule.php: ' . $e->getMessage());
    api_fail('server_error', 500);
}
