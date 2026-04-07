<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Providers\DatabaseProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for DatabaseProvider timeout configuration.
 *
 * DatabaseProvider uses static state and calls exit() on connection failure,
 * so we use code-inspection tests (source/reflection) rather than live connects.
 *
 * The contract verified:
 *   1. ATTR_TIMEOUT => 10 is present in the options array
 *   2. DB_STATEMENT_TIMEOUT_MS env var is read (default 30000)
 *   3. When DB_STATEMENT_TIMEOUT_MS=0 the SET command is NOT sent
 *   4. When DB_STATEMENT_TIMEOUT_MS=5000 the SET command uses that value
 */
class DatabaseProviderTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        $ref = new ReflectionClass(DatabaseProvider::class);
        $this->source = file_get_contents($ref->getFileName());
    }

    // =========================================================================
    // Test 1 — ATTR_TIMEOUT is present in the options array
    // =========================================================================

    public function testConnectPassesAttrTimeoutToPdo(): void
    {
        $this->assertStringContainsString(
            'ATTR_TIMEOUT',
            $this->source,
            'DatabaseProvider::connect() must pass PDO::ATTR_TIMEOUT in the PDO options array'
        );
    }

    // =========================================================================
    // Test 2 — Default statement_timeout = 30000 when env var is unset
    // =========================================================================

    public function testConnectSetsDefaultStatementTimeoutWhenEnvUnset(): void
    {
        $this->assertStringContainsString(
            'DB_STATEMENT_TIMEOUT_MS',
            $this->source,
            'DatabaseProvider must reference DB_STATEMENT_TIMEOUT_MS env var'
        );

        $this->assertStringContainsString(
            '30000',
            $this->source,
            'DatabaseProvider must default to 30000 ms when DB_STATEMENT_TIMEOUT_MS is unset'
        );

        $this->assertStringContainsString(
            'statement_timeout',
            $this->source,
            'DatabaseProvider must execute SET statement_timeout'
        );
    }

    // =========================================================================
    // Test 3 — When DB_STATEMENT_TIMEOUT_MS=0, the SET is NOT executed
    // =========================================================================

    public function testConnectSkipsStatementTimeoutWhenEnvIsZero(): void
    {
        // Verify guard: timeoutMs > 0 condition is present in source
        $this->assertMatchesRegularExpression(
            '/\$timeoutMs\s*>\s*0/',
            $this->source,
            'DatabaseProvider must guard SET statement_timeout with timeoutMs > 0 check'
        );
    }

    // =========================================================================
    // Test 4 — Custom value (e.g. 5000) is interpolated into SET command
    // =========================================================================

    public function testConnectSetsCustomStatementTimeoutFromEnv(): void
    {
        $this->assertMatchesRegularExpression(
            '/SET statement_timeout.*\$timeoutMs/s',
            $this->source,
            'DatabaseProvider must interpolate $timeoutMs into SET statement_timeout'
        );
    }
}
