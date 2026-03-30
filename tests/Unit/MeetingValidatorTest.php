<?php

declare(strict_types=1);

namespace AgVote\Tests\Unit;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingStatsRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Service\MeetingValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MeetingValidator.
 *
 * All repository dependencies are mocked; no database connection is needed.
 * Tests cover the canBeValidated() method with all validation rules.
 */
class MeetingValidatorTest extends TestCase {
    private const TENANT  = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const MEETING = 'bbbbbbbb-1111-2222-3333-444444444444';

    // =========================================================================
    // Helper: build a MeetingValidator with mocked repos
    // =========================================================================

    /**
     * @param array<string,mixed> $overrides
     */
    private function buildValidator(array $overrides = []): MeetingValidator {
        /** @var MeetingRepository&MockObject $meetingRepo */
        $meetingRepo = $overrides['meetingRepo'] ?? $this->createMock(MeetingRepository::class);
        /** @var MotionRepository&MockObject $motionRepo */
        $motionRepo  = $overrides['motionRepo']  ?? $this->createMock(MotionRepository::class);
        /** @var MeetingStatsRepository&MockObject $statsRepo */
        $statsRepo   = $overrides['statsRepo']   ?? $this->createMock(MeetingStatsRepository::class);

        return new MeetingValidator($meetingRepo, $motionRepo, $statsRepo);
    }

    /**
     * Returns a full valid meeting row.
     */
    private function validMeetingRow(): array {
        return [
            'id'             => self::MEETING,
            'tenant_id'      => self::TENANT,
            'president_name' => 'Jean Dupont',
            'status'         => 'live',
        ];
    }

    /**
     * Builds repos mocked for the happy-path (all clear).
     *
     * @return array{meetingRepo: MeetingRepository&MockObject, motionRepo: MotionRepository&MockObject, statsRepo: MeetingStatsRepository&MockObject}
     */
    private function happyPathRepos(): array {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('countOpenMotions')->willReturn(0);
        $statsRepo->method('countClosedMotions')->willReturn(3);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('countBadClosedMotions')->willReturn(0);
        $motionRepo->method('countConsolidatedMotions')->willReturn(3);

        return compact('meetingRepo', 'motionRepo', 'statsRepo');
    }

    // =========================================================================
    // Meeting not found
    // =========================================================================

    public function testCanBeValidatedMeetingNotFound(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        $validator = $this->buildValidator(['meetingRepo' => $meetingRepo]);
        $result    = $validator->canBeValidated(self::MEETING, self::TENANT);

        $this->assertFalse($result['can']);
        $this->assertStringContainsString('introuvable', implode(' ', $result['reasons']));
        $this->assertEmpty($result['metrics']);
    }

    // =========================================================================
    // Happy path (all valid)
    // =========================================================================

    public function testCanBeValidatedAllValid(): void {
        $validator = $this->buildValidator($this->happyPathRepos());
        $result    = $validator->canBeValidated(self::MEETING, self::TENANT);

        $this->assertTrue($result['can']);
        $this->assertEmpty($result['reasons']);
        $this->assertEmpty($result['codes']);
    }

    // =========================================================================
    // Missing president
    // =========================================================================

    public function testCanBeValidatedMissingPresident(): void {
        $result = $this->canBeValidatedWithPresidentName('');
        $this->assertFalse($result['can']);
        $this->assertContains('missing_president', $result['codes']);
    }

    public function testCanBeValidatedMissingPresidentNull(): void {
        $result = $this->canBeValidatedWithPresidentName(null);
        $this->assertFalse($result['can']);
        $this->assertContains('missing_president', $result['codes']);
    }

    public function testCanBeValidatedMissingPresidentWhitespace(): void {
        $result = $this->canBeValidatedWithPresidentName('   ');
        $this->assertFalse($result['can']);
        $this->assertContains('missing_president', $result['codes']);
    }

    /**
     * Helper: run canBeValidated with only the president_name varied.
     * All other conditions are "clear" (no motions open, no bad closed, consolidation done).
     */
    private function canBeValidatedWithPresidentName(mixed $presidentName): array {
        $row = $this->validMeetingRow();
        $row['president_name'] = $presidentName;

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($row);

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('countOpenMotions')->willReturn(0);
        $statsRepo->method('countClosedMotions')->willReturn(3);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('countBadClosedMotions')->willReturn(0);
        $motionRepo->method('countConsolidatedMotions')->willReturn(3);

        $validator = $this->buildValidator(compact('meetingRepo', 'motionRepo', 'statsRepo'));
        return $validator->canBeValidated(self::MEETING, self::TENANT);
    }

    // =========================================================================
    // Open motions
    // =========================================================================

    public function testCanBeValidatedOpenMotions(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('countOpenMotions')->willReturn(2);
        $statsRepo->method('countClosedMotions')->willReturn(0);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('countBadClosedMotions')->willReturn(0);
        $motionRepo->method('countConsolidatedMotions')->willReturn(0);

        $validator = $this->buildValidator(compact('meetingRepo', 'motionRepo', 'statsRepo'));
        $result    = $validator->canBeValidated(self::MEETING, self::TENANT);

        $this->assertFalse($result['can']);
        $this->assertContains('open_motions', $result['codes']);
        $this->assertStringContainsString('2 motion(s)', implode(' ', $result['reasons']));
    }

    // =========================================================================
    // Bad closed motions
    // =========================================================================

    public function testCanBeValidatedBadClosedMotions(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('countOpenMotions')->willReturn(0);
        $statsRepo->method('countClosedMotions')->willReturn(2);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('countBadClosedMotions')->willReturn(1);
        $motionRepo->method('countConsolidatedMotions')->willReturn(2);

        $validator = $this->buildValidator(compact('meetingRepo', 'motionRepo', 'statsRepo'));
        $result    = $validator->canBeValidated(self::MEETING, self::TENANT);

        $this->assertFalse($result['can']);
        $this->assertContains('bad_closed_results', $result['codes']);
    }

    // =========================================================================
    // Consolidation missing
    // =========================================================================

    public function testCanBeValidatedConsolidationMissing(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('countOpenMotions')->willReturn(0);
        $statsRepo->method('countClosedMotions')->willReturn(3);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('countBadClosedMotions')->willReturn(0);
        $motionRepo->method('countConsolidatedMotions')->willReturn(1);

        $validator = $this->buildValidator(compact('meetingRepo', 'motionRepo', 'statsRepo'));
        $result    = $validator->canBeValidated(self::MEETING, self::TENANT);

        $this->assertFalse($result['can']);
        $this->assertContains('consolidation_missing', $result['codes']);
    }

    // =========================================================================
    // Consolidation not required when no closed motions
    // =========================================================================

    public function testCanBeValidatedNoClosedMotionsSkipsConsolidation(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('countOpenMotions')->willReturn(0);
        $statsRepo->method('countClosedMotions')->willReturn(0);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('countBadClosedMotions')->willReturn(0);
        $motionRepo->method('countConsolidatedMotions')->willReturn(0);

        $validator = $this->buildValidator(compact('meetingRepo', 'motionRepo', 'statsRepo'));
        $result    = $validator->canBeValidated(self::MEETING, self::TENANT);

        $this->assertTrue($result['can']);
        $this->assertTrue($result['metrics']['consolidation_done']);
        $this->assertNotContains('consolidation_missing', $result['codes']);
    }

    // =========================================================================
    // Multiple simultaneous blockers
    // =========================================================================

    public function testCanBeValidatedMultipleBlockers(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $row         = $this->validMeetingRow();
        $row['president_name'] = '';
        $meetingRepo->method('findByIdForTenant')->willReturn($row);

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('countOpenMotions')->willReturn(2);
        $statsRepo->method('countClosedMotions')->willReturn(0);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('countBadClosedMotions')->willReturn(1);
        $motionRepo->method('countConsolidatedMotions')->willReturn(0);

        $validator = $this->buildValidator(compact('meetingRepo', 'motionRepo', 'statsRepo'));
        $result    = $validator->canBeValidated(self::MEETING, self::TENANT);

        $this->assertFalse($result['can']);
        $this->assertCount(3, $result['codes']);
        $this->assertCount(3, $result['reasons']);
        $this->assertContains('missing_president', $result['codes']);
        $this->assertContains('open_motions', $result['codes']);
        $this->assertContains('bad_closed_results', $result['codes']);
    }

    // =========================================================================
    // Metrics structure
    // =========================================================================

    public function testCanBeValidatedMetricsStructure(): void {
        $validator = $this->buildValidator($this->happyPathRepos());
        $result    = $validator->canBeValidated(self::MEETING, self::TENANT);

        $metrics = $result['metrics'];
        $this->assertArrayHasKey('open_motions', $metrics);
        $this->assertArrayHasKey('bad_closed_motions', $metrics);
        $this->assertArrayHasKey('closed_motions', $metrics);
        $this->assertArrayHasKey('consolidated_motions', $metrics);
        $this->assertArrayHasKey('consolidation_done', $metrics);
    }
}
