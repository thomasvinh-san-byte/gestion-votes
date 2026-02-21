<?php

declare(strict_types=1);

namespace AgVote\Core;

use AgVote\Core\Middleware\MiddlewareInterface;

/**
 * Executes a stack of middlewares in sequence, then a final handler.
 *
 *   $pipeline = new MiddlewarePipeline();
 *   $pipeline->pipe(new RoleMiddleware('operator'));
 *   $pipeline->pipe(new RateLimitGuard('import', 10, 3600));
 *   $pipeline->then(fn() => $controller->handle('method'));
 */
final class MiddlewarePipeline {
    /** @var MiddlewareInterface[] */
    private array $stack = [];

    public function pipe(MiddlewareInterface $middleware): self {
        $this->stack[] = $middleware;
        return $this;
    }

    /**
     * Run the pipeline, then call $handler at the end.
     */
    public function then(callable $handler): void {
        $pipeline = array_reduce(
            array_reverse($this->stack),
            fn (callable $next, MiddlewareInterface $mw) => fn () => $mw->process($next),
            $handler,
        );
        $pipeline();
    }
}
