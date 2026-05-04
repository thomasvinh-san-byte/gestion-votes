<?php

declare(strict_types=1);

namespace AgVote\Core\Middleware;

/**
 * Restricts a route to a whitelist of APP_ENV values.
 *
 * Used to gate development-only endpoints (test seeders, dev tools) so they
 * return a silent 404 in production environments. Pairs with controller-level
 * `guardProduction()` checks for defence-in-depth (triple guard pattern):
 *   1. Conditional route registration in `app/routes.php` (outer gate)
 *   2. EnvGuardMiddleware (this class — request-level gate)
 *   3. Controller-level guard helper (inner gate)
 *
 * Default whitelist matches the standard development workflow: development,
 * test, demo. Anything else (production, prod, staging, ...) returns 404.
 *
 * Source: TEST-V24-01 / D-03 — Plan 03.1 (Phase 3 v2.4).
 */
final class EnvGuardMiddleware implements MiddlewareInterface {
    /** @var list<string> */
    private array $allowedEnvs;

    /**
     * @param list<string> $allowedEnvs Lowercase env names that may access the route.
     */
    public function __construct(array $allowedEnvs = ['development', 'dev', 'test', 'testing', 'demo']) {
        $this->allowedEnvs = $allowedEnvs;
    }

    public function process(callable $next): void {
        if (!$this->isAllowed()) {
            // Silent 404 — endpoint behaves as if it does not exist.
            api_fail('not_found', 404, ['detail' => 'Route inconnue.']);
        }
        $next();
    }

    private function isAllowed(): bool {
        $env = strtolower((string) (
            $_ENV['APP_ENV']
            ?? getenv('APP_ENV')
            ?: \AgVote\Core\Application::config('env')
            ?? 'production'
        ));
        return in_array($env, $this->allowedEnvs, true);
    }
}
