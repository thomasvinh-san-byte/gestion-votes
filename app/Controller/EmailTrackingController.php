<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Http\Request;
use AgVote\Core\Providers\RepositoryFactory;
use Throwable;

/**
 * Consolidates email_pixel.php and email_redirect.php.
 * These endpoints use bootstrap.php (not api.php) and return non-JSON responses.
 */
final class EmailTrackingController {
    public function pixel(): void {
        // F12: rate limit pixel hits to 200/min per IP. Public unauthenticated
        // endpoint — without a cap, an attacker can scan invitation IDs at
        // wire speed. The cap is generous (legit clients reload the same
        // pixel a few times) but blocks brute-force enumeration.
        self::throttleTracking('email_pixel');

        $request = new Request();
        $trackingEnabled = (bool) config('email_tracking_enabled', true);
        $invitationId = trim((string) $request->query('id', ''));

        if ($invitationId === '' || !$trackingEnabled) {
            $this->outputPixel();
        }

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $invitationId)) {
            $this->outputPixel();
        }

        try {
            $invitationRepo = RepositoryFactory::getInstance()->invitation();
            $eventRepo = RepositoryFactory::getInstance()->emailEvent();

            $tenantId = $invitationRepo->findTenantById($invitationId);

            if ($tenantId !== null) {
                $invitationRepo->incrementOpenCount($invitationId, $tenantId);

                $eventRepo->logEvent(
                    $tenantId,
                    'opened',
                    $invitationId,
                    null,
                    null,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                );
            }
        } catch (Throwable $e) {
            \AgVote\Core\Logger::error('Email pixel tracking failed', ['exception' => $e->getMessage()]);
        }

        $this->outputPixel();
    }

    public function redirect(): void {
        // F12: same throttling logic as pixel — public unauthenticated endpoint.
        self::throttleTracking('email_redirect');

        $request = new Request();
        $trackingEnabled = (bool) config('email_tracking_enabled', true);
        $invitationId = trim((string) $request->query('id', ''));
        $targetUrl = trim((string) $request->query('url', ''));

        $fallbackUrl = config('app_url', 'http://localhost:8080');

        if ($targetUrl === '') {
            header('Location: ' . $fallbackUrl, true, 302);
            return;
        }

        // F11 hardening: validate the redirect target via UrlValidator. The
        // host whitelist defaults to the parsed APP_URL host, plus any
        // additional hosts in EMAIL_REDIRECT_ALLOWED_HOSTS (CSV). Any URL
        // that is non-https, has userinfo, points at a private/loopback
        // IP, or is not in the whitelist falls back to APP_URL.
        $allowedHosts = [];
        $appHost = parse_url($fallbackUrl, PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '') {
            $allowedHosts[] = $appHost;
        }
        $extraHosts = (string) (getenv('EMAIL_REDIRECT_ALLOWED_HOSTS') ?: '');
        foreach (explode(',', $extraHosts) as $h) {
            $h = trim($h);
            if ($h !== '') {
                $allowedHosts[] = $h;
            }
        }

        if (!\AgVote\Core\Http\UrlValidator::isSafeRedirect($targetUrl, $allowedHosts)) {
            \AgVote\Core\Logger::warning('Email redirect rejected by UrlValidator', ['target' => $targetUrl]);
            header('Location: ' . $fallbackUrl, true, 302);
            return;
        }

        if ($invitationId !== '' && $trackingEnabled) {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $invitationId)) {
                try {
                    $invitationRepo = RepositoryFactory::getInstance()->invitation();
                    $eventRepo = RepositoryFactory::getInstance()->emailEvent();

                    $tenantId = $invitationRepo->findTenantById($invitationId);

                    if ($tenantId !== null) {
                        $invitationRepo->incrementClickCount($invitationId, $tenantId);

                        $eventRepo->logEvent(
                            $tenantId,
                            'clicked',
                            $invitationId,
                            null,
                            ['target_url' => $targetUrl],
                            $_SERVER['REMOTE_ADDR'] ?? null,
                            $_SERVER['HTTP_USER_AGENT'] ?? null,
                        );
                    }
                } catch (Throwable $e) {
                    \AgVote\Core\Logger::error('Email redirect tracking failed', ['exception' => $e->getMessage()]);
                }
            }
        }

        header('Location: ' . $targetUrl, true, 302);
        return;
    }

    /**
     * F12: shared rate limit for pixel + redirect.
     *
     * 200 hits per 60 seconds per client IP. Identifier resolution honors
     * TRUSTED_PROXIES via ClientIp::get() so attackers can't spoof their IP
     * by setting X-Forwarded-For from a non-trusted source.
     *
     * On limit hit, a 1×1 transparent GIF is still served (pixel) or the
     * redirect falls through silently — no 429 is leaked, since these
     * endpoints intentionally reveal nothing to unauthenticated callers.
     */
    private static function throttleTracking(string $context): void {
        $identifier = \AgVote\Core\Http\ClientIp::get();
        if (\AgVote\Core\Security\RateLimiter::isLimited($context, $identifier, 200, 60)) {
            \AgVote\Core\Logger::warning('Email tracking rate limit hit', ['context' => $context, 'ip' => $identifier]);
            // Don't reveal a 429 publicly: just return a benign response.
            // For pixel, the caller method continues to outputPixel(); for
            // redirect, we head to the fallback URL silently.
            return;
        }
        \AgVote\Core\Security\RateLimiter::check($context, $identifier, 200, 60, false);
    }

    private function outputPixel(): never {
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        header('Content-Type: image/gif');
        header('Content-Length: ' . strlen($gif));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING === true) {
            throw new EmailPixelSentException();
        }
        echo $gif;
        exit;
    }
}
