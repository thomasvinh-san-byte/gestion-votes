<?php
// public/api/v1/motions_close.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MotionRepository;
use AgVote\Service\OfficialResultsService;

api_require_role('operator');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    api_fail('method_not_allowed', 405);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$motionId = trim((string)($input['motion_id'] ?? ''));
if ($motionId === '' || !api_is_uuid($motionId)) {
    api_fail('invalid_motion_id', 422);
    exit;
}

try {
    $repo = new MotionRepository();
    $motion = $repo->findByIdForTenant($motionId, api_current_tenant_id());
    if (!$motion) { api_fail('motion_not_found', 404); exit; }

    if (empty($motion['opened_at'])) {
        api_fail('motion_not_open', 409);
        exit;
    }
    if (!empty($motion['closed_at'])) {
        api_fail('motion_already_closed', 409);
        exit;
    }

    db()->beginTransaction();

    $repo->markClosed($motionId, api_current_tenant_id());

    // Compute/freeze results (official)
    OfficialResultsService::computeAndPersistMotion((string)$motionId);

    db()->commit();

    audit_log('motion_closed', 'motion', $motionId, [
        'meeting_id' => (string)$motion['meeting_id'],
    ]);

    api_ok([
        'meeting_id'       => (string)$motion['meeting_id'],
        'closed_motion_id' => $motionId,
    ]);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    api_fail('motion_close_failed', 500, ['detail' => $e->getMessage()]);
}
