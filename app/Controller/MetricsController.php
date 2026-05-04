<?php

declare(strict_types=1);

namespace AgVote\Controller;

/**
 * Lightweight metrics ingestion for UX instrumentation.
 *
 * Public-ish but tenant-bound (uses session tenant if present, NULL otherwise).
 * Body validation is permissive — we'd rather lose noisy clicks than 4xx the
 * frontend over a typo. Rate-limited via the route config.
 */
final class MetricsController extends AbstractController {
    public function nextStepClicked(): void {
        $body = api_request('POST');
        $errorCode = trim((string) ($body['error_code'] ?? ''));
        if ($errorCode === '' || strlen($errorCode) > 80) {
            api_fail('invalid_request', 422, ['field' => 'error_code']);
        }
        $suggestion = (string) ($body['suggestion'] ?? '');
        if (strlen($suggestion) > 500) {
            $suggestion = substr($suggestion, 0, 500);
        }

        $this->repo()->nextStepClick()->capture(
            $errorCode,
            $suggestion === '' ? null : $suggestion,
            api_current_tenant_id() ?: null,
            api_current_user_id() ?: null,
            $_SERVER['REQUEST_URI'] ?? null,
            \AgVote\Core\Logger::getRequestId(),
        );
        api_ok(['recorded' => true]);
    }
}
