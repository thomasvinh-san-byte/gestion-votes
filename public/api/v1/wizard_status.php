<?php
// public/api/v1/wizard_status.php
// Endpoint léger pour le polling du wizard de séance.
// Retourne l'état synthétique d'une séance en un seul appel.
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

api_require_role('viewer'); // any authenticated user

$meetingId = trim($_GET['meeting_id'] ?? '');
if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 422);
}

$tenantId = api_current_tenant_id();
$pdo = db();

try {
    // Meeting basics
    $meeting = $pdo->prepare(
        "SELECT id, title, status, vote_policy_id, quorum_policy_id, current_motion_id
         FROM meetings WHERE id = :id AND tenant_id = :tid"
    );
    $meeting->execute([':id' => $meetingId, ':tid' => $tenantId]);
    $m = $meeting->fetch(\PDO::FETCH_ASSOC);

    if (!$m) {
        api_fail('meeting_not_found', 404);
    }

    // Members count: enrolled in this meeting (via attendances table)
    $members = $pdo->prepare(
        "SELECT COUNT(*) FROM attendances WHERE meeting_id = :mid"
    );
    $members->execute([':mid' => $meetingId]);
    $membersCount = (int)$members->fetchColumn();

    // Fallback: if no attendances yet, count all active members for tenant
    if ($membersCount === 0) {
        $membersAll = $pdo->prepare("SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND is_active = true");
        $membersAll->execute([':tid' => $tenantId]);
        $membersCount = (int)$membersAll->fetchColumn();
    }

    // Attendance: present count (any mode except absent/null)
    $att = $pdo->prepare(
        "SELECT COUNT(*) FROM attendances WHERE meeting_id = :mid AND mode IN ('present','remote','proxy')"
    );
    $att->execute([':mid' => $meetingId]);
    $presentCount = (int)$att->fetchColumn();

    // Motions counts
    $motions = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN closed_at IS NOT NULL THEN 1 ELSE 0 END) AS closed
         FROM motions WHERE meeting_id = :mid"
    );
    $motions->execute([':mid' => $meetingId]);
    $mc = $motions->fetch(\PDO::FETCH_ASSOC);
    $motionsTotal = (int)($mc['total'] ?? 0);
    $motionsClosed = (int)($mc['closed'] ?? 0);

    // President assigned?
    $pres = $pdo->prepare(
        "SELECT 1 FROM meeting_roles
         WHERE meeting_id = :mid AND role = 'president' AND revoked_at IS NULL
         LIMIT 1"
    );
    $pres->execute([':mid' => $meetingId]);
    $hasPresident = (bool)$pres->fetchColumn();

    // Quorum — simplified check
    $quorumMet = false;
    if ($membersCount > 0) {
        // Use quorum_status endpoint logic simplified: present / eligible
        $ratio = $presentCount / $membersCount;
        // Default threshold 0 (no quorum required) if no policy
        $quorumMet = $ratio > 0;
        if ($m['quorum_policy_id']) {
            $qp = $pdo->prepare("SELECT threshold FROM quorum_policies WHERE id = :id");
            $qp->execute([':id' => $m['quorum_policy_id']]);
            $threshold = $qp->fetchColumn();
            if ($threshold !== false) {
                $quorumMet = $ratio >= (float)$threshold;
            }
        }
    }

    api_ok([
        'meeting_id'        => $m['id'],
        'meeting_title'     => $m['title'],
        'meeting_status'    => $m['status'],
        'current_motion_id' => $m['current_motion_id'],
        'members_count'     => $membersCount,
        'present_count'     => $presentCount,
        'motions_total'     => $motionsTotal,
        'motions_closed'    => $motionsClosed,
        'has_president'     => $hasPresident,
        'quorum_met'        => $quorumMet,
        'policies_assigned' => !empty($m['vote_policy_id']) && !empty($m['quorum_policy_id']),
    ]);
} catch (Throwable $e) {
    error_log("wizard_status.php error: " . $e->getMessage());
    api_fail('internal_error', 500);
}
