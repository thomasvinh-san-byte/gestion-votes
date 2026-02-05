<?php
// public/api/v1/dev_seed_attendances.php
// Dev endpoint: seed demo attendances for a meeting
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MemberRepository;
use AgVote\Repository\AttendanceRepository;

// Block this endpoint in production environments
$env = getenv('APP_ENV') ?: 'dev';
if (in_array($env, ['production', 'prod'], true)) {
    api_fail('endpoint_disabled', 403, [
        'detail' => 'Cet endpoint de développement est désactivé en production.',
    ]);
}

try {
    api_require_role('operator');
    $in = api_request('POST');
    $meetingId = trim((string)($in['meeting_id'] ?? ''));
    if ($meetingId === '') throw new InvalidArgumentException('meeting_id requis');

    $modes = ['present', 'remote', 'absent'];
    $presentRatio = (float)($in['present_ratio'] ?? 0.7);

    $memberRepo     = new MemberRepository();
    $attendanceRepo = new AttendanceRepository();

    // Get all active members
    $members = $memberRepo->listActiveIds(api_current_tenant_id());

    if (empty($members)) {
        api_fail('no_members', 400, ['detail' => 'Aucun membre actif trouvé. Créez des membres d\'abord.']);
    }

    $created = 0;
    foreach ($members as $m) {
        $rand = mt_rand(1, 100) / 100.0;
        if ($rand <= $presentRatio) {
            $mode = (mt_rand(1, 10) <= 8) ? 'present' : 'remote';
        } else {
            $mode = 'absent';
        }

        if ($mode === 'absent') continue;

        $id = api_uuid4();
        try {
            $attendanceRepo->upsertSeed($id, api_current_tenant_id(), $meetingId, $m['id'], $mode);
            $created++;
        } catch (Throwable $e) {
            // skip errors
        }
    }

    api_ok(['created' => $created, 'total_members' => count($members)]);
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in dev_seed_attendances.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne']);
}
