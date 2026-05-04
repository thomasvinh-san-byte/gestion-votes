<?php

declare(strict_types=1);

namespace AgVote\Controller;

/**
 * Thrown by AdminErrorStatsController when a non-admin role attempts to access
 * the page. Used instead of HtmlView::text()/exit() so unit tests can assert
 * the 403 response without terminating the PHPUnit process.
 *
 * Source: ERR-V24-03 / D-10 — Plan 02.3 (Phase 2 v2.4).
 */
final class AdminErrorStatsForbiddenException extends \RuntimeException {
    public function __construct(string $message = 'Accès refusé.') {
        parent::__construct($message);
    }
}
