<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard for Phase 11 orphan cleanup.
 * Prevents accidental reintroduction of orphan buttons and dead settings.
 */
final class HtmlOrphanCleanupTest extends TestCase
{
    private function readPublic(string $file): string
    {
        $path = __DIR__ . '/../../public/' . $file;
        return file_get_contents($path) ?: '';
    }

    public function testTrustHasNoOrphanExportButtons(): void
    {
        $html = $this->readPublic('trust.htmx.html');
        $this->assertStringNotContainsString('id="btnExportSelection"', $html);
        $this->assertStringNotContainsString('id="btnExportSelectedCsv"', $html);
        $this->assertStringNotContainsString('id="btnExportSelectedJson"', $html);
    }

    public function testMeetingsHasNoStartTourButton(): void
    {
        $html = $this->readPublic('meetings.htmx.html');
        $this->assertStringNotContainsString('id="btnStartTour"', $html);
    }

    public function testSettingsHasNoDeadFields(): void
    {
        $html = $this->readPublic('settings.htmx.html');
        $this->assertStringNotContainsString('id="settMaxLoginAttempts"', $html);
        $this->assertStringNotContainsString('id="settPasswordMinLength"', $html);
        $this->assertStringNotContainsString('id="settHighContrast"', $html);
    }

    public function testSettingsStillHasWorkingFields(): void
    {
        // Positive guard: we must NOT accidentally strip the working SMTP fields
        $html = $this->readPublic('settings.htmx.html');
        $this->assertStringContainsString('id="settSmtpHost"', $html);
        $this->assertStringContainsString('id="settVoteMode"', $html);
        $this->assertStringContainsString('id="settQuorumThreshold"', $html);
        $this->assertStringContainsString('id="settMajority"', $html);
    }
}
