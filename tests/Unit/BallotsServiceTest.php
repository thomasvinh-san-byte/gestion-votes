<?php

declare(strict_types=1);

namespace AgVote\Tests\Unit;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\ProxyRepository;
use AgVote\Service\AttendancesService;
use AgVote\Service\BallotsService;
use AgVote\Service\ProxiesService;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for BallotsService.
 *
 * All repository dependencies are mocked; no database connection is needed.
 * Since AttendancesService and ProxiesService are declared final, they cannot
 * be mocked directly. Instead we create real instances with mocked repositories.
 * The global api_transaction() is stubbed in bootstrap.php.
 */
class BallotsServiceTest extends TestCase {
    private const TENANT = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const MEETING = 'bbbbbbbb-1111-2222-3333-444444444444';
    private const MOTION = 'cccccccc-1111-2222-3333-444444444444';
    private const MEMBER = 'dddddddd-1111-2222-3333-444444444444';

    private BallotRepository&MockObject $ballotRepo;
    private MotionRepository&MockObject $motionRepo;
    private MemberRepository&MockObject $memberRepo;
    private MeetingRepository&MockObject $meetingRepo;

    // Repositories injected into the real AttendancesService
    private AttendanceRepository&MockObject $attendanceRepo;

    // Repository injected into the real ProxiesService
    private ProxyRepository&MockObject $proxyRepo;

    private BallotsService $service;

    protected function setUp(): void {
        $this->ballotRepo = $this->createMock(BallotRepository::class);
        $this->motionRepo = $this->createMock(MotionRepository::class);
        $this->memberRepo = $this->createMock(MemberRepository::class);
        $this->meetingRepo = $this->createMock(MeetingRepository::class);

        // Create real AttendancesService with mocked repos (cannot mock final class)
        $this->attendanceRepo = $this->createMock(AttendanceRepository::class);
        $attendancesService = new AttendancesService(
            $this->attendanceRepo,
            $this->createMock(MeetingRepository::class),
            $this->createMock(MemberRepository::class),
        );

        // Create real ProxiesService with mocked repo (cannot mock final class)
        $this->proxyRepo = $this->createMock(ProxyRepository::class);
        $proxiesService = new ProxiesService($this->proxyRepo);

        $this->service = new BallotsService(
            $this->ballotRepo,
            $this->motionRepo,
            $this->memberRepo,
            $this->meetingRepo,
            $attendancesService,
            $proxiesService,
        );
    }

    // =========================================================================
    // Helper to build a valid ballot context
    // =========================================================================

    private function validBallotContext(): array {
        return [
            'motion_id' => self::MOTION,
            'motion_opened_at' => '2026-01-01 10:00:00',
            'motion_closed_at' => null,
            'meeting_id' => self::MEETING,
            'meeting_status' => 'live',
            'meeting_validated_at' => null,
            'tenant_id' => self::TENANT,
        ];
    }

    private function activeMember(float $votingPower = 1.0): array {
        return [
            'id' => self::MEMBER,
            'voting_power' => $votingPower,
            'is_active' => true,
            'tenant_id' => self::TENANT,
        ];
    }

    private function validData(array $overrides = []): array {
        return array_merge([
            'motion_id' => self::MOTION,
            'member_id' => self::MEMBER,
            'value' => 'for',
            '_tenant_id' => self::TENANT,
        ], $overrides);
    }

    /**
     * Configures mocks for a successful direct vote scenario.
     */
    private function setupSuccessfulDirectVote(float $votingPower = 1.0): void {
        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($this->validBallotContext());

        $this->memberRepo->method('findByIdForTenant')
            ->willReturn($this->activeMember($votingPower));

        // isPresentDirect is delegated to attendanceRepo.isPresentDirect
        $this->attendanceRepo->method('isPresentDirect')
            ->willReturn(true);

        // lockForUpdate inside api_transaction
        $this->meetingRepo->method('lockForUpdate')
            ->willReturn(['id' => self::MEETING, 'status' => 'live']);
    }

    // =========================================================================
    // castBallot() -- successful cast
    // =========================================================================

    public function testCastBallotWithValidDataReturnsBallotArray(): void {
        $this->setupSuccessfulDirectVote();

        $expectedBallot = [
            'motion_id' => self::MOTION,
            'member_id' => self::MEMBER,
            'value' => 'for',
            'weight' => 1.0,
        ];

        $this->ballotRepo->method('findByMotionAndMember')
            ->willReturn($expectedBallot);

        $result = $this->service->castBallot($this->validData());

        $this->assertSame(self::MOTION, $result['motion_id']);
        $this->assertSame(self::MEMBER, $result['member_id']);
        $this->assertSame('for', $result['value']);
    }

    public function testCastBallotAcceptsForValue(): void {
        $this->setupSuccessfulDirectVote();
        $this->ballotRepo->method('findByMotionAndMember')
            ->willReturn(['motion_id' => self::MOTION, 'member_id' => self::MEMBER, 'value' => 'for', 'weight' => 1.0]);

        $result = $this->service->castBallot($this->validData(['value' => 'for']));
        $this->assertSame('for', $result['value']);
    }

    public function testCastBallotAcceptsAgainstValue(): void {
        $this->setupSuccessfulDirectVote();
        $this->ballotRepo->method('findByMotionAndMember')
            ->willReturn(['motion_id' => self::MOTION, 'member_id' => self::MEMBER, 'value' => 'against', 'weight' => 1.0]);

        $result = $this->service->castBallot($this->validData(['value' => 'against']));
        $this->assertSame('against', $result['value']);
    }

    public function testCastBallotAcceptsAbstainValue(): void {
        $this->setupSuccessfulDirectVote();
        $this->ballotRepo->method('findByMotionAndMember')
            ->willReturn(['motion_id' => self::MOTION, 'member_id' => self::MEMBER, 'value' => 'abstain', 'weight' => 1.0]);

        $result = $this->service->castBallot($this->validData(['value' => 'abstain']));
        $this->assertSame('abstain', $result['value']);
    }

    public function testCastBallotAcceptsNspValue(): void {
        $this->setupSuccessfulDirectVote();
        $this->ballotRepo->method('findByMotionAndMember')
            ->willReturn(['motion_id' => self::MOTION, 'member_id' => self::MEMBER, 'value' => 'nsp', 'weight' => 1.0]);

        $result = $this->service->castBallot($this->validData(['value' => 'nsp']));
        $this->assertSame('nsp', $result['value']);
    }

    // =========================================================================
    // castBallot() -- missing required fields
    // =========================================================================

    public function testCastBallotThrowsOnEmptyMotionId(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('motion_id, member_id et value sont obligatoires');
        $this->service->castBallot($this->validData(['motion_id' => '']));
    }

    public function testCastBallotThrowsOnEmptyMemberId(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('motion_id, member_id et value sont obligatoires');
        $this->service->castBallot($this->validData(['member_id' => '']));
    }

    public function testCastBallotThrowsOnEmptyValue(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('motion_id, member_id et value sont obligatoires');
        $this->service->castBallot($this->validData(['value' => '']));
    }

    public function testCastBallotThrowsOnMissingMotionId(): void {
        $this->expectException(InvalidArgumentException::class);
        $data = $this->validData();
        unset($data['motion_id']);
        $this->service->castBallot($data);
    }

    public function testCastBallotThrowsOnMissingMemberId(): void {
        $this->expectException(InvalidArgumentException::class);
        $data = $this->validData();
        unset($data['member_id']);
        $this->service->castBallot($data);
    }

    // =========================================================================
    // castBallot() -- invalid choice value
    // =========================================================================

    public function testCastBallotThrowsOnInvalidChoiceValue(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Valeur de vote invalide');
        $this->service->castBallot($this->validData(['value' => 'maybe']));
    }

    public function testCastBallotThrowsOnYesValue(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Valeur de vote invalide');
        $this->service->castBallot($this->validData(['value' => 'yes']));
    }

    public function testCastBallotThrowsOnNoValue(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Valeur de vote invalide');
        $this->service->castBallot($this->validData(['value' => 'no']));
    }

    // =========================================================================
    // castBallot() -- motion not found
    // =========================================================================

    public function testCastBallotThrowsWhenMotionNotFound(): void {
        $this->motionRepo->method('findWithBallotContext')
            ->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Motion introuvable');
        $this->service->castBallot($this->validData());
    }

    // =========================================================================
    // castBallot() -- meeting not live
    // =========================================================================

    public function testCastBallotThrowsWhenMeetingIsDraft(): void {
        $context = $this->validBallotContext();
        $context['meeting_status'] = 'draft';

        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($context);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("n'est pas en cours");
        $this->service->castBallot($this->validData());
    }

    public function testCastBallotThrowsWhenMeetingIsClosed(): void {
        $context = $this->validBallotContext();
        $context['meeting_status'] = 'closed';

        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($context);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("n'est pas en cours");
        $this->service->castBallot($this->validData());
    }

    public function testCastBallotThrowsWhenMeetingIsArchived(): void {
        $context = $this->validBallotContext();
        $context['meeting_status'] = 'archived';

        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($context);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("n'est pas en cours");
        $this->service->castBallot($this->validData());
    }

    // =========================================================================
    // castBallot() -- meeting is validated
    // =========================================================================

    public function testCastBallotThrowsWhenMeetingIsValidated(): void {
        $context = $this->validBallotContext();
        $context['meeting_validated_at'] = '2026-01-01 12:00:00';

        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($context);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('validée');
        $this->service->castBallot($this->validData());
    }

    // =========================================================================
    // castBallot() -- motion is closed
    // =========================================================================

    public function testCastBallotThrowsWhenMotionIsClosed(): void {
        $context = $this->validBallotContext();
        $context['motion_closed_at'] = '2026-01-01 11:00:00';

        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($context);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("n'est pas ouverte au vote");
        $this->service->castBallot($this->validData());
    }

    public function testCastBallotThrowsWhenMotionNotOpened(): void {
        $context = $this->validBallotContext();
        $context['motion_opened_at'] = null;

        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($context);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("n'est pas ouverte au vote");
        $this->service->castBallot($this->validData());
    }

    // =========================================================================
    // castBallot() -- member validation
    // =========================================================================

    public function testCastBallotThrowsWhenMemberNotFound(): void {
        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($this->validBallotContext());

        $this->memberRepo->method('findByIdForTenant')
            ->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Membre inconnu');
        $this->service->castBallot($this->validData());
    }

    public function testCastBallotThrowsWhenMemberIsInactive(): void {
        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($this->validBallotContext());

        $member = $this->activeMember();
        $member['is_active'] = false;

        $this->memberRepo->method('findByIdForTenant')
            ->willReturn($member);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Membre inactif');
        $this->service->castBallot($this->validData());
    }

    // =========================================================================
    // castBallot() -- member not present (direct vote)
    // =========================================================================

    public function testCastBallotThrowsWhenMemberNotPresent(): void {
        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($this->validBallotContext());

        $this->memberRepo->method('findByIdForTenant')
            ->willReturn($this->activeMember());

        // Control via the underlying attendance repo mock
        $this->attendanceRepo->method('isPresentDirect')
            ->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non enregistré comme présent');
        $this->service->castBallot($this->validData());
    }

    // =========================================================================
    // castBallot() -- weight calculation
    // =========================================================================

    public function testCastBallotUsesVotingPowerAsWeight(): void {
        $this->setupSuccessfulDirectVote(3.5);

        $this->ballotRepo->expects($this->once())
            ->method('castBallot')
            ->with(
                self::TENANT,
                self::MEETING,
                self::MOTION,
                self::MEMBER,
                'for',
                3.5,     // weight should equal voting_power
                false,   // not a proxy vote
                null,    // no proxy source
            );

        $this->ballotRepo->method('findByMotionAndMember')
            ->willReturn([
                'motion_id' => self::MOTION,
                'member_id' => self::MEMBER,
                'value' => 'for',
                'weight' => 3.5,
            ]);

        $this->service->castBallot($this->validData());
    }

    public function testCastBallotRejectsNegativeWeight(): void {
        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($this->validBallotContext());

        $member = $this->activeMember();
        $member['voting_power'] = -5.0;

        $this->memberRepo->method('findByIdForTenant')
            ->willReturn($member);

        $this->attendanceRepo->method('isPresentDirect')
            ->willReturn(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Poids de vote invalide');

        $this->service->castBallot($this->validData());
    }

    public function testCastBallotDefaultsWeightToOneWhenMissing(): void {
        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($this->validBallotContext());

        $member = $this->activeMember();
        unset($member['voting_power']); // no voting_power key

        $this->memberRepo->method('findByIdForTenant')
            ->willReturn($member);

        $this->attendanceRepo->method('isPresentDirect')
            ->willReturn(true);

        $this->meetingRepo->method('lockForUpdate')
            ->willReturn(['id' => self::MEETING, 'status' => 'live']);

        $this->ballotRepo->expects($this->once())
            ->method('castBallot')
            ->with(
                self::TENANT,
                self::MEETING,
                self::MOTION,
                self::MEMBER,
                'for',
                1.0,     // default weight
                false,
                null,
            );

        $this->ballotRepo->method('findByMotionAndMember')
            ->willReturn([
                'motion_id' => self::MOTION,
                'member_id' => self::MEMBER,
                'value' => 'for',
                'weight' => 1.0,
            ]);

        $this->service->castBallot($this->validData());
    }

    // =========================================================================
    // castBallot() -- fallback when findByMotionAndMember returns null
    // =========================================================================

    public function testCastBallotReturnsFallbackWhenFindReturnsNull(): void {
        $this->setupSuccessfulDirectVote();

        $this->ballotRepo->method('findByMotionAndMember')
            ->willReturn(null);

        $result = $this->service->castBallot($this->validData());

        $this->assertSame(self::MOTION, $result['motion_id']);
        $this->assertSame(self::MEMBER, $result['member_id']);
        $this->assertSame('for', $result['value']);
        $this->assertSame(1.0, $result['weight']);
    }

    // =========================================================================
    // castBallot() -- lockForUpdate race condition guard
    // =========================================================================

    public function testCastBallotThrowsWhenLockForUpdateReturnsNull(): void {
        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($this->validBallotContext());

        $this->memberRepo->method('findByIdForTenant')
            ->willReturn($this->activeMember());

        $this->attendanceRepo->method('isPresentDirect')
            ->willReturn(true);

        $this->meetingRepo->method('lockForUpdate')
            ->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non disponible');
        $this->service->castBallot($this->validData());
    }

    public function testCastBallotThrowsWhenLockForUpdateReturnsDifferentStatus(): void {
        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($this->validBallotContext());

        $this->memberRepo->method('findByIdForTenant')
            ->willReturn($this->activeMember());

        $this->attendanceRepo->method('isPresentDirect')
            ->willReturn(true);

        // Meeting transitioned to 'closed' between the initial check and the lock
        $this->meetingRepo->method('lockForUpdate')
            ->willReturn(['id' => self::MEETING, 'status' => 'closed']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non disponible');
        $this->service->castBallot($this->validData());
    }

    // =========================================================================
    // castBallot() -- proxy vote scenarios
    // =========================================================================

    public function testCastBallotThrowsOnProxyVoteWithMissingProxySourceMemberId(): void {
        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($this->validBallotContext());

        $this->memberRepo->method('findByIdForTenant')
            ->willReturn($this->activeMember());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('proxy_source_member_id est obligatoire');
        $this->service->castBallot($this->validData([
            'is_proxy_vote' => true,
            'proxy_source_member_id' => '',
        ]));
    }

    public function testCastBallotThrowsOnProxyVoteWithInvalidUuid(): void {
        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($this->validBallotContext());

        $this->memberRepo->method('findByIdForTenant')
            ->willReturn($this->activeMember());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('proxy_source_member_id est obligatoire');
        $this->service->castBallot($this->validData([
            'is_proxy_vote' => true,
            'proxy_source_member_id' => 'not-a-valid-uuid',
        ]));
    }

    public function testCastBallotThrowsOnProxyVoteWhenProxyVoterNotFound(): void {
        $proxyVoterId = 'eeeeeeee-1111-4222-a333-444444444444';

        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($this->validBallotContext());

        // First call: the represented member (giver)
        // Second call: the proxy voter (receiver) returns null
        $this->memberRepo->method('findByIdForTenant')
            ->willReturnCallback(function (string $id) use ($proxyVoterId) {
                if ($id === self::MEMBER) {
                    return $this->activeMember();
                }
                if ($id === $proxyVoterId) {
                    return null;
                }
                return null;
            });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mandataire inconnu');
        $this->service->castBallot($this->validData([
            'is_proxy_vote' => true,
            'proxy_source_member_id' => $proxyVoterId,
        ]));
    }

    public function testCastBallotThrowsOnProxyVoteWhenProxyVoterInactive(): void {
        $proxyVoterId = 'eeeeeeee-1111-4222-a333-444444444444';

        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($this->validBallotContext());

        $this->memberRepo->method('findByIdForTenant')
            ->willReturnCallback(function (string $id) use ($proxyVoterId) {
                if ($id === self::MEMBER) {
                    return $this->activeMember();
                }
                if ($id === $proxyVoterId) {
                    return [
                        'id' => $proxyVoterId,
                        'voting_power' => 1.0,
                        'is_active' => false,
                    ];
                }
                return null;
            });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mandataire inactif');
        $this->service->castBallot($this->validData([
            'is_proxy_vote' => true,
            'proxy_source_member_id' => $proxyVoterId,
        ]));
    }

    public function testCastBallotThrowsOnProxyVoteWhenProxyVoterNotPresent(): void {
        $proxyVoterId = 'eeeeeeee-1111-4222-a333-444444444444';

        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($this->validBallotContext());

        $this->memberRepo->method('findByIdForTenant')
            ->willReturnCallback(function (string $id) use ($proxyVoterId) {
                if ($id === self::MEMBER) {
                    return $this->activeMember();
                }
                if ($id === $proxyVoterId) {
                    return [
                        'id' => $proxyVoterId,
                        'voting_power' => 1.0,
                        'is_active' => true,
                    ];
                }
                return null;
            });

        // isPresentDirect for the proxy voter returns false
        $this->attendanceRepo->method('isPresentDirect')
            ->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mandataire non enregistré');
        $this->service->castBallot($this->validData([
            'is_proxy_vote' => true,
            'proxy_source_member_id' => $proxyVoterId,
        ]));
    }

    public function testCastBallotThrowsOnProxyVoteWhenNoActiveProxy(): void {
        $proxyVoterId = 'eeeeeeee-1111-4222-a333-444444444444';

        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($this->validBallotContext());

        $this->memberRepo->method('findByIdForTenant')
            ->willReturnCallback(function (string $id) use ($proxyVoterId) {
                if ($id === self::MEMBER) {
                    return $this->activeMember();
                }
                if ($id === $proxyVoterId) {
                    return [
                        'id' => $proxyVoterId,
                        'voting_power' => 1.0,
                        'is_active' => true,
                    ];
                }
                return null;
            });

        $this->attendanceRepo->method('isPresentDirect')
            ->willReturn(true);

        // ProxiesService delegates to proxyRepo.hasActiveProxy
        $this->proxyRepo->method('hasActiveProxy')
            ->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Aucune procuration active');
        $this->service->castBallot($this->validData([
            'is_proxy_vote' => true,
            'proxy_source_member_id' => $proxyVoterId,
        ]));
    }

    // =========================================================================
    // castBallot() -- WebSocket broadcast failure does not break vote
    // =========================================================================

    public function testCastBallotSucceedsEvenWhenBroadcastFails(): void {
        $this->setupSuccessfulDirectVote();

        $this->ballotRepo->method('tally')
            ->willThrowException(new RuntimeException('WebSocket down'));

        $this->ballotRepo->method('findByMotionAndMember')
            ->willReturn([
                'motion_id' => self::MOTION,
                'member_id' => self::MEMBER,
                'value' => 'for',
                'weight' => 1.0,
            ]);

        // Should not throw despite broadcast failure
        $result = $this->service->castBallot($this->validData());
        $this->assertSame('for', $result['value']);
    }

    // =========================================================================
    // castBallot() -- whitespace trimming
    // =========================================================================

    public function testCastBallotTrimsWhitespaceFromInputs(): void {
        $this->setupSuccessfulDirectVote();

        $this->ballotRepo->method('findByMotionAndMember')
            ->willReturn([
                'motion_id' => self::MOTION,
                'member_id' => self::MEMBER,
                'value' => 'for',
                'weight' => 1.0,
            ]);

        // Data with leading/trailing spaces
        $result = $this->service->castBallot([
            'motion_id' => '  ' . self::MOTION . '  ',
            'member_id' => '  ' . self::MEMBER . '  ',
            'value' => '  for  ',
            '_tenant_id' => self::TENANT,
        ]);

        $this->assertSame('for', $result['value']);
    }
}
