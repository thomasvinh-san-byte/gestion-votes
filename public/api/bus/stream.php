<?php
declare(strict_types=1);

/**
 * SSE stream endpoint — authenticated, tenant-scoped.
 *
 * Requires operator/admin/president session or valid API key.
 * Only streams events matching the authenticated user's tenant.
 */

require __DIR__ . '/../../../app/api.php';

// Auth: operator, admin, or president can subscribe to the event stream
api_require_role(['operator', 'admin', 'president']);

$tenantId = api_current_tenant_id();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

$file = __DIR__ . '/events.jsonl';
$start = time();

while (true) {
    if (is_readable($file)) {
        $fh = fopen($file, 'r');
        if ($fh) {
            while (($line = fgets($fh)) !== false) {
                $evt = json_decode($line, true);
                if (!is_array($evt)) continue;

                // Tenant isolation: skip events from other tenants
                if (isset($evt['tenant_id']) && $evt['tenant_id'] !== $tenantId) {
                    continue;
                }

                $type = $evt['type'] ?? 'message';
                $payload = $evt['payload'] ?? new \stdClass();
                echo 'event: ' . $type . "\n";
                echo 'data: ' . json_encode($payload) . "\n\n";
                @ob_flush();
                @flush();
            }
            fclose($fh);
        }
    }

    echo "event: ping\ndata: {}\n\n";
    @ob_flush();
    @flush();

    if (time() - $start > 25) break;
    sleep(1);
}
