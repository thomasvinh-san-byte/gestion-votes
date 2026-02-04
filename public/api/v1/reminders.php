<?php
declare(strict_types=1);

/**
 * API pour la gestion des rappels programmes.
 *
 * GET    /reminders.php?meeting_id=X          - Liste les rappels pour une seance
 * POST   /reminders.php                       - Creer/modifier un rappel
 * DELETE /reminders.php?id=X                  - Supprimer un rappel
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\ReminderScheduleRepository;
use AgVote\Repository\EmailTemplateRepository;

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$repo = new ReminderScheduleRepository();
$tenantId = api_current_tenant_id();

switch ($method) {
    case 'GET':
        api_require_role(['operator', 'admin']);
        handleGet($repo, $tenantId);
        break;

    case 'POST':
        api_require_role(['operator', 'admin']);
        handlePost($repo, $tenantId);
        break;

    case 'DELETE':
        api_require_role(['operator', 'admin']);
        handleDelete($repo, $tenantId);
        break;

    default:
        api_fail('method_not_allowed', 405);
}

function handleGet(ReminderScheduleRepository $repo, string $tenantId): void
{
    $meetingId = trim((string)($_GET['meeting_id'] ?? ''));

    if ($meetingId === '' || !api_is_uuid($meetingId)) {
        api_fail('missing_meeting_id', 400);
    }

    api_guard_meeting_exists($meetingId);

    $reminders = $repo->listForMeeting($meetingId, $tenantId);

    // Recuperer les templates disponibles pour le dropdown
    $templateRepo = new EmailTemplateRepository();
    $templates = $templateRepo->listForTenant($tenantId, 'reminder');

    api_ok([
        'meeting_id' => $meetingId,
        'reminders' => $reminders,
        'available_templates' => $templates,
    ]);
}

function handlePost(ReminderScheduleRepository $repo, string $tenantId): void
{
    $input = api_request('POST');

    $meetingId = trim((string)($input['meeting_id'] ?? ''));
    $daysBefore = isset($input['days_before']) ? (int)$input['days_before'] : null;
    $templateId = isset($input['template_id']) ? trim((string)$input['template_id']) : null;
    $sendTime = isset($input['send_time']) ? trim((string)$input['send_time']) : '09:00';
    $isActive = !isset($input['is_active']) || (bool)$input['is_active'];

    // Action speciale: configurer les rappels par defaut
    if (isset($input['action']) && $input['action'] === 'setup_defaults') {
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }
        api_guard_meeting_not_validated($meetingId);

        $repo->setupDefaults($tenantId, $meetingId, $templateId);
        $reminders = $repo->listForMeeting($meetingId, $tenantId);

        api_ok([
            'meeting_id' => $meetingId,
            'reminders' => $reminders,
            'message' => 'Rappels par defaut configures (J-7, J-3, J-1)',
        ]);
    }

    // Validation
    if ($meetingId === '' || !api_is_uuid($meetingId)) {
        api_fail('missing_meeting_id', 400);
    }
    if ($daysBefore === null || $daysBefore < 0 || $daysBefore > 30) {
        api_fail('invalid_days_before', 400, ['detail' => 'days_before doit etre entre 0 et 30']);
    }

    api_guard_meeting_not_validated($meetingId);

    // Valider template_id si fourni
    if ($templateId !== null && $templateId !== '') {
        if (!api_is_uuid($templateId)) {
            api_fail('invalid_template_id', 400);
        }
        $templateRepo = new EmailTemplateRepository();
        $template = $templateRepo->findById($templateId, $tenantId);
        if (!$template) {
            api_fail('template_not_found', 404);
        }
    }

    // Valider format send_time
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $sendTime)) {
        api_fail('invalid_send_time', 400, ['detail' => 'Format attendu: HH:MM ou HH:MM:SS']);
    }

    $reminder = $repo->upsert(
        $tenantId,
        $meetingId,
        $daysBefore,
        $templateId,
        $sendTime,
        $isActive
    );

    api_ok(['reminder' => $reminder], 201);
}

function handleDelete(ReminderScheduleRepository $repo, string $tenantId): void
{
    $id = trim((string)($_GET['id'] ?? ''));

    if (!api_is_uuid($id)) {
        api_fail('invalid_reminder_id', 400);
    }

    $deleted = $repo->delete($id, $tenantId);
    if (!$deleted) {
        api_fail('reminder_not_found', 404);
    }

    api_ok(['deleted' => true]);
}
