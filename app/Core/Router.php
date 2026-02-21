<?php

declare(strict_types=1);

namespace AgVote\Core;

use AgVote\Core\Http\ApiResponseException;
use AgVote\Core\Middleware\MiddlewareInterface;
use AgVote\Core\Middleware\RateLimitGuard;
use AgVote\Core\Middleware\RoleMiddleware;

/**
 * Router with exact-match + parameterized route support.
 *
 * Routes are declared as:
 *   $router->map('GET', '/api/v1/meetings', MeetingsController::class, 'index', ['role' => 'viewer']);
 *   $router->map('GET', '/api/v1/meetings/{id}', MeetingsController::class, 'show', ['role' => 'viewer']);
 *
 * Parameterized routes use {name} placeholders. Matched values are available
 * via $_REQUEST['_route_params'] and individual $_REQUEST keys.
 *
 * Exact-match routes are checked first (O(1)), then parameterized routes
 * are checked in registration order (O(n) on parameterized routes only).
 *
 * Middleware config keys:
 *   'role'       => string|array   — required role(s)
 *   'rate_limit' => [context, max, window]  — rate limiting
 */
final class Router {
    /** @var array<string, array<string, array{class: class-string, method: string, middleware: array}>> */
    private array $routes = [];

    /** @var list<array{pattern: string, regex: string, params: list<string>, methods: array<string, array{class: class-string, method: string, middleware: array}>}> */
    private array $paramRoutes = [];

    /** @var array<string, array{class: class-string, method: string, bootstrap: bool}> */
    private array $specialRoutes = [];

    /**
     * Register a route for one or more HTTP methods.
     * Supports {param} placeholders for dynamic segments.
     */
    public function map(string|array $httpMethods, string $uri, string $controllerClass, string $controllerMethod, array $middleware = []): self {
        if (is_string($httpMethods)) {
            $httpMethods = [$httpMethods];
        }

        $handler = [
            'class' => $controllerClass,
            'method' => $controllerMethod,
            'middleware' => $middleware,
        ];

        if (str_contains($uri, '{')) {
            $this->addParamRoute($uri, $httpMethods, $handler);
        } else {
            foreach ($httpMethods as $method) {
                $this->routes[$uri][strtoupper($method)] = $handler;
            }
        }

        return $this;
    }

    /**
     * Register a multi-method route.
     */
    public function mapMulti(string $uri, array $methodMap): self {
        foreach ($methodMap as $httpMethod => $entry) {
            $handler = [
                'class' => $entry[0],
                'method' => $entry[1],
                'middleware' => $entry[2] ?? [],
            ];

            if (str_contains($uri, '{')) {
                $this->addParamRoute($uri, [strtoupper($httpMethod)], $handler);
            } else {
                $this->routes[$uri][strtoupper($httpMethod)] = $handler;
            }
        }
        return $this;
    }

    /**
     * Register a route that accepts any HTTP method.
     */
    public function mapAny(string $uri, string $controllerClass, string $controllerMethod, array $middleware = []): self {
        return $this->map(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $uri, $controllerClass, $controllerMethod, $middleware);
    }

    /**
     * Register a special route that uses bootstrap.php instead of api.php.
     */
    public function mapBootstrap(string $uri, string $controllerClass, string $controllerMethod): self {
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
    public function dispatch(string $httpMethod, string $uri): bool {
        $httpMethod = strtoupper($httpMethod);

        // Normalize: strip query string, trailing slash
        $uri = strtok($uri, '?');
        $uri = rtrim($uri, '/');

        // Strip .php extension for matching
        $uriWithoutPhp = preg_replace('/\.php$/', '', $uri);

        try {
            // 1. Try exact match first (O(1))
            if (isset($this->routes[$uri])) {
                return $this->dispatchRoute($this->routes[$uri], $httpMethod);
            }

            if ($uriWithoutPhp !== $uri && isset($this->routes[$uriWithoutPhp])) {
                return $this->dispatchRoute($this->routes[$uriWithoutPhp], $httpMethod);
            }

            // 2. Try parameterized routes
            $paramMatch = $this->matchParamRoute($uri, $httpMethod);
            if ($paramMatch !== null) {
                return $this->dispatchRoute($paramMatch['methods'], $httpMethod, $paramMatch['params']);
            }

            if ($uriWithoutPhp !== $uri) {
                $paramMatch = $this->matchParamRoute($uriWithoutPhp, $httpMethod);
                if ($paramMatch !== null) {
                    return $this->dispatchRoute($paramMatch['methods'], $httpMethod, $paramMatch['params']);
                }
            }

            // 3. Try special routes
            if (isset($this->specialRoutes[$uri])) {
                return $this->dispatchSpecial($this->specialRoutes[$uri]);
            }
            if ($uriWithoutPhp !== $uri && isset($this->specialRoutes[$uriWithoutPhp])) {
                return $this->dispatchSpecial($this->specialRoutes[$uriWithoutPhp]);
            }
        } catch (ApiResponseException $e) {
            $e->getResponse()->send();
            return true;
        }

        return false;
    }

    private function dispatchRoute(array $methodMap, string $httpMethod, array $routeParams = []): bool {
        if (!isset($methodMap[$httpMethod])) {
            if ($httpMethod === 'OPTIONS') {
                return true;
            }
            api_fail('method_not_allowed', 405);
        }

        // Expose route params via $_REQUEST (safe: only set by router, not user input)
        if (!empty($routeParams)) {
            $_REQUEST['_route_params'] = $routeParams;
            foreach ($routeParams as $key => $value) {
                $_REQUEST[$key] = $value;
            }
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

    private function dispatchSpecial(array $handler): bool {
        $controller = new $handler['class']();
        $controller->{$handler['method']}();
        return true;
    }

    // ── Parameterized route support ─────────────────────────────────────

    /**
     * Register a parameterized route.
     * Converts /api/v1/meetings/{id} to regex /api/v1/meetings/([^/]+)
     */
    private function addParamRoute(string $uri, array $httpMethods, array $handler): void {
        // Extract param names and build regex
        $params = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($m) use (&$params) {
            $params[] = $m[1];
            return '([^/]+)';
        }, $uri);
        $regex = '#^' . $regex . '$#';

        // Find existing paramRoute entry for this pattern
        $found = false;
        foreach ($this->paramRoutes as &$route) {
            if ($route['pattern'] === $uri) {
                foreach ($httpMethods as $method) {
                    $route['methods'][strtoupper($method)] = $handler;
                }
                $found = true;
                break;
            }
        }
        unset($route);

        if (!$found) {
            $methods = [];
            foreach ($httpMethods as $method) {
                $methods[strtoupper($method)] = $handler;
            }
            $this->paramRoutes[] = [
                'pattern' => $uri,
                'regex' => $regex,
                'params' => $params,
                'methods' => $methods,
            ];
        }
    }

    /**
     * Match a URI against parameterized routes.
     *
     * @return array{methods: array, params: array<string,string>}|null
     */
    private function matchParamRoute(string $uri, string $httpMethod): ?array {
        foreach ($this->paramRoutes as $route) {
            if (preg_match($route['regex'], $uri, $matches)) {
                array_shift($matches); // remove full match
                $params = [];
                foreach ($route['params'] as $i => $name) {
                    $params[$name] = rawurldecode($matches[$i] ?? '');
                }
                return [
                    'methods' => $route['methods'],
                    'params' => $params,
                ];
            }
        }
        return null;
    }

    /**
     * Build middleware instances from config array.
     *
     * @return MiddlewareInterface[]
     */
    private static function buildMiddleware(array $config): array {
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
    public function getRoutes(): array {
        return $this->routes;
    }

    /**
     * Get all registered parameterized routes.
     */
    public function getParamRoutes(): array {
        return $this->paramRoutes;
    }
}
