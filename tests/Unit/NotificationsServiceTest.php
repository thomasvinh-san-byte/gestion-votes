<?php

declare(strict_types=1);

namespace AgVote\Tests\Unit;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\NotificationRepository;
use AgVote\Service\NotificationsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for NotificationsService.
 *
 * All repository dependencies are mocked; no database connection is needed.
 * Tests cover emit(), emitReadinessTransitions(), and pass-through delegations.
 */
class NotificationsServiceTest extends TestCase {
    private const TENANT  = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const MEETING = 'bbbbbbbb-1111-2222-3333-444444444444';

    // =========================================================================
    // Helper: build service with overridable mocked repos
    // =========================================================================

    /**
     * @param array<string,mixed> $overrides
     */
    private function buildService(array $overrides = []): NotificationsService {
        /** @var MeetingRepository&MockObject $meetingRepo */
        $meetingRepo = $overrides['meetingRepo'] ?? $this->createMock(MeetingRepository::class);
        /** @var NotificationRepository&MockObject $notifRepo */
        $notifRepo   = $overrides['notifRepo']   ?? $this->createMock(NotificationRepository::class);

        return new NotificationsService($meetingRepo, $notifRepo);
    }

    /**
     * Returns a simple valid meeting row.
     */
    private function validMeetingRow(): array {
        return [
            'id'        => self::MEETING,
            'tenant_id' => self::TENANT,
            'status'    => 'live',
        ];
    }

    /**
     * Returns a validation array representing a "can validate" state with no blockers.
     */
    private function readyValidation(array $codes = []): array {
        return ['can' => true, 'codes' => $codes, 'reasons' => []];
    }

    /**
     * Returns a validation array representing a "cannot validate" state.
     */
    private function notReadyValidation(array $codes = ['open_motions']): array {
        return ['can' => false, 'codes' => $codes, 'reasons' => ['...']];
    }

    // =========================================================================
    // emit() — meeting not found: silent return
    // =========================================================================

    public function testEmitMeetingNotFoundSilentlyReturns(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->expects($this->never())->method('insert');

        $service = $this->buildService(compact('meetingRepo', 'notifRepo'));
        $service->emit(self::MEETING, 'info', 'test_code', 'Test message', ['operator'], [], self::TENANT);
    }

    // =========================================================================
    // emit() — deduplication
    // =========================================================================

    public function testEmitDeduplicatesRecentMessages(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->method('countRecentDuplicates')->willReturn(1);
        $notifRepo->expects($this->never())->method('insert');

        $service = $this->buildService(compact('meetingRepo', 'notifRepo'));
        $service->emit(self::MEETING, 'info', 'test_code', 'Test message', ['operator'], [], self::TENANT);
    }

    public function testEmitInsertsWhenNoDuplicates(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->method('countRecentDuplicates')->willReturn(0);
        $notifRepo->expects($this->once())->method('insert')->with(
            self::TENANT,
            self::MEETING,
            'info',
            'test_code',
            'Test message',
            $this->anything(),
            $this->anything(),
        );

        $service = $this->buildService(compact('meetingRepo', 'notifRepo'));
        $service->emit(self::MEETING, 'info', 'test_code', 'Test message', ['operator'], [], self::TENANT);
    }

    // =========================================================================
    // emit() — audience normalization
    // =========================================================================

    public function testEmitNormalizesAudience(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->method('countRecentDuplicates')->willReturn(0);

        // Capture the audience literal passed to insert
        $capturedAudLiteral = null;
        $notifRepo->expects($this->once())->method('insert')
            ->willReturnCallback(function (
                string $tenantId,
                string $meetingId,
                string $severity,
                string $code,
                string $message,
                string $audLiteral,
                string $dataJson,
            ) use (&$capturedAudLiteral): void {
                $capturedAudLiteral = $audLiteral;
            });

        $service = $this->buildService(compact('meetingRepo', 'notifRepo'));
        // Duplicates and empty string — should dedupe to operator and trust only
        $service->emit(self::MEETING, 'info', 'c', 'msg', ['operator', '', 'operator', 'trust'], [], self::TENANT);

        // Audience literal must contain exactly operator and trust, no duplicates, no empties
        $this->assertStringContainsString('"operator"', (string) $capturedAudLiteral);
        $this->assertStringContainsString('"trust"', (string) $capturedAudLiteral);
        // No empty string entry
        $this->assertStringNotContainsString('""', (string) $capturedAudLiteral);
        // The literal format starts with { and ends with }
        $this->assertStringStartsWith('{', (string) $capturedAudLiteral);
        $this->assertStringEndsWith('}', (string) $capturedAudLiteral);
    }

    public function testEmitDefaultsAudienceWhenEmpty(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->method('countRecentDuplicates')->willReturn(0);

        $capturedAudLiteral = null;
        $notifRepo->expects($this->once())->method('insert')
            ->willReturnCallback(function (
                string $tenantId,
                string $meetingId,
                string $severity,
                string $code,
                string $message,
                string $audLiteral,
                string $dataJson,
            ) use (&$capturedAudLiteral): void {
                $capturedAudLiteral = $audLiteral;
            });

        $service = $this->buildService(compact('meetingRepo', 'notifRepo'));
        $service->emit(self::MEETING, 'info', 'c', 'msg', [], [], self::TENANT);

        // Should default to {"operator","trust"}
        $this->assertStringContainsString('"operator"', (string) $capturedAudLiteral);
        $this->assertStringContainsString('"trust"', (string) $capturedAudLiteral);
    }

    // =========================================================================
    // emitReadinessTransitions() — first pass (silent init)
    // =========================================================================

    public function testEmitReadinessFirstPassSilent(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $notifRepo = $this->createMock(NotificationRepository::class);
        // First pass: no previous state
        $notifRepo->method('findValidationState')->willReturn(null);
        // upsert must be called to store initial state
        $notifRepo->expects($this->once())->method('upsertValidationState');
        // insert must NOT be called (silent init)
        $notifRepo->expects($this->never())->method('insert');
        // countRecentDuplicates must NOT be called either
        $notifRepo->expects($this->never())->method('countRecentDuplicates');

        $service = $this->buildService(compact('meetingRepo', 'notifRepo'));
        $service->emitReadinessTransitions(self::MEETING, $this->readyValidation(), self::TENANT);
    }

    // =========================================================================
    // emitReadinessTransitions() — global transitions
    // =========================================================================

    public function testEmitReadinessNotReadyToReady(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->method('findValidationState')->willReturn([
            'ready' => false,
            'codes' => json_encode([]),
        ]);
        $notifRepo->method('countRecentDuplicates')->willReturn(0);

        // Capture insert call for the global readiness_ready notification
        $capturedCode = null;
        $capturedSeverity = null;
        $notifRepo->expects($this->once())->method('insert')
            ->willReturnCallback(function (
                string $tenantId,
                string $meetingId,
                string $severity,
                string $code,
            ) use (&$capturedCode, &$capturedSeverity): void {
                $capturedCode     = $code;
                $capturedSeverity = $severity;
            });

        $service = $this->buildService(compact('meetingRepo', 'notifRepo'));
        $service->emitReadinessTransitions(self::MEETING, $this->readyValidation(), self::TENANT);

        $this->assertSame('readiness_ready', $capturedCode);
        $this->assertSame('info', $capturedSeverity);
    }

    public function testEmitReadinessReadyToNotReady(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->method('findValidationState')->willReturn([
            'ready' => true,
            'codes' => json_encode([]),
        ]);
        $notifRepo->method('countRecentDuplicates')->willReturn(0);

        $capturedCodes = [];
        $notifRepo->method('insert')
            ->willReturnCallback(function (
                string $tenantId,
                string $meetingId,
                string $severity,
                string $code,
            ) use (&$capturedCodes): void {
                $capturedCodes[] = $code;
            });

        $service = $this->buildService(compact('meetingRepo', 'notifRepo'));
        $service->emitReadinessTransitions(self::MEETING, $this->notReadyValidation(['open_motions']), self::TENANT);

        $this->assertContains('readiness_not_ready', $capturedCodes);
    }

    public function testEmitReadinessNoTransitionNoEmit(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $notifRepo = $this->createMock(NotificationRepository::class);
        // Same state: ready=true, no codes
        $notifRepo->method('findValidationState')->willReturn([
            'ready' => true,
            'codes' => json_encode([]),
        ]);
        // No insert expected: stable state
        $notifRepo->expects($this->never())->method('insert');
        $notifRepo->expects($this->never())->method('countRecentDuplicates');

        $service = $this->buildService(compact('meetingRepo', 'notifRepo'));
        $service->emitReadinessTransitions(self::MEETING, $this->readyValidation([]), self::TENANT);
    }

    // =========================================================================
    // emitReadinessTransitions() — code diff additions / removals
    // =========================================================================

    public function testEmitReadinessCodeAdded(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $notifRepo = $this->createMock(NotificationRepository::class);
        // prev: not_ready with no codes, new: not_ready with open_motions added
        $notifRepo->method('findValidationState')->willReturn([
            'ready' => false,
            'codes' => json_encode([]),
        ]);
        $notifRepo->method('countRecentDuplicates')->willReturn(0);

        $capturedCodes = [];
        $notifRepo->method('insert')
            ->willReturnCallback(function (
                string $tenantId,
                string $meetingId,
                string $severity,
                string $code,
            ) use (&$capturedCodes): void {
                $capturedCodes[] = $code;
            });

        $service = $this->buildService(compact('meetingRepo', 'notifRepo'));
        $service->emitReadinessTransitions(self::MEETING, $this->notReadyValidation(['open_motions']), self::TENANT);

        $this->assertContains('readiness_open_motions', $capturedCodes);
    }

    public function testEmitReadinessCodeRemoved(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $notifRepo = $this->createMock(NotificationRepository::class);
        // prev: not_ready with open_motions; new: not_ready, open_motions removed, consolidation_missing added
        // (both still not_ready so no global transition fires — code diff is reached)
        $notifRepo->method('findValidationState')->willReturn([
            'ready' => false,
            'codes' => json_encode(['open_motions']),
        ]);
        $notifRepo->method('countRecentDuplicates')->willReturn(0);

        $capturedCodes = [];
        $notifRepo->method('insert')
            ->willReturnCallback(function (
                string $tenantId,
                string $meetingId,
                string $severity,
                string $code,
            ) use (&$capturedCodes): void {
                $capturedCodes[] = $code;
            });

        $service = $this->buildService(compact('meetingRepo', 'notifRepo'));
        // New: still not_ready but code changed from open_motions to consolidation_missing
        $newValidation = ['can' => false, 'codes' => ['consolidation_missing'], 'reasons' => []];
        $service->emitReadinessTransitions(self::MEETING, $newValidation, self::TENANT);

        // open_motions was removed → _resolved notification emitted
        $this->assertContains('readiness_open_motions_resolved', $capturedCodes);
        // consolidation_missing was added
        $this->assertContains('readiness_consolidation_missing', $capturedCodes);
    }

    public function testEmitReadinessMultipleCodeChanges(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $notifRepo = $this->createMock(NotificationRepository::class);
        // prev: not_ready with open_motions
        $notifRepo->method('findValidationState')->willReturn([
            'ready' => false,
            'codes' => json_encode(['open_motions']),
        ]);
        $notifRepo->method('countRecentDuplicates')->willReturn(0);

        $capturedCodes = [];
        $notifRepo->method('insert')
            ->willReturnCallback(function (
                string $tenantId,
                string $meetingId,
                string $severity,
                string $code,
            ) use (&$capturedCodes): void {
                $capturedCodes[] = $code;
            });

        $service = $this->buildService(compact('meetingRepo', 'notifRepo'));
        // New state: still not ready, open_motions resolved but 2 new blockers added
        $newValidation = ['can' => false, 'codes' => ['missing_president', 'bad_closed_results'], 'reasons' => []];
        $service->emitReadinessTransitions(self::MEETING, $newValidation, self::TENANT);

        // No global readiness_not_ready (prev was already false)
        // Code diff: open_motions removed, missing_president + bad_closed_results added = 3 inserts
        $this->assertNotContains('readiness_not_ready', $capturedCodes);
        $this->assertContains('readiness_open_motions_resolved', $capturedCodes);
        $this->assertContains('readiness_missing_president', $capturedCodes);
        $this->assertContains('readiness_bad_closed_results', $capturedCodes);
        $this->assertCount(3, $capturedCodes);
    }

    // =========================================================================
    // emitReadinessTransitions() — meeting not found
    // =========================================================================

    public function testEmitReadinessMeetingNotFoundSilentlyReturns(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->expects($this->never())->method('findValidationState');
        $notifRepo->expects($this->never())->method('upsertValidationState');
        $notifRepo->expects($this->never())->method('insert');

        $service = $this->buildService(compact('meetingRepo', 'notifRepo'));
        // Must not throw
        $service->emitReadinessTransitions(self::MEETING, $this->readyValidation(), self::TENANT);
    }

    // =========================================================================
    // readinessTemplate — indirect testing via emitReadinessTransitions
    // =========================================================================

    public function testEmitReadinessOpenMotionsTemplate(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->method('findValidationState')->willReturn([
            'ready' => false,
            'codes' => json_encode([]),
        ]);
        $notifRepo->method('countRecentDuplicates')->willReturn(0);

        $capturedSeverity = null;
        $capturedCode = null;
        $notifRepo->method('insert')
            ->willReturnCallback(function (
                string $tenantId,
                string $meetingId,
                string $severity,
                string $code,
            ) use (&$capturedSeverity, &$capturedCode): void {
                if ($code === 'readiness_open_motions') {
                    $capturedSeverity = $severity;
                    $capturedCode = $code;
                }
            });

        $service = $this->buildService(compact('meetingRepo', 'notifRepo'));
        $service->emitReadinessTransitions(self::MEETING, $this->notReadyValidation(['open_motions']), self::TENANT);

        $this->assertSame('readiness_open_motions', $capturedCode);
        $this->assertSame('blocking', $capturedSeverity);
    }

    public function testEmitReadinessMissingPresidentResolved(): void {
        // Scenario: prev not_ready with missing_president AND open_motions,
        // new not_ready with only open_motions remaining (missing_president resolved).
        // Both still not_ready so no global transition fires; code diff section runs.
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($this->validMeetingRow());

        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->method('findValidationState')->willReturn([
            'ready' => false,
            'codes' => json_encode(['missing_president', 'open_motions']),
        ]);
        $notifRepo->method('countRecentDuplicates')->willReturn(0);

        $capturedSeverity = null;
        $notifRepo->method('insert')
            ->willReturnCallback(function (
                string $tenantId,
                string $meetingId,
                string $severity,
                string $code,
            ) use (&$capturedSeverity): void {
                if ($code === 'readiness_missing_president_resolved') {
                    $capturedSeverity = $severity;
                }
            });

        $service = $this->buildService(compact('meetingRepo', 'notifRepo'));
        // missing_president resolved, open_motions still present
        $newValidation = ['can' => false, 'codes' => ['open_motions'], 'reasons' => []];
        $service->emitReadinessTransitions(self::MEETING, $newValidation, self::TENANT);

        // The resolved notification for missing_president uses severity='info'
        $this->assertSame('info', $capturedSeverity);
    }

    // =========================================================================
    // Pass-through delegation tests
    // =========================================================================

    public function testListDelegatesToRepo(): void {
        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->expects($this->once())
            ->method('listSinceId')
            ->with(self::MEETING, 5, 20, 'operator', self::TENANT)
            ->willReturn([['id' => 1]]);

        $service = $this->buildService(['notifRepo' => $notifRepo]);
        $result  = $service->list(self::MEETING, 'operator', 5, 20, self::TENANT);

        $this->assertCount(1, $result);
    }

    public function testRecentDelegatesToRepo(): void {
        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->expects($this->once())
            ->method('listRecent')
            ->with(self::MEETING, 50, 'trust', self::TENANT)
            ->willReturn([['id' => 2], ['id' => 3]]);

        $service = $this->buildService(['notifRepo' => $notifRepo]);
        $result  = $service->recent(self::MEETING, 'trust', 50, self::TENANT);

        $this->assertCount(2, $result);
    }

    public function testMarkReadDelegatesToRepo(): void {
        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->expects($this->once())
            ->method('markRead')
            ->with(self::MEETING, 42, self::TENANT);

        $service = $this->buildService(['notifRepo' => $notifRepo]);
        $service->markRead(self::MEETING, 42, self::TENANT);
    }

    public function testMarkAllReadDelegatesToRepo(): void {
        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->expects($this->once())
            ->method('markAllRead')
            ->with(self::MEETING, 'operator', self::TENANT);

        $service = $this->buildService(['notifRepo' => $notifRepo]);
        $service->markAllRead(self::MEETING, 'operator', self::TENANT);
    }

    public function testClearDelegatesToRepo(): void {
        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->expects($this->once())
            ->method('clear')
            ->with(self::MEETING, 'trust', self::TENANT);

        $service = $this->buildService(['notifRepo' => $notifRepo]);
        $service->clear(self::MEETING, 'trust', self::TENANT);
    }
}
