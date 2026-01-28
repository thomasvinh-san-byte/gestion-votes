<?php
// public/api/v1/ballots_cast.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/BallotsService.php';

try {
    api_require_role('public');
    $data = api_request('POST');
    $ballot = BallotsService::castBallot($data);

    api_ok(['ballot' => $ballot], 201);
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in ballots_cast.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
