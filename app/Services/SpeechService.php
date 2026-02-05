<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\SpeechRepository;
use RuntimeException;

/**
 * SpeechService - Speech management (raise hand).
 *
 * Expected table: speech_requests
 *  - id (uuid), tenant_id, meeting_id, member_id
 *  - status: waiting|speaking|finished|cancelled
 *  - created_at, updated_at
 */
final class SpeechService
{
    private static function ensureSchema(): void
    {
        (new SpeechRepository())->ensureSchema();
    }

    private static function memberLabel(string $memberId): ?string
    {
        $memberRepo = new MemberRepository();
        $m = $memberRepo->findById($memberId);
        if (!$m) return null;
        $name = trim((string)($m['full_name'] ?? ''));
        return $name !== '' ? $name : null;
    }

    private static function memberPayload(string $memberId): array
    {
        return [
            'member_id' => $memberId,
            'member_name' => self::memberLabel($memberId),
        ];
    }

    /**
     * Resolve tenant from meeting, optionally validating against expected tenant.
     *
     * @param string $meetingId Meeting ID
     * @param string|null $expectedTenantId If provided, validates meeting belongs to this tenant
     * @return string The meeting's tenant_id
     * @throws RuntimeException If meeting not found or tenant mismatch
     */
    private static function resolveTenant(string $meetingId, ?string $expectedTenantId = null): string
    {
        $meetingRepo = new MeetingRepository();

        // If we have expected tenant, use secure query
        if ($expectedTenantId !== null && $expectedTenantId !== '') {
            $meeting = $meetingRepo->findByIdForTenant($meetingId, $expectedTenantId);
            if (!$meeting) {
                throw new RuntimeException('Séance introuvable');
            }
            return $expectedTenantId;
        }

        // Fallback for public endpoints (no tenant context)
        $meeting = $meetingRepo->findById($meetingId);
        if (!$meeting) {
            throw new RuntimeException('Séance introuvable');
        }
        return (string)$meeting['tenant_id'];
    }

    /** @return array{speaker: ?array<string,mixed>, queue: array<int,array<string,mixed>>} */
    public static function getQueue(string $meetingId): array
    {
        self::ensureSchema();

        $tenantId = self::resolveTenant($meetingId);
        $repo = new SpeechRepository();

        $speaker = $repo->findCurrentSpeaker($meetingId, $tenantId);
        $queue = $repo->listWaiting($meetingId, $tenantId);

        return ['speaker' => $speaker ?: null, 'queue' => $queue];
    }

    /** @return array{status: string, request_id: ?string} */
    public static function getMyStatus(string $meetingId, string $memberId): array
    {
        self::ensureSchema();

        $tenantId = self::resolveTenant($meetingId);
        $repo = new SpeechRepository();

        $row = $repo->findActive($meetingId, $tenantId, $memberId);

        if (!$row) return ['status' => 'none', 'request_id' => null];

        return ['status' => (string)$row['status'], 'request_id' => (string)$row['id']];
    }

    /**
     * Toggle request: if already waiting -> cancel; otherwise -> create waiting.
     * @param string $meetingId Meeting ID
     * @param string $memberId Member ID
     * @param string|null $expectedTenantId If provided, validates meeting belongs to this tenant
     * @return array{status: string, request_id: ?string}
     */
    public static function toggleRequest(string $meetingId, string $memberId, ?string $expectedTenantId = null): array
    {
        self::ensureSchema();

        $tenantId = self::resolveTenant($meetingId, $expectedTenantId);
        $repo = new SpeechRepository();

        $existing = $repo->findActive($meetingId, $tenantId, $memberId);

        if ($existing && (string)$existing['status'] === 'waiting') {
            $repo->updateStatus((string)$existing['id'], $tenantId, 'cancelled');
            audit_log('speech_cancelled', 'meeting', $meetingId, self::memberPayload($memberId));
            return ['status' => 'none', 'request_id' => null];
        }

        if ($existing && (string)$existing['status'] === 'speaking') {
            $repo->updateStatus((string)$existing['id'], $tenantId, 'finished');
            audit_log('speech_finished_self', 'meeting', $meetingId, self::memberPayload($memberId));
            return ['status' => 'none', 'request_id' => null];
        }

        $id = api_uuid4();
        $repo->insert($id, $tenantId, $meetingId, $memberId, 'waiting');
        audit_log('speech_requested', 'meeting', $meetingId, self::memberPayload($memberId));
        return ['status' => 'waiting', 'request_id' => $id];
    }

    /**
     * Grants speech: either to the provided member, or to the next in queue.
     * @param string $meetingId Meeting ID
     * @param string|null $memberId Member ID (optional, picks next in queue if null)
     * @param string|null $expectedTenantId If provided, validates meeting belongs to this tenant
     */
    public static function grant(string $meetingId, ?string $memberId = null, ?string $expectedTenantId = null): array
    {
        self::ensureSchema();

        $tenantId = self::resolveTenant($meetingId, $expectedTenantId);
        $repo = new SpeechRepository();

        // End current speaker if any
        $repo->finishAllSpeaking($meetingId, $tenantId);

        if ($memberId) {
            $req = $repo->findWaitingForMember($meetingId, $tenantId, $memberId);

            if ($req) {
                $repo->updateStatus((string)$req['id'], $tenantId, 'speaking');
                audit_log('speech_granted', 'meeting', $meetingId, array_merge(self::memberPayload($memberId), ['request_id' => (string)$req['id']]));
                return self::getQueue($meetingId);
            }

            // Direct speaking
            $id = api_uuid4();
            $repo->insert($id, $tenantId, $meetingId, $memberId, 'speaking');
            audit_log('speech_granted_direct', 'meeting', $meetingId, array_merge(self::memberPayload($memberId), ['request_id' => $id]));
            return self::getQueue($meetingId);
        }

        // Pick next waiting
        $next = $repo->findNextWaiting($meetingId, $tenantId);

        if ($next) {
            $repo->updateStatus((string)$next['id'], $tenantId, 'speaking');
            audit_log('speech_granted_next', 'meeting', $meetingId, array_merge(self::memberPayload((string)$next['member_id']), ['request_id' => (string)$next['id']]));
        }

        return self::getQueue($meetingId);
    }

    /**
     * Ends the current speaker's speech.
     * @param string $meetingId Meeting ID
     * @param string|null $expectedTenantId If provided, validates meeting belongs to this tenant
     */
    public static function endCurrent(string $meetingId, ?string $expectedTenantId = null): array
    {
        self::ensureSchema();

        $tenantId = self::resolveTenant($meetingId, $expectedTenantId);
        $repo = new SpeechRepository();

        $cur = $repo->findCurrentSpeaker($meetingId, $tenantId);

        $repo->finishAllSpeaking($meetingId, $tenantId);

        $payload = [];
        if ($cur) {
            $payload = array_merge(
                self::memberPayload((string)$cur['member_id']),
                ['request_id' => (string)$cur['id']]
            );
        }
        audit_log('speech_ended', 'meeting', $meetingId, $payload);

        return self::getQueue($meetingId);
    }

    /**
     * Clears the history of finished speech requests.
     * @param string $meetingId Meeting ID
     * @param string|null $expectedTenantId If provided, validates meeting belongs to this tenant
     */
    public static function clearHistory(string $meetingId, ?string $expectedTenantId = null): array
    {
        self::ensureSchema();

        $tenantId = self::resolveTenant($meetingId, $expectedTenantId);
        $repo = new SpeechRepository();

        $count = $repo->countFinished($meetingId, $tenantId);
        $repo->deleteFinished($meetingId, $tenantId);

        audit_log('speech_cleared', 'meeting', $meetingId, ['deleted' => $count]);

        return self::getQueue($meetingId);
    }
}
