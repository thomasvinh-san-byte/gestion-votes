<?php

declare(strict_types=1);

namespace AgVote\SSE;

use AgVote\Repository\MeetingRepository;
use AgVote\Service\QuorumEngine;
use Closure;
use Throwable;

/**
 * Builds the meeting.heartbeat SSE payload — fresh status + quorum + presence
 * snapshot emitted every 10s independently of event-driven dispatches.
 *
 * Each sub-query is try/catch isolated so a single failure does not break
 * the SSE loop. Extracted from public/api/v1/events.php for unit testability
 * (TEST-V26-01 / HEARTBEAT-V25-03).
 *
 * The quorum lookup is injected as a Closure rather than the QuorumEngine
 * class directly because QuorumEngine is final — wrapping the call in a
 * closure lets unit tests substitute a stub without bypassing PHP's final
 * class restrictions.
 */
final class HeartbeatPayloadBuilder {
    /** @var Closure(string, ?string): array */
    private readonly Closure $quorumLookup;

    /**
     * @param Closure(string, ?string): array $quorumLookup function ($meetingId, $tenantId) => quorum array
     */
    public function __construct(
        Closure $quorumLookup,
        private readonly MeetingRepository $meetingRepo,
    ) {
        $this->quorumLookup = $quorumLookup;
    }

    /**
     * Convenience factory that wires the builder against the production QuorumEngine.
     */
    public static function fromQuorumEngine(QuorumEngine $quorum, MeetingRepository $meetingRepo): self {
        return new self(
            static fn (string $meetingId, ?string $tenantId): array => $quorum->computeForMeeting($meetingId, $tenantId),
            $meetingRepo,
        );
    }

    /**
     * @param object|null $redis Redis client (phpredis) — must expose sCard(string): int
     * @return array{meeting_id: string, server_time: string, status?: string, validated_at?: string|null, quorum?: array{applied: bool, met: bool|null, present_members: int, eligible_members: int, present_weight: float, eligible_weight: float}, operator_count?: int}
     */
    public function build(
        string $meetingId,
        ?string $tenantId,
        ?string $presenceKey,
        ?object $redis,
    ): array {
        $payload = [
            'meeting_id'  => $meetingId,
            'server_time' => date('c'),
        ];

        if ($tenantId !== null && $tenantId !== '') {
            try {
                $row = $this->meetingRepo->findByIdForTenant($meetingId, $tenantId);
                if ($row !== null) {
                    $payload['status']       = (string) ($row['status'] ?? '');
                    $payload['validated_at'] = $row['validated_at'] ?? null;
                }
            } catch (Throwable $e) {
                // Heartbeat must never break the SSE loop
            }

            try {
                $q = ($this->quorumLookup)($meetingId, $tenantId);
                $payload['quorum'] = [
                    'applied'          => (bool) ($q['applied'] ?? false),
                    'met'              => $q['met'] ?? null,
                    'present_members'  => (int) ($q['numerator']['members'] ?? 0),
                    'eligible_members' => (int) ($q['eligible']['members'] ?? 0),
                    'present_weight'   => (float) ($q['numerator']['weight'] ?? 0),
                    'eligible_weight'  => (float) ($q['eligible']['weight'] ?? 0),
                ];
            } catch (Throwable $e) {
                // Quorum compute may throw RuntimeException for missing meetings — skip
            }
        }

        if ($presenceKey !== null && $redis !== null) {
            try {
                $payload['operator_count'] = (int) $redis->sCard($presenceKey);
            } catch (Throwable $e) {
                // Redis unavailable — omit operator_count
            }
        }

        return $payload;
    }
}
