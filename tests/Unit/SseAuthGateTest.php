<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Repository\MeetingRepository;
use AgVote\SSE\SseAuthGate;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SseAuthGate — auth + tenant isolation gate for SSE consumers.
 *
 * Verifies the F05 hardening guarantees:
 * - Unauthenticated requests are rejected before meeting lookup
 * - Idle sessions are rejected (timeout)
 * - Cross-tenant meeting_id lookups return 404 (no enumeration signal)
 * - Auth-disabled mode (dev) bypasses tenant gate but still validates UUID
 */
final class SseAuthGateTest extends TestCase {
    private const TENANT_A = '11111111-1111-1111-1111-111111111111';
    private const TENANT_B = '22222222-2222-2222-2222-222222222222';
    private const MEETING_UUID = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    private const NOW = 1745000000;

    public function testRejectsUnauthenticatedRequest(): void {
        $gate = new SseAuthGate($this->createMock(MeetingRepository::class));

        $decision = $gate->evaluate([], self::MEETING_UUID, self::NOW);

        $this->assertSame(SseAuthGate::RESULT_AUTH_REQUIRED, $decision['result']);
        $this->assertSame(401, $decision['status']);
        $this->assertNull($decision['tenant_id']);
        $this->assertNull($decision['meeting']);
    }

    public function testRejectsIdleSession(): void {
        $gate = new SseAuthGate($this->createMock(MeetingRepository::class));
        $session = [
            'auth_user' => ['id' => 'u1', 'tenant_id' => self::TENANT_A, 'role' => 'operator'],
            'auth_last_activity' => self::NOW - SseAuthGate::SESSION_TIMEOUT - 1,
        ];

        $decision = $gate->evaluate($session, self::MEETING_UUID, self::NOW);

        $this->assertSame(SseAuthGate::RESULT_SESSION_EXPIRED, $decision['result']);
        $this->assertSame(401, $decision['status']);
    }

    public function testRejectsSessionWithoutTenantId(): void {
        $gate = new SseAuthGate($this->createMock(MeetingRepository::class));
        $session = [
            'auth_user' => ['id' => 'u1', 'role' => 'operator'], // no tenant_id
            'auth_last_activity' => self::NOW,
        ];

        $decision = $gate->evaluate($session, self::MEETING_UUID, self::NOW);

        $this->assertSame(SseAuthGate::RESULT_SESSION_MISSING_TENANT, $decision['result']);
    }

    public function testRejectsMissingMeetingId(): void {
        $gate = new SseAuthGate($this->createMock(MeetingRepository::class));
        $session = [
            'auth_user' => ['id' => 'u1', 'tenant_id' => self::TENANT_A],
            'auth_last_activity' => self::NOW,
        ];

        $decision = $gate->evaluate($session, null, self::NOW);

        $this->assertSame(SseAuthGate::RESULT_INVALID_MEETING_ID, $decision['result']);
        $this->assertSame(400, $decision['status']);
    }

    public function testRejectsMalformedMeetingId(): void {
        $gate = new SseAuthGate($this->createMock(MeetingRepository::class));
        $session = [
            'auth_user' => ['id' => 'u1', 'tenant_id' => self::TENANT_A],
            'auth_last_activity' => self::NOW,
        ];

        $decision = $gate->evaluate($session, 'not-a-uuid', self::NOW);

        $this->assertSame(SseAuthGate::RESULT_INVALID_MEETING_ID, $decision['result']);
    }

    /**
     * F05 critical: cross-tenant meeting access returns 404 (not 403) so a
     * tenant-A user can't enumerate tenant-B meeting IDs by status code.
     */
    public function testRejectsMeetingFromAnotherTenantWith404(): void {
        $repo = $this->createMock(MeetingRepository::class);
        $repo->expects($this->once())
            ->method('findByIdForTenant')
            ->with(self::MEETING_UUID, self::TENANT_A)
            ->willReturn(null); // meeting belongs to tenant B → tenant-A lookup misses

        $gate = new SseAuthGate($repo);
        $session = [
            'auth_user' => ['id' => 'u1', 'tenant_id' => self::TENANT_A],
            'auth_last_activity' => self::NOW,
        ];

        $decision = $gate->evaluate($session, self::MEETING_UUID, self::NOW);

        $this->assertSame(SseAuthGate::RESULT_MEETING_NOT_FOUND, $decision['result']);
        $this->assertSame(404, $decision['status'], 'Cross-tenant access must return 404 to avoid ID enumeration.');
        $this->assertNull($decision['meeting']);
    }

    public function testAllowsOwnTenantMeeting(): void {
        $meetingRow = ['id' => self::MEETING_UUID, 'tenant_id' => self::TENANT_A, 'status' => 'live'];
        $repo = $this->createMock(MeetingRepository::class);
        $repo->expects($this->once())
            ->method('findByIdForTenant')
            ->with(self::MEETING_UUID, self::TENANT_A)
            ->willReturn($meetingRow);

        $gate = new SseAuthGate($repo);
        $session = [
            'auth_user' => ['id' => 'u1', 'tenant_id' => self::TENANT_A],
            'auth_last_activity' => self::NOW - 60, // active session
        ];

        $decision = $gate->evaluate($session, self::MEETING_UUID, self::NOW);

        $this->assertSame(SseAuthGate::RESULT_ALLOWED, $decision['result']);
        $this->assertSame(200, $decision['status']);
        $this->assertSame(self::TENANT_A, $decision['tenant_id']);
        $this->assertSame($meetingRow, $decision['meeting']);
        $this->assertSame(self::NOW, $decision['refreshed_last_activity']);
    }

    public function testAuthDisabledBypassesTenantGateButValidatesUuid(): void {
        $repo = $this->createMock(MeetingRepository::class);
        // Repo MUST NOT be hit when auth is disabled — there's no tenant context.
        $repo->expects($this->never())->method('findByIdForTenant');

        $gate = new SseAuthGate($repo);
        $decision = $gate->evaluate([], self::MEETING_UUID, self::NOW, false);

        $this->assertSame(SseAuthGate::RESULT_ALLOWED, $decision['result']);
        $this->assertNull($decision['tenant_id']);
        $this->assertNull($decision['refreshed_last_activity']);
    }

    public function testAuthDisabledStillRejectsBadUuid(): void {
        $repo = $this->createMock(MeetingRepository::class);
        $gate = new SseAuthGate($repo);

        $decision = $gate->evaluate([], 'not-a-uuid', self::NOW, false);

        $this->assertSame(SseAuthGate::RESULT_INVALID_MEETING_ID, $decision['result']);
    }

    /**
     * Anti-leak guard: even with valid auth, the gate must NOT proceed to a DB
     * lookup if the meeting_id is malformed. This avoids generating SQL load
     * from a slow scan on every garbage SSE request.
     */
    public function testMalformedMeetingIdDoesNotHitRepository(): void {
        $repo = $this->createMock(MeetingRepository::class);
        $repo->expects($this->never())->method('findByIdForTenant');

        $gate = new SseAuthGate($repo);
        $session = [
            'auth_user' => ['id' => 'u1', 'tenant_id' => self::TENANT_A],
            'auth_last_activity' => self::NOW,
        ];

        $gate->evaluate($session, '../../etc/passwd', self::NOW);
    }
}
