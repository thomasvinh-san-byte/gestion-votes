<?php

/**
 * _csrf_scripts.php - Scripts CSRF à inclure avant </body>
 *
 * Usage: <?php include __DIR__ . '/partials/_csrf_scripts.php'; ?>
 */

require_once __DIR__ . '/../app/Core/Security/CsrfMiddleware.php';

// Génère le snippet JS avec le token CSRF
echo CsrfMiddleware::jsSnippet();
