<?php
// public/api/v1/members.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

api_require_role('operator');

require __DIR__ . '/../../../app/services/MembersService.php';

try {
    api_request('GET'); // Valide la méthode

    $members = MembersService::getActiveMembers();

    api_ok(['members' => $members]);
} catch (Throwable $e) {
    error_log('Error in members.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
