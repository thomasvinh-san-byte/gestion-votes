<?php
declare(strict_types=1);

namespace AgVote\Core\Http;

/**
 * Encapsulates a JSON HTTP response as a value object.
 *
 * Used by api_ok() / api_fail() and eventually as the standard return
 * type for controller actions.
 */
final class JsonResponse
{
    /** @param array<string, mixed> $body */
    public function __construct(
        private readonly int $statusCode,
        private readonly array $body,
        private readonly array $headers = [],
    ) {}

    /**
     * Build a success response.
     */
    public static function ok(array $data = [], int $code = 200): self
    {
        return new self($code, ['ok' => true, 'data' => $data]);
    }

    /**
     * Build an error response with optional French enrichment.
     */
    public static function fail(string $error, int $code = 400, array $extra = []): self
    {
        // Strip internal details from 5xx in non-development
        $appEnv = $_ENV['APP_ENV'] ?? 'demo';
        if ($appEnv !== 'development' && $code >= 500) {
            error_log("[api_fail] $error: " . ($extra['detail'] ?? '(no detail)'));
            unset($extra['detail']);
        }

        $enriched = \AgVote\Service\ErrorDictionary::enrichError($error, $extra);

        return new self($code, ['ok' => false, 'error' => $error] + $enriched);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * Send the response to the client (headers + body).
     */
    public function send(): void
    {
        http_response_code($this->statusCode);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo json_encode($this->body, JSON_UNESCAPED_UNICODE);
    }
}
