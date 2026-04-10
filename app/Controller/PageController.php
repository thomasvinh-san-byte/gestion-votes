<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Providers\SecurityProvider;

/**
 * Serves .htmx.html page shells through PHP for CSP nonce injection.
 *
 * Reads the static file, replaces %%CSP_NONCE%% placeholders with the
 * per-request nonce, and outputs the result as HTML.
 */
final class PageController {
    /** Pages that can be served via this controller. */
    private const PAGES = [
        'dashboard', 'wizard', 'hub', 'operator', 'postsession',
        'validate', 'archives', 'meetings', 'audit', 'members',
        'users', 'analytics', 'settings', 'admin', 'help',
        'email-templates', 'public', 'report', 'trust', 'docs',
    ];

    /**
     * Serve an .htmx.html file with CSP nonce injection.
     *
     * Extracts the page name from the first URI segment.
     */
    public static function serveFromUri(): void {
        $uri = trim(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), '/');

        // Handle /page/uuid patterns — extract first segment
        $page = explode('/', $uri)[0];

        if (!in_array($page, self::PAGES, true)) {
            http_response_code(404);
            return;
        }

        self::serve($page);
    }

    /**
     * Serve a specific .htmx.html page with nonce replacement.
     */
    public static function serve(string $page): void {
        $file = dirname(__DIR__, 2) . '/public/' . $page . '.htmx.html';
        if (!file_exists($file)) {
            http_response_code(404);
            return;
        }

        $cspNonce = SecurityProvider::nonce();
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        $html = file_get_contents($file);
        $html = str_replace('%%CSP_NONCE%%', $cspNonce, $html);
        echo $html;
    }
}
