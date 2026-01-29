<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap.php';
require __DIR__ . '/../../../app/auth.php';
require __DIR__ . '/../../../app/services/MeetingValidator.php';
require __DIR__ . '/../../../app/services/NotificationsService.php';

require_role('operator');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') json_err('missing_meeting_id', 400);

$minOpen = (int)($_GET['min_open'] ?? 900);
$minParticipation = (float)($_GET['min_participation'] ?? 0.5);

$tenant = DEFAULT_TENANT_ID;

$meeting = db_select_one(
  "SELECT id, title, status, president_name FROM meetings WHERE tenant_id = ? AND id = ?",
  [$tenant, $meetingId]
);
if (!$meeting) json_err('meeting_not_found', 404);

$eligibleMembers = (int)(db_scalar("SELECT COUNT(*) FROM members WHERE tenant_id = ? AND is_active = true", [$tenant]) ?? 0);

$attRows = db_select_all(
  "SELECT m.id AS member_id, m.full_name, COALESCE(m.voting_power, m.vote_weight, 1.0) AS voting_power, a.mode AS attendance_mode
   FROM members m
   LEFT JOIN attendances a ON a.member_id = m.id AND a.meeting_id = ?
   WHERE m.tenant_id = ? AND m.is_active = true
   ORDER BY m.full_name ASC",
  [$meetingId, $tenant]
);

$presentCount = 0;
$presentWeight = 0.0;
$totalCount = count($attRows);
$totalWeight = 0.0;
$absentIds = [];
$absentNames = [];

foreach ($attRows as $r) {
  $vp = (float)($r['voting_power'] ?? 0);
  $totalWeight += $vp;
  $mode = (string)($r['attendance_mode'] ?? '');
  if ($mode === 'present' || $mode === 'remote' || $mode === 'proxy') {
    $presentCount++;
    $presentWeight += $vp;
  } else {
    $mid = (string)$r['member_id'];
    $absentIds[] = $mid;
    $absentNames[$mid] = (string)($r['full_name'] ?? '');
  }
}

$quorumThreshold = 0.5;
$quorumRatio = $eligibleMembers > 0 ? ($presentCount / $eligibleMembers) : 0.0;
$quorumOk = $presentCount > 0 && $quorumRatio >= $quorumThreshold;

$coveredRows = db_select_all("SELECT DISTINCT giver_member_id FROM proxies WHERE meeting_id = ? AND revoked_at IS NULL", [$meetingId]);
$coveredSet = [];
foreach ($coveredRows as $x) $coveredSet[(string)$x['giver_member_id']] = true;

$missing = [];
foreach ($absentIds as $mid) if (!isset($coveredSet[$mid])) $missing[] = $mid;
$missingNames = array_values(array_filter(array_map(fn($id)=>$absentNames[$id] ?? '', $missing)));

$proxyActive = (int)(db_scalar("SELECT count(*) FROM proxies WHERE meeting_id = ? AND revoked_at IS NULL", [$meetingId]) ?? 0);

$motions = db_select_one(
  "SELECT count(*) AS total,
          sum(CASE WHEN opened_at IS NOT NULL AND closed_at IS NULL THEN 1 ELSE 0 END) AS open
   FROM motions WHERE meeting_id = ?",
  [$meetingId]
) ?: ['total'=>0,'open'=>0];

$openMotion = db_select_one(
  "SELECT id, title, opened_at FROM motions
   WHERE meeting_id = ? AND opened_at IS NOT NULL AND closed_at IS NULL
   ORDER BY opened_at DESC LIMIT 1",
  [$meetingId]
);
$nextMotion = db_select_one(
  "SELECT id, title FROM motions
   WHERE meeting_id = ? AND opened_at IS NULL
   ORDER BY position ASC NULLS LAST, created_at ASC LIMIT 1",
  [$meetingId]
);

$lastClosedMotion = db_select_one(
  "SELECT id, title, closed_at FROM motions
   WHERE meeting_id = ? AND closed_at IS NOT NULL
   ORDER BY closed_at DESC LIMIT 1",
  [$meetingId]
);

$hasAnyMotion = ((int)($motions['total'] ?? 0)) > 0;

$openBallots = 0;
$openAgeSeconds = 0;
$participationRatio = null;
$potentialVoters = $presentCount + count($coveredSet);
$closeBlockers = [];
$canCloseOpen = false;

if ($openMotion) {
  $openBallots = (int)(db_scalar("SELECT count(*) FROM ballots WHERE motion_id = ?", [$openMotion['id']]) ?? 0);
  if (!empty($openMotion['opened_at'])) {
    $openAgeSeconds = (int)(db_scalar("SELECT EXTRACT(EPOCH FROM (NOW() - ?::timestamptz))::int", [$openMotion['opened_at']]) ?? 0);
    if ($openAgeSeconds < 0) $openAgeSeconds = 0;
  }
  $participationRatio = $potentialVoters > 0 ? ($openBallots / $potentialVoters) : 0.0;

  if ($openAgeSeconds < $minOpen) $closeBlockers[] = "Délai minimum non atteint ({$openAgeSeconds}s / {$minOpen}s).";
  if ($participationRatio < $minParticipation) $closeBlockers[] = "Participation insuffisante (".round($participationRatio*100)."%, min ".round($minParticipation*100)."%).";
  $canCloseOpen = count($closeBlockers) === 0;
}

$canOpenNext = $quorumOk && (count($missing) === 0) && ($openMotion === null) && ($nextMotion !== null);

$hasClosed = (int)(db_scalar("SELECT count(*) FROM motions WHERE meeting_id = ? AND closed_at IS NOT NULL", [$meetingId]) ?? 0);
$canConsolidate = ((int)($motions['open'] ?? 0)) === 0 && $hasClosed > 0;
$consolidatedCount = (int)(db_scalar("SELECT count(*) FROM motions WHERE meeting_id = ? AND closed_at IS NOT NULL AND official_source IS NOT NULL", [$meetingId]) ?? 0);
$consolidationDone = ($hasClosed > 0) && ($consolidatedCount >= $hasClosed);

$consolidateDetail = $canConsolidate ? "Motions fermées: $hasClosed. Vous pouvez consolider."
  : (((int)($motions['open'] ?? 0)) > 0 ? "Fermez toutes les motions ouvertes avant consolidation." : "Aucune motion fermée à consolider.");

$validation = MeetingValidator::canBeValidated($meetingId, $tenant);
$ready = (bool)($validation['can'] ?? false);
$reasons = (array)($validation['reasons'] ?? []);

// Notifications: blocages / résolutions issus du ready-check (sans spam via cache).
NotificationsService::emitReadinessTransitions($meetingId, $validation);

json_ok([
  'meeting' => [
    'id' => $meeting['id'],
    'title' => $meeting['title'] ?? '',
    'status' => $meeting['status'] ?? '',
    'president_name' => $meeting['president_name'] ?? '',
  ],
  'motions' => [
    'total' => (int)($motions['total'] ?? 0),
    'open' => (int)($motions['open'] ?? 0),
  ],
  'attendance' => [
    'ok' => $presentCount > 0,
    'present_count' => $presentCount,
    'present_weight' => $presentWeight,
    'total_count' => $totalCount,
    'total_weight' => $totalWeight,
    'quorum_threshold' => $quorumThreshold,
    'quorum_ratio' => round($quorumRatio, 4),
    'quorum_ok' => $quorumOk,
  ],
  'proxies' => [
    'ok' => $quorumOk && (count($missing) === 0),
    'active_count' => $proxyActive,
    'missing_absent_without_proxy' => count($missing),
    'missing_names' => $missingNames,
  ],
  'tokens' => [ 'disabled' => true ],
  'motion' => [
    'has_any_motion' => $hasAnyMotion,
    'open_motion_id' => $openMotion['id'] ?? null,
    'open_title' => $openMotion['title'] ?? null,
    'open_ballots' => $openBallots,
    'open_age_seconds' => $openAgeSeconds,
    'potential_voters' => $potentialVoters,
    'participation_ratio' => $participationRatio !== null ? round($participationRatio, 4) : null,
    'close_blockers' => $closeBlockers,
    'next_motion_id' => $nextMotion['id'] ?? null,
    'next_title' => $nextMotion['title'] ?? null,
    'can_open_next' => $canOpenNext,
    'can_close_open' => $canCloseOpen,

    // Utile pour le mode dégradé : saisie manuelle possible sur la dernière motion fermée
    'last_closed_motion_id' => $lastClosedMotion['id'] ?? null,
    'last_closed_title' => $lastClosedMotion['title'] ?? null,
  ],
  'consolidation' => [
    'can' => $canConsolidate,
    'done' => $consolidationDone,
    'detail' => $consolidateDetail,
    'closed_motions' => $hasClosed,
    'consolidated_motions' => $consolidatedCount,
  ],
  'validation' => [
    'ready' => $ready,
    'reasons' => $reasons,
  ],
]);
