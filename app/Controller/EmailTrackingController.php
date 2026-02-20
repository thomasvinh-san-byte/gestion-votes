<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\InvitationRepository;
use AgVote\Repository\EmailEventRepository;

/**
 * Consolidates email_pixel.php and email_redirect.php.
 * These endpoints use bootstrap.php (not api.php) and return non-JSON responses.
 */
final class EmailTrackingController
{
    public function pixel(): void
    {
        $trackingEnabled = getenv('EMAIL_TRACKING_ENABLED') !== '0';
        $invitationId = trim((string)($_GET['id'] ?? ''));

        if ($invitationId === '' || !$trackingEnabled) {
            $this->outputPixel();
        }

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $invitationId)) {
            $this->outputPixel();
        }

        try {
            $invitationRepo = new InvitationRepository();
            $eventRepo = new EmailEventRepository();

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
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                );
            }
        } catch (\AgVote\Core\Http\ApiResponseException $__apiResp) { throw $__apiResp;
        } catch (\Throwable $e) {
            error_log('Email pixel tracking error: ' . $e->getMessage());
        }

        $this->outputPixel();
    }

    public function redirect(): void
    {
        $trackingEnabled = getenv('EMAIL_TRACKING_ENABLED') !== '0';
        $invitationId = trim((string)($_GET['id'] ?? ''));
        $targetUrl = trim((string)($_GET['url'] ?? ''));

        $fallbackUrl = getenv('APP_URL') ?: 'http://localhost:8080';

        if ($targetUrl === '') {
            header('Location: ' . $fallbackUrl, true, 302);
            exit;
        }

        $parsedUrl = parse_url($targetUrl);
        if (!$parsedUrl || !isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], ['http', 'https'], true)) {
            header('Location: ' . $fallbackUrl, true, 302);
            exit;
        }

        $allowedHost = parse_url($fallbackUrl, PHP_URL_HOST) ?: 'localhost';
        $targetHost = $parsedUrl['host'] ?? '';
        if ($targetHost !== '' && $targetHost !== $allowedHost) {
            header('Location: ' . $fallbackUrl, true, 302);
            exit;
        }

        if ($invitationId !== '' && $trackingEnabled) {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $invitationId)) {
                try {
                    $invitationRepo = new InvitationRepository();
                    $eventRepo = new EmailEventRepository();

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
                            $_SERVER['HTTP_USER_AGENT'] ?? null
                        );
                    }
                } catch (\AgVote\Core\Http\ApiResponseException $__apiResp) { throw $__apiResp;
        } catch (\Throwable $e) {
                    error_log('Email redirect tracking error: ' . $e->getMessage());
                }
            }
        }

        header('Location: ' . $targetUrl, true, 302);
        exit;
    }

    private function outputPixel(): never
    {
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        header('Content-Type: image/gif');
        header('Content-Length: ' . strlen($gif));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $gif;
        exit;
    }
}
