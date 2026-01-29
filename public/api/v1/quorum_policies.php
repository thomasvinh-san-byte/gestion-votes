<?php
require __DIR__ . '/../../../app/api.php';

api_request('GET');

global $pdo;
$stmt = $pdo->prepare(
  "SELECT id, name, description, mode, denominator, threshold, threshold_call2, denominator2, threshold2,
          include_proxies, count_remote, updated_at
   FROM quorum_policies
   WHERE tenant_id = :t
   ORDER BY name ASC"
);
$stmt->execute([':t' => DEFAULT_TENANT_ID]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

api_ok(['items' => $rows]);
