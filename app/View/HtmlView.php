<?php

declare(strict_types=1);

namespace AgVote\View;

/**
 * Lightweight HTML view renderer.
 *
 * Renders PHP templates from app/Templates/ with extracted variables.
 * Used by the two public HTML endpoints (vote, doc) instead of JsonResponse.
 */
final class HtmlView {
    /**
     * Render a PHP template and output HTML.
     *
     * @param string $template Template name (without .php), relative to app/Templates/
     * @param array $data Variables to extract into the template scope
     * @param int $statusCode HTTP status code
     */
    public static function render(string $template, array $data = [], int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
        extract($data, EXTR_SKIP);
        require dirname(__DIR__) . '/Templates/' . $template . '.php';
    }

    /**
     * Send a plain text response and terminate.
     */
    public static function text(string $message, int $statusCode = 200): never {
        http_response_code($statusCode);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
        exit;
    }
}
