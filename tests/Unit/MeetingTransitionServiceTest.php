<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Repository\MeetingRepository;
use AgVote\Service\MeetingTransitionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MeetingTransitionService.
 *
 * Tests structural contract and basic behavioral assertions.
 */
class MeetingTransitionServiceTest extends TestCase {
    private const TENANT = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const MEETING = 'bbbbbbbb-1111-2222-3333-444444444444';
    private const USER = 'cccccccc-1111-2222-3333-444444444444';

    private MeetingRepository&MockObject $meetingRepo;
    private RepositoryFactory $factory;
    private MeetingTransitionService $service;

    protected function setUp(): void {
        $this->factory = new RepositoryFactory(null);
        $ref = new \ReflectionClass(RepositoryFactory::class);
        $cacheProp = $ref->getProperty('cache');
        $cacheProp->setAccessible(true);

        $this->meetingRepo = $this->createMock(MeetingRepository::class);
        $cacheProp->setValue($this->factory, [
            MeetingRepository::class => $this->meetingRepo,
        ]);

        $this->service = new MeetingTransitionService($this->factory);
    }

    public function testServiceIsFinal(): void {
        $ref = new \ReflectionClass(MeetingTransitionService::class);
        $this->assertTrue($ref->isFinal(), 'MeetingTransitionService should be final');
    }

    public function testConstructorAcceptsNullableRepoFactory(): void {
        $ref = new \ReflectionClass(MeetingTransitionService::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);

        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        $this->assertTrue($params[0]->allowsNull(), 'Constructor param should be nullable');

        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertStringContainsString('RepositoryFactory', (string) $type);
    }

    public function testTransitionThrowsOnEmptyStatus(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('to_status');

        $this->service->transition(self::MEETING, self::TENANT, '', self::USER);
    }

    public function testTransitionThrowsOnInvalidStatus(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalide');

        $this->service->transition(self::MEETING, self::TENANT, 'bogus', self::USER);
    }

    public function testTransitionThrowsOnMeetingNotFound(): void {
        $this->meetingRepo->method('findByIdForTenant')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('meeting_not_found');

        $this->service->transition(self::MEETING, self::TENANT, 'live', self::USER);
    }

    public function testTransitionThrowsOnSameStatus(): void {
        $this->meetingRepo->method('findByIdForTenant')->willReturn([
            'meeting_id' => self::MEETING,
            'status' => 'live',
            'title' => 'Test',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('déjà au statut');

        $this->service->transition(self::MEETING, self::TENANT, 'live', self::USER);
    }

    public function testLaunchThrowsOnMeetingNotFound(): void {
        $this->meetingRepo->method('findByIdForTenant')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('meeting_not_found');

        $this->service->launch(self::MEETING, self::TENANT, self::USER);
    }

    public function testLaunchThrowsOnAlreadyLive(): void {
        $this->meetingRepo->method('findByIdForTenant')->willReturn([
            'meeting_id' => self::MEETING,
            'status' => 'live',
            'title' => 'Test',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('déjà en cours');

        $this->service->launch(self::MEETING, self::TENANT, self::USER);
    }

    public function testReadyCheckThrowsOnMeetingNotFound(): void {
        // readyCheck accesses multiple repos, so we need to test via the
        // meeting repo returning null which happens before other repos are used
        $this->meetingRepo->method('findByIdForTenant')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('meeting_not_found');

        $this->service->readyCheck(self::MEETING, self::TENANT);
    }
}
