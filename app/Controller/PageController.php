<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Providers\SecurityProvider;
use AgVote\Core\Security\AuthMiddleware;

/**
 * Serves .htmx.html page shells through PHP for CSP nonce injection.
 *
 * Reads the static file, replaces %%CSP_NONCE%% placeholders with the
 * per-request nonce, and outputs the result as HTML.
 */
final class PageController {
    /** Application version displayed across all pages. */
    public const APP_VERSION = 'v2.0';

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
        $html = str_replace('%%APP_VERSION%%', self::APP_VERSION, $html);

        // v2.2 (DESIGN-P03): inject role on <body data-persona="..."> for the
        // 3px persona bar + sidebar badge. Read from session via AuthMiddleware.
        // Fallback to "guest" when no session — used by public pages.
        $role = self::resolveCurrentRole();
        $html = preg_replace(
            '/<body(?![^>]*data-persona)/i',
            '<body data-persona="' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . '"',
            $html,
            1,
        );
        $html = str_replace('%%PERSONA_LABEL%%', self::personaLabel($role), $html);

        echo $html;
    }

    /**
     * v2.2 (DESIGN-P02): label affiché dans le badge sidebar.
     */
    private static function personaLabel(string $role): string {
        return match ($role) {
            'admin'     => 'Admin',
            'operator'  => 'Opérateur',
            'president' => 'Président',
            'auditor'   => 'Auditeur',
            'voter'     => 'Votant',
            'public'    => 'Observateur',
            default     => '',
        };
    }

    /**
     * v2.2: map AuthMiddleware role → CSS persona token name.
     * Roles in DB: admin, operator, president, auditor, voter, viewer.
     * Returns "guest" when no session (public pages).
     */
    private static function resolveCurrentRole(): string {
        try {
            $role = AuthMiddleware::getCurrentRole();
        } catch (\Throwable) {
            return 'guest';
        }
        // Map DB roles to persona tokens. "viewer" maps to "public" for the
        // mauve doux accent. Any unknown role falls back to "guest".
        return match ($role) {
            'admin'     => 'admin',
            'operator'  => 'operator',
            'president' => 'president',
            'auditor'   => 'auditor',
            'voter'     => 'voter',
            'viewer', 'public' => 'public',
            default     => 'guest',
        };
    }
}
