<?php
declare(strict_types=1);

namespace AgVote\Core;

use AgVote\Core\Middleware\MiddlewareInterface;
use AgVote\Core\Middleware\RoleMiddleware;
use AgVote\Core\Middleware\RateLimitGuard;

/**
 * Simple exact-match router with middleware support.
 *
 * Routes are declared as:
 *   $router->map('GET', '/api/v1/meetings', MeetingsController::class, 'index', ['role' => 'viewer']);
 *
 * Multi-method endpoints can use a dispatch map:
 *   $router->mapMulti('/api/v1/members', [
 *       'GET'    => [MembersController::class, 'index',        ['role' => 'operator']],
 *       'POST'   => [MembersController::class, 'create',       ['role' => 'operator']],
 *   ]);
 *
 * Middleware config keys:
 *   'role'       => string|array   — required role(s)
 *   'rate_limit' => [context, max, window]  — rate limiting
 */
final class Router
{
    /** @var array<string, array<string, array{class: class-string, method: string, middleware: array}>> */
    private array $routes = [];

    /** @var array<string, array{class: class-string, method: string, bootstrap: bool}> */
    private array $specialRoutes = [];

    /**
     * Register a route for one or more HTTP methods.
     */
    public function map(string|array $httpMethods, string $uri, string $controllerClass, string $controllerMethod, array $middleware = []): self
    {
        if (is_string($httpMethods)) {
            $httpMethods = [$httpMethods];
        }
        foreach ($httpMethods as $method) {
            $this->routes[$uri][strtoupper($method)] = [
                'class' => $controllerClass,
                'method' => $controllerMethod,
                'middleware' => $middleware,
            ];
        }
        return $this;
    }

    /**
     * Register a multi-method route.
     *
     * Each entry can be [Controller::class, 'method'] or [Controller::class, 'method', ['role' => ...]].
     */
    public function mapMulti(string $uri, array $methodMap): self
    {
        foreach ($methodMap as $httpMethod => $entry) {
            $controllerClass = $entry[0];
            $controllerMethod = $entry[1];
            $middleware = $entry[2] ?? [];

            $this->routes[$uri][strtoupper($httpMethod)] = [
                'class' => $controllerClass,
                'method' => $controllerMethod,
                'middleware' => $middleware,
            ];
        }
        return $this;
    }

    /**
     * Register a route that accepts any HTTP method.
     */
    public function mapAny(string $uri, string $controllerClass, string $controllerMethod, array $middleware = []): self
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->routes[$uri][$method] = [
                'class' => $controllerClass,
                'method' => $controllerMethod,
                'middleware' => $middleware,
            ];
        }
        return $this;
    }

    /**
     * Register a special route that uses bootstrap.php instead of api.php.
     */
    public function mapBootstrap(string $uri, string $controllerClass, string $controllerMethod): self
    {
        $this->specialRoutes[$uri] = [
            'class' => $controllerClass,
            'method' => $controllerMethod,
            'bootstrap' => true,
        ];
        return $this;
    }

    /**
     * Dispatch the current request to the matching controller.
     *
     * @return bool true if a route was matched, false otherwise
     */
    public function dispatch(string $httpMethod, string $uri): bool
    {
        $httpMethod = strtoupper($httpMethod);

        // Normalize: strip query string, trailing slash
        $uri = strtok($uri, '?');
        $uri = rtrim($uri, '/');

        // Strip .php extension for matching
        $uriWithoutPhp = preg_replace('/\.php$/', '', $uri);

        // Try exact match first
        if (isset($this->routes[$uri])) {
            return $this->dispatchRoute($this->routes[$uri], $httpMethod);
        }

        // Try without .php
        if ($uriWithoutPhp !== $uri && isset($this->routes[$uriWithoutPhp])) {
            return $this->dispatchRoute($this->routes[$uriWithoutPhp], $httpMethod);
        }

        // Try special routes
        if (isset($this->specialRoutes[$uri])) {
            return $this->dispatchSpecial($this->specialRoutes[$uri]);
        }
        if ($uriWithoutPhp !== $uri && isset($this->specialRoutes[$uriWithoutPhp])) {
            return $this->dispatchSpecial($this->specialRoutes[$uriWithoutPhp]);
        }

        return false;
    }

    private function dispatchRoute(array $methodMap, string $httpMethod): bool
    {
        if (!isset($methodMap[$httpMethod])) {
            if ($httpMethod === 'OPTIONS') {
                return true;
            }
            api_fail('method_not_allowed', 405);
        }

        $handler = $methodMap[$httpMethod];
        $middlewareConfig = $handler['middleware'] ?? [];

        // Build middleware pipeline
        $pipeline = new MiddlewarePipeline();
        foreach (self::buildMiddleware($middlewareConfig) as $mw) {
            $pipeline->pipe($mw);
        }

        // Run pipeline then controller
        $pipeline->then(function () use ($handler) {
            $controller = new $handler['class']();
            if (method_exists($controller, 'handle')) {
                $controller->handle($handler['method']);
            } else {
                $controller->{$handler['method']}();
            }
        });

        return true;
    }

    private function dispatchSpecial(array $handler): bool
    {
        $controller = new $handler['class']();
        $controller->{$handler['method']}();
        return true;
    }

    /**
     * Build middleware instances from config array.
     *
     * @return MiddlewareInterface[]
     */
    private static function buildMiddleware(array $config): array
    {
        $middlewares = [];

        if (isset($config['role'])) {
            $middlewares[] = new RoleMiddleware($config['role']);
        }

        if (isset($config['rate_limit'])) {
            $rl = $config['rate_limit'];
            $middlewares[] = new RateLimitGuard($rl[0], $rl[1] ?? 100, $rl[2] ?? 60);
        }

        return $middlewares;
    }

    /**
     * Get all registered routes (for debugging/documentation).
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
