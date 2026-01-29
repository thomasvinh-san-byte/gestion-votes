<?php
/**
 * _csrf_head.php - Partial à inclure dans le <head> de chaque page HTMX
 * 
 * Usage: <?php include __DIR__ . '/partials/_csrf_head.php'; ?>
 */

require_once __DIR__ . '/../app/Core/Security/CsrfMiddleware.php';

// Génère le meta tag CSRF
echo CsrfMiddleware::metaTag();
