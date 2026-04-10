<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Security\IdempotencyGuard;
use AgVote\Service\MeetingLifecycleService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Consolidates meeting CRUD + status endpoints.
 *
 * Business logic delegated to MeetingLifecycleService.
 * This controller is a thin HTTP adapter.
 */
final class MeetingsController extends AbstractController {
    public function index(): void {
        api_request('GET');

        $limit = api_query_int('limit', 50);
        if ($limit <= 0 || $limit > 200) {
            $limit = 50;
        }

        $activeOnly = filter_var(api_query('active_only', '0'), FILTER_VALIDATE_BOOLEAN);

        $repo = $this->repo()->meeting();
        if ($activeOnly) {
            $rows = $repo->listActiveByTenantCompact(api_current_tenant_id(), $limit);
        } else {
            $rows = $repo->listByTenantCompact(api_current_tenant_id(), $limit);
        }

        api_ok(['items' => $rows]);
    }

    public function update(): void {
        $input = api_request('POST');

        $meetingId = trim((string) ($input['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400, ['detail' => 'meeting_id est obligatoire (uuid).']);
        }

        api_guard_meeting_not_validated($meetingId);

        try {
            $service = new MeetingLifecycleService($this->repo());
            $result = $service->updateMeeting($meetingId, api_current_tenant_id(), $input);
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $code = 'validation_error';
            if (str_contains($msg, 'transitions de statut')) { $code = 'status_via_transition'; }
            elseif (str_contains($msg, 'titre') && str_contains($msg, 'obligatoire')) { $code = 'missing_title'; }
            elseif (str_contains($msg, '120')) { $code = 'title_too_long'; }
            elseif (str_contains($msg, 'président')) { $code = 'president_name_too_long'; }
            elseif (str_contains($msg, 'séance invalide')) { $code = 'invalid_meeting_type'; }
            api_fail($code, 400, ['detail' => $msg]);
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'meeting_not_found') { api_fail('meeting_not_found', 404); }
            if (str_contains($msg, 'archivée')) { api_fail('meeting_archived_locked', 409, ['detail' => $msg]); }
            api_fail('business_error', 409, ['detail' => $msg]);
        }

        if (isset($result['fields'])) {
            audit_log('meeting_updated', 'meeting', $meetingId, ['fields' => $result['fields']]);
            unset($result['fields']);
        }

        api_ok($result);
    }

    public function archive(): void {
        api_request('GET');
        $rows = $this->repo()->meeting()->listArchived(api_current_tenant_id(), api_query('from'), api_query('to'));
        api_ok(['items' => $rows]);
    }

    public function archivesList(): void {
        api_request('GET');
        api_ok(['items' => $this->repo()->meeting()->listArchivedWithReports(api_current_tenant_id())]);
    }

    public function status(): void {
        api_request('GET');
        try {
            $service = new MeetingLifecycleService($this->repo());
            $result = $service->getStatus(api_current_tenant_id(), api_current_role());
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'no_live_meeting') { api_fail('no_live_meeting', 404); }
            api_fail('business_error', 400, ['detail' => $e->getMessage()]);
        }
        api_ok($result);
    }

    public function statusForMeeting(): void {
        api_request('GET');
        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) { api_fail('missing_meeting_id', 422); }
        try {
            $service = new MeetingLifecycleService($this->repo());
            $result = $service->getStatusForMeeting($meetingId, api_current_tenant_id());
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'meeting_not_found') { api_fail('meeting_not_found', 404); }
            api_fail('business_error', 400, ['detail' => $e->getMessage()]);
        }
        api_ok($result);
    }

    public function summary(): void {
        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }
        try {
            $service = new MeetingLifecycleService($this->repo());
            $result = $service->getSummary($meetingId, api_current_tenant_id());
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'meeting_not_found') { api_fail('meeting_not_found', 404); }
            api_fail('business_error', 400, ['detail' => $e->getMessage()]);
        }
        api_ok($result);
    }

    public function stats(): void {
        api_request('GET');
        $meetingId = api_query('meeting_id');
        if ($meetingId === '') { api_fail('missing_meeting_id', 422); }
        try {
            $service = new MeetingLifecycleService($this->repo());
            $result = $service->getStats($meetingId, api_current_tenant_id());
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'meeting_not_found') { api_fail('meeting_not_found', 404); }
            api_fail('business_error', 400, ['detail' => $e->getMessage()]);
        }
        api_ok($result);
    }

    public function createMeeting(): void {
        $cached = IdempotencyGuard::check();
        if ($cached !== null) {
            api_ok($cached, 201);
        }

        $data = api_request('POST');
        $tenantId = api_current_tenant_id();

        try {
            $service = new MeetingLifecycleService($this->repo());
            $result = $service->createFromWizard($data, $tenantId);
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $code = 'validation_error';
            if (str_contains($msg, 'titre') && str_contains($msg, '3 car')) { $code = 'missing_title'; }
            elseif (str_contains($msg, 'nom') && str_contains($msg, 'obligatoire')) { $code = 'invalid_member'; }
            elseif (str_contains($msg, 'Email invalide')) { $code = 'invalid_member'; }
            elseif (str_contains($msg, 'résolution')) { $code = 'invalid_resolution'; }
            api_fail($code, 422, ['detail' => $msg]);
        }

        audit_log('meeting_created', 'meeting', (string) $result['meeting_id'], [
            'title' => $result['title'],
        ]);

        IdempotencyGuard::store($result);

        if (api_current_role() === 'president') {
            $currentUserId = api_current_user_id();
            $this->repo()->user()->assignMeetingRole(
                $tenantId,
                (string) $result['meeting_id'],
                $currentUserId,
                'president',
                $currentUserId,
            );
        }

        api_ok($result, 201);
    }

    public function deleteMeeting(): void {
        $input = api_request('POST');

        $meetingId = trim((string) ($input['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400, ['detail' => 'meeting_id est obligatoire (uuid).']);
        }

        $this->requireConfirmation($input, api_current_tenant_id());

        try {
            $service = new MeetingLifecycleService($this->repo());
            $result = $service->deleteDraft($meetingId, api_current_tenant_id());
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'meeting_not_found') {
                api_fail('meeting_not_found', 404);
            }
            if ($msg === 'meeting_live_cannot_delete') {
                api_fail('meeting_live_cannot_delete', 409, [
                    'detail' => "Fermez d'abord la séance avant de la supprimer.",
                ]);
            }
            if ($msg === 'meeting_not_draft') {
                api_fail('meeting_not_draft', 409, [
                    'detail' => 'Seules les séances en brouillon peuvent être supprimées.',
                ]);
            }
            api_fail('business_error', 400, ['detail' => $msg]);
        }

        audit_log('meeting_deleted', 'meeting', $meetingId, ['title' => $result['title']]);
        unset($result['title']);

        api_ok($result);
    }

    public function voteSettings(): void {
        $method = api_method();
        $repo = $this->repo()->meeting();

        if ($method === 'GET') {
            $q = api_request('GET');
            $meetingId = api_require_uuid($q, 'meeting_id');

            $row = $repo->findVoteSettings($meetingId, api_current_tenant_id());
            if (!$row) {
                api_fail('meeting_not_found', 404);
            }

            api_ok([
                'meeting_id' => $row['meeting_id'],
                'title' => $row['title'],
                'vote_policy_id' => $row['vote_policy_id'],
            ]);
        } elseif ($method === 'POST') {
            $in = api_request('POST');
            $meetingId = api_require_uuid($in, 'meeting_id');

            api_guard_meeting_not_validated($meetingId);

            $policyId = trim((string) ($in['vote_policy_id'] ?? ''));
            if ($policyId !== '' && !api_is_uuid($policyId)) { api_fail('invalid_vote_policy_id', 400, ['expected' => 'uuid or empty']); }
            if (!$repo->existsForTenant($meetingId, api_current_tenant_id())) { api_fail('meeting_not_found', 404); }
            if ($policyId !== '' && !$this->repo()->policy()->votePolicyExists($policyId, api_current_tenant_id())) {
                api_fail('vote_policy_not_found', 404);
            }
            $effectiveId = $policyId === '' ? null : $policyId;
            $repo->updateVotePolicy($meetingId, api_current_tenant_id(), $effectiveId);
            audit_log('meeting_vote_policy_updated', 'meeting', $meetingId, ['vote_policy_id' => $effectiveId]);

            api_ok(['saved' => true]);
        } else {
            api_fail('method_not_allowed', 405);
        }
    }

    public function validate(): void {
        $input = api_request('POST');

        $meetingId = trim((string) ($input['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('invalid_meeting_id', 400);
        }

        api_guard_meeting_not_validated($meetingId);

        $presidentName = trim((string) ($input['president_name'] ?? ''));
        if ($presidentName === '') {
            api_fail('missing_president_name', 400);
        }

        $tenant = api_current_tenant_id();

        try {
            $service = new MeetingLifecycleService($this->repo());
            $result = api_transaction(function () use ($service, $meetingId, $tenant, $presidentName) {
                return $service->validateMeeting($meetingId, $tenant, $presidentName);
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'meeting_not_found') {
                api_fail('meeting_not_found', 404);
            }
            api_fail('business_error', 400, ['detail' => $e->getMessage()]);
        }

        audit_log('meeting.validated', 'meeting', $meetingId, [
            'president_name' => $presidentName,
        ], $meetingId);

        api_ok($result);
    }
}
