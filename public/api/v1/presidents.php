<?php
// public/api/v1/presidents.php
// Liste des personnes pouvant être sélectionnées comme Président
// (basé sur la table members pour le tenant courant).

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MemberRepository;

api_require_role('auditor');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_fail('method_not_allowed', 405);
}

try {
    $repo = new MemberRepository();
    $rows = $repo->listActiveForPresident(api_current_tenant_id());

    api_ok(['presidents' => $rows]);
} catch (PDOException $e) {
    error_log("Database error in presidents.php: " . $e->getMessage());
    api_fail('database_error', 500, ['detail' => 'Erreur de base de données']);
} catch (Throwable $e) {
    error_log("Unexpected error in presidents.php: " . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
