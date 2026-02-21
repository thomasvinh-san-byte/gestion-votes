<?php

declare(strict_types=1);

namespace AgVote\Tests\Unit;

use AgVote\Repository\ProxyRepository;
use AgVote\Service\ProxiesService;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ProxiesService.
 *
 * All repository dependencies are mocked -- no database connection needed.
 * Global functions config() and api_transaction() are stubbed at the bottom
 * of this file so the service can be tested in isolation.
 */
class ProxiesServiceTest extends TestCase {
    private const TENANT_ID = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const MEETING_ID = 'meeting-0001';
    private const GIVER_ID = 'member-giver-001';
    private const RECEIVER_ID = 'member-receiver-001';

    private ProxyRepository&MockObject $proxyRepo;
    private ProxiesService $service;

    protected function setUp(): void {
        $this->proxyRepo = $this->createMock(ProxyRepository::class);
        $this->service = new ProxiesService($this->proxyRepo);
    }

    // =========================================================================
    // listForMeeting() TESTS
    // =========================================================================

    public function testListForMeetingReturnsArrayFromRepo(): void {
        $expected = [
            ['id' => 'proxy-1', 'giver_member_id' => 'a', 'receiver_member_id' => 'b'],
            ['id' => 'proxy-2', 'giver_member_id' => 'c', 'receiver_member_id' => 'd'],
        ];

        $this->proxyRepo
            ->expects($this->once())
            ->method('listForMeeting')
            ->with(self::MEETING_ID, self::TENANT_ID)
            ->willReturn($expected);

        $result = $this->service->listForMeeting(self::MEETING_ID, self::TENANT_ID);

        $this->assertSame($expected, $result);
    }

    public function testListForMeetingReturnsEmptyArrayWhenNoProxies(): void {
        $this->proxyRepo
            ->method('listForMeeting')
            ->willReturn([]);

        $result = $this->service->listForMeeting(self::MEETING_ID, self::TENANT_ID);

        $this->assertSame([], $result);
    }

    // =========================================================================
    // upsert() TESTS
    // =========================================================================

    public function testUpsertCreatesProxySuccessfully(): void {
        // Tenant coherence check passes for giver
        $this->proxyRepo
            ->method('countTenantCoherence')
            ->willReturnCallback(function (string $meetingId, string $tenantId, string $memberId): int {
                return 1; // coherence OK for all members
            });

        // No chain: receiver has not delegated
        $this->proxyRepo
            ->method('countActiveAsGiverForUpdate')
            ->with(self::MEETING_ID, self::RECEIVER_ID, self::TENANT_ID)
            ->willReturn(0);

        // Cap not reached
        $this->proxyRepo
            ->method('countActiveAsReceiverForUpdate')
            ->with(self::MEETING_ID, self::RECEIVER_ID, self::TENANT_ID)
            ->willReturn(0);

        // Expect the upsert to be called
        $this->proxyRepo
            ->expects($this->once())
            ->method('upsertProxy')
            ->with(self::TENANT_ID, self::MEETING_ID, self::GIVER_ID, self::RECEIVER_ID);

        $this->service->upsert(self::MEETING_ID, self::GIVER_ID, self::RECEIVER_ID, self::TENANT_ID);
    }

    public function testUpsertThrowsOnEmptyGiverMemberId(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('giver_member_id');

        $this->service->upsert(self::MEETING_ID, '', self::RECEIVER_ID, self::TENANT_ID);
    }

    public function testUpsertThrowsOnSelfDelegation(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('giver != receiver');

        $sameMemberId = 'member-same-001';

        $this->service->upsert(self::MEETING_ID, $sameMemberId, $sameMemberId, self::TENANT_ID);
    }

    public function testUpsertThrowsWhenGiverTenantCoherenceFails(): void {
        $this->proxyRepo
            ->method('countTenantCoherence')
            ->with(self::MEETING_ID, self::TENANT_ID, self::GIVER_ID)
            ->willReturn(0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalide pour ce tenant');

        $this->service->upsert(self::MEETING_ID, self::GIVER_ID, self::RECEIVER_ID, self::TENANT_ID);
    }

    public function testUpsertThrowsWhenChainDetected(): void {
        // Tenant coherence OK for both giver and receiver
        $this->proxyRepo
            ->method('countTenantCoherence')
            ->willReturn(1);

        // Chain detected: receiver already delegates
        $this->proxyRepo
            ->method('countActiveAsGiverForUpdate')
            ->with(self::MEETING_ID, self::RECEIVER_ID, self::TENANT_ID)
            ->willReturn(1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('interdite');

        $this->service->upsert(self::MEETING_ID, self::GIVER_ID, self::RECEIVER_ID, self::TENANT_ID);
    }

    public function testUpsertThrowsWhenReceiverCapExceeded(): void {
        // Tenant coherence OK for both
        $this->proxyRepo
            ->method('countTenantCoherence')
            ->willReturn(1);

        // No chain
        $this->proxyRepo
            ->method('countActiveAsGiverForUpdate')
            ->willReturn(0);

        // Cap reached (99 is the default from config())
        $this->proxyRepo
            ->method('countActiveAsReceiverForUpdate')
            ->with(self::MEETING_ID, self::RECEIVER_ID, self::TENANT_ID)
            ->willReturn(99);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Plafond');

        $this->service->upsert(self::MEETING_ID, self::GIVER_ID, self::RECEIVER_ID, self::TENANT_ID);
    }

    public function testUpsertWithEmptyReceiverRevokesProxy(): void {
        // Tenant coherence OK for giver
        $this->proxyRepo
            ->method('countTenantCoherence')
            ->with(self::MEETING_ID, self::TENANT_ID, self::GIVER_ID)
            ->willReturn(1);

        // Expect revoke, not upsert
        $this->proxyRepo
            ->expects($this->once())
            ->method('revokeForGiver')
            ->with(self::MEETING_ID, self::GIVER_ID);

        $this->proxyRepo
            ->expects($this->never())
            ->method('upsertProxy');

        $this->service->upsert(self::MEETING_ID, self::GIVER_ID, '', self::TENANT_ID);
    }

    public function testUpsertThrowsWhenReceiverTenantCoherenceFails(): void {
        // Giver coherence OK, receiver coherence fails
        $this->proxyRepo
            ->method('countTenantCoherence')
            ->willReturnCallback(function (string $meetingId, string $tenantId, string $memberId): int {
                if ($memberId === self::GIVER_ID) {
                    return 1;
                }
                return 0; // receiver fails coherence
            });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('receiver_member_id invalide');

        $this->service->upsert(self::MEETING_ID, self::GIVER_ID, self::RECEIVER_ID, self::TENANT_ID);
    }

    // =========================================================================
    // revoke() TESTS
    // =========================================================================

    public function testRevokeCallsRepoCorrectly(): void {
        $this->proxyRepo
            ->expects($this->once())
            ->method('revokeForGiver')
            ->with(self::MEETING_ID, self::GIVER_ID);

        $this->service->revoke(self::MEETING_ID, self::GIVER_ID);
    }

    // =========================================================================
    // hasActiveProxy() TESTS
    // =========================================================================

    public function testHasActiveProxyReturnsTrueWhenProxyExists(): void {
        $this->proxyRepo
            ->method('hasActiveProxy')
            ->with(self::MEETING_ID, self::GIVER_ID, self::RECEIVER_ID, self::TENANT_ID)
            ->willReturn(true);

        $result = $this->service->hasActiveProxy(self::MEETING_ID, self::GIVER_ID, self::RECEIVER_ID, self::TENANT_ID);

        $this->assertTrue($result);
    }

    public function testHasActiveProxyReturnsFalseWhenNoProxy(): void {
        $this->proxyRepo
            ->method('hasActiveProxy')
            ->with(self::MEETING_ID, self::GIVER_ID, self::RECEIVER_ID, self::TENANT_ID)
            ->willReturn(false);

        $result = $this->service->hasActiveProxy(self::MEETING_ID, self::GIVER_ID, self::RECEIVER_ID, self::TENANT_ID);

        $this->assertFalse($result);
    }

    public function testHasActiveProxyIsolatesByMeeting(): void {
        $otherMeetingId = 'meeting-other-001';

        $this->proxyRepo
            ->method('hasActiveProxy')
            ->willReturnCallback(function (string $meetingId, string $giverId, string $receiverId, string $tenantId): bool {
                return $meetingId === self::MEETING_ID;
            });

        $this->assertTrue($this->service->hasActiveProxy(self::MEETING_ID, self::GIVER_ID, self::RECEIVER_ID, self::TENANT_ID));
        $this->assertFalse($this->service->hasActiveProxy($otherMeetingId, self::GIVER_ID, self::RECEIVER_ID, self::TENANT_ID));
    }
}

