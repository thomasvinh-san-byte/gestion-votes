<?php

declare(strict_types=1);

namespace Tests\Security;

use AgVote\Core\Http\ClientIp;
use AgVote\Core\Http\UrlValidator;
use AgVote\Core\Security\AccountLockout;
use AgVote\Core\Security\CsrfMiddleware;
use AgVote\Core\Security\SessionHelper;
use AgVote\SSE\SseAuthGate;
use PHPUnit\Framework\TestCase;

/**
 * F20 — Security regression suite (v2.1 milestone).
 *
 * Cross-cutting smoke tests that lock the contract of every hardening
 * delivered in milestone v2.1 (F02-F19). One assertion per finding,
 * targeting the most damaging regression mode for that finding. The
 * detailed unit tests for each helper live in tests/Unit/; this suite
 * is the "tripwire" — if any assertion ever fails on main, an attacker
 * has gained back a vector that was already closed.
 *
 * Wired to a dedicated PHPUnit testsuite `Security` so it can be run
 * standalone in CI:
 *   vendor/bin/phpunit --testsuite Security --no-coverage
 */
final class HardeningRegressionTest extends TestCase {
    /** @var array<string, mixed> */
    private array $serverBackup;
    private string $envProxiesBackup;
    private string $envSamesiteBackup;

    protected function setUp(): void {
        parent::setUp();
        $this->serverBackup = $_SERVER;
        $this->envProxiesBackup = (string) (getenv('TRUSTED_PROXIES') ?: '');
        $this->envSamesiteBackup = (string) (getenv('SESSION_COOKIE_SAMESITE') ?: '');
        ClientIp::reset();
    }

    protected function tearDown(): void {
        $_SERVER = $this->serverBackup;
        if ($this->envProxiesBackup === '') {
            putenv('TRUSTED_PROXIES');
        } else {
            putenv('TRUSTED_PROXIES=' . $this->envProxiesBackup);
        }
        if ($this->envSamesiteBackup === '') {
            putenv('SESSION_COOKIE_SAMESITE');
        } else {
            putenv('SESSION_COOKIE_SAMESITE=' . $this->envSamesiteBackup);
        }
        ClientIp::reset();
        parent::tearDown();
    }

    // F02 ─────────────────────────────────────────────────────────────────────
    public function testF02ClientIpRejectsForwardedFromUntrustedSource(): void {
        putenv('TRUSTED_PROXIES=10.0.0.1');
        $_SERVER['REMOTE_ADDR'] = '203.0.113.99'; // not trusted
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4'; // attacker spoof
        ClientIp::reset();
        $this->assertSame(
            '203.0.113.99',
            ClientIp::get(),
            'F02: X-Forwarded-For from a non-trusted peer must be ignored.',
        );
    }

    // F05 ─────────────────────────────────────────────────────────────────────
    public function testF05SseGateReturns404ForCrossTenantMeeting(): void {
        $repo = $this->createMock(\AgVote\Repository\MeetingRepository::class);
        $repo->method('findByIdForTenant')->willReturn(null); // foreign tenant

        $gate = new SseAuthGate($repo);
        $session = [
            'auth_user' => ['id' => 'u1', 'tenant_id' => 'tenant-a'],
            'auth_last_activity' => time(),
        ];
        $decision = $gate->evaluate($session, 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', time());
        $this->assertSame(404, $decision['status'], 'F05: cross-tenant lookups must return 404, not 403.');
    }

    // F06 ─────────────────────────────────────────────────────────────────────
    public function testF06VoteTokenRepositoryHasAtomicConsume(): void {
        $ref = new \ReflectionClass(\AgVote\Repository\VoteTokenRepository::class);
        $this->assertTrue(
            $ref->hasMethod('consumeIfValid'),
            'F06: VoteTokenRepository must expose consumeIfValid (atomic UPDATE...RETURNING).',
        );
    }

    // F08 ─────────────────────────────────────────────────────────────────────
    public function testF08DeadIdorMethodIsRemoved(): void {
        $ref = new \ReflectionClass(\AgVote\Repository\MotionRepository::class);
        $this->assertFalse(
            $ref->hasMethod('findByIdAndMeeting'),
            'F08: findByIdAndMeeting (no tenant gate) must remain deleted.',
        );
    }

    public function testF08FindByIdAndMeetingWithDatesRequiresTenant(): void {
        $ref = new \ReflectionMethod(\AgVote\Repository\MotionRepository::class, 'findByIdAndMeetingWithDates');
        $this->assertGreaterThanOrEqual(
            3,
            $ref->getNumberOfRequiredParameters(),
            'F08: findByIdAndMeetingWithDates must require tenant_id as a third arg.',
        );
    }

    // F10 ─────────────────────────────────────────────────────────────────────
    public function testF10CsrfTokensAreActionScopedAndCrossActionRejects(): void {
        // Simulate a session by writing the secret directly. tokenFor() will
        // also generate it lazily — we assert the symmetric relationship.
        $_SESSION = [];
        $tokenA = CsrfMiddleware::tokenFor('POST', '/api/v1/meetings');
        $tokenB = CsrfMiddleware::tokenFor('POST', '/api/v1/admin_settings');
        $this->assertNotSame(
            $tokenA,
            $tokenB,
            'F10: tokens must differ across actions even within the same session.',
        );
        // Same action → same token (deterministic)
        $tokenAagain = CsrfMiddleware::tokenFor('POST', '/api/v1/meetings');
        $this->assertSame($tokenA, $tokenAagain);
    }

    // F11 ─────────────────────────────────────────────────────────────────────
    public function testF11UrlValidatorRefusesCloudMetadataIp(): void {
        $this->assertFalse(
            UrlValidator::isSafeOutbound('https://169.254.169.254/latest/meta-data/', ['169.254.169.254']),
            'F11: AWS-style metadata IP must NEVER be reachable as outbound, even when whitelisted.',
        );
    }

    public function testF11UrlValidatorRefusesUserinfoPhish(): void {
        $this->assertFalse(
            UrlValidator::isSafeRedirect('https://app.example.com@evil.com/path', ['app.example.com']),
            'F11: userinfo-style URL must be refused (phishing redirect).',
        );
    }

    // F13 ─────────────────────────────────────────────────────────────────────
    public function testF13AccountLockoutBackoffIsExponential(): void {
        $this->assertSame(60,    AccountLockout::lockSecondsForCount(5));   // 1 min
        $this->assertSame(120,   AccountLockout::lockSecondsForCount(6));   // 2 min
        $this->assertSame(86400, AccountLockout::lockSecondsForCount(50));  // capped at 24 h
    }

    // F18 ─────────────────────────────────────────────────────────────────────
    public function testF18CookieSameSiteDefaultsToStrict(): void {
        putenv('SESSION_COOKIE_SAMESITE');
        $params = SessionHelper::cookieParams();
        $this->assertSame('Strict', $params['samesite']);
        $this->assertTrue($params['httponly']);
    }

    public function testF18InvalidSamesiteEnvFallsBackToStrict(): void {
        putenv('SESSION_COOKIE_SAMESITE=foo');
        $params = SessionHelper::cookieParams();
        $this->assertSame('Strict', $params['samesite'], 'F18: garbage env must NEVER weaken to Lax/None.');
    }
}
