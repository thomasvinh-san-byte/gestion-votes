<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Service\ProcurationPdfService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ProcurationPdfService.
 *
 * Tests HTML template rendering and PDF generation.
 * No database required — all fixture data passed directly.
 */
class ProcurationPdfServiceTest extends TestCase
{
    private ProcurationPdfService $service;

    private array $proxy = [
        'id' => 'abc-123',
        'giver_name' => 'Jean Dupont',
        'receiver_name' => 'Marie Martin',
    ];

    private array $meeting = [
        'title' => 'AG Annuelle 2026',
        'scheduled_at' => '2026-06-15 10:00:00',
    ];

    private string $orgName = 'Association Test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProcurationPdfService();
    }

    // =========================================================================
    // renderHtml: CONTENT TESTS
    // =========================================================================

    public function testRenderHtmlReturnsNonEmptyString(): void
    {
        $html = $this->service->renderHtml($this->proxy, $this->meeting, $this->orgName);
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    public function testRenderHtmlContainsGiverName(): void
    {
        $html = $this->service->renderHtml($this->proxy, $this->meeting, $this->orgName);
        $this->assertStringContainsString('Jean Dupont', $html);
    }

    public function testRenderHtmlContainsReceiverName(): void
    {
        $html = $this->service->renderHtml($this->proxy, $this->meeting, $this->orgName);
        $this->assertStringContainsString('Marie Martin', $html);
    }

    public function testRenderHtmlContainsMeetingTitle(): void
    {
        $html = $this->service->renderHtml($this->proxy, $this->meeting, $this->orgName);
        $this->assertStringContainsString('AG Annuelle 2026', $html);
    }

    public function testRenderHtmlContainsPouvoirOrProcuration(): void
    {
        $html = $this->service->renderHtml($this->proxy, $this->meeting, $this->orgName);
        $hasPouvoir = stripos($html, 'POUVOIR') !== false;
        $hasProcuration = stripos($html, 'Procuration') !== false;
        $this->assertTrue($hasPouvoir || $hasProcuration, 'HTML should contain "POUVOIR" or "Procuration"');
    }

    public function testRenderHtmlContainsLegalMention(): void
    {
        $html = $this->service->renderHtml($this->proxy, $this->meeting, $this->orgName);
        $hasLegalMention = stripos($html, 'mention legale') !== false
            || stripos($html, 'Fait le') !== false
            || stripos($html, 'conforme') !== false
            || stripos($html, 'loi') !== false;
        $this->assertTrue($hasLegalMention, 'HTML should contain a legal mention (mention legale, Fait le, conforme, or loi)');
    }

    // =========================================================================
    // generatePdf: BINARY OUTPUT TEST
    // =========================================================================

    public function testGeneratePdfReturnsNonEmptyBinary(): void
    {
        $pdf = $this->service->generatePdf($this->proxy, $this->meeting, $this->orgName);
        $this->assertIsString($pdf);
        $this->assertGreaterThan(100, strlen($pdf));
    }
}
