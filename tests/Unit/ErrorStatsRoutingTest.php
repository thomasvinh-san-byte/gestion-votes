<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Repository\ErrorEventsRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * ERR-V26-03 — smoke test: capture() emits the new specific error codes
 * into error_events and topCodesSince() retrieves them. Mock-PDO pure
 * (no DB, no extension) — capture() uses ::jsonb (PostgreSQL-specific)
 * which would require a real Postgres for an end-to-end test. The mock
 * verifies the SQL routing contract: the right code reaches the INSERT
 * and the SELECT shape returns the expected columns.
 *
 * Cross-plan contract with Plan 02-02: each capture() call below uses a
 * distinct request_id (req-A, req-B) to escape the intra-request idempotency
 * guard introduced by 02-02. Intentional — these tests verify SQL routing,
 * not dedupe.
 */
final class ErrorStatsRoutingTest extends TestCase {
    public function test_capture_emits_archived_meeting_locked_into_insert(): void {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params): bool {
                return ($params[':code'] ?? null) === 'archived_meeting_locked';
            }));

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO error_events'))
            ->willReturn($stmt);

        $repo = new ErrorEventsRepository($pdo);
        $repo->capture(
            'archived_meeting_locked',
            400,
            null,
            null,
            '/api/v1/meetings/123/transition',
            'POST',
            'req-A',
            [],
        );
    }

    public function test_capture_emits_validated_meeting_locked_into_insert(): void {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params): bool {
                return ($params[':code'] ?? null) === 'validated_meeting_locked';
            }));

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $repo = new ErrorEventsRepository($pdo);
        $repo->capture(
            'validated_meeting_locked',
            400,
            null,
            null,
            '/api/v1/meetings/123/reset',
            'POST',
            'req-B',
            [],
        );
    }

    public function test_top_codes_since_executes_select(): void {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            ['error_code' => 'archived_meeting_locked', 'count' => 5, 'last_occurred_at' => '2026-05-05 10:00:00'],
            ['error_code' => 'validated_meeting_locked', 'count' => 3, 'last_occurred_at' => '2026-05-05 09:00:00'],
        ]);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('FROM error_events'))
            ->willReturn($stmt);

        $repo = new ErrorEventsRepository($pdo);
        $top = $repo->topCodesSince(168, 10, null);
        $codes = array_column($top, 'error_code');
        $this->assertContains('archived_meeting_locked', $codes);
        $this->assertContains('validated_meeting_locked', $codes);
    }
}
