<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Repository\MeetingRepository;
use AgVote\Service\MeetingLifecycleService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MeetingLifecycleService.
 *
 * Tests structural contract and basic behavioral assertions.
 */
class MeetingLifecycleServiceTest extends TestCase {
    private const TENANT = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const MEETING = 'bbbbbbbb-1111-2222-3333-444444444444';

    private MeetingRepository&MockObject $meetingRepo;
    private RepositoryFactory $factory;
    private MeetingLifecycleService $service;

    protected function setUp(): void {
        $this->factory = new RepositoryFactory(null);
        $ref = new \ReflectionClass(RepositoryFactory::class);
        $cacheProp = $ref->getProperty('cache');
        $cacheProp->setAccessible(true);

        $this->meetingRepo = $this->createMock(MeetingRepository::class);
        $cacheProp->setValue($this->factory, [
            MeetingRepository::class => $this->meetingRepo,
        ]);

        $this->service = new MeetingLifecycleService($this->factory);
    }

    public function testServiceIsFinal(): void {
        $ref = new \ReflectionClass(MeetingLifecycleService::class);
        $this->assertTrue($ref->isFinal(), 'MeetingLifecycleService should be final');
    }

    public function testConstructorAcceptsNullableRepoFactory(): void {
        $ref = new \ReflectionClass(MeetingLifecycleService::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);

        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        $this->assertTrue($params[0]->allowsNull(), 'Constructor param should be nullable');

        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertStringContainsString('RepositoryFactory', (string) $type);
    }

    public function testUpdateMeetingThrowsOnStatusField(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('transitions de statut');

        $this->service->updateMeeting(self::MEETING, self::TENANT, ['status' => 'live']);
    }

    public function testUpdateMeetingThrowsOnEmptyTitle(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('titre');

        $this->service->updateMeeting(self::MEETING, self::TENANT, ['title' => '']);
    }

    public function testUpdateMeetingThrowsOnTitleTooLong(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('120');

        $this->service->updateMeeting(self::MEETING, self::TENANT, ['title' => str_repeat('a', 121)]);
    }

    public function testUpdateMeetingReturnsNotUpdatedWhenNoFields(): void {
        $this->meetingRepo->method('findByIdForTenant')->willReturn([
            'meeting_id' => self::MEETING,
            'status' => 'draft',
            'title' => 'Test',
        ]);

        $result = $this->service->updateMeeting(self::MEETING, self::TENANT, []);
        $this->assertFalse($result['updated']);
        $this->assertSame(self::MEETING, $result['meeting_id']);
    }

    public function testDeleteDraftThrowsOnNotFound(): void {
        $this->meetingRepo->method('findByIdForTenant')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('meeting_not_found');

        $this->service->deleteDraft(self::MEETING, self::TENANT);
    }

    public function testCreateFromWizardThrowsOnShortTitle(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('3 caractères');

        $this->service->createFromWizard(['title' => 'ab'], self::TENANT);
    }
}
