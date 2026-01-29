<?php
require __DIR__ . '/../../../app/api.php';

api_request('GET');
// Accessible opérateur & admin (Tech panel)
api_require_any_role(['operator','admin']);

$serverTime = date('c');

$t0 = microtime(true);
try { db_scalar("SELECT 1"); $dbLat = (microtime(true) - $t0) * 1000.0; } catch (Throwable $e) { $dbLat = null; }

// Postgres-only metric; kept best-effort
try { $active = db_scalar("SELECT COUNT(*) FROM pg_stat_activity WHERE datname = current_database()"); }
catch (Throwable $e) { $active = null; }

$path = __DIR__;
try { $free = @disk_free_space($path); $total = @disk_total_space($path); } catch (Throwable $e) { $free = null; $total = null; }

$cntMeet = (int)(db_scalar("SELECT COUNT(*) FROM meetings WHERE tenant_id = ?", [DEFAULT_TENANT_ID]) ?? 0);
$cntMot  = (int)(db_scalar("SELECT COUNT(*) FROM motions") ?? 0);

$cntTok = null;
try { $cntTok = (int)(db_scalar("SELECT COUNT(*) FROM vote_tokens") ?? 0); } catch (Throwable $e) { $cntTok = null; }

$cntAud = null;
try { $cntAud = (int)(db_scalar("SELECT COUNT(*) FROM audit_events WHERE tenant_id = ?", [DEFAULT_TENANT_ID]) ?? 0); } catch (Throwable $e) { $cntAud = null; }

$fail15 = null;
try { $fail15 = (int)(db_scalar("SELECT COUNT(*) FROM auth_failures WHERE created_at > NOW() - INTERVAL '15 minutes'") ?? 0); }
catch (Throwable $e) { $fail15 = null; }

$alertsToCreate = [];
function push_alert(&$arr, $code, $severity, $message, $details=null){ $arr[] = ['code'=>$code,'severity'=>$severity,'message'=>$message,'details'=>$details]; }

if ($fail15 !== null && $fail15 > 5) push_alert($alertsToCreate, 'auth_failures', 'warn', 'Plus de 5 échecs de clé API sur 15 minutes.', ['count'=>$fail15]);
if ($dbLat !== null && $dbLat > 2000.0) push_alert($alertsToCreate, 'slow_db', 'critical', 'Latence DB > 2s.', ['db_latency_ms'=>round($dbLat,2)]);
if ($free !== null && $total) {
  $pct = ($free / $total) * 100.0;
  if ($pct < 10.0) push_alert($alertsToCreate, 'low_disk', 'critical', 'Espace disque < 10%.', ['free_pct'=>round($pct,2),'free_bytes'=>$free,'total_bytes'=>$total]);
}

foreach ($alertsToCreate as $a) {
  try {
    $exists = db_scalar("SELECT 1 FROM system_alerts WHERE code = ? AND created_at > NOW() - INTERVAL '10 minutes' LIMIT 1", [$a['code']]);
    if (!$exists) {
      db_execute("INSERT INTO system_alerts(code, severity, message, details_json, created_at) VALUES (:c,:s,:m,:d,NOW())",
        [':c'=>$a['code'], ':s'=>$a['severity'], ':m'=>$a['message'], ':d'=>json_encode($a['details'])]
      );
    }
  } catch (Throwable $e) {}
}

$recentAlerts = [];
try {
  $recentAlerts = db_select_all("SELECT id, created_at, code, severity, message, details_json FROM system_alerts ORDER BY created_at DESC LIMIT 20");
} catch (Throwable $e) { $recentAlerts = []; }

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
