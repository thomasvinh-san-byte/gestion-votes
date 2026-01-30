<?php
// public/api/v1/meetings_archive.php
require __DIR__ . '/../../../app/api.php';

api_require_role('operator');


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_fail('method_not_allowed', 405);
}

// Filtres optionnels : from, to (YYYY-MM-DD)
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to']   ?? '');

// Construire la clause WHERE dynamiquement
$conditions = [
    'tenant_id = :tenant_id',
    "status IN ('closed','archived')"
];
$params = [
    'tenant_id' => api_current_tenant_id(),
];

if ($from !== '') {
    $conditions[]   = 'created_at >= :from';
    $params['from'] = $from . ' 00:00:00';
}

if ($to !== '') {
    $conditions[] = 'created_at <= :to';
    $params['to'] = $to . ' 23:59:59';
}

$whereSql = implode(' AND ', $conditions);

try {
    global $pdo;
    $sql = "
        SELECT
          id,
          title,
          status,
          created_at,
          validated_by,
          validated_at
        FROM meetings
        WHERE $whereSql
        ORDER BY created_at DESC
        LIMIT 500
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    api_ok(['meetings' => $rows]);
} catch (PDOException $e) {
    api_fail('database_error', 500, ['detail' => $e->getMessage()]);
}
