<?php
/**
 * GET /api/v1/audit_export.php?meeting_id={uuid}&format=csv|json
 *
 * Exporte le journal d'audit d'une séance.
 * - format=csv (défaut) : fichier CSV téléchargeable
 * - format=json : document JSON structuré avec chaîne d'intégrité
 */
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;

$q = api_request('GET');
$meetingId = api_require_uuid($q, 'meeting_id');
$format = strtolower(trim((string)($_GET['format'] ?? 'csv')));

$repo = new MeetingRepository();
$tenantId = api_current_tenant_id();

// Verify meeting exists
$meeting = $repo->findByIdForTenant($meetingId, $tenantId);
if (!$meeting) {
    api_fail('meeting_not_found', 404);
}

$events = $repo->listAuditEventsForExport($tenantId, $meetingId);

// ─── JSON format ──────────────────────────────────────────────────
if ($format === 'json') {
    // Enrich events with parsed payload and hash chain
    $jsonEvents = [];
    foreach ($events as $e) {
        $payload = [];
        if (!empty($e['payload'])) {
            $payload = is_string($e['payload'])
                ? (json_decode($e['payload'], true) ?? [])
                : (array)$e['payload'];
        }

        $jsonEvents[] = [
            'timestamp'     => $e['created_at'],
            'action'        => $e['action'],
            'actor_role'    => $e['actor_role'],
            'actor_user_id' => $e['actor_user_id'],
            'resource_type' => $e['resource_type'],
            'resource_id'   => $e['resource_id'],
            'payload'       => $payload,
            'prev_hash'     => $e['prev_hash'] ?? null,
            'this_hash'     => $e['this_hash'] ?? null,
        ];
    }

    // Verify hash chain integrity
    $chainValid = true;
    $chainErrors = [];
    for ($i = 1; $i < count($jsonEvents); $i++) {
        $prev = $jsonEvents[$i - 1]['this_hash'] ?? null;
        $curr = $jsonEvents[$i]['prev_hash'] ?? null;
        if ($prev !== null && $curr !== null && $prev !== $curr) {
            $chainValid = false;
            $chainErrors[] = [
                'index' => $i,
                'expected_prev' => $prev,
                'actual_prev'   => $curr,
            ];
        }
    }

    $slug = $meeting['slug'] ?? $meetingId;
    $filename = "audit_{$slug}_" . date('Ymd_His') . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo json_encode([
        'export_format'  => 'ag-vote-audit-v1',
        'exported_at'    => date('c'),
        'meeting_id'     => $meetingId,
        'meeting_title'  => $meeting['title'] ?? '',
        'meeting_status' => $meeting['status'] ?? '',
        'total_events'   => count($jsonEvents),
        'chain_integrity' => [
            'valid'  => $chainValid,
            'errors' => $chainErrors,
        ],
        'events' => $jsonEvents,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── CSV format (default) ─────────────────────────────────────────
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="audit_'.$meetingId.'.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['created_at','actor_role','actor_user_id','action','resource_type','resource_id','payload']);
foreach ($events as $e) {
    fputcsv($out, [
      $e['created_at'],
      $e['actor_role'],
      $e['actor_user_id'],
      $e['action'],
      $e['resource_type'],
      $e['resource_id'],
      is_string($e['payload']) ? $e['payload'] : json_encode($e['payload']),
    ]);
}
fclose($out);
exit;
