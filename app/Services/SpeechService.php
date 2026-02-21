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
final class SpeechService {
    private static function memberLabel(string $memberId, string $tenantId): ?string {
        $memberRepo = new MemberRepository();
        $m = $memberRepo->findByIdForTenant($memberId, $tenantId);
        if (!$m) {
            return null;
        }
        $name = trim((string) ($m['full_name'] ?? ''));
        return $name !== '' ? $name : null;
    }

    private static function memberPayload(string $memberId, string $tenantId): array {
        return [
            'member_id' => $memberId,
            'member_name' => self::memberLabel($memberId, $tenantId),
        ];
    }

    /**
     * Resolve tenant from meeting, validating it belongs to the given tenant.
     *
     * @param string $meetingId Meeting ID
     * @param string $tenantId Tenant ID to validate against
     *
     * @throws RuntimeException If meeting not found or tenant mismatch
     *
     * @return string The validated tenant_id
     */
    private static function resolveTenant(string $meetingId, string $tenantId): string {
        $meetingRepo = new MeetingRepository();
        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            throw new RuntimeException('Séance introuvable');
        }
        return $tenantId;
    }

    /** @return array{speaker: ?array<string,mixed>, queue: array<int,array<string,mixed>>} */
    public static function getQueue(string $meetingId, string $tenantId): array {
        $tenantId = self::resolveTenant($meetingId, $tenantId);
        $repo = new SpeechRepository();

        $speaker = $repo->findCurrentSpeaker($meetingId, $tenantId);
        $queue = $repo->listWaiting($meetingId, $tenantId);

        return ['speaker' => $speaker ?: null, 'queue' => $queue];
    }

    /** @return array{status: string, request_id: ?string, position: ?int, queue_size: int} */
    public static function getMyStatus(string $meetingId, string $memberId, string $tenantId): array {
        $tenantId = self::resolveTenant($meetingId, $tenantId);
        $repo = new SpeechRepository();

        $row = $repo->findActive($meetingId, $tenantId, $memberId);
        $queue = $repo->listWaiting($meetingId, $tenantId);
        $queueSize = count($queue);

        if (!$row) {
            return ['status' => 'none', 'request_id' => null, 'position' => null, 'queue_size' => $queueSize];
        }

        $status = (string) $row['status'];
        $position = null;
        if ($status === 'waiting') {
            foreach ($queue as $i => $q) {
                if ((string) $q['member_id'] === $memberId) {
                    $position = $i + 1; // 1-indexed
                    break;
                }
            }
        }

        return ['status' => $status, 'request_id' => (string) $row['id'], 'position' => $position, 'queue_size' => $queueSize];
    }

    /**
     * Toggle request: if already waiting -> cancel; otherwise -> create waiting.
     *
     * @param string $meetingId Meeting ID
     * @param string $memberId Member ID
     * @param string $tenantId Tenant ID for tenant isolation
     *
     * @return array{status: string, request_id: ?string}
     */
    public static function toggleRequest(string $meetingId, string $memberId, string $tenantId): array {

        $tenantId = self::resolveTenant($meetingId, $tenantId);
        $repo = new SpeechRepository();

        $existing = $repo->findActive($meetingId, $tenantId, $memberId);

        if ($existing && (string) $existing['status'] === 'waiting') {
            $repo->updateStatus((string) $existing['id'], $tenantId, 'cancelled');
            audit_log('speech_cancelled', 'meeting', $meetingId, self::memberPayload($memberId, $tenantId));
            return ['status' => 'none', 'request_id' => null];
        }

        if ($existing && (string) $existing['status'] === 'speaking') {
            $repo->updateStatus((string) $existing['id'], $tenantId, 'finished');
            audit_log('speech_finished_self', 'meeting', $meetingId, self::memberPayload($memberId, $tenantId));
            return ['status' => 'none', 'request_id' => null];
        }

        $id = api_uuid4();
        $repo->insert($id, $tenantId, $meetingId, $memberId, 'waiting');
        audit_log('speech_requested', 'meeting', $meetingId, self::memberPayload($memberId, $tenantId));
        return ['status' => 'waiting', 'request_id' => $id];
    }

    /**
     * Grants speech: either to the provided member, or to the next in queue.
     *
     * @param string $meetingId Meeting ID
     * @param string|null $memberId Member ID (optional, picks next in queue if null)
     * @param string $tenantId Tenant ID for tenant isolation
     */
    public static function grant(string $meetingId, ?string $memberId, string $tenantId): array {
        $tenantId = self::resolveTenant($meetingId, $tenantId);
        $repo = new SpeechRepository();

        // End current speaker if any
        $repo->finishAllSpeaking($meetingId, $tenantId);

        if ($memberId) {
            $req = $repo->findWaitingForMember($meetingId, $tenantId, $memberId);

            if ($req) {
                $repo->updateStatus((string) $req['id'], $tenantId, 'speaking');
                audit_log('speech_granted', 'meeting', $meetingId, array_merge(self::memberPayload($memberId, $tenantId), ['request_id' => (string) $req['id']]));
                return self::getQueue($meetingId, $tenantId);
            }

            // Direct speaking
            $id = api_uuid4();
            $repo->insert($id, $tenantId, $meetingId, $memberId, 'speaking');
            audit_log('speech_granted_direct', 'meeting', $meetingId, array_merge(self::memberPayload($memberId, $tenantId), ['request_id' => $id]));
            return self::getQueue($meetingId, $tenantId);
        }

        // Pick next waiting
        $next = $repo->findNextWaiting($meetingId, $tenantId);

        if ($next) {
            $repo->updateStatus((string) $next['id'], $tenantId, 'speaking');
            audit_log('speech_granted_next', 'meeting', $meetingId, array_merge(self::memberPayload((string) $next['member_id'], $tenantId), ['request_id' => (string) $next['id']]));
        }

        return self::getQueue($meetingId, $tenantId);
    }

    /**
     * Ends the current speaker's speech.
     *
     * @param string $meetingId Meeting ID
     * @param string $tenantId Tenant ID for tenant isolation
     */
    public static function endCurrent(string $meetingId, string $tenantId): array {

        $tenantId = self::resolveTenant($meetingId, $tenantId);
        $repo = new SpeechRepository();

        $cur = $repo->findCurrentSpeaker($meetingId, $tenantId);

        $repo->finishAllSpeaking($meetingId, $tenantId);

        $payload = [];
        if ($cur) {
            $payload = array_merge(
                self::memberPayload((string) $cur['member_id'], $tenantId),
                ['request_id' => (string) $cur['id']],
            );
        }
        audit_log('speech_ended', 'meeting', $meetingId, $payload);

        return self::getQueue($meetingId, $tenantId);
    }

    /**
     * Cancel a specific speech request by ID.
     * Only cancels requests with 'waiting' status.
     *
     * @param string $meetingId Meeting ID
     * @param string $requestId Speech request ID
     * @param string $tenantId Tenant ID for tenant isolation
     *
     * @throws RuntimeException If request not found or not cancellable
     *
     * @return array{speaker: ?array<string,mixed>, queue: array<int,array<string,mixed>>}
     */
    public static function cancelRequest(string $meetingId, string $requestId, string $tenantId): array {

        $tenantId = self::resolveTenant($meetingId, $tenantId);
        $repo = new SpeechRepository();

        $req = $repo->findById($requestId, $tenantId);
        if (!$req) {
            throw new RuntimeException('Demande de parole introuvable');
        }
        if ((string) $req['meeting_id'] !== $meetingId) {
            throw new RuntimeException('Demande de parole introuvable');
        }
        if ((string) $req['status'] !== 'waiting') {
            throw new RuntimeException('Seules les demandes en attente peuvent être annulées');
        }

        $repo->updateStatus($requestId, $tenantId, 'cancelled');
        audit_log('speech_cancelled', 'meeting', $meetingId, array_merge(
            self::memberPayload((string) $req['member_id'], $tenantId),
            ['request_id' => $requestId],
        ));

        return self::getQueue($meetingId, $tenantId);
    }

    /**
     * Clears the history of finished speech requests.
     *
     * @param string $meetingId Meeting ID
     * @param string $tenantId Tenant ID for tenant isolation
     */
    public static function clearHistory(string $meetingId, string $tenantId): array {

        $tenantId = self::resolveTenant($meetingId, $tenantId);
        $repo = new SpeechRepository();

        $count = $repo->countFinished($meetingId, $tenantId);
        $repo->deleteFinished($meetingId, $tenantId);

        audit_log('speech_cleared', 'meeting', $meetingId, ['deleted' => $count]);

        return self::getQueue($meetingId, $tenantId);
    }
}
