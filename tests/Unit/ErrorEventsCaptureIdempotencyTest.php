<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Repository\ErrorEventsRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Validates the in-memory idempotency guard on ErrorEventsRepository::capture().
 *
 * Covers ERR-V26-02: back-to-back capture() calls within the same HTTP request
 * with the same (request_id, error_code, route) tuple must result in a single
 * INSERT — preventing artificial inflation of the error_events counter when
 * SSE empty-state handlers fire multiple times in a single request.
 */
final class ErrorEventsCaptureIdempotencyTest extends TestCase {
    private PDO $pdo;
    private PDOStatement $stmt;
    private int $executeCount = 0;
    private ErrorEventsRepository $repo;

    protected function setUp(): void {
        $this->pdo = $this->createMock(PDO::class);
        $this->stmt = $this->createMock(PDOStatement::class);

        $this->executeCount = 0;
        $this->stmt->method('execute')->willReturnCallback(function (): bool {
            $this->executeCount++;
            return true;
        });
        $this->stmt->method('rowCount')->willReturn(1);

        $this->pdo->method('prepare')->willReturn($this->stmt);

        $this->repo = new ErrorEventsRepository($this->pdo);

        ErrorEventsRepository::resetIdempotencyCache();
    }

    protected function tearDown(): void {
        ErrorEventsRepository::resetIdempotencyCache();
    }

    public function test_back_to_back_same_key_inserts_once(): void {
        $this->repo->capture('archived_meeting_locked', 400, null, null, '/api/v1/foo', 'POST', 'req-abc', []);
        $this->repo->capture('archived_meeting_locked', 400, null, null, '/api/v1/foo', 'POST', 'req-abc', []);
        $this->assertSame(1, $this->executeCount, '2 captures back-to-back avec meme (rid, code, route) doivent inserer 1 seule fois');
    }

    public function test_different_codes_same_request_inserts_twice(): void {
        $this->repo->capture('archived_meeting_locked', 400, null, null, '/api/v1/foo', 'POST', 'req-abc', []);
        $this->repo->capture('validated_meeting_locked', 400, null, null, '/api/v1/foo', 'POST', 'req-abc', []);
        $this->assertSame(2, $this->executeCount);
    }

    public function test_different_request_ids_inserts_twice(): void {
        $this->repo->capture('archived_meeting_locked', 400, null, null, '/api/v1/foo', 'POST', 'req-abc', []);
        $this->repo->capture('archived_meeting_locked', 400, null, null, '/api/v1/foo', 'POST', 'req-xyz', []);
        $this->assertSame(2, $this->executeCount);
    }

    public function test_null_request_id_skips_guard(): void {
        $this->repo->capture('archived_meeting_locked', 400, null, null, '/api/v1/foo', 'POST', null, []);
        $this->repo->capture('archived_meeting_locked', 400, null, null, '/api/v1/foo', 'POST', null, []);
        $this->assertSame(2, $this->executeCount, 'request_id null doit sauter le guard (CLI/bootstrap compat)');
    }

    public function test_null_route_skips_guard(): void {
        $this->repo->capture('archived_meeting_locked', 400, null, null, null, 'POST', 'req-abc', []);
        $this->repo->capture('archived_meeting_locked', 400, null, null, null, 'POST', 'req-abc', []);
        $this->assertSame(2, $this->executeCount, 'route null doit sauter le guard');
    }

    public function test_reset_cache_allows_re_insert(): void {
        $this->repo->capture('archived_meeting_locked', 400, null, null, '/api/v1/foo', 'POST', 'req-abc', []);
        $this->assertSame(1, $this->executeCount);
        ErrorEventsRepository::resetIdempotencyCache();
        $this->repo->capture('archived_meeting_locked', 400, null, null, '/api/v1/foo', 'POST', 'req-abc', []);
        $this->assertSame(2, $this->executeCount);
    }

    public function test_payload_difference_does_not_break_dedupe(): void {
        // La cle est (rid, code, route) — payload different doit quand meme dedupe
        // (le payload differe peu entre 2 appels du meme handler en rafale).
        $this->repo->capture('archived_meeting_locked', 400, null, null, '/api/v1/foo', 'POST', 'req-abc', ['detail' => 'A']);
        $this->repo->capture('archived_meeting_locked', 400, null, null, '/api/v1/foo', 'POST', 'req-abc', ['detail' => 'B']);
        $this->assertSame(1, $this->executeCount);
    }
}
