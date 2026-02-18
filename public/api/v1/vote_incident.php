<?php
require __DIR__ . '/../../../app/api.php';

api_require_role('public');

$in = api_request('POST');

$kind = trim((string)($in['kind'] ?? 'network'));
$detail = trim((string)($in['detail'] ?? ''));
$tokenHash = trim((string)($in['token_hash'] ?? ''));

if ($kind === '') api_fail('missing_kind', 400);

try {
  if (function_exists('audit_log')) {
    audit_log('vote_incident', 'vote', $tokenHash ?: null, [
      'kind' => $kind,
      'detail' => $detail,
    ]);
  }
} catch (Throwable $e) {}

api_ok(['saved' => true]);
