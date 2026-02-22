<?php

declare(strict_types=1);

namespace AgVote\Tests\Unit;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\VoteTokenRepository;
use AgVote\Service\VoteTokenService;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for VoteTokenService.
 *
 * All repository dependencies are mocked — no database connection needed.
 */
class VoteTokenServiceTest extends TestCase {
    private const TENANT_ID = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const MEETING_ID = 'meeting-0001';
    private const MEMBER_ID = 'member-0001';
    private const MOTION_ID = 'motion-0001';

    private MeetingRepository&MockObject $meetingRepo;
    private VoteTokenRepository&MockObject $tokenRepo;
    private VoteTokenService $service;

    protected function setUp(): void {
        $this->meetingRepo = $this->createMock(MeetingRepository::class);
        $this->tokenRepo = $this->createMock(VoteTokenRepository::class);

        $this->service = new VoteTokenService(
            $this->meetingRepo,
            $this->tokenRepo,
        );
    }

    // =========================================================================
    // generate() TESTS
    // =========================================================================

    public function testGenerateReturnsTokenArray(): void {
        $this->meetingRepo
            ->method('findByIdForTenant')
            ->with(self::MEETING_ID, self::TENANT_ID)
            ->willReturn(['id' => self::MEETING_ID, 'tenant_id' => self::TENANT_ID]);

        $this->tokenRepo
            ->expects($this->once())
            ->method('insert');

        $result = $this->service->generate(
            self::MEETING_ID,
            self::MEMBER_ID,
            self::MOTION_ID,
            3600,
            self::TENANT_ID,
        );

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('token_hash', $result);
        $this->assertArrayHasKey('expires_at', $result);

        // Token should be a 64-char hex string (32 random bytes)
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result['token']);
        // Hash should be HMAC-SHA256 of the token (keyed with APP_SECRET)
        $this->assertSame(hash_hmac('sha256', $result['token'], APP_SECRET), $result['token_hash']);
    }

    public function testGenerateThrowsOnEmptyMotionId(): void {
        $this->expectException(InvalidArgumentException::class);

        $this->service->generate(
            self::MEETING_ID,
            self::MEMBER_ID,
            '',
            3600,
            self::TENANT_ID,
        );
    }

    public function testGenerateThrowsOnEmptyMeetingId(): void {
        $this->expectException(InvalidArgumentException::class);

        $this->service->generate(
            '',
            self::MEMBER_ID,
            self::MOTION_ID,
            3600,
            self::TENANT_ID,
        );
    }

    public function testGenerateThrowsOnEmptyMemberId(): void {
        $this->expectException(InvalidArgumentException::class);

        $this->service->generate(
            self::MEETING_ID,
            '',
            self::MOTION_ID,
            3600,
            self::TENANT_ID,
        );
    }

    public function testGenerateThrowsWhenMeetingNotFound(): void {
        $this->meetingRepo
            ->method('findByIdForTenant')
            ->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('introuvable');

        $this->service->generate(
            self::MEETING_ID,
            self::MEMBER_ID,
            self::MOTION_ID,
            3600,
            self::TENANT_ID,
        );
    }

    public function testGenerateEnforcesMinimumTtl(): void {
        $this->meetingRepo
            ->method('findByIdForTenant')
            ->willReturn(['id' => self::MEETING_ID]);

        $capturedExpiresAt = null;
        $this->tokenRepo
            ->expects($this->once())
            ->method('insert')
            ->willReturnCallback(function (
                string $tokenHash,
                string $tenantId,
                string $meetingId,
                string $memberId,
                string $motionId,
                string $expiresAt,
            ) use (&$capturedExpiresAt): void {
                $capturedExpiresAt = $expiresAt;
            });

        $result = $this->service->generate(
            self::MEETING_ID,
            self::MEMBER_ID,
            self::MOTION_ID,
            10, // below minimum of 60
            self::TENANT_ID,
        );

        // The expiry should be at least 60 seconds from now, not 10
        $expiresTs = strtotime($capturedExpiresAt);
        $this->assertGreaterThanOrEqual(time() + 55, $expiresTs);
    }

    public function testGenerateTokenHashIsPersisted(): void {
        $this->meetingRepo
            ->method('findByIdForTenant')
            ->willReturn(['id' => self::MEETING_ID]);

        $capturedHash = null;
        $this->tokenRepo
            ->expects($this->once())
            ->method('insert')
            ->willReturnCallback(function (
                string $tokenHash,
                string $tenantId,
                string $meetingId,
                string $memberId,
                string $motionId,
                string $expiresAt,
            ) use (&$capturedHash): void {
                $capturedHash = $tokenHash;
            });

        $result = $this->service->generate(
            self::MEETING_ID,
            self::MEMBER_ID,
            self::MOTION_ID,
            3600,
            self::TENANT_ID,
        );

        // The hash stored in DB should match the returned token_hash
        $this->assertSame($result['token_hash'], $capturedHash);
    }

    public function testGeneratePassesTenantIdToRepo(): void {
        $this->meetingRepo
            ->method('findByIdForTenant')
            ->willReturn(['id' => self::MEETING_ID]);

        $capturedTenantId = null;
        $this->tokenRepo
            ->expects($this->once())
            ->method('insert')
            ->willReturnCallback(function (
                string $tokenHash,
                string $tenantId,
                string $meetingId,
                string $memberId,
                string $motionId,
                string $expiresAt,
            ) use (&$capturedTenantId): void {
                $capturedTenantId = $tenantId;
            });

        $this->service->generate(
            self::MEETING_ID,
            self::MEMBER_ID,
            self::MOTION_ID,
            3600,
            self::TENANT_ID,
        );

        $this->assertSame(self::TENANT_ID, $capturedTenantId);
    }

    // =========================================================================
    // validate() TESTS
    // =========================================================================

    public function testValidateWithValidTokenReturnsValid(): void {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash_hmac('sha256', $rawToken, APP_SECRET);

        $this->tokenRepo
            ->method('findByHash')
            ->with($tokenHash)
            ->willReturn([
                'token_hash' => $tokenHash,
                'tenant_id' => self::TENANT_ID,
                'meeting_id' => self::MEETING_ID,
                'member_id' => self::MEMBER_ID,
                'motion_id' => self::MOTION_ID,
                'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 3600),
                'used_at' => null,
            ]);

        $result = $this->service->validate($rawToken);

        $this->assertTrue($result['valid']);
        $this->assertSame($tokenHash, $result['token_hash']);
        $this->assertSame(self::MEETING_ID, $result['meeting_id']);
        $this->assertSame(self::MEMBER_ID, $result['member_id']);
        $this->assertSame(self::MOTION_ID, $result['motion_id']);
    }

    public function testValidateWithExpiredTokenReturnsInvalid(): void {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash_hmac('sha256', $rawToken, APP_SECRET);

        $this->tokenRepo
            ->method('findByHash')
            ->with($tokenHash)
            ->willReturn([
                'token_hash' => $tokenHash,
                'tenant_id' => self::TENANT_ID,
                'meeting_id' => self::MEETING_ID,
                'member_id' => self::MEMBER_ID,
                'motion_id' => self::MOTION_ID,
                'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 100),
                'used_at' => null,
            ]);

        $result = $this->service->validate($rawToken);

        $this->assertFalse($result['valid']);
        $this->assertSame('token_expired', $result['reason']);
    }

    public function testValidateWithAlreadyUsedTokenReturnsInvalid(): void {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash_hmac('sha256', $rawToken, APP_SECRET);

        $this->tokenRepo
            ->method('findByHash')
            ->with($tokenHash)
            ->willReturn([
                'token_hash' => $tokenHash,
                'tenant_id' => self::TENANT_ID,
                'meeting_id' => self::MEETING_ID,
                'member_id' => self::MEMBER_ID,
                'motion_id' => self::MOTION_ID,
                'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 3600),
                'used_at' => '2025-01-01T00:00:00Z',
            ]);

        $result = $this->service->validate($rawToken);

        $this->assertFalse($result['valid']);
        $this->assertSame('token_already_used', $result['reason']);
    }

    public function testValidateWithEmptyTokenReturnsInvalid(): void {
        $result = $this->service->validate('');

        $this->assertFalse($result['valid']);
        $this->assertSame('token_empty', $result['reason']);
    }

    public function testValidateWithNotFoundTokenReturnsInvalid(): void {
        $this->tokenRepo
            ->method('findByHash')
            ->willReturn(null);

        $result = $this->service->validate('nonexistent-token');

        $this->assertFalse($result['valid']);
        $this->assertSame('token_not_found', $result['reason']);
    }

    // =========================================================================
    // validateAndConsume() TESTS
    // =========================================================================

    public function testValidateAndConsumeWithValidTokenReturnsBallotData(): void {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash_hmac('sha256', $rawToken, APP_SECRET);

        $this->tokenRepo
            ->method('consumeIfValid')
            ->with($tokenHash)
            ->willReturn([
                'token_hash' => $tokenHash,
                'tenant_id' => self::TENANT_ID,
                'meeting_id' => self::MEETING_ID,
                'member_id' => self::MEMBER_ID,
                'motion_id' => self::MOTION_ID,
                'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 3600),
                'used_at' => gmdate('Y-m-d\TH:i:s\Z'),
            ]);

        $result = $this->service->validateAndConsume($rawToken);

        $this->assertTrue($result['valid']);
        $this->assertSame($tokenHash, $result['token_hash']);
        $this->assertSame(self::MEETING_ID, $result['meeting_id']);
        $this->assertSame(self::MEMBER_ID, $result['member_id']);
        $this->assertSame(self::MOTION_ID, $result['motion_id']);
    }

    public function testValidateAndConsumeWithExpiredTokenReturnsExpiredReason(): void {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash_hmac('sha256', $rawToken, APP_SECRET);

        // consumeIfValid returns null (token is expired or invalid)
        $this->tokenRepo
            ->method('consumeIfValid')
            ->with($tokenHash)
            ->willReturn(null);

        // diagnoseFailure returns the reason in a single query
        $this->tokenRepo
            ->method('diagnoseFailure')
            ->with($tokenHash)
            ->willReturn('token_expired');

        $result = $this->service->validateAndConsume($rawToken);

        $this->assertFalse($result['valid']);
        $this->assertSame('token_expired', $result['reason']);
    }

    public function testValidateAndConsumeWithAlreadyUsedTokenReturnsUsedReason(): void {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash_hmac('sha256', $rawToken, APP_SECRET);

        $this->tokenRepo
            ->method('consumeIfValid')
            ->willReturn(null);

        $this->tokenRepo
            ->method('diagnoseFailure')
            ->with($tokenHash)
            ->willReturn('token_already_used');

        $result = $this->service->validateAndConsume($rawToken);

        $this->assertFalse($result['valid']);
        $this->assertSame('token_already_used', $result['reason']);
    }

    public function testValidateAndConsumeWithNotFoundTokenReturnsNotFoundReason(): void {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash_hmac('sha256', $rawToken, APP_SECRET);

        $this->tokenRepo
            ->method('consumeIfValid')
            ->willReturn(null);

        $this->tokenRepo
            ->method('diagnoseFailure')
            ->with($tokenHash)
            ->willReturn('token_not_found');

        $result = $this->service->validateAndConsume($rawToken);

        $this->assertFalse($result['valid']);
        $this->assertSame('token_not_found', $result['reason']);
    }

    public function testValidateAndConsumeWithEmptyTokenReturnsEmptyReason(): void {
        $result = $this->service->validateAndConsume('');

        $this->assertFalse($result['valid']);
        $this->assertSame('token_empty', $result['reason']);
    }

    public function testValidateAndConsumeTrimsWhitespace(): void {
        $result = $this->service->validateAndConsume('   ');

        $this->assertFalse($result['valid']);
        $this->assertSame('token_empty', $result['reason']);
    }

    // =========================================================================
    // consume() TESTS
    // =========================================================================

    public function testConsumeWithValidTokenReturnsTrue(): void {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash_hmac('sha256', $rawToken, APP_SECRET);

        $this->tokenRepo
            ->method('findByHash')
            ->with($tokenHash)
            ->willReturn([
                'token_hash' => $tokenHash,
                'tenant_id' => self::TENANT_ID,
            ]);

        $this->tokenRepo
            ->method('consume')
            ->with($tokenHash, self::TENANT_ID)
            ->willReturn(1);

        $result = $this->service->consume($rawToken);

        $this->assertTrue($result);
    }

    public function testConsumeWithEmptyTokenReturnsFalse(): void {
        $result = $this->service->consume('');

        $this->assertFalse($result);
    }

    public function testConsumeWithExplicitTenantId(): void {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash_hmac('sha256', $rawToken, APP_SECRET);

        $this->tokenRepo
            ->expects($this->once())
            ->method('consume')
            ->with($tokenHash, self::TENANT_ID)
            ->willReturn(1);

        $result = $this->service->consume($rawToken, self::TENANT_ID);

        $this->assertTrue($result);
    }

    public function testConsumeWhenTokenNotFoundReturnsFalse(): void {
        $rawToken = bin2hex(random_bytes(32));

        $this->tokenRepo
            ->method('findByHash')
            ->willReturn(null);

        $result = $this->service->consume($rawToken);

        $this->assertFalse($result);
    }

    // =========================================================================
    // revokeForMotion() TESTS
    // =========================================================================

    public function testRevokeForMotionCallsRepoCorrectly(): void {
        $this->tokenRepo
            ->expects($this->once())
            ->method('revokeForMotion')
            ->with(self::MOTION_ID, self::TENANT_ID)
            ->willReturn(5);

        $result = $this->service->revokeForMotion(self::MOTION_ID, self::TENANT_ID);

        $this->assertSame(5, $result);
    }

    public function testRevokeForMotionReturnsZeroWhenNoTokens(): void {
        $this->tokenRepo
            ->method('revokeForMotion')
            ->willReturn(0);

        $result = $this->service->revokeForMotion(self::MOTION_ID, self::TENANT_ID);

        $this->assertSame(0, $result);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function testTokenHashIsConsistentHmacSha256(): void {
        $rawToken = 'a1b2c3d4e5f6';
        $expectedHash = hash_hmac('sha256', $rawToken, APP_SECRET);

        $this->tokenRepo
            ->method('findByHash')
            ->with($expectedHash)
            ->willReturn(null);

        $result = $this->service->validate($rawToken);

        $this->assertSame($expectedHash, $result['token_hash']);
    }

    public function testGenerateProducesDifferentTokensEachCall(): void {
        $this->meetingRepo
            ->method('findByIdForTenant')
            ->willReturn(['id' => self::MEETING_ID]);

        $this->tokenRepo->method('insert');

        $result1 = $this->service->generate(
            self::MEETING_ID,
            self::MEMBER_ID,
            self::MOTION_ID,
            3600,
            self::TENANT_ID,
        );

        $result2 = $this->service->generate(
            self::MEETING_ID,
            self::MEMBER_ID,
            self::MOTION_ID,
            3600,
            self::TENANT_ID,
        );

        $this->assertNotSame($result1['token'], $result2['token']);
        $this->assertNotSame($result1['token_hash'], $result2['token_hash']);
    }

    public function testGenerateWhitespaceOnlyParamsThrows(): void {
        $this->expectException(InvalidArgumentException::class);

        $this->service->generate(
            '  ',
            self::MEMBER_ID,
            self::MOTION_ID,
            3600,
            self::TENANT_ID,
        );
    }
}
