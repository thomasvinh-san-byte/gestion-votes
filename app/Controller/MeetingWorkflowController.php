<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Security\AuthMiddleware;
use AgVote\Service\EmailQueueService;
use AgVote\Service\MeetingTransitionService;
use AgVote\Service\MeetingWorkflowService;
use AgVote\Service\OfficialResultsService;
use AgVote\SSE\EventBroadcaster;
use RuntimeException;
use Throwable;

/**
 * Consolidates meeting workflow endpoints.
 * Transition/launch/readyCheck/resetDemo delegated to MeetingTransitionService.
 */
final class MeetingWorkflowController extends AbstractController {
    public function transition(): void {
        $input = api_request('POST');
        $meetingId = api_require_uuid($input, 'meeting_id');
        $toStatus = trim((string) ($input['to_status'] ?? ''));
        $tenantId = api_current_tenant_id();
        $userId = api_current_user_id();
        $service = new MeetingTransitionService($this->repo());
        try {
            $preCheck = $service->transition($meetingId, $tenantId, $toStatus, $userId);
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $code = str_contains($msg, 'requis') ? 'missing_to_status' : 'invalid_status';
            $extra = ['detail' => $msg];
            if ($code === 'invalid_status') { $extra['valid'] = ['draft','scheduled','frozen','live','paused','closed','validated','archived']; }
            api_fail($code, 400, $extra);
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'meeting_not_found') { api_fail('meeting_not_found', 404); }
            if (str_contains($msg, 'archivée')) { api_fail('archived_immutable', 403, ['detail' => $msg]); }
            api_fail('business_error', 400, ['detail' => $msg]);
        }
        if (!empty($preCheck['already_in_target'])) {
            api_ok([
                'meeting_id' => $meetingId,
                'from_status' => $preCheck['from_status'],
                'to_status' => $toStatus,
                'transitioned_at' => date('c'),
                'already_in_target' => true,
                'warnings' => [],
                'results_emails' => 0,
            ]);
        }
        $fromStatus = $preCheck['from_status'];
        AuthMiddleware::requireTransition($fromStatus, $toStatus, $meetingId);
        $workflowCheck = (new MeetingWorkflowService())->issuesBeforeTransition($meetingId, $tenantId, $toStatus);
        $forceTransition = filter_var($input['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($forceTransition && api_current_role() !== 'admin') {
            api_fail('force_requires_admin', 403, ['detail' => 'Seul un administrateur peut forcer une transition.']);
        }
        if (!$workflowCheck['can_proceed'] && !$forceTransition) {
            api_fail('workflow_issues', 422, [
                'detail' => 'Transition bloquée par des pré-requis', 'issues' => $workflowCheck['issues'],
                'warnings' => $workflowCheck['warnings'], 'hint' => 'Corrigez les issues ou passez force=true pour ignorer (admin uniquement)',
            ]);
        }
        $txResult = api_transaction(function () use ($service, $meetingId, $tenantId, $toStatus, $userId, $forceTransition) {
            $repo = $this->repo()->meeting();
            $locked = $repo->lockForUpdate($meetingId, $tenantId);
            if (!$locked) { api_fail('meeting_not_found', 404); }
            $lockedFrom = $locked['status'];
            if ($lockedFrom === $toStatus) { return ['already_in_target' => true, 'from_status' => $lockedFrom]; }
            if ($lockedFrom === 'archived') { api_fail('archived_immutable', 403, ['detail' => 'Séance archivée : aucune transition autorisée.']); }
            $fields = $service->buildTransitionFields($toStatus, $lockedFrom, $locked, $userId, api_current_user()['name'] ?? null);
            $repo->updateFields($meetingId, $tenantId, $fields);
            $auditData = ['from_status' => $lockedFrom, 'to_status' => $toStatus, 'title' => $locked['title']];
            if ($forceTransition) { $auditData['forced'] = true; }
            audit_log('meeting.transition', 'meeting', $meetingId, $auditData, $meetingId);
            return $lockedFrom;
        });
        if (is_array($txResult) && !empty($txResult['already_in_target'])) {
            api_ok([
                'meeting_id' => $meetingId,
                'from_status' => $txResult['from_status'],
                'to_status' => $toStatus,
                'transitioned_at' => date('c'),
                'already_in_target' => true,
                'warnings' => [],
                'results_emails' => 0,
            ]);
        }
        $fromStatus = $txResult;
        try { EventBroadcaster::meetingStatusChanged($meetingId, $tenantId, $toStatus, $fromStatus); }
        catch (Throwable $e) { error_log('[SSE] Broadcast failed after meeting transition: ' . $e->getMessage()); }
        $resultsEmailCount = 0;
        if ($toStatus === 'closed') {
            try {
                global $config;
                $mergedConfig = \AgVote\Service\MailerService::buildMailerConfig($config ?? [], $this->repo()->settings(), $tenantId);
                $resultsEmailCount = (new EmailQueueService($mergedConfig))->scheduleResults($tenantId, $meetingId)['scheduled'] ?? 0;
            } catch (Throwable $e) { error_log('[Email] Results email scheduling failed: ' . $e->getMessage()); }
        }
        api_ok(['meeting_id' => $meetingId, 'from_status' => $fromStatus, 'to_status' => $toStatus,
            'transitioned_at' => date('c'), 'warnings' => $workflowCheck['warnings'] ?? [], 'results_emails' => $resultsEmailCount]);
    }

    public function launch(): void {
        $input = api_request('POST');
        $meetingId = api_require_uuid($input, 'meeting_id');
        $tenant = api_current_tenant_id();
        $userId = api_current_user_id();
        $service = new MeetingTransitionService($this->repo());
        try {
            $preCheck = $service->launch($meetingId, $tenant, $userId);
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'meeting_not_found') { api_fail('meeting_not_found', 404); }
            $decoded = json_decode($msg, true);
            if (is_array($decoded) && ($decoded['code'] ?? '') === 'workflow_issues') { api_fail('workflow_issues', 422, $decoded); }
            api_fail('invalid_launch_status', 422, ['detail' => $msg]);
        }
        if (!empty($preCheck['already_in_target'])) {
            api_ok([
                'meeting_id' => $meetingId,
                'from_status' => 'live',
                'to_status' => 'live',
                'already_in_target' => true,
            ]);
        }
        $path = $preCheck['path'];
        $fromStatus = $preCheck['from_status'];
        api_transaction(function () use ($service, $meetingId, $tenant, $userId, $path, $fromStatus) {
            $repo = $this->repo()->meeting();
            $locked = $repo->lockForUpdate($meetingId, $tenant);
            if (!$locked) { api_fail('meeting_not_found', 404); }
            $currentStatus = $locked['status'];
            foreach ($path as $toStatus) {
                AuthMiddleware::requireTransition($currentStatus, $toStatus, $meetingId);
                $fields = $service->buildTransitionFields($toStatus, $currentStatus, $locked, $userId);
                $repo->updateFields($meetingId, $tenant, $fields);
                $currentStatus = $toStatus;
            }
            audit_log('meeting.launch', 'meeting', $meetingId, [
                'from_status' => $fromStatus, 'to_status' => 'live', 'path' => $path, 'title' => $locked['title'],
            ], $meetingId);
        });
        try { EventBroadcaster::meetingStatusChanged($meetingId, $tenant, 'live', $fromStatus); }
        catch (Throwable $e) { error_log('[SSE] Broadcast failed after meeting launch: ' . $e->getMessage()); }
        api_ok(['meeting_id' => $meetingId, 'from_status' => $fromStatus, 'to_status' => 'live',
            'path' => $path, 'transitioned_at' => date('c'), 'warnings' => $preCheck['warnings']]);
    }

    public function workflowCheck(): void {
        $q = api_request('GET');
        $meetingId = api_require_uuid($q, 'meeting_id');
        $toStatus = api_query('to_status');
        $tenantId = api_current_tenant_id();
        if ($toStatus === '') {
            api_ok((new MeetingWorkflowService())->getTransitionReadiness($meetingId, $tenantId));
        } else {
            $result = (new MeetingWorkflowService())->issuesBeforeTransition($meetingId, $tenantId, $toStatus);
            api_ok(['to_status' => $toStatus, 'can_proceed' => $result['can_proceed'], 'issues' => $result['issues'], 'warnings' => $result['warnings']]);
        }
    }

    public function readyCheck(): void {
        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) { api_fail('missing_meeting_id', 400); }
        try {
            $result = (new MeetingTransitionService($this->repo()))->readyCheck($meetingId, api_current_tenant_id());
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'meeting_not_found') { api_fail('meeting_not_found', 404); }
            api_fail('business_error', 400, ['detail' => $e->getMessage()]);
        }
        api_ok($result);
    }

    public function consolidate(): void {
        $body = api_request('POST');
        $meetingId = api_require_uuid($body, 'meeting_id');
        $tenantId = api_current_tenant_id();
        $repo = $this->repo()->meeting();
        $meeting = $repo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) { api_fail('meeting_not_found', 404); }
        if (!in_array($meeting['status'], ['closed', 'validated'], true)) {
            api_fail('invalid_status_for_consolidation', 422, ['detail' => 'Seule une séance clôturée ou validée peut être consolidée.', 'current_status' => $meeting['status']]);
        }
        $r = (new OfficialResultsService())->consolidateMeeting($meetingId, $tenantId);
        audit_log('meeting.consolidate', 'meeting', $meetingId, ['updated_motions' => $r['updated'], 'title' => $meeting['title'] ?? ''], $meetingId);
        api_ok(['updated_motions' => $r['updated']]);
    }

    public function resetDemo(): void {
        $in = api_request('POST');

        // F09 hardening — defense in depth around a destructive operation.
        $tenantId = api_current_tenant_id();
        $meetingId = trim((string) ($in['meeting_id'] ?? ''));

        // 1. meeting_id is REQUIRED. The previous code, when called with empty
        //    meeting_id, wiped EVERY non-validated meeting in the tenant. That
        //    behavior was an accidental "rm -rf" primitive — removed.
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id (UUID) est requis. Le reset global est interdit.']);
        }

        // 2. Production + non-admin → hard refuse. Demo reset is a developer
        //    tool; in prod only an admin should ever invoke it (and even then
        //    only for explicit demo accounts).
        $isProd = strtolower((string) (getenv('APP_ENV') ?: '')) === 'production';
        $role = (string) (api_current_role() ?? '');
        if ($isProd && $role !== 'admin') {
            api_fail('forbidden_in_production', 403, [
                'detail' => 'Le reset démo est interdit en production hors compte admin.',
            ]);
        }

        // 3. Typed confirmation — operator MUST type the meeting_id prefix.
        //    Stops accidental clicks and replay across meetings.
        $expectedConfirm = 'RESET-' . substr($meetingId, 0, 8);
        $providedConfirm = (string) ($in['confirm'] ?? '');
        if ($providedConfirm !== $expectedConfirm) {
            api_fail('confirm_token_invalid', 400, [
                'detail' => 'Confirmation requise: {confirm:"' . $expectedConfirm . '"}',
            ]);
        }

        // 4. Snapshot the meeting state BEFORE we wipe it, so the audit row
        //    records what was actually reset (status, title, validated state).
        $meetingBefore = $this->repo()->meeting()->findByIdForTenant($meetingId, $tenantId);
        if (!$meetingBefore) {
            api_fail('meeting_not_found', 404);
        }

        try {
            $service = new MeetingTransitionService($this->repo());
            api_transaction(function () use ($service, $meetingId, $tenantId, $meetingBefore) {
                $service->resetDemo($meetingId, $tenantId);
                audit_log('meeting.reset_demo', 'meeting', $meetingId, [
                    'reset_by'      => api_current_user_id(),
                    'before_status' => $meetingBefore['status'] ?? null,
                    'before_title'  => $meetingBefore['title'] ?? null,
                ], $meetingId);
            });
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'meeting_not_found') { api_fail('meeting_not_found', 404); }
            if (str_contains($msg, 'validée')) { api_fail('meeting_validated', 409, ['detail' => $msg]); }
            if ($msg === 'meeting_status_not_resettable') {
                api_fail('meeting_status_not_resettable', 409, [
                    'detail' => 'Le reset n\'est autorisé que sur les séances en statut draft ou scheduled.',
                ]);
            }
            api_fail('business_error', 400, ['detail' => $msg]);
        }
        api_ok(['ok' => true, 'reset_count' => 1]);
    }
}
