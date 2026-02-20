<?php
declare(strict_types=1);

namespace AgVote\Core;

/**
 * Simple exact-match router.
 *
 * Routes are declared as:
 *   $router->map('GET', '/api/v1/meetings', MeetingsController::class, 'index');
 *   $router->map(['GET','POST'], '/api/v1/agendas', AgendaController::class, 'dispatch');
 *
 * Multi-method endpoints can use a dispatch map:
 *   $router->mapMulti('/api/v1/members', [
 *       'GET'    => [MembersController::class, 'index'],
 *       'POST'   => [MembersController::class, 'create'],
 *       'PATCH'  => [MembersController::class, 'updateMember'],
 *       'DELETE' => [MembersController::class, 'delete'],
 *   ]);
 */
final class Router
{
    /** @var array<string, array<string, array{class: class-string, method: string}>> URI → method → handler */
    private array $routes = [];

    /** @var array<string, array{class: class-string, method: string, bootstrap: bool}> Special non-api routes */
    private array $specialRoutes = [];

    /**
     * Register a route for one or more HTTP methods.
     */
    public function map(string|array $httpMethods, string $uri, string $controllerClass, string $controllerMethod): self
    {
        if (is_string($httpMethods)) {
            $httpMethods = [$httpMethods];
        }
        foreach ($httpMethods as $method) {
            $this->routes[$uri][strtoupper($method)] = [
                'class' => $controllerClass,
                'method' => $controllerMethod,
            ];
        }
        return $this;
    }

    /**
     * Register a multi-method route.
     *
     * @param string $uri
     * @param array<string, array{0: class-string, 1: string}> $methodMap ['GET' => [Controller::class, 'method'], ...]
     */
    public function mapMulti(string $uri, array $methodMap): self
    {
        foreach ($methodMap as $httpMethod => [$controllerClass, $controllerMethod]) {
            $this->routes[$uri][strtoupper($httpMethod)] = [
                'class' => $controllerClass,
                'method' => $controllerMethod,
            ];
        }
        return $this;
    }

    /**
     * Register a route that accepts any HTTP method.
     * The controller method is responsible for validating the HTTP method.
     */
    public function mapAny(string $uri, string $controllerClass, string $controllerMethod): self
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->routes[$uri][$method] = [
                'class' => $controllerClass,
                'method' => $controllerMethod,
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
            // Allow OPTIONS for CORS (handled by bootstrap)
            if ($httpMethod === 'OPTIONS') {
                return true;
            }
            api_fail('method_not_allowed', 405);
        }

        $handler = $methodMap[$httpMethod];
        $controller = new $handler['class']();

        if (method_exists($controller, 'handle')) {
            $controller->handle($handler['method']);
        } else {
            $controller->{$handler['method']}();
        }

        return true;
    }

    private function dispatchSpecial(array $handler): bool
    {
        $controller = new $handler['class']();
        $controller->{$handler['method']}();
        return true;
    }

    /**
     * Get all registered routes (for debugging/documentation).
     *
     * @return array<string, array<string, array{class: string, method: string}>>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
