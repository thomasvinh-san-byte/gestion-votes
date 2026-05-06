<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Static configuration tests for the Redis-backed session storage.
 *
 * These do NOT exercise a live Redis connection — they validate that the
 * deploy artifacts (php.ini baseline + entrypoint runtime injection) declare
 * the expected directives. Live persistence is covered by the E2E spec
 * tests/e2e/specs/session-persistence.spec.js.
 *
 * Refs: M-INFRA-CLEANUP / CLEANUP-SESSIONS-01..03.
 */
final class SessionRedisConfigTest extends TestCase {
    private const PHP_INI_PATH = __DIR__ . '/../../deploy/php.ini';
    private const ENTRYPOINT_PATH = __DIR__ . '/../../deploy/entrypoint.sh';

    public function testPhpIniDeclaresRedisHandler(): void {
        $ini = file_get_contents(self::PHP_INI_PATH);
        $this->assertNotFalse($ini, 'deploy/php.ini missing');

        $this->assertStringContainsString(
            'session.save_handler = redis',
            $ini,
            'php.ini must enable phpredis session handler.',
        );
    }

    public function testPhpIniDeclaresIsolatedRedisDatabase(): void {
        $ini = file_get_contents(self::PHP_INI_PATH);

        // DB1 keeps sessions away from the app cache (RedisProvider on DB0).
        $this->assertMatchesRegularExpression(
            '/session\.save_path\s*=\s*"tcp:\/\/redis:6379\?database=1&prefix=agvote-sess:"/',
            $ini,
            'php.ini must point sessions at Redis DB1 with namespaced prefix.',
        );
    }

    public function testEntrypointInjectsAuthenticatedSavePathWhenPasswordPresent(): void {
        $entrypoint = file_get_contents(self::ENTRYPOINT_PATH);
        $this->assertNotFalse($entrypoint, 'deploy/entrypoint.sh missing');

        // The runtime override must include the auth= parameter sourced from env.
        $this->assertStringContainsString(
            'auth=${REDIS_PASSWORD}',
            $entrypoint,
            'entrypoint must interpolate REDIS_PASSWORD into the authenticated save_path.',
        );
        $this->assertStringContainsString(
            '/tmp/php-runtime/zz-runtime.ini',
            $entrypoint,
            'entrypoint must write runtime override into the directory PHP_INI_SCAN_DIR scans.',
        );
        $this->assertStringContainsString(
            'database=1',
            $entrypoint,
            'entrypoint must keep sessions on DB1 even after auth injection.',
        );
    }

    public function testEntrypointGuardsAgainstMissingPassword(): void {
        $entrypoint = file_get_contents(self::ENTRYPOINT_PATH);

        // The block must be conditional — production with no password should
        // log a warning, not silently emit an unauthenticated save_path.
        $this->assertMatchesRegularExpression(
            '/if\s*\[\s*-n\s*"\$\{REDIS_PASSWORD\}"\s*\]/',
            $entrypoint,
            'entrypoint must only inject the authenticated save_path when REDIS_PASSWORD is set.',
        );
    }
}
