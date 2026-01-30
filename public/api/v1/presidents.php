<?php
// public/api/v1/presidents.php
// Liste des personnes pouvant être sélectionnées comme Président
// (basé sur la table members pour le tenant courant).

require __DIR__ . '/../../../app/api.php';

api_require_role('auditor');


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_err('method_not_allowed', 405);
}

try {
    global $pdo;

    $sql = "
        SELECT
            id,
            full_name,
            email,
            role
        FROM members
        WHERE tenant_id = :tenant_id
          AND is_active = true
        ORDER BY full_name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':tenant_id' => DEFAULT_TENANT_ID]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === false) {
        $rows = [];
    }

    json_ok(['presidents' => $rows]);
} catch (PDOException $e) {
    error_log("Database error in presidents.php: " . $e->getMessage());
    json_err('database_error', 500, ['detail' => 'Erreur de base de données']);
} catch (Throwable $e) {
    error_log("Unexpected error in presidents.php: " . $e->getMessage());
    json_err('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}