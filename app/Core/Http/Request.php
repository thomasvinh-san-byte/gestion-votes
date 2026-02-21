<?php

declare(strict_types=1);

namespace AgVote\Core\Http;

use AgVote\Core\Security\AuthMiddleware;

/**
 * Encapsulates the current HTTP request.
 *
 * Wraps $_GET, $_POST, $_SERVER, and the JSON body into a single object.
 * Replaces the global api_request() / api_method() / api_require_uuid()
 * functions with an OOP interface.
 */
final class Request {
    /** @var string|null Cached raw body (php://input can only be read once) */
    private static ?string $cachedRawBody = null;

    private string $method;
    private array $query;
    private array $body;
    private array $server;
    private string $rawBody;

    public function __construct() {
        $this->server = $_SERVER;
        $this->query = $_GET;
        $this->method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        $this->rawBody = self::getRawBody();

        $decoded = json_decode($this->rawBody, true);
        $this->body = is_array($decoded) ? $decoded : $_POST;
    }

    /**
     * Get the raw request body, caching it for reuse.
     * Central source of truth — replaces $GLOBALS['__ag_vote_raw_body'].
     */
    public static function getRawBody(): string {
        if (self::$cachedRawBody === null) {
            self::$cachedRawBody = file_get_contents('php://input') ?: '';
        }
        return self::$cachedRawBody;
    }

    // ── HTTP method ─────────────────────────────────────────────────────

    public function method(): string {
        return $this->method;
    }

    public function isMethod(string ...$methods): bool {
        $upper = array_map('strtoupper', $methods);
        return in_array($this->method, $upper, true);
    }

    /**
     * Validate that the HTTP method is one of the allowed methods.
     * Throws ApiResponseException (405) on mismatch.
     *
     * @return $this for chaining: $req->validate('GET', 'POST')->all()
     */
    public function validate(string ...$methods): self {
        if (!$this->isMethod(...$methods)) {
            throw new ApiResponseException(
                JsonResponse::fail('method_not_allowed', 405, [
                    'detail' => "Méthode {$this->method} non autorisée, "
                        . implode('/', $methods) . ' attendu.',
                ]),
            );
        }
        return $this;
    }

    // ── Input access ────────────────────────────────────────────────────

    /**
     * Get all input (query + body merged).
     */
    public function all(): array {
        return array_merge($this->query, $this->body);
    }

    /**
     * Get a single input value from merged query+body.
     */
    public function input(string $key, mixed $default = null): mixed {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Get query string parameter(s).
     */
    public function query(?string $key = null, mixed $default = null): mixed {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    /**
     * Get request body parameter(s).
     */
    public function body(?string $key = null, mixed $default = null): mixed {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    /**
     * Get a request header value.
     */
    public function header(string $name): ?string {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? null;
    }

    /**
     * Get the raw request body string.
     */
    public function rawBody(): string {
        return $this->rawBody;
    }

    /**
     * Get a $_SERVER variable.
     */
    public function server(string $key): mixed {
        return $this->server[$key] ?? null;
    }

    // ── Validation helpers ──────────────────────────────────────────────

    /**
     * Require and validate a UUID field from input.
     * Throws ApiResponseException (400) if missing or invalid.
     */
    public function requireUuid(string $key): string {
        $all = $this->all();
        $v = trim((string) ($all[$key] ?? ''));
        if ($v === '' || !preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $v,
        )) {
            throw new ApiResponseException(
                JsonResponse::fail('missing_or_invalid_uuid', 400, [
                    'field' => $key,
                    'expected' => 'uuid',
                ]),
            );
        }
        return $v;
    }

    // ── Auth context (delegates to existing AuthMiddleware) ─────────────

    public function user(): ?array {
        return AuthMiddleware::getCurrentUser();
    }

    public function userId(): ?string {
        return AuthMiddleware::getCurrentUserId();
    }

    public function role(): string {
        return AuthMiddleware::getCurrentRole();
    }

    public function tenantId(): string {
        return AuthMiddleware::getCurrentTenantId();
    }
}
