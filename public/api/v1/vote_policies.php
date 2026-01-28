<?php
// Liste des politiques de vote (majorité) du tenant
require __DIR__ . '/../../../app/api.php';

api_request('GET');

global $pdo;
$stmt = $pdo->prepare(
  "SELECT id, name, description, base, threshold, abstention_as_against, updated_at
   FROM vote_policies
   WHERE tenant_id = :t
   ORDER BY name ASC"
);
$stmt->execute([':t' => DEFAULT_TENANT_ID]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

api_ok(['items' => $rows]);
