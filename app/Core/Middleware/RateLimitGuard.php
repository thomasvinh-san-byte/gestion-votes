<?php
declare(strict_types=1);

namespace AgVote\Core\Middleware;

/**
 * Applies rate limiting before the controller is called.
 *
 * Delegates to api_rate_limit() which uses the user ID (if auth)
 * or IP address as the limiting key.
 */
final class RateLimitGuard implements MiddlewareInterface
{
    public function __construct(
        private readonly string $context,
        private readonly int $maxAttempts = 100,
        private readonly int $windowSeconds = 60,
    ) {}

    public function process(callable $next): void
    {
        api_rate_limit($this->context, $this->maxAttempts, $this->windowSeconds);
        $next();
    }
}
