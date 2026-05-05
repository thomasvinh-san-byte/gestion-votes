<?php

declare(strict_types=1);

namespace Tests\Unit\Sse;

use AgVote\Repository\MeetingRepository;
use AgVote\SSE\HeartbeatPayloadBuilder;
use Closure;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit test for AgVote\SSE\HeartbeatPayloadBuilder.
 *
 * Lève la dette HEARTBEAT-V25-03 / TEST-V26-01 : verrouille la forme du
 * payload meeting.heartbeat (5 champs : meeting_id, server_time, status,
 * quorum, operator_count) consommé par event-stream.js + operator-realtime.js.
 *
 * Tout changement de forme du payload doit casser ce test (c'est le contrat).
 *
 * Note: QuorumEngine est `final` donc non-mockable par PHPUnit — on injecte
 * un Closure stub via le constructeur du builder. MeetingRepository n'est
 * pas final et reste mockable normalement.
 */
final class HeartbeatPayloadTest extends TestCase {
    private const MEETING_ID = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    private const TENANT_ID  = '11111111-1111-1111-1111-111111111111';
    private const PRESENCE_KEY = 'sse:operators:aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

    /**
     * Default no-op quorum closure that returns an empty array (callers should
     * override when the test exercises the quorum branch).
     */
    private function noopQuorum(): Closure {
        return static fn (string $meetingId, ?string $tenantId): array => [];
    }

    public function testReturnsMandatoryFieldsWhenTenantIdNull(): void {
        $builder = new HeartbeatPayloadBuilder(
            $this->noopQuorum(),
            $this->createMock(MeetingRepository::class),
        );

        $payload = $builder->build(self::MEETING_ID, null, null, null);

        $this->assertSame(self::MEETING_ID, $payload['meeting_id']);
        $this->assertIsString($payload['server_time']);
        $this->assertArrayNotHasKey('status', $payload);
        $this->assertArrayNotHasKey('quorum', $payload);
        $this->assertArrayNotHasKey('operator_count', $payload);
    }

    public function testServerTimeIsIso8601(): void {
        $builder = new HeartbeatPayloadBuilder(
            $this->noopQuorum(),
            $this->createMock(MeetingRepository::class),
        );

        $payload = $builder->build(self::MEETING_ID, null, null, null);

        // date('c') format: 2026-05-05T14:30:00+02:00
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}$/',
            $payload['server_time'],
        );
    }

    public function testIncludesStatusAndQuorumWhenTenantSet(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')
            ->with(self::MEETING_ID, self::TENANT_ID)
            ->willReturn(['status' => 'running', 'validated_at' => null]);

        $quorumLookup = static fn (string $meetingId, ?string $tenantId): array => [
            'applied'   => true,
            'met'       => false,
            'numerator' => ['members' => 3, 'weight' => 4.5],
            'eligible'  => ['members' => 10, 'weight' => 12.0],
        ];

        $builder = new HeartbeatPayloadBuilder($quorumLookup, $meetingRepo);
        $payload = $builder->build(self::MEETING_ID, self::TENANT_ID, null, null);

        $this->assertSame('running', $payload['status']);
        $this->assertNull($payload['validated_at']);
        $this->assertIsArray($payload['quorum']);
    }

    public function testQuorumSubArrayHasExactSixKeys(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(['status' => 'running', 'validated_at' => null]);

        $quorumLookup = static fn (string $meetingId, ?string $tenantId): array => [
            'applied'   => true,
            'met'       => true,
            'numerator' => ['members' => 5, 'weight' => 5.0],
            'eligible'  => ['members' => 5, 'weight' => 5.0],
        ];

        $builder = new HeartbeatPayloadBuilder($quorumLookup, $meetingRepo);
        $payload = $builder->build(self::MEETING_ID, self::TENANT_ID, null, null);

        $this->assertArrayHasKey('applied', $payload['quorum']);
        $this->assertArrayHasKey('met', $payload['quorum']);
        $this->assertArrayHasKey('present_members', $payload['quorum']);
        $this->assertArrayHasKey('eligible_members', $payload['quorum']);
        $this->assertArrayHasKey('present_weight', $payload['quorum']);
        $this->assertArrayHasKey('eligible_weight', $payload['quorum']);
        $this->assertCount(6, $payload['quorum']);

        $this->assertIsBool($payload['quorum']['applied']);
        $this->assertIsBool($payload['quorum']['met']);
        $this->assertIsInt($payload['quorum']['present_members']);
        $this->assertIsInt($payload['quorum']['eligible_members']);
        $this->assertIsFloat($payload['quorum']['present_weight']);
        $this->assertIsFloat($payload['quorum']['eligible_weight']);
    }

    public function testIncludesOperatorCountWhenPresenceKeySet(): void {
        $redis = new class {
            public function sCard(string $key): int {
                return 7;
            }
        };

        $builder = new HeartbeatPayloadBuilder(
            $this->noopQuorum(),
            $this->createMock(MeetingRepository::class),
        );
        $payload = $builder->build(self::MEETING_ID, null, self::PRESENCE_KEY, $redis);

        $this->assertArrayHasKey('operator_count', $payload);
        $this->assertSame(7, $payload['operator_count']);
    }

    public function testHandlesMeetingRepoExceptionGracefully(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')
            ->willThrowException(new RuntimeException('db down'));

        $quorumLookup = static fn (string $meetingId, ?string $tenantId): array => [
            'applied'   => false,
            'met'       => null,
            'numerator' => ['members' => 0, 'weight' => 0],
            'eligible'  => ['members' => 0, 'weight' => 0],
        ];

        $builder = new HeartbeatPayloadBuilder($quorumLookup, $meetingRepo);
        $payload = $builder->build(self::MEETING_ID, self::TENANT_ID, null, null);

        $this->assertArrayNotHasKey('status', $payload);
        $this->assertArrayHasKey('quorum', $payload);
    }

    public function testHandlesQuorumExceptionGracefully(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(['status' => 'running', 'validated_at' => null]);

        $quorumLookup = static function (string $meetingId, ?string $tenantId): array {
            throw new RuntimeException('quorum compute failed');
        };

        $builder = new HeartbeatPayloadBuilder($quorumLookup, $meetingRepo);
        $payload = $builder->build(self::MEETING_ID, self::TENANT_ID, null, null);

        $this->assertSame('running', $payload['status']);
        $this->assertArrayNotHasKey('quorum', $payload);
    }

    public function testHandlesRedisExceptionGracefully(): void {
        $redis = new class {
            public function sCard(string $key): int {
                throw new RuntimeException('redis down');
            }
        };

        $builder = new HeartbeatPayloadBuilder(
            $this->noopQuorum(),
            $this->createMock(MeetingRepository::class),
        );
        $payload = $builder->build(self::MEETING_ID, null, self::PRESENCE_KEY, $redis);

        $this->assertArrayNotHasKey('operator_count', $payload);
    }
}
