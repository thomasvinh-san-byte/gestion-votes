<?php
// public/api/v1/ballots.php - Liste les bulletins de vote d'une motion
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\BallotRepository;

api_require_role(['operator', 'admin', 'president']);
api_request('GET');

$motionId = trim((string)($_GET['motion_id'] ?? ''));
if ($motionId === '' || !api_is_uuid($motionId)) {
    api_fail('missing_motion_id', 422, ['detail' => 'motion_id requis']);
}

try {
    $repo = new BallotRepository();
    $ballots = $repo->listForMotion($motionId);

    api_ok(['ballots' => $ballots]);
} catch (Throwable $e) {
    error_log('Error in ballots.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne']);
}
