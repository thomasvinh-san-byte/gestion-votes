<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Code-as-specification tests for EmailQueueRepository retry/backoff logic.
 *
 * These tests verify that critical SQL patterns (exponential backoff formula,
 * permanent failure at max_retries, concurrent-safe fetching) are present in
 * the source file and cannot be accidentally removed.
 *
 * Implementation note: EmailQueueRepository extends AbstractRepository (needs PDO),
 * so we cannot instantiate it in a unit test. Instead we use file_get_contents()
 * on the source files to assert that the required SQL patterns exist.
 */
class EmailQueueRepositoryRetryTest extends TestCase
{
    private string $repoSource;
    private string $migrationSource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repoSource = (string) file_get_contents(__DIR__ . '/../../app/Repository/EmailQueueRepository.php');
        $this->migrationSource = (string) file_get_contents(__DIR__ . '/../../database/migrations/005_email_system.sql');
    }

    // =========================================================================
    // markFailed() — exponential backoff and permanent failure
    // =========================================================================

    public function testMarkFailedUsesExponentialBackoff(): void
    {
        $this->assertStringContainsString(
            "power(2, retry_count)",
            $this->repoSource,
            'markFailed() must use power(2, retry_count) for exponential backoff',
        );
    }

    public function testMarkFailedReschedulesWithFiveMinuteInterval(): void
    {
        // Backoff formula: 5 minutes * 2^retry_count
        $this->assertStringContainsString(
            "interval '5 minutes' * power(2, retry_count)",
            $this->repoSource,
            'markFailed() must reschedule using interval 5 minutes * power(2, retry_count)',
        );
    }

    public function testMarkFailedSetsPermanentFailureAtMaxRetries(): void
    {
        $this->assertStringContainsString(
            "retry_count + 1 >= max_retries THEN 'failed'",
            $this->repoSource,
            "markFailed() must set status='failed' when retry_count + 1 >= max_retries",
        );
    }

    // =========================================================================
    // fetchPendingBatch() — retry_count filter and concurrent safety
    // =========================================================================

    public function testFetchPendingBatchRespectsRetryCount(): void
    {
        $this->assertStringContainsString(
            "retry_count < max_retries",
            $this->repoSource,
            'fetchPendingBatch() must filter rows where retry_count < max_retries',
        );
    }

    public function testFetchPendingBatchUsesSkipLocked(): void
    {
        $this->assertStringContainsString(
            "FOR UPDATE SKIP LOCKED",
            $this->repoSource,
            'fetchPendingBatch() must use FOR UPDATE SKIP LOCKED for concurrent safety',
        );
    }

    // =========================================================================
    // Migration schema — retry columns with correct defaults
    // =========================================================================

    public function testMigrationDefinesRetryCountColumn(): void
    {
        $this->assertStringContainsString(
            "retry_count INT DEFAULT 0",
            $this->migrationSource,
            'email_queue migration must define retry_count INT DEFAULT 0',
        );
    }

    public function testMigrationDefinesMaxRetriesColumn(): void
    {
        $this->assertStringContainsString(
            "max_retries INT DEFAULT 3",
            $this->migrationSource,
            'email_queue migration must define max_retries INT DEFAULT 3',
        );
    }
}
