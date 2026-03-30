<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\SystemRepository;
use AgVote\Repository\VoteTokenRepository;
use AgVote\Service\MonitoringService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MonitoringService.
 *
 * Tests metric collection, alert threshold evaluation (auth_failures, slow_db,
 * db_unreachable, email_backlog), alert deduplication, metric persistence,
 * cleanup delegation, and notification suppression.
 *
 * Note: RepositoryFactory is declared final, so it cannot be mocked directly.
 * Instead, we create a real RepositoryFactory(null) and use Reflection to
 * pre-populate its internal cache with PHPUnit mocks of the individual
 * repository classes (which are NOT final). The cache-first pattern in
 * RepositoryFactory::get() ensures these mocks are returned when called.
 *
 * Notification dispatch is disabled by setting MONITOR_ALERT_EMAILS and
 * MONITOR_WEBHOOK_URL to empty in setUp so we test only the core logic.
 */
class MonitoringServiceTest extends TestCase
{
    private MonitoringService $service;
    private RepositoryFactory $repoFactory;
    /** @var \PHPUnit\Framework\MockObject\MockObject&SystemRepository */
    private $sysRepo;
    /** @var \PHPUnit\Framework\MockObject\MockObject&MeetingRepository */
    private $meetingRepo;
    /** @var \PHPUnit\Framework\MockObject\MockObject&MotionRepository */
    private $motionRepo;
    /** @var \PHPUnit\Framework\MockObject\MockObject&VoteTokenRepository */
    private $voteTokenRepo;

    // =========================================================================
    // SETUP / TEARDOWN
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        // Disable notification dispatch
        putenv('MONITOR_ALERT_EMAILS=');
        putenv('MONITOR_WEBHOOK_URL=');
        // Set known thresholds
        putenv('MONITOR_AUTH_FAILURES_THRESHOLD=5');
        putenv('MONITOR_DB_LATENCY_MS=2000');
        putenv('MONITOR_DISK_FREE_PCT=10');
        putenv('MONITOR_EMAIL_BACKLOG=100');

        // Create mocks for individual repository classes (not final)
        $this->sysRepo = $this->createMock(SystemRepository::class);
        $this->meetingRepo = $this->createMock(MeetingRepository::class);
        $this->motionRepo = $this->createMock(MotionRepository::class);
        $this->voteTokenRepo = $this->createMock(VoteTokenRepository::class);

        // Default repo returns (below all alert thresholds)
        $this->meetingRepo->method('countForTenant')->willReturn(10);
        $this->motionRepo->method('countAll')->willReturn(25);
        $this->voteTokenRepo->method('countAll')->willReturn(50);
        $this->sysRepo->method('countAuditEvents')->willReturn(100);
        // Note: countPendingEmails is NOT set as a global default here because individual
        // tests need to configure different return values. Tests that don't specify it
        // will get the PHPUnit default of 0 for int-returning methods.

        // RepositoryFactory is final — inject mocks via Reflection cache trick.
        // RepositoryFactory::get() uses: $this->cache[$class] ??= new $class($this->pdo)
        // Pre-populating the cache means our mocks are returned directly.
        $this->repoFactory = new RepositoryFactory(null);
        $refFactory = new \ReflectionClass(RepositoryFactory::class);
        $cacheProp = $refFactory->getProperty('cache');
        $cacheProp->setAccessible(true);
        $cacheProp->setValue($this->repoFactory, [
            SystemRepository::class   => $this->sysRepo,
            MeetingRepository::class  => $this->meetingRepo,
            MotionRepository::class   => $this->motionRepo,
            VoteTokenRepository::class => $this->voteTokenRepo,
        ]);

        $this->service = new MonitoringService(
            ['default_tenant_id' => 'tenant-001', 'app_url' => 'https://votes.test'],
            $this->repoFactory,
        );
    }

    protected function tearDown(): void
    {
        putenv('MONITOR_ALERT_EMAILS');
        putenv('MONITOR_WEBHOOK_URL');
        putenv('MONITOR_AUTH_FAILURES_THRESHOLD');
        putenv('MONITOR_DB_LATENCY_MS');
        putenv('MONITOR_DISK_FREE_PCT');
        putenv('MONITOR_EMAIL_BACKLOG');

        parent::tearDown();
    }

    // =========================================================================
    // check() -- METRICS COLLECTION
    // =========================================================================

    public function testCheckReturnsMetricsArray(): void
    {
        $this->sysRepo->method('dbPing')->willReturn(15.0);
        $this->sysRepo->method('dbActiveConnections')->willReturn(3);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);

        $result = $this->service->check();

        $this->assertArrayHasKey('metrics', $result);
        $metrics = $result['metrics'];

        $this->assertArrayHasKey('server_time', $metrics);
        $this->assertArrayHasKey('db_latency_ms', $metrics);
        $this->assertArrayHasKey('db_active_connections', $metrics);
        $this->assertArrayHasKey('count_meetings', $metrics);
        $this->assertArrayHasKey('count_motions', $metrics);
        $this->assertArrayHasKey('php_version', $metrics);
        $this->assertArrayHasKey('memory_usage_mb', $metrics);
    }

    public function testCheckMetricsContainDbLatency(): void
    {
        $this->sysRepo->method('dbPing')->willReturn(42.5);
        $this->sysRepo->method('dbActiveConnections')->willReturn(1);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);

        $result = $this->service->check();

        $this->assertEquals(42.5, $result['metrics']['db_latency_ms']);
    }

    public function testCheckMetricsHandleNullDbPing(): void
    {
        $this->sysRepo->method('dbPing')->willReturn(null);
        $this->sysRepo->method('dbActiveConnections')->willReturn(null);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);

        $result = $this->service->check();

        $this->assertNull($result['metrics']['db_latency_ms']);
    }

    // =========================================================================
    // check() -- ALERT THRESHOLDS
    // =========================================================================

    public function testCheckCreatesAuthFailureAlert(): void
    {
        // 10 failures > threshold of 5
        $this->sysRepo->method('dbPing')->willReturn(15.0);
        $this->sysRepo->method('dbActiveConnections')->willReturn(1);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(10);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);
        $this->sysRepo->method('insertSystemAlert');

        $result = $this->service->check();

        $codes = array_column($result['alerts_created'], 'code');
        $this->assertContains('auth_failures', $codes);
    }

    public function testCheckCreatesSlowDbAlert(): void
    {
        // 3000ms > threshold of 2000ms
        $this->sysRepo->method('dbPing')->willReturn(3000.0);
        $this->sysRepo->method('dbActiveConnections')->willReturn(1);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);
        $this->sysRepo->method('insertSystemAlert');

        $result = $this->service->check();

        $alerts = $result['alerts_created'];
        $slowDb = array_filter($alerts, fn ($a) => $a['code'] === 'slow_db');
        $this->assertNotEmpty($slowDb);
        $slowDbAlert = array_values($slowDb)[0];
        $this->assertEquals('critical', $slowDbAlert['severity']);
    }

    public function testCheckCreatesDbUnreachableAlert(): void
    {
        $this->sysRepo->method('dbPing')->willReturn(null);
        $this->sysRepo->method('dbActiveConnections')->willReturn(null);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);
        $this->sysRepo->method('insertSystemAlert');

        $result = $this->service->check();

        $codes = array_column($result['alerts_created'], 'code');
        $this->assertContains('db_unreachable', $codes);

        $dbAlert = array_values(array_filter($alerts = $result['alerts_created'], fn ($a) => $a['code'] === 'db_unreachable'))[0];
        $this->assertEquals('critical', $dbAlert['severity']);
    }

    public function testCheckNoAlertsWhenBelowThresholds(): void
    {
        // All values well below thresholds
        $this->sysRepo->method('dbPing')->willReturn(10.0);
        $this->sysRepo->method('dbActiveConnections')->willReturn(2);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(2);
        $this->sysRepo->method('countPendingEmails')->willReturn(5);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);

        $result = $this->service->check();

        $this->assertEmpty($result['alerts_created']);
    }

    public function testCheckCreatesEmailBacklogAlert(): void
    {
        // 150 emails > threshold of 100
        $this->sysRepo->method('dbPing')->willReturn(10.0);
        $this->sysRepo->method('dbActiveConnections')->willReturn(1);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('countPendingEmails')->willReturn(150);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);
        $this->sysRepo->method('insertSystemAlert');

        $result = $this->service->check();

        $codes = array_column($result['alerts_created'], 'code');
        $this->assertContains('email_backlog', $codes);
    }

    // =========================================================================
    // check() -- ALERT DEDUPLICATION
    // =========================================================================

    public function testCheckDeduplicatesRecentAlerts(): void
    {
        // Auth failures exceed threshold, but findRecentAlert returns truthy (dedup)
        $this->sysRepo->method('dbPing')->willReturn(15.0);
        $this->sysRepo->method('dbActiveConnections')->willReturn(1);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(10);
        // Simulate that an alert was already created within the dedup window
        $this->sysRepo->method('findRecentAlert')->willReturn(true);

        $result = $this->service->check();

        // Alert was above threshold but deduped — should NOT appear in alerts_created
        $this->assertEmpty($result['alerts_created']);
    }

    // =========================================================================
    // check() -- METRIC PERSISTENCE
    // =========================================================================

    public function testCheckPersistsMetrics(): void
    {
        $this->sysRepo->method('dbPing')->willReturn(15.0);
        $this->sysRepo->method('dbActiveConnections')->willReturn(1);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);

        // insertSystemMetric must be called exactly once
        $this->sysRepo->expects($this->once())
            ->method('insertSystemMetric');

        $this->service->check();
    }

    // =========================================================================
    // CLEANUP METHODS
    // =========================================================================

    public function testCleanupMetricsDelegatesToRepo(): void
    {
        $this->sysRepo->expects($this->once())
            ->method('cleanupMetrics')
            ->with(30)
            ->willReturn(5);

        $result = $this->service->cleanupMetrics(30);

        $this->assertEquals(5, $result);
    }

    public function testCleanupAlertsDelegatesToRepo(): void
    {
        $this->sysRepo->expects($this->once())
            ->method('cleanupAlerts')
            ->with(90)
            ->willReturn(3);

        $result = $this->service->cleanupAlerts(90);

        $this->assertEquals(3, $result);
    }

    public function testCleanupMetricsDefaultRetainDays(): void
    {
        // Calling with no args should use default of 30
        $this->sysRepo->expects($this->once())
            ->method('cleanupMetrics')
            ->with(30);

        $this->service->cleanupMetrics();
    }

    // =========================================================================
    // NOTIFICATIONS SUPPRESSION
    // =========================================================================

    public function testCheckReturnsZeroNotificationsWhenEnvDisabled(): void
    {
        // MONITOR_ALERT_EMAILS is already set to empty in setUp
        // Even with alerts being triggered, notifications_sent should be 0
        $this->sysRepo->method('dbPing')->willReturn(3000.0);
        $this->sysRepo->method('dbActiveConnections')->willReturn(1);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(10);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);
        $this->sysRepo->method('insertSystemAlert');

        $result = $this->service->check();

        $this->assertArrayHasKey('notifications_sent', $result);
        $this->assertEquals(0, $result['notifications_sent']);
    }

    // =========================================================================
    // RESULT STRUCTURE
    // =========================================================================

    public function testCheckReturnStructureHasAllKeys(): void
    {
        $this->sysRepo->method('dbPing')->willReturn(20.0);
        $this->sysRepo->method('dbActiveConnections')->willReturn(2);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);

        $result = $this->service->check();

        $this->assertArrayHasKey('metrics', $result);
        $this->assertArrayHasKey('alerts_created', $result);
        $this->assertArrayHasKey('notifications_sent', $result);
        $this->assertIsArray($result['metrics']);
        $this->assertIsArray($result['alerts_created']);
        $this->assertIsInt($result['notifications_sent']);
    }

    public function testCheckMetricsContainExpectedCountFields(): void
    {
        $this->sysRepo->method('dbPing')->willReturn(10.0);
        $this->sysRepo->method('dbActiveConnections')->willReturn(1);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);

        $result = $this->service->check();

        $metrics = $result['metrics'];
        $this->assertEquals(10, $metrics['count_meetings']);
        $this->assertEquals(25, $metrics['count_motions']);
        $this->assertEquals(50, $metrics['count_vote_tokens']);
    }

    // =========================================================================
    // DISK SPACE ALERT
    // =========================================================================

    public function testCheckMetricsContainDiskFields(): void
    {
        $this->sysRepo->method('dbPing')->willReturn(10.0);
        $this->sysRepo->method('dbActiveConnections')->willReturn(1);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);

        $result = $this->service->check();

        // disk_free_bytes and disk_total_bytes are populated from real disk (not mocked)
        $this->assertArrayHasKey('disk_free_bytes', $result['metrics']);
        $this->assertArrayHasKey('disk_total_bytes', $result['metrics']);
        $this->assertArrayHasKey('disk_free_pct', $result['metrics']);
    }

    public function testCheckMetricsContainAuthFailures(): void
    {
        $this->sysRepo->method('dbPing')->willReturn(10.0);
        $this->sysRepo->method('dbActiveConnections')->willReturn(1);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(3);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);

        $result = $this->service->check();

        $this->assertEquals(3, $result['metrics']['auth_failures_15m']);
    }

    public function testCheckMetricsContainEmailBacklog(): void
    {
        $this->sysRepo->method('dbPing')->willReturn(10.0);
        $this->sysRepo->method('dbActiveConnections')->willReturn(1);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('countPendingEmails')->willReturn(42);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);

        $result = $this->service->check();

        $this->assertEquals(42, $result['metrics']['email_queue_backlog']);
    }

    // =========================================================================
    // MULTIPLE ALERTS IN ONE CHECK
    // =========================================================================

    public function testCheckCanCreateMultipleAlertsAtOnce(): void
    {
        // Both DB unreachable (null ping = db_unreachable) AND auth failures
        $this->sysRepo->method('dbPing')->willReturn(null);
        $this->sysRepo->method('dbActiveConnections')->willReturn(null);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(10); // > threshold of 5
        $this->sysRepo->method('countPendingEmails')->willReturn(0);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);
        $this->sysRepo->method('insertSystemAlert');

        $result = $this->service->check();

        $codes = array_column($result['alerts_created'], 'code');
        $this->assertContains('db_unreachable', $codes);
        $this->assertContains('auth_failures', $codes);
    }

    // =========================================================================
    // CLEANUP DEFAULTS
    // =========================================================================

    public function testCleanupAlertsDefaultRetainDays(): void
    {
        $this->sysRepo->expects($this->once())
            ->method('cleanupAlerts')
            ->with(90);

        $this->service->cleanupAlerts();
    }

    // =========================================================================
    // PHP METRICS
    // =========================================================================

    public function testCheckMetricsContainPhpVersion(): void
    {
        $this->sysRepo->method('dbPing')->willReturn(10.0);
        $this->sysRepo->method('dbActiveConnections')->willReturn(1);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);

        $result = $this->service->check();

        $this->assertSame(phpversion(), $result['metrics']['php_version']);
        $this->assertIsFloat($result['metrics']['memory_usage_mb']);
    }

    // =========================================================================
    // METRIC PERSISTENCE FAILURE IS NON-CRITICAL
    // =========================================================================

    public function testCheckContinuesWhenMetricPersistenceFails(): void
    {
        $this->sysRepo->method('dbPing')->willReturn(10.0);
        $this->sysRepo->method('dbActiveConnections')->willReturn(1);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);
        $this->sysRepo->method('insertSystemMetric')
            ->willThrowException(new \RuntimeException('DB write failed'));

        // Should not throw — metric persistence failure is non-critical
        $result = $this->service->check();

        $this->assertArrayHasKey('metrics', $result);
    }

    // =========================================================================
    // SEND ALERT EMAIL PATH (covers sendAlertEmails + getAlertRecipients)
    // =========================================================================

    public function testCheckAlertEmailPathWithSmtpNotConfigured(): void
    {
        // Enable alert emails — triggers sendAlertEmails() code path
        putenv('MONITOR_ALERT_EMAILS=admin@test.local');

        // Trigger an alert: db_unreachable
        $this->sysRepo->method('dbPing')->willReturn(null);
        $this->sysRepo->method('dbActiveConnections')->willReturn(null);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('countPendingEmails')->willReturn(0);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);
        $this->sysRepo->method('insertSystemAlert');

        // Service config has no SMTP → MailerService::isConfigured() returns false → early return
        // This still exercises sendAlertEmails() up to the isConfigured() check
        $result = $this->service->check();

        // Alert was created, but no emails sent (SMTP not configured)
        $codes = array_column($result['alerts_created'], 'code');
        $this->assertContains('db_unreachable', $codes);
        $this->assertEquals(0, $result['notifications_sent']);
    }

    public function testCheckAlertEmailPathWithAutoRecipients(): void
    {
        // Enable "auto" mode — triggers getAlertRecipients() auto branch
        putenv('MONITOR_ALERT_EMAILS=auto');

        $this->sysRepo->method('dbPing')->willReturn(null);
        $this->sysRepo->method('dbActiveConnections')->willReturn(null);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('countPendingEmails')->willReturn(0);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);
        $this->sysRepo->method('insertSystemAlert');

        // repo->user() is not in our factory cache — will try to create UserRepository with null PDO
        // but the try/catch in getAlertRecipients() catches it and returns []
        $result = $this->service->check();

        $codes = array_column($result['alerts_created'], 'code');
        $this->assertContains('db_unreachable', $codes);
        // No emails sent (no SMTP config + recipients failed to load)
        $this->assertEquals(0, $result['notifications_sent']);
    }

    public function testCheckAlertEmailPathWithCommaRecipients(): void
    {
        // Multiple recipients — triggers array_filter + explode in getAlertRecipients()
        putenv('MONITOR_ALERT_EMAILS=a@test.local,b@test.local');

        $this->sysRepo->method('dbPing')->willReturn(null);
        $this->sysRepo->method('dbActiveConnections')->willReturn(null);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('countPendingEmails')->willReturn(0);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);
        $this->sysRepo->method('insertSystemAlert');

        // No SMTP config → isConfigured() = false → return 0 (no emails sent)
        $result = $this->service->check();

        $codes = array_column($result['alerts_created'], 'code');
        $this->assertContains('db_unreachable', $codes);
        $this->assertEquals(0, $result['notifications_sent']);
    }

    // =========================================================================
    // SEND WEBHOOK PATH (covers sendWebhook)
    // =========================================================================

    public function testCheckWebhookPathWithInvalidUrl(): void
    {
        // Enable webhook with an invalid URL — triggers sendWebhook() code path
        // curl_init() will fail on a non-URL or return connection refused quickly
        putenv('MONITOR_WEBHOOK_URL=http://127.0.0.1:19999/nonexistent');

        $this->sysRepo->method('dbPing')->willReturn(null);
        $this->sysRepo->method('dbActiveConnections')->willReturn(null);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('countPendingEmails')->willReturn(0);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);
        $this->sysRepo->method('insertSystemAlert');

        // sendWebhook tries curl but connection fails → returns 0, logs warning
        $result = $this->service->check();

        $codes = array_column($result['alerts_created'], 'code');
        $this->assertContains('db_unreachable', $codes);
        // 0 notifications_sent (curl failed)
        $this->assertIsInt($result['notifications_sent']);
    }

    // =========================================================================
    // RENDER ALERT EMAIL (covered via sendAlertEmails with non-empty env)
    // The renderAlertEmail path is exercised via testCheckAlertEmailPathWith* above
    // but we also test the low_disk alert which passes details[] to renderAlertEmail
    // =========================================================================

    public function testCheckLowDiskAlertCreated(): void
    {
        // Force disk free pct to be very low by using a threshold that's impossibly high
        putenv('MONITOR_DISK_FREE_PCT=99999');

        $service = new MonitoringService(
            ['default_tenant_id' => 'tenant-001'],
            $this->repoFactory,
        );

        $this->sysRepo->method('dbPing')->willReturn(10.0);
        $this->sysRepo->method('dbActiveConnections')->willReturn(1);
        $this->sysRepo->method('countAuthFailures15m')->willReturn(0);
        $this->sysRepo->method('countPendingEmails')->willReturn(0);
        $this->sysRepo->method('findRecentAlert')->willReturn(false);
        $this->sysRepo->method('insertSystemAlert');

        $result = $service->check();

        // disk_free_pct will be very low relative to threshold=99999 → low_disk alert
        $codes = array_column($result['alerts_created'], 'code');
        $this->assertContains('low_disk', $codes);
    }
}
