<?php
// public/api/v1/presidents.php
// Liste des personnes pouvant être sélectionnées comme Président
// (basé sur la table members pour le tenant courant).

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MemberRepository;

api_require_role('auditor');

api_request('GET');

try {
    $repo = new MemberRepository();
    $rows = $repo->listActiveForPresident(api_current_tenant_id());

    api_ok(['presidents' => $rows]);
} catch (Throwable $e) {
    error_log('Error in presidents.php: ' . $e->getMessage());
    api_fail('server_error', 500, ['detail' => $e->getMessage()]);
}
