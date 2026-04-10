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
            error_log('Email pixel tracking error: ' . $e->getMessage());
        }

        $this->outputPixel();
    }

    public function redirect(): void {
        $request = new Request();
        $trackingEnabled = (bool) config('email_tracking_enabled', true);
        $invitationId = trim((string) $request->query('id', ''));
        $targetUrl = trim((string) $request->query('url', ''));

        $fallbackUrl = config('app_url', 'http://localhost:8080');

        if ($targetUrl === '') {
            header('Location: ' . $fallbackUrl, true, 302);
            return;
        }

        $parsedUrl = parse_url($targetUrl);
        if (!$parsedUrl || !isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], ['http', 'https'], true)) {
            header('Location: ' . $fallbackUrl, true, 302);
            return;
        }

        $allowedHost = parse_url($fallbackUrl, PHP_URL_HOST) ?: 'localhost';
        $targetHost = $parsedUrl['host'] ?? '';
        // Block empty host (protocol-relative URLs like //attacker.com) and mismatched hosts
        if ($targetHost === '' || $targetHost !== $allowedHost) {
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
                    error_log('Email redirect tracking error: ' . $e->getMessage());
                }
            }
        }

        header('Location: ' . $targetUrl, true, 302);
        return;
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
