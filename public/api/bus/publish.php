<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

api_require_role('operator');
$body = api_request('POST');

$type = $body['type'] ?? null;
$payload = $body['payload'] ?? null;

if (!$type) {
    api_fail('missing_type', 422);
}

$evt = [
    'id' => bin2hex(random_bytes(16)),
    'tenant_id' => api_current_tenant_id(),
    'type' => $type,
    'payload' => $payload,
    'ts' => time(),
];

$file = __DIR__ . '/events.jsonl';
file_put_contents($file, json_encode($evt) . "\n", FILE_APPEND | LOCK_EX);

api_ok(['event' => $evt]);
