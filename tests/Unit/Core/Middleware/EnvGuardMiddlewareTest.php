<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Middleware;

use AgVote\Core\Http\ApiResponseException;
use AgVote\Core\Middleware\EnvGuardMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EnvGuardMiddleware.
 *
 * Source: TEST-V24-01 / D-03 — Plan 03.1 (Phase 3 v2.4).
 *
 * Verifies the gate behaviour:
 *   - Allowed envs (development, dev, test, testing, demo) → next() runs
 *   - Disallowed envs (production, prod, staging, ...) → 404 ApiResponseException
 *   - Custom allowlist honoured
 *   - $_ENV takes precedence over getenv() for deterministic tests
 */
class EnvGuardMiddlewareTest extends TestCase
{
    /** @var string|false */
    private string|false $previousEnv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousEnv = $_ENV['APP_ENV'] ?? false;
    }

    protected function tearDown(): void
    {
        if ($this->previousEnv === false) {
            unset($_ENV['APP_ENV']);
            putenv('APP_ENV');
        } else {
            $_ENV['APP_ENV'] = $this->previousEnv;
            putenv('APP_ENV=' . $this->previousEnv);
        }
        parent::tearDown();
    }

    public function testAllowedEnvCallsNext(): void
    {
        foreach (['development', 'dev', 'test', 'testing', 'demo'] as $env) {
            $_ENV['APP_ENV'] = $env;
            putenv('APP_ENV=' . $env);

            $called = false;
            $mw = new EnvGuardMiddleware();
            $mw->process(function () use (&$called) {
                $called = true;
            });

            $this->assertTrue($called, "Env '{$env}' should pass through");
        }
    }

    public function testProductionEnvShortCircuitsWith404(): void
    {
        $_ENV['APP_ENV'] = 'production';
        putenv('APP_ENV=production');

        $mw = new EnvGuardMiddleware();
        $called = false;

        try {
            $mw->process(function () use (&$called) {
                $called = true;
            });
            $this->fail('Expected ApiResponseException for production env');
        } catch (ApiResponseException $e) {
            $this->assertSame(404, $e->getResponse()->getStatusCode());
            $this->assertFalse($called, 'next() must NOT run when env is blocked');

            $body = $e->getResponse()->getBody();
            $this->assertSame('not_found', $body['error'] ?? null);
        }
    }

    public function testProdAliasIsBlocked(): void
    {
        $_ENV['APP_ENV'] = 'prod';
        putenv('APP_ENV=prod');

        $this->expectException(ApiResponseException::class);
        (new EnvGuardMiddleware())->process(fn() => null);
    }

    public function testStagingIsBlockedByDefault(): void
    {
        $_ENV['APP_ENV'] = 'staging';
        putenv('APP_ENV=staging');

        $this->expectException(ApiResponseException::class);
        (new EnvGuardMiddleware())->process(fn() => null);
    }

    public function testCustomAllowlistRespected(): void
    {
        $_ENV['APP_ENV'] = 'staging';
        putenv('APP_ENV=staging');

        $called = false;
        $mw = new EnvGuardMiddleware(['staging']);
        $mw->process(function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called, 'Custom allowlist must permit staging');
    }

    public function testMissingEnvDefaultsToProduction(): void
    {
        unset($_ENV['APP_ENV']);
        putenv('APP_ENV');

        $this->expectException(ApiResponseException::class);
        (new EnvGuardMiddleware())->process(fn() => null);
    }
}
