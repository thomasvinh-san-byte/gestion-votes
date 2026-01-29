<?php
// public/api/v1/ballots_result.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/VoteEngine.php';

try {
    $params = api_request('GET');
    $motionId = trim((string)($params['motion_id'] ?? ''));

    if ($motionId === '') {
        api_fail('missing_motion_id', 422, ['detail' => 'Paramètre motion_id obligatoire']);
    }

    $result = VoteEngine::computeMotionResult($motionId);

    api_ok($result);
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in ballots_result.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
