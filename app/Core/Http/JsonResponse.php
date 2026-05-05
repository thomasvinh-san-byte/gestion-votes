<?php

declare(strict_types=1);

namespace AgVote\Core\Http;

/**
 * Encapsulates a JSON HTTP response as a value object.
 *
 * Used by api_ok() / api_fail() and eventually as the standard return
 * type for controller actions.
 */
final class JsonResponse {
    /** @param array<string, mixed> $body */
    public function __construct(
        private readonly int $statusCode,
        private readonly array $body,
        private readonly array $headers = [],
    ) {
    }

    /**
     * Build a success response.
     */
    public static function ok(array $data = [], int $code = 200): self {
        return new self($code, ['ok' => true, 'data' => $data]);
    }

    /**
     * Build an error response with optional French enrichment.
     */
    public static function fail(string $error, int $code = 400, array $extra = []): self {
        // Strip internal details from 5xx in non-development
        $appEnv = $_ENV['APP_ENV'] ?? 'demo';
        if ($appEnv !== 'development' && $code >= 500) {
            \AgVote\Core\Logger::error('api_fail emitted server error', [
                'error_code' => $error,
                'detail' => $extra['detail'] ?? '(no detail)',
                'http_status' => $code,
            ]);
            unset($extra['detail']);
        }

        $enriched = \AgVote\Service\ErrorDictionary::enrichError($error, $extra);

        // Expose request_id on 5xx so users can give support a grep-able handle
        // into error_events / log files. 4xx are user-actionable, no debug
        // handle needed.
        $body = ['ok' => false, 'error' => $error] + $enriched;
        if ($code >= 500) {
            $body['request_id'] = \AgVote\Core\Logger::getRequestId();
        }

        return new self($code, $body);
    }

    public function getStatusCode(): int {
        return $this->statusCode;
    }

    public function getBody(): array {
        return $this->body;
    }

    /**
     * @return array<string, string> Optional response headers (e.g. ETag, Cache-Control).
     */
    public function getHeaders(): array {
        return $this->headers;
    }

    /**
     * Send the response to the client (headers + body).
     *
     * Status 304 (Not Modified) and 204 (No Content) MUST NOT include a body
     * per RFC 7232 §4.1 / RFC 7230 §3.3.2 — body emission is skipped along with
     * the Content-Type header so caches behave correctly.
     */
    public function send(): void {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        if ($this->statusCode === 304 || $this->statusCode === 204) {
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->body, JSON_UNESCAPED_UNICODE);
    }
}
