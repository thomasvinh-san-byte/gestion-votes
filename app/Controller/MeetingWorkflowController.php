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
            if (str_contains($msg, 'déjà au statut')) { api_fail('already_in_status', 422, ['detail' => $msg]); }
            if (str_contains($msg, 'archivée')) { api_fail('archived_immutable', 403, ['detail' => $msg]); }
            api_fail('business_error', 400, ['detail' => $msg]);
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
            if ($lockedFrom === $toStatus) { api_fail('already_in_status', 422, ['detail' => "La séance est déjà au statut '{$toStatus}'."]); }
            if ($lockedFrom === 'archived') { api_fail('archived_immutable', 403, ['detail' => 'Séance archivée : aucune transition autorisée.']); }
            $fields = $service->buildTransitionFields($toStatus, $lockedFrom, $locked, $userId, api_current_user()['name'] ?? null);
            $repo->updateFields($meetingId, $tenantId, $fields);
            $auditData = ['from_status' => $lockedFrom, 'to_status' => $toStatus, 'title' => $locked['title']];
            if ($forceTransition) { $auditData['forced'] = true; }
            audit_log('meeting.transition', 'meeting', $meetingId, $auditData, $meetingId);
            return $lockedFrom;
        });
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
            if (str_contains($msg, 'déjà en cours')) { api_fail('already_in_status', 422, ['detail' => $msg]); }
            $decoded = json_decode($msg, true);
            if (is_array($decoded) && ($decoded['code'] ?? '') === 'workflow_issues') { api_fail('workflow_issues', 422, $decoded); }
            api_fail('invalid_launch_status', 422, ['detail' => $msg]);
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
        if ((string) ($in['confirm'] ?? '') !== 'RESET') {
            api_fail('missing_confirm', 400, ['detail' => 'Envoyez {confirm:"RESET"} pour éviter les resets accidentels.']);
        }
        $tenantId = api_current_tenant_id();
        $meetingId = trim((string) ($in['meeting_id'] ?? ''));
        try {
            $service = new MeetingTransitionService($this->repo());
            api_transaction(function () use ($service, $meetingId, $tenantId) {
                $service->resetDemo($meetingId, $tenantId);
                audit_log('meeting.reset_demo', 'meeting', $meetingId, ['reset_by' => api_current_user_id()], $meetingId);
            });
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'meeting_not_found') { api_fail('meeting_not_found', 404); }
            if (str_contains($msg, 'validée')) { api_fail('meeting_validated', 409, ['detail' => $msg]); }
            api_fail('business_error', 400, ['detail' => $msg]);
        }
        api_ok(['ok' => true, 'reset_count' => 1]);
    }
}
