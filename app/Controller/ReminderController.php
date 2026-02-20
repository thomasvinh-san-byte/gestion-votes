<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\ReminderScheduleRepository;
use AgVote\Repository\EmailTemplateRepository;

/**
 * Consolidates reminders.php.
 */
final class ReminderController extends AbstractController
{
    public function listForMeeting(): void
    {
        api_require_role(['operator', 'admin']);

        $meetingId = trim((string)($_GET['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        api_guard_meeting_exists($meetingId);

        $tenantId = api_current_tenant_id();
        $repo = new ReminderScheduleRepository();
        $reminders = $repo->listForMeeting($meetingId, $tenantId);

        $templateRepo = new EmailTemplateRepository();
        $templates = $templateRepo->listForTenant($tenantId, 'reminder');

        api_ok([
            'meeting_id' => $meetingId,
            'reminders' => $reminders,
            'available_templates' => $templates,
        ]);
    }

    public function upsert(): void
    {
        api_require_role(['operator', 'admin']);

        $input = api_request('POST');
        $tenantId = api_current_tenant_id();
        $repo = new ReminderScheduleRepository();

        $meetingId = trim((string)($input['meeting_id'] ?? ''));
        $daysBefore = isset($input['days_before']) ? (int)$input['days_before'] : null;
        $templateId = isset($input['template_id']) ? trim((string)$input['template_id']) : null;
        $sendTime = isset($input['send_time']) ? trim((string)$input['send_time']) : '09:00';
        $isActive = !isset($input['is_active']) || (bool)$input['is_active'];

        // Special action: setup defaults
        if (isset($input['action']) && $input['action'] === 'setup_defaults') {
            if ($meetingId === '' || !api_is_uuid($meetingId)) {
                api_fail('missing_meeting_id', 400);
            }
            api_guard_meeting_not_validated($meetingId);

            $repo->setupDefaults($tenantId, $meetingId, $templateId);
            $reminders = $repo->listForMeeting($meetingId, $tenantId);

            audit_log('reminder.setup_defaults', 'meeting', $meetingId, [
                'count' => count($reminders),
            ], $meetingId);

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

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $sendTime)) {
            api_fail('invalid_send_time', 400, ['detail' => 'Format attendu: HH:MM ou HH:MM:SS']);
        }

        $reminder = $repo->upsert($tenantId, $meetingId, $daysBefore, $templateId, $sendTime, $isActive);

        audit_log('reminder.upsert', 'meeting', $meetingId, [
            'days_before' => $daysBefore,
            'is_active' => $isActive,
        ], $meetingId);

        api_ok(['reminder' => $reminder], 201);
    }

    public function delete(): void
    {
        api_require_role(['operator', 'admin']);

        $id = trim((string)($_GET['id'] ?? ''));
        if (!api_is_uuid($id)) {
            api_fail('invalid_reminder_id', 400);
        }

        $tenantId = api_current_tenant_id();
        $repo = new ReminderScheduleRepository();
        $deleted = $repo->delete($id, $tenantId);
        if (!$deleted) {
            api_fail('reminder_not_found', 404);
        }

        audit_log('reminder.delete', 'reminder', $id);

        api_ok(['deleted' => true]);
    }
}
