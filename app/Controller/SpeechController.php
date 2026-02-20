<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\SpeechRepository;
use AgVote\Service\SpeechService;

final class SpeechController extends AbstractController
{
    public function request(): void
    {
        $data = api_request('POST');
        $meetingId = api_require_uuid($data, 'meeting_id');
        $memberId = api_require_uuid($data, 'member_id');
        $tenantId = api_current_tenant_id();

        // Tenant isolation: verify meeting belongs to current tenant
        $meeting = (new \AgVote\Repository\MeetingRepository())->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        SpeechService::toggleRequest($meetingId, $memberId, $tenantId);
        $out = SpeechService::getMyStatus($meetingId, $memberId, $tenantId);

        audit_log('speech.requested', 'meeting', $meetingId, [
            'member_id' => $memberId,
        ], $meetingId);

        api_ok($out);
    }

    public function grant(): void
    {
        $data = api_request('POST');
        $meetingId = api_require_uuid($data, 'meeting_id');
        $memberId = trim((string)($data['member_id'] ?? ''));
        $requestId = trim((string)($data['request_id'] ?? ''));

        if ($memberId !== '' && !api_is_uuid($memberId)) api_fail('invalid_uuid', 400, ['field' => 'member_id']);
        if ($requestId !== '' && !api_is_uuid($requestId)) api_fail('invalid_uuid', 400, ['field' => 'request_id']);

        $tenantId = api_current_tenant_id();

        if ($memberId === '' && $requestId !== '') {
            $req = (new SpeechRepository())->findById($requestId, $tenantId);
            if ($req) {
                $memberId = (string)$req['member_id'];
            }
        }

        $out = SpeechService::grant($meetingId, $memberId !== '' ? $memberId : null, $tenantId);

        audit_log('speech.granted', 'meeting', $meetingId, [
            'member_id' => $memberId ?: ($out['speaker']['member_id'] ?? null),
        ], $meetingId);

        api_ok($out);
    }

    public function end(): void
    {
        $data = api_request('POST');
        $meetingId = api_require_uuid($data, 'meeting_id');
        $tenantId = api_current_tenant_id();

        $out = SpeechService::endCurrent($meetingId, $tenantId);

        audit_log('speech.ended', 'meeting', $meetingId, [], $meetingId);
        api_ok($out);
    }

    public function cancel(): void
    {
        $data = api_request('POST');
        $meetingId = api_require_uuid($data, 'meeting_id');
        $requestId = api_require_uuid($data, 'request_id');
        $tenantId = api_current_tenant_id();

        $out = SpeechService::cancelRequest($meetingId, $requestId, $tenantId);

        audit_log('speech.cancelled', 'meeting', $meetingId, [
            'request_id' => $requestId,
        ], $meetingId);

        api_ok($out);
    }

    public function clear(): void
    {
        $data = api_request('POST');
        $meetingId = api_require_uuid($data, 'meeting_id');
        $tenantId = api_current_tenant_id();

        $out = SpeechService::clearHistory($meetingId, $tenantId);

        audit_log('speech.cleared', 'meeting', $meetingId, [], $meetingId);
        api_ok($out);
    }

    public function next(): void
    {
        $data = api_request('POST');
        $meetingId = api_require_uuid($data, 'meeting_id');
        $tenantId = api_current_tenant_id();

        $out = SpeechService::grant($meetingId, null, $tenantId);

        audit_log('speech.next', 'meeting', $meetingId, [
            'member_id' => $out['speaker']['member_id'] ?? null,
        ], $meetingId);

        api_ok($out);
    }

    public function queue(): void
    {
        $q = api_request('GET');
        $meetingId = api_require_uuid($q, 'meeting_id');
        $tenantId = api_current_tenant_id();

        $out = SpeechService::getQueue($meetingId, $tenantId);

        $queue = $out['queue'] ?? [];
        foreach ($queue as &$item) {
            $item['member_name'] = $item['full_name'] ?? $item['member_name'] ?? '';
            $item['requested_at'] = $item['created_at'] ?? null;
        }
        unset($item);

        api_ok([
            'speaker' => $out['speaker'] ?? null,
            'queue' => $queue,
        ]);
    }

    public function current(): void
    {
        $q = api_request('GET');
        $meetingId = api_require_uuid($q, 'meeting_id');
        $tenantId = api_current_tenant_id();

        $out = SpeechService::getQueue($meetingId, $tenantId);
        $speaker = $out['speaker'] ?? null;
        $queueCount = count($out['queue'] ?? []);

        if (!$speaker) {
            api_ok(['speaker' => null, 'queue_count' => $queueCount]);
            return;
        }

        $startedAt = $speaker['updated_at'] ?? null;
        $elapsedSeconds = 0;
        if ($startedAt) {
            $startTime = strtotime($startedAt);
            if ($startTime !== false) {
                $elapsedSeconds = max(0, time() - $startTime);
            }
        }

        $minutes = floor($elapsedSeconds / 60);
        $seconds = $elapsedSeconds % 60;

        api_ok([
            'member_name' => $speaker['full_name'] ?? null,
            'member_id'   => $speaker['member_id'] ?? null,
            'request_id'  => $speaker['id'] ?? null,
            'started_at'  => $startedAt,
            'elapsed_seconds' => $elapsedSeconds,
            'elapsed_formatted' => sprintf('%02d:%02d', $minutes, $seconds),
            'queue_count' => $queueCount,
        ]);
    }

    public function myStatus(): void
    {
        $q = api_request('GET');
        $meetingId = api_require_uuid($q, 'meeting_id');
        $memberId = api_require_uuid($q, 'member_id');

        $tenantId = api_current_tenant_id();
        api_ok(SpeechService::getMyStatus($meetingId, $memberId, $tenantId));
    }
}
