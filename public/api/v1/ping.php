<?php
declare(strict_types=1);

/**
 * ping.php - Health check endpoint
 *
 * GET /api/v1/ping.php
 *
 * Public endpoint for monitoring. Rate-limited to prevent abuse.
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Core\Security\RateLimiter;

// Rate limit: 60 requests per minute per IP
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!RateLimiter::check('ping', $ip, 60, 60, false)) {
    api_fail('rate_limit_exceeded', 429);
}

api_ok(['ts' => date('c')]);
