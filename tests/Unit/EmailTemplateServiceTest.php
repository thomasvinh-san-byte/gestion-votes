<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Repository\EmailTemplateRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingStatsRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Service\EmailTemplateService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EmailTemplateService.
 *
 * Uses createMock() for all four repository dependencies injected via constructor.
 * No database connection required.
 */
class EmailTemplateServiceTest extends TestCase
{
    private EmailTemplateService $service;
    private $templateRepo;
    private $meetingRepo;
    private $memberRepo;
    private $statsRepo;

    protected function setUp(): void
    {
        $this->templateRepo = $this->createMock(EmailTemplateRepository::class);
        $this->meetingRepo  = $this->createMock(MeetingRepository::class);
        $this->memberRepo   = $this->createMock(MemberRepository::class);
        $this->statsRepo    = $this->createMock(MeetingStatsRepository::class);

        $this->service = new EmailTemplateService(
            ['app' => ['url' => 'https://votes.test']],
            $this->templateRepo,
            $this->meetingRepo,
            $this->memberRepo,
            $this->statsRepo,
        );
    }

    // =========================================================================
    // render() tests
    // =========================================================================

    public function testRenderSubstitutesVariables(): void
    {
        $result = $this->service->render('Hello {{member_name}}', ['{{member_name}}' => 'Jean']);
        $this->assertSame('Hello Jean', $result);
    }

    public function testRenderHandlesMultipleVariables(): void
    {
        $template = '{{meeting_title}} le {{meeting_date}} a {{meeting_time}}';
        $vars = [
            '{{meeting_title}}' => 'AG 2024',
            '{{meeting_date}}'  => '15 janvier 2024',
            '{{meeting_time}}'  => '14h30',
        ];
        $result = $this->service->render($template, $vars);
        $this->assertSame('AG 2024 le 15 janvier 2024 a 14h30', $result);
    }

    public function testRenderLeavesUnknownVariablesUntouched(): void
    {
        $result = $this->service->render('Hello {{unknown_var}}', []);
        $this->assertSame('Hello {{unknown_var}}', $result);
    }

    public function testRenderEmptyTemplate(): void
    {
        $result = $this->service->render('', ['{{member_name}}' => 'Jean']);
        $this->assertSame('', $result);
    }

    // =========================================================================
    // validate() tests
    // =========================================================================

    public function testValidateValidTemplate(): void
    {
        $result = $this->service->validate('Bonjour {{member_name}}, votre reunion est {{meeting_title}}.');
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['unknown_variables']);
    }

    public function testValidateDetectsUnknownVariables(): void
    {
        $result = $this->service->validate('Bonjour {{fake_var}} et {{member_name}}.');
        $this->assertFalse($result['valid']);
        $this->assertContains('{{fake_var}}', $result['unknown_variables']);
    }

    public function testValidateReturnsUsedVariables(): void
    {
        $result = $this->service->validate('{{member_name}} pour {{meeting_title}}.');
        $this->assertContains('{{member_name}}', $result['used_variables']);
        $this->assertContains('{{meeting_title}}', $result['used_variables']);
    }

    // =========================================================================
    // preview() tests
    // =========================================================================

    public function testPreviewUsesSampleData(): void
    {
        $result = $this->service->preview(EmailTemplateService::DEFAULT_INVITATION_TEMPLATE);
        $this->assertStringContainsString('Jean Dupont', $result);
    }

    public function testPreviewUsesCustomVariablesWhenProvided(): void
    {
        $template = 'Bonjour {{member_name}}';
        $result = $this->service->preview($template, ['{{member_name}}' => 'Custom']);
        $this->assertStringContainsString('Custom', $result);
    }

    // =========================================================================
    // getVariables() tests
    // =========================================================================

    public function testGetVariablesReturnsAllExpectedKeys(): void
    {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn(['title' => 'AG 2024', 'scheduled_at' => '2024-01-15 14:30:00', 'location' => 'Salle A', 'status' => 'scheduled']);
        $this->memberRepo->method('findByIdForTenant')
            ->willReturn(['full_name' => 'Jean Dupont', 'email' => 'jean@example.com', 'voting_power' => '150']);
        $this->statsRepo->method('countMotions')
            ->willReturn(5);

        $vars = $this->service->getVariables('tenant-1', 'meeting-1', 'member-1', 'tok123');

        $expectedKeys = array_keys(EmailTemplateService::AVAILABLE_VARIABLES);
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $vars, "Missing variable key: {$key}");
        }
    }

    public function testGetVariablesBuildVoteUrl(): void
    {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn(['title' => 'AG 2024', 'scheduled_at' => '2024-01-15 14:30:00', 'location' => 'Salle A', 'status' => 'scheduled']);
        $this->memberRepo->method('findByIdForTenant')
            ->willReturn(['full_name' => 'Jean Dupont', 'email' => 'jean@example.com', 'voting_power' => '150']);
        $this->statsRepo->method('countMotions')->willReturn(0);

        $vars = $this->service->getVariables('tenant-1', 'meeting-1', 'member-1', 'mytoken');

        $this->assertStringStartsWith(
            'https://votes.test/vote.htmx.html?token=',
            $vars['{{vote_url}}'],
        );
    }

    public function testGetVariablesExtractsFirstName(): void
    {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn(['title' => 'AG 2024', 'scheduled_at' => '2024-01-15 14:30:00', 'location' => 'Salle A', 'status' => 'scheduled']);
        $this->memberRepo->method('findByIdForTenant')
            ->willReturn(['full_name' => 'Jean Pierre Dupont', 'email' => 'jp@example.com', 'voting_power' => '1']);
        $this->statsRepo->method('countMotions')->willReturn(0);

        $vars = $this->service->getVariables('tenant-1', 'meeting-1', 'member-1', 'tok');
        $this->assertSame('Jean', $vars['{{member_first_name}}']);
    }

    // =========================================================================
    // renderTemplate() tests
    // =========================================================================

    public function testRenderTemplateNotFound(): void
    {
        $this->templateRepo->method('findById')->willReturn(null);

        $result = $this->service->renderTemplate('tenant-1', 'tpl-999', 'meeting-1', 'member-1', 'tok');
        $this->assertFalse($result['ok']);
        $this->assertSame('template_not_found', $result['error']);
    }

    public function testRenderTemplateSuccess(): void
    {
        $this->templateRepo->method('findById')->willReturn([
            'subject'   => 'Vote pour {{meeting_title}}',
            'body_html' => '<p>Bonjour {{member_name}}</p>',
            'body_text' => null,
        ]);
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn(['title' => 'AG 2024', 'scheduled_at' => '2024-01-15 14:30:00', 'location' => 'Salle A', 'status' => 'scheduled']);
        $this->memberRepo->method('findByIdForTenant')
            ->willReturn(['full_name' => 'Jean Dupont', 'email' => 'jean@example.com', 'voting_power' => '1']);
        $this->statsRepo->method('countMotions')->willReturn(0);

        $result = $this->service->renderTemplate('tenant-1', 'tpl-1', 'meeting-1', 'member-1', 'tok');

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('AG 2024', $result['subject']);
        $this->assertStringContainsString('Jean Dupont', $result['body_html']);
    }

    // =========================================================================
    // listAvailableVariables() tests
    // =========================================================================

    public function testListAvailableVariablesReturnsConstant(): void
    {
        $result = $this->service->listAvailableVariables();
        $this->assertSame(EmailTemplateService::AVAILABLE_VARIABLES, $result);
    }

    // =========================================================================
    // createDefaultTemplates() tests
    // =========================================================================

    public function testCreateDefaultTemplatesCallsRepoTwice(): void
    {
        $fakeTemplate = ['id' => 'tpl-uuid', 'name' => 'default'];

        $this->templateRepo
            ->expects($this->exactly(2))
            ->method('create')
            ->willReturn($fakeTemplate);

        $result = $this->service->createDefaultTemplates('tenant-1');
        $this->assertCount(2, $result);
    }
}
