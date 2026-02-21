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
    private SpeechRepository $speechRepo;
    private MeetingRepository $meetingRepo;
    private MemberRepository $memberRepo;

    public function __construct(
        ?SpeechRepository $speechRepo = null,
        ?MeetingRepository $meetingRepo = null,
        ?MemberRepository $memberRepo = null,
    ) {
        $this->speechRepo = $speechRepo ?? new SpeechRepository();
        $this->meetingRepo = $meetingRepo ?? new MeetingRepository();
        $this->memberRepo = $memberRepo ?? new MemberRepository();
    }

    private function memberLabel(string $memberId, string $tenantId): ?string {
        $m = $this->memberRepo->findByIdForTenant($memberId, $tenantId);
        if (!$m) {
            return null;
        }
        $name = trim((string) ($m['full_name'] ?? ''));
        return $name !== '' ? $name : null;
    }

    private function memberPayload(string $memberId, string $tenantId): array {
        return [
            'member_id' => $memberId,
            'member_name' => $this->memberLabel($memberId, $tenantId),
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
    private function resolveTenant(string $meetingId, string $tenantId): string {
        $meeting = $this->meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            throw new RuntimeException('Séance introuvable');
        }
        return $tenantId;
    }

    /** @return array{speaker: ?array<string,mixed>, queue: array<int,array<string,mixed>>} */
    public function getQueue(string $meetingId, string $tenantId): array {
        $tenantId = $this->resolveTenant($meetingId, $tenantId);

        $speaker = $this->speechRepo->findCurrentSpeaker($meetingId, $tenantId);
        $queue = $this->speechRepo->listWaiting($meetingId, $tenantId);

        return ['speaker' => $speaker ?: null, 'queue' => $queue];
    }

    /** @return array{status: string, request_id: ?string, position: ?int, queue_size: int} */
    public function getMyStatus(string $meetingId, string $memberId, string $tenantId): array {
        $tenantId = $this->resolveTenant($meetingId, $tenantId);

        $row = $this->speechRepo->findActive($meetingId, $tenantId, $memberId);
        $queue = $this->speechRepo->listWaiting($meetingId, $tenantId);
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
    public function toggleRequest(string $meetingId, string $memberId, string $tenantId): array {

        $tenantId = $this->resolveTenant($meetingId, $tenantId);

        $existing = $this->speechRepo->findActive($meetingId, $tenantId, $memberId);

        if ($existing && (string) $existing['status'] === 'waiting') {
            $this->speechRepo->updateStatus((string) $existing['id'], $tenantId, 'cancelled');
            audit_log('speech_cancelled', 'meeting', $meetingId, $this->memberPayload($memberId, $tenantId));
            return ['status' => 'none', 'request_id' => null];
        }

        if ($existing && (string) $existing['status'] === 'speaking') {
            $this->speechRepo->updateStatus((string) $existing['id'], $tenantId, 'finished');
            audit_log('speech_finished_self', 'meeting', $meetingId, $this->memberPayload($memberId, $tenantId));
            return ['status' => 'none', 'request_id' => null];
        }

        $id = api_uuid4();
        $this->speechRepo->insert($id, $tenantId, $meetingId, $memberId, 'waiting');
        audit_log('speech_requested', 'meeting', $meetingId, $this->memberPayload($memberId, $tenantId));
        return ['status' => 'waiting', 'request_id' => $id];
    }

    /**
     * Grants speech: either to the provided member, or to the next in queue.
     *
     * @param string $meetingId Meeting ID
     * @param string|null $memberId Member ID (optional, picks next in queue if null)
     * @param string $tenantId Tenant ID for tenant isolation
     */
    public function grant(string $meetingId, ?string $memberId, string $tenantId): array {
        $tenantId = $this->resolveTenant($meetingId, $tenantId);

        // End current speaker if any
        $this->speechRepo->finishAllSpeaking($meetingId, $tenantId);

        if ($memberId) {
            $req = $this->speechRepo->findWaitingForMember($meetingId, $tenantId, $memberId);

            if ($req) {
                $this->speechRepo->updateStatus((string) $req['id'], $tenantId, 'speaking');
                audit_log('speech_granted', 'meeting', $meetingId, array_merge($this->memberPayload($memberId, $tenantId), ['request_id' => (string) $req['id']]));
                return $this->getQueue($meetingId, $tenantId);
            }

            // Direct speaking
            $id = api_uuid4();
            $this->speechRepo->insert($id, $tenantId, $meetingId, $memberId, 'speaking');
            audit_log('speech_granted_direct', 'meeting', $meetingId, array_merge($this->memberPayload($memberId, $tenantId), ['request_id' => $id]));
            return $this->getQueue($meetingId, $tenantId);
        }

        // Pick next waiting
        $next = $this->speechRepo->findNextWaiting($meetingId, $tenantId);

        if ($next) {
            $this->speechRepo->updateStatus((string) $next['id'], $tenantId, 'speaking');
            audit_log('speech_granted_next', 'meeting', $meetingId, array_merge($this->memberPayload((string) $next['member_id'], $tenantId), ['request_id' => (string) $next['id']]));
        }

        return $this->getQueue($meetingId, $tenantId);
    }

    /**
     * Ends the current speaker's speech.
     *
     * @param string $meetingId Meeting ID
     * @param string $tenantId Tenant ID for tenant isolation
     */
    public function endCurrent(string $meetingId, string $tenantId): array {

        $tenantId = $this->resolveTenant($meetingId, $tenantId);

        $cur = $this->speechRepo->findCurrentSpeaker($meetingId, $tenantId);

        $this->speechRepo->finishAllSpeaking($meetingId, $tenantId);

        $payload = [];
        if ($cur) {
            $payload = array_merge(
                $this->memberPayload((string) $cur['member_id'], $tenantId),
                ['request_id' => (string) $cur['id']],
            );
        }
        audit_log('speech_ended', 'meeting', $meetingId, $payload);

        return $this->getQueue($meetingId, $tenantId);
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
    public function cancelRequest(string $meetingId, string $requestId, string $tenantId): array {

        $tenantId = $this->resolveTenant($meetingId, $tenantId);

        $req = $this->speechRepo->findById($requestId, $tenantId);
        if (!$req) {
            throw new RuntimeException('Demande de parole introuvable');
        }
        if ((string) $req['meeting_id'] !== $meetingId) {
            throw new RuntimeException('Demande de parole introuvable');
        }
        if ((string) $req['status'] !== 'waiting') {
            throw new RuntimeException('Seules les demandes en attente peuvent être annulées');
        }

        $this->speechRepo->updateStatus($requestId, $tenantId, 'cancelled');
        audit_log('speech_cancelled', 'meeting', $meetingId, array_merge(
            $this->memberPayload((string) $req['member_id'], $tenantId),
            ['request_id' => $requestId],
        ));

        return $this->getQueue($meetingId, $tenantId);
    }

    /**
     * Clears the history of finished speech requests.
     *
     * @param string $meetingId Meeting ID
     * @param string $tenantId Tenant ID for tenant isolation
     */
    public function clearHistory(string $meetingId, string $tenantId): array {

        $tenantId = $this->resolveTenant($meetingId, $tenantId);

        $count = $this->speechRepo->countFinished($meetingId, $tenantId);
        $this->speechRepo->deleteFinished($meetingId, $tenantId);

        audit_log('speech_cleared', 'meeting', $meetingId, ['deleted' => $count]);

        return $this->getQueue($meetingId, $tenantId);
    }
}
