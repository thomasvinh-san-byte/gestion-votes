<?php
// public/api/v1/motions_close.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

api_require_role('operator');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_err('method_not_allowed', 405);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$motionId = trim((string)($input['motion_id'] ?? ''));
if ($motionId === '' || !api_is_uuid($motionId)) {
    json_err('invalid_motion_id', 422);
    exit;
}

try {
    $motion = db_select_one(
        "SELECT id, meeting_id, opened_at, closed_at
         FROM motions
         WHERE tenant_id=:tid AND id=:id",
        [':tid'=>DEFAULT_TENANT_ID, ':id'=>$motionId]
    );
    if (!$motion) { json_err('motion_not_found', 404); exit; }

    if (empty($motion['opened_at'])) {
        json_err('motion_not_open', 409);
        exit;
    }
    if (!empty($motion['closed_at'])) {
        json_err('motion_already_closed', 409);
        exit;
    }

    global $pdo;
    $pdo->beginTransaction();

    db_execute(
        "UPDATE motions
         SET closed_at = now()
         WHERE tenant_id=:tid AND id=:id AND closed_at IS NULL",
        [':tid'=>DEFAULT_TENANT_ID, ':id'=>$motionId]
    );

    // Compute/freeze results (official)
    require_once __DIR__ . '/../../../app/services/OfficialResultsService.php';
    OfficialResultsService::computeAndPersistMotion((string)$motionId);

    $pdo->commit();

    audit_log('motion_closed', 'motion', $motionId, [
        'meeting_id' => (string)$motion['meeting_id'],
    ]);

    json_ok([
        'meeting_id'       => (string)$motion['meeting_id'],
        'closed_motion_id' => $motionId,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    json_err('motion_close_failed', 500, ['detail' => $e->getMessage()]);
}
