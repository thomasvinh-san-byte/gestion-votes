<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\VoteTokenRepository;

api_request('GET');
// Accessible opérateur & admin (Tech panel)
api_require_any_role(['operator','admin']);

$serverTime = date('c');

$meetingRepo = new MeetingRepository();

$t0 = microtime(true);
$dbOk = $meetingRepo->ping();
$dbLat = $dbOk ? (microtime(true) - $t0) * 1000.0 : null;

// Postgres-only metric; kept best-effort
$active = $meetingRepo->activeConnections();

$path = __DIR__;
try { $free = @disk_free_space($path); $total = @disk_total_space($path); } catch (Throwable $e) { $free = null; $total = null; }

$cntMeet = $meetingRepo->countForTenant(api_current_tenant_id());
$cntMot  = (new MotionRepository())->countAll();

$cntTok = (new VoteTokenRepository())->countAll();

$cntAud = $meetingRepo->countAuditEventsForTenant(api_current_tenant_id());

$fail15 = $meetingRepo->countRecentAuthFailures();

$alertsToCreate = [];
function push_alert(&$arr, $code, $severity, $message, $details=null){ $arr[] = ['code'=>$code,'severity'=>$severity,'message'=>$message,'details'=>$details]; }

if ($fail15 !== null && $fail15 > 5) push_alert($alertsToCreate, 'auth_failures', 'warn', 'Plus de 5 échecs de clé API sur 15 minutes.', ['count'=>$fail15]);
if ($dbLat !== null && $dbLat > 2000.0) push_alert($alertsToCreate, 'slow_db', 'critical', 'Latence DB > 2s.', ['db_latency_ms'=>round($dbLat,2)]);
if ($free !== null && $total) {
  $pct = ($free / $total) * 100.0;
  if ($pct < 10.0) push_alert($alertsToCreate, 'low_disk', 'critical', 'Espace disque < 10%.', ['free_pct'=>round($pct,2),'free_bytes'=>$free,'total_bytes'=>$total]);
}

foreach ($alertsToCreate as $a) {
  $exists = $meetingRepo->findRecentAlert($a['code']);
  if (!$exists) {
    $meetingRepo->createSystemAlert($a['code'], $a['severity'], $a['message'], json_encode($a['details']));
  }
}

$recentAlerts = $meetingRepo->listRecentAlerts(20);

api_ok([
  'system' => [
    'server_time' => $serverTime,
    'db_latency_ms' => $dbLat === null ? null : round($dbLat, 2),
    'db_active_connections' => $active === null ? null : (int)$active,
    'disk_free_bytes' => $free,
    'disk_total_bytes' => $total,
    'disk_free_pct' => ($free !== null && $total) ? round(($free/$total)*100.0, 2) : null,
    'count_meetings' => $cntMeet,
    'count_motions' => $cntMot,
    'count_vote_tokens' => $cntTok,
    'count_audit_events' => $cntAud,
    'auth_failures_15m' => $fail15,
  ],
  'alerts' => $recentAlerts,
]);
