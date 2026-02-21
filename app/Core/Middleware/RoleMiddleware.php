<?php
declare(strict_types=1);

namespace AgVote\Core\Middleware;

/**
 * Validates that the current user has the required role(s).
 *
 * Delegates to api_require_role() which handles:
 * - CSRF validation for write methods
 * - Authentication via AuthMiddleware
 * - Role/permission checking
 */
final class RoleMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string|array $roles) {}

    public function process(callable $next): void
    {
        api_require_role($this->roles);
        $next();
    }
}
