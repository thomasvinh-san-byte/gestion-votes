<?php

declare(strict_types=1);

namespace AgVote\Core\Middleware;

/**
 * Middleware contract.
 *
 * Each middleware receives a $next callable and must invoke it
 * to continue the pipeline (or skip it to short-circuit).
 */
interface MiddlewareInterface {
    public function process(callable $next): void;
}
