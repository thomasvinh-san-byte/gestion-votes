<?php

declare(strict_types=1);

use AgVote\Core\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour Logger
 */
class LoggerTest extends TestCase {
    private string $tempLogFile;

    protected function setUp(): void {
        $this->tempLogFile = sys_get_temp_dir() . '/ag-vote-test-' . uniqid() . '.log';
        Logger::reset();
        Logger::configure(['file' => $this->tempLogFile, 'level' => 'debug']);
    }

    protected function tearDown(): void {
        Logger::reset();
        if (file_exists($this->tempLogFile)) {
            @unlink($this->tempLogFile);
        }
    }

    // =========================================================================
    // BASIC LOGGING TESTS
    // =========================================================================

    public function testDebugLogWritesEntry(): void {
        Logger::debug('Test debug message');

        $this->assertFileExists($this->tempLogFile);
        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('Test debug message', $content);
        $this->assertStringContainsString('"level":"DEBUG"', $content);
    }

    public function testInfoLogWritesEntry(): void {
        Logger::info('Test info message');

        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('Test info message', $content);
        $this->assertStringContainsString('"level":"INFO"', $content);
    }

    public function testWarningLogWritesEntry(): void {
        Logger::warning('Test warning message');

        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('Test warning message', $content);
        $this->assertStringContainsString('"level":"WARNING"', $content);
    }

    public function testErrorLogWritesEntry(): void {
        Logger::error('Test error message');

        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('Test error message', $content);
        $this->assertStringContainsString('"level":"ERROR"', $content);
    }

    public function testCriticalLogWritesEntry(): void {
        Logger::critical('Test critical message');

        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('Test critical message', $content);
        $this->assertStringContainsString('"level":"CRITICAL"', $content);
    }

    // =========================================================================
    // LOG LEVEL FILTERING TESTS
    // =========================================================================

    public function testMinLevelFiltersDebug(): void {
        Logger::reset();
        Logger::configure(['file' => $this->tempLogFile, 'level' => 'info']);

        Logger::debug('Should be filtered');
        Logger::info('Should appear');

        $content = file_get_contents($this->tempLogFile);
        $this->assertStringNotContainsString('Should be filtered', $content);
        $this->assertStringContainsString('Should appear', $content);
    }

    public function testMinLevelFiltersInfo(): void {
        Logger::reset();
        Logger::configure(['file' => $this->tempLogFile, 'level' => 'warning']);

        Logger::info('Should be filtered');
        Logger::warning('Should appear');

        $content = file_get_contents($this->tempLogFile);
        $this->assertStringNotContainsString('Should be filtered', $content);
        $this->assertStringContainsString('Should appear', $content);
    }

    public function testErrorLevelStillLogsError(): void {
        Logger::reset();
        Logger::configure(['file' => $this->tempLogFile, 'level' => 'error']);

        Logger::warning('Should be filtered');
        Logger::error('Should appear');

        $content = file_get_contents($this->tempLogFile);
        $this->assertStringNotContainsString('Should be filtered', $content);
        $this->assertStringContainsString('Should appear', $content);
    }

    // =========================================================================
    // CONTEXT TESTS
    // =========================================================================

    public function testContextIsIncluded(): void {
        Logger::info('Test with context', [
            'user_id' => '123',
            'action' => 'login',
        ]);

        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('"context"', $content);
        $this->assertStringContainsString('"user_id":"123"', $content);
        $this->assertStringContainsString('"action":"login"', $content);
    }

    public function testEmptyContextNotIncluded(): void {
        Logger::info('Test without context');

        $content = file_get_contents($this->tempLogFile);
        // Empty context should not add the context key
        $lines = array_filter(explode("\n", trim($content)));
        $entry = json_decode(end($lines), true);

        // Context key should not exist when empty
        $this->assertArrayNotHasKey('context', $entry);
    }

    // =========================================================================
    // JSON FORMAT TESTS
    // =========================================================================

    public function testLogEntryIsValidJson(): void {
        Logger::info('Test JSON format');

        $content = file_get_contents($this->tempLogFile);
        $lines = array_filter(explode("\n", trim($content)));

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            $this->assertNotNull($decoded, "Invalid JSON: {$line}");
        }
    }

    public function testLogEntryContainsRequiredFields(): void {
        Logger::info('Test required fields');

        $content = file_get_contents($this->tempLogFile);
        $lines = array_filter(explode("\n", trim($content)));
        $entry = json_decode(end($lines), true);

        $this->assertArrayHasKey('timestamp', $entry);
        $this->assertArrayHasKey('level', $entry);
        $this->assertArrayHasKey('message', $entry);
        $this->assertArrayHasKey('request_id', $entry);
    }

    public function testTimestampIsIso8601(): void {
        Logger::info('Test timestamp');

        $content = file_get_contents($this->tempLogFile);
        $lines = array_filter(explode("\n", trim($content)));
        $entry = json_decode(end($lines), true);

        $timestamp = $entry['timestamp'];
        // Try parsing with DateTimeImmutable constructor which accepts ISO 8601
        $parsed = new \DateTimeImmutable($timestamp);
        $this->assertInstanceOf(\DateTimeImmutable::class, $parsed);
        // Verify format matches ISO 8601 pattern
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $timestamp);
    }

    // =========================================================================
    // SPECIAL LOG METHODS TESTS
    // =========================================================================

    public function testApiLogMethod(): void {
        Logger::api('GET', '/api/v1/meetings', 200, 0.150, ['user' => 'test']);

        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('GET /api/v1/meetings 200', $content);
        $this->assertStringContainsString('"method":"GET"', $content);
        $this->assertStringContainsString('"status_code":200', $content);
        $this->assertStringContainsString('"duration_ms":', $content);
    }

    public function testApiLogLevelBasedOnStatus(): void {
        // 2xx -> info
        Logger::api('GET', '/test', 200, 0.1);
        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('"level":"INFO"', $content);

        // Clear file
        file_put_contents($this->tempLogFile, '');

        // 4xx -> warning
        Logger::api('POST', '/test', 404, 0.1);
        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('"level":"WARNING"', $content);

        // Clear file
        file_put_contents($this->tempLogFile, '');

        // 5xx -> error
        Logger::api('PUT', '/test', 500, 0.1);
        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('"level":"ERROR"', $content);
    }

    public function testAuthLogMethod(): void {
        Logger::auth('login', true, ['method' => 'password']);

        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('Auth success: login', $content);
        $this->assertStringContainsString('"auth_event":"login"', $content);
        $this->assertStringContainsString('"auth_success":true', $content);
    }

    public function testAuthLogFailure(): void {
        Logger::auth('login', false, ['reason' => 'bad_password']);

        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('Auth failure: login', $content);
        $this->assertStringContainsString('"level":"WARNING"', $content);
        $this->assertStringContainsString('"auth_success":false', $content);
    }

    public function testSecurityLogMethod(): void {
        Logger::security('rate_limit_exceeded', ['ip' => '1.2.3.4']);

        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('Security: rate_limit_exceeded', $content);
        $this->assertStringContainsString('"level":"WARNING"', $content);
        $this->assertStringContainsString('"security_event":"rate_limit_exceeded"', $content);
    }

    public function testExceptionLogMethod(): void {
        try {
            throw new \RuntimeException('Test exception', 42);
        } catch (\Throwable $e) {
            Logger::exception($e, ['extra' => 'context']);
        }

        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('Test exception', $content);
        $this->assertStringContainsString('"level":"ERROR"', $content);
        $this->assertStringContainsString('"class":"RuntimeException"', $content);
        $this->assertStringContainsString('"code":42', $content);
    }

    // =========================================================================
    // REQUEST ID TESTS
    // =========================================================================

    public function testRequestIdIsConsistent(): void {
        Logger::info('First message');
        Logger::info('Second message');

        $content = file_get_contents($this->tempLogFile);
        $lines = array_filter(explode("\n", trim($content)));

        $requestIds = [];
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            $requestIds[] = $entry['request_id'];
        }

        // All entries in the same request should have the same ID
        $this->assertCount(2, $requestIds);
        $this->assertEquals($requestIds[0], $requestIds[1]);
    }

    // =========================================================================
    // RESET TESTS
    // =========================================================================

    public function testResetClearsConfiguration(): void {
        Logger::configure(['level' => 'error']);
        Logger::reset();

        // After reset, should use default level (debug)
        Logger::configure(['file' => $this->tempLogFile]);
        Logger::debug('Should appear after reset');

        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('Should appear after reset', $content);
    }

    // =========================================================================
    // ERROR CONTEXT TESTS — Plan 02.3 / ERR-V24-03 / D-09
    // =========================================================================

    /**
     * Le helper errorContext() doit auto-remplir request_id (toujours dispo via
     * Logger::getRequestId()) en plus du contexte fourni par l'appelant.
     */
    public function testErrorContextAutoFillsRequestId(): void {
        Logger::errorContext('Vote already cast', [
            'error_code' => 'vote_already_cast',
            'caller'     => __METHOD__,
        ]);

        $content = file_get_contents($this->tempLogFile);
        $lines = array_filter(explode("\n", trim($content)));
        $this->assertNotEmpty($lines, 'errorContext should write a log entry');
        $entry = json_decode(end($lines), true);

        $this->assertSame('ERROR', $entry['level']);
        $this->assertSame('Vote already cast', $entry['message']);
        $this->assertArrayHasKey('context', $entry);

        $ctx = $entry['context'];
        // Auto-filled keys.
        $this->assertArrayHasKey('request_id', $ctx);
        $this->assertNotEmpty($ctx['request_id']);
        // Caller-provided keys preserved.
        $this->assertSame('vote_already_cast', $ctx['error_code']);
        $this->assertStringContainsString('testErrorContextAutoFillsRequestId', $ctx['caller']);
    }

    /**
     * Le contexte fourni par l'appelant doit avoir la priorite sur l'auto-fill.
     * Si l'appelant fournit explicitement un user_id ou un request_id, il
     * doit gagner sur la valeur auto-detectee.
     */
    public function testErrorContextMergesWithCallerContextAndCallerWins(): void {
        Logger::errorContext('Custom error', [
            'error_code' => 'custom_code',
            'caller'     => 'Some::method',
            // Caller fournit un request_id explicite — doit gagner.
            'request_id' => 'custom-req-id-xyz',
            // Champ libre additionnel — doit etre preserve.
            'extra_key'  => 'extra_value',
        ]);

        $content = file_get_contents($this->tempLogFile);
        $lines = array_filter(explode("\n", trim($content)));
        $entry = json_decode(end($lines), true);

        $ctx = $entry['context'];
        $this->assertSame('custom_code', $ctx['error_code']);
        $this->assertSame('Some::method', $ctx['caller']);
        // Caller a fourni request_id → doit gagner sur l'auto-fill.
        $this->assertSame('custom-req-id-xyz', $ctx['request_id']);
        // Champs libres preserves.
        $this->assertSame('extra_value', $ctx['extra_key']);
    }

    /**
     * criticalContext() et alertContext() partagent la meme mecanique de fusion.
     */
    public function testCriticalAndAlertContextHelpers(): void {
        Logger::criticalContext('Critical incident', ['error_code' => 'crit_x']);
        Logger::alertContext('Alert incident', ['error_code' => 'alert_y']);

        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('"level":"CRITICAL"', $content);
        $this->assertStringContainsString('"level":"ALERT"', $content);
        $this->assertStringContainsString('"error_code":"crit_x"', $content);
        $this->assertStringContainsString('"error_code":"alert_y"', $content);
        $this->assertStringContainsString('"request_id"', $content);
    }
}
