<?php

declare(strict_types=1);

namespace AgVote\Controller;

/**
 * Thrown by AccountController to signal an HTTP redirect.
 *
 * Used instead of header()/exit() to keep the controller testable
 * without output or process termination in unit tests.
 */
final class AccountRedirectException extends \RuntimeException {
    public function __construct(
        private readonly string $location,
        private readonly int $statusCode = 302,
    ) {
        parent::__construct("Redirect to {$location}");
    }

    public function getLocation(): string {
        return $this->location;
    }

    public function getStatusCode(): int {
        return $this->statusCode;
    }
}
