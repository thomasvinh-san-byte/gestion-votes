<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for security hardening measures.
 * Verifies that known vulnerabilities have been fixed and stay fixed.
 */
class SecurityHardeningTest extends TestCase {
    public function testApiKeyNotAcceptedInQueryString(): void {
        // The get_api_key() function must NOT read from $_GET['api_key']
        // This test verifies the source code directly (static analysis).
        $authFile = PROJECT_ROOT . '/app/auth.php';
        $this->assertFileExists($authFile);

        $content = file_get_contents($authFile);

        $this->assertStringNotContainsString(
            "\$_GET['api_key']",
            $content,
            'SECURITY: API key must not be accepted from query string ($_GET). It leaks in access logs, Referer headers, and browser history.',
        );
    }

    public function testNoDeprecatedDbFunctions(): void {
        // Verify deprecated global DB functions have been removed from bootstrap.php
        $bootstrapFile = PROJECT_ROOT . '/app/bootstrap.php';
        $content = file_get_contents($bootstrapFile);

        $deprecated = ['db_select_one', 'db_one', 'db_select_all', 'db_all', 'db_execute', 'db_exec', 'db_scalar'];
        foreach ($deprecated as $fn) {
            $this->assertStringNotContainsString(
                "function {$fn}(",
                $content,
                "Deprecated function {$fn}() must be removed from bootstrap.php",
            );
        }
    }

    public function testAuditLogDelegatesToRepository(): void {
        // Verify audit_log() no longer contains raw SQL
        $bootstrapFile = PROJECT_ROOT . '/app/bootstrap.php';
        $content = file_get_contents($bootstrapFile);

        // Extract the audit_log function body
        $this->assertStringContainsString(
            'AuditEventRepository',
            $content,
            'audit_log() must delegate to AuditEventRepository',
        );
        $this->assertStringNotContainsString(
            'INSERT INTO audit_events',
            $content,
            'audit_log() must not contain raw SQL',
        );
    }

    public function testApiGuardsUseRepository(): void {
        // Verify api_guard_meeting_* functions use MeetingRepository instead of raw SQL
        $apiFile = PROJECT_ROOT . '/app/api.php';
        $content = file_get_contents($apiFile);

        $this->assertStringContainsString(
            'MeetingRepository',
            $content,
            'api_guard functions must use MeetingRepository',
        );

        // Count raw SQL statements — only api_request() merger is acceptable (no SELECT/INSERT)
        $this->assertStringNotContainsString(
            'SELECT validated_at FROM meetings',
            $content,
            'api_guard_meeting_not_validated must not contain raw SQL',
        );
        $this->assertStringNotContainsString(
            'SELECT * FROM meetings WHERE',
            $content,
            'api_guard_meeting_exists must not contain raw SQL',
        );
    }

    public function testParsedownSafeModeUsed(): void {
        $docController = PROJECT_ROOT . '/app/Controller/DocController.php';
        $this->assertFileExists($docController);

        $content = file_get_contents($docController);
        $this->assertStringContainsString(
            'setSafeMode(true)',
            $content,
            'Parsedown must be used with setSafeMode(true) to prevent XSS',
        );
    }
}
