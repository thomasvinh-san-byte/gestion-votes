<?php
declare(strict_types=1);

require __DIR__ . '/_drawer_util.php';

$meetingId = get_meeting_id();
if ($meetingId === '') {
  echo "<div class='card pad'>meeting_id manquant.</div>";
  exit;
}

$tenantId = defined('DEFAULT_TENANT_ID') ? DEFAULT_TENANT_ID : null;

$meeting = db_select_one(
  "SELECT id, title, validated_at FROM meetings WHERE tenant_id = ? AND id = ?",
  [$tenantId, $meetingId]
);
if (!$meeting) {
  echo "<div class='card pad'>Séance introuvable.</div>";
  exit;
}

// Global guardrail: multiple open motions
$openMotions = db_select_all(
  "SELECT id, title, opened_at
   FROM motions
   WHERE tenant_id = ? AND meeting_id = ? AND opened_at IS NOT NULL AND closed_at IS NULL
   ORDER BY opened_at DESC",
  [$tenantId, $meetingId]
);

// Eligible voters: present/remote/proxy; fallback all active members
$eligibleRows = db_select_all(
  "SELECT m.id AS member_id, m.full_name
   FROM members m
   JOIN attendances a ON a.member_id = m.id AND a.meeting_id = ? AND a.tenant_id = m.tenant_id
   WHERE m.tenant_id = ? AND m.is_active = true AND a.mode IN ('present','remote','proxy')
   ORDER BY m.full_name ASC",
  [$meetingId, $tenantId]
);
$fallbackUsed = false;
if (!$eligibleRows) {
  $fallbackUsed = true;
  $eligibleRows = db_select_all(
    "SELECT id AS member_id, full_name
     FROM members
     WHERE tenant_id = ? AND is_active = true
     ORDER BY full_name ASC",
    [$tenantId]
  );
}

$eligibleSet = [];
foreach ($eligibleRows as $r) {
  $id = (string)($r['member_id'] ?? '');
  if ($id !== '') $eligibleSet[$id] = (string)($r['full_name'] ?? '');
}
$eligibleCount = count($eligibleSet);

// Motions list (for per-motion anomalies)
$motions = db_select_all(
  "SELECT id, title, opened_at, closed_at, position
   FROM motions
   WHERE tenant_id = ? AND meeting_id = ?
   ORDER BY position ASC NULLS LAST, created_at ASC",
  [$tenantId, $meetingId]
);

echo "<div class='card pad'>";
echo "<div class='h2' style='margin:0 0 6px;'>Anomalies</div>";
echo "<div class='tiny muted' style='margin:0 0 10px;'>CDC — écarts attendus/reçus, tokens non utilisés/expirés, votes hors statut, procurations.</div>";

if (!empty($meeting['validated_at'])) {
  echo "<div class='callout danger tiny'>Séance validée : les votes et corrections doivent être bloqués. (validated_at=".$h($meeting['validated_at']).")</div>";
}

if (count($openMotions) > 1) {
  echo "<div class='callout danger tiny'><strong>Incohérence :</strong> ".count($openMotions)." motions sont ouvertes simultanément. Il ne doit y en avoir qu’une.</div>";
} elseif (count($openMotions) === 1) {
  echo "<div class='callout ok tiny'>Vote en cours : <strong>".$h($openMotions[0]['title'] ?? '')."</strong></div>";
} else {
  echo "<div class='callout tiny muted'>Aucun vote en cours.</div>";
}

echo "<div class='hr'></div>";
echo "<div class='row' style='justify-content:space-between; gap:10px; flex-wrap:wrap;'>";
echo "<div class='tiny muted'>Éligibles : <strong>".$h((string)$eligibleCount)."</strong>".($fallbackUsed ? " <span class='badge'>fallback (pas de présences)</span>" : "")."</div>";
echo "<div class='tiny muted'>Séance : ".$h($meeting['title'] ?? '')." (#".$h($meetingId).")</div>";
echo "</div>";

if (!$motions) {
  echo "<div class='state warn' style='margin-top:10px;'><div class='icon'>i</div><div class='text'><div class='headline'>Aucune résolution</div><div class='hint'>Ajoutez des motions pour activer le Live.</div></div></div>";
  echo "</div>";
  exit;
}

// Table per motion
echo "<div style='overflow:auto; margin-top:10px;'>";
echo "<table class='table'><thead><tr>
        <th>#</th>
        <th>Résolution</th>
        <th class='right'>Ballots</th>
        <th class='right'>Manuels</th>
        <th class='right'>Manquants</th>
        <th class='right'>Tokens actifs non utilisés</th>
        <th class='right'>Tokens expirés non utilisés</th>
        <th>Statut</th>
      </tr></thead><tbody>";

$missingList = [];
$unexpectedList = [];
$expiredTokensList = [];

foreach ($motions as $mo) {
  $motionId = (string)$mo['id'];

  $ballots = db_select_all(
    "SELECT b.member_id, mb.full_name AS voter_name, b.value::text AS value, b.source, b.cast_at
     FROM ballots b
     LEFT JOIN members mb ON mb.id = b.member_id
     WHERE b.motion_id = ?
     ORDER BY b.cast_at ASC",
    [$motionId]
  );

  $ballotsCount = 0;
  $manualCount = 0;
  $votedSet = [];
  foreach ($ballots as $b) {
    $mid = (string)($b['member_id'] ?? '');
    if ($mid === '') continue;
    $ballotsCount++;
    $votedSet[$mid] = true;
    if (($b['source'] ?? '') === 'manual') $manualCount++;
    if (!isset($eligibleSet[$mid])) {
      $unexpectedList[] = [
        'motion_id' => $motionId,
        'motion_title' => (string)($mo['title'] ?? ''),
        'member_id' => $mid,
        'voter_name' => (string)($b['voter_name'] ?? ''),
        'value' => (string)($b['value'] ?? ''),
        'source' => (string)($b['source'] ?? ''),
        'cast_at' => (string)($b['cast_at'] ?? ''),
      ];
    }
  }

  $tok = db_select_one(
    "SELECT
        COALESCE(COUNT(*),0) AS total,
        COALESCE(SUM(CASE WHEN used_at IS NULL AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 ELSE 0 END),0) AS active_unused,
        COALESCE(SUM(CASE WHEN used_at IS NULL AND expires_at IS NOT NULL AND expires_at <= NOW() THEN 1 ELSE 0 END),0) AS expired_unused
     FROM vote_tokens
     WHERE motion_id = ?",
    [$motionId]
  );

  $activeUnused = (int)($tok['active_unused'] ?? 0);
  $expiredUnused = (int)($tok['expired_unused'] ?? 0);

  if ($expiredUnused > 0 && count($expiredTokensList) < 60) {
    $trs = db_select_all(
      "SELECT member_id, LEFT(token_hash, 12) AS token_hash_prefix, expires_at
       FROM vote_tokens
       WHERE motion_id = ? AND used_at IS NULL AND expires_at IS NOT NULL AND expires_at <= NOW()
       ORDER BY expires_at DESC
       LIMIT 20",
      [$motionId]
    );
    foreach ($trs as $tr) {
      $expiredTokensList[] = [
        'motion_id' => $motionId,
        'motion_title' => (string)($mo['title'] ?? ''),
        'member_id' => (string)($tr['member_id'] ?? ''),
        'token_hash_prefix' => (string)($tr['token_hash_prefix'] ?? ''),
        'expires_at' => (string)($tr['expires_at'] ?? ''),
      ];
    }
  }

  $missing = max(0, $eligibleCount - $ballotsCount);
  if ($missing > 0 && count($missingList) < 50) {
    foreach ($eligibleSet as $mid => $name) {
      if (!isset($votedSet[$mid])) {
        $missingList[] = [
          'motion_id' => $motionId,
          'motion_title' => (string)($mo['title'] ?? ''),
          'member_id' => $mid,
          'voter_name' => $name,
        ];
        if (count($missingList) >= 50) break;
      }
    }
  }

  $status = "—";
  if (!empty($mo['closed_at'])) $status = "Clôturée";
  elseif (!empty($mo['opened_at'])) $status = "Ouverte";
  else $status = "Non ouverte";

  $pill = "badge";
  if ($missing > 0 || $unexpectedList) $pill = "badge is-warn";
  if ($missing > 0 && !empty($mo['closed_at'])) $pill = "badge is-bad";

  echo "<tr>";
  echo "<td class='tiny muted'>".$h((string)($mo['position'] ?? ''))."</td>";
  echo "<td><div style='font-weight:600;'>".$h($mo['title'] ?? '')."</div><div class='tiny muted'>#".$h($motionId)."</div></td>";
  echo "<td class='right'>".$h((string)$ballotsCount)."</td>";
  echo "<td class='right'>".$h((string)$manualCount)."</td>";
  echo "<td class='right'>".$h((string)$missing)."</td>";
  echo "<td class='right'>".$h((string)$activeUnused)."</td>";
  echo "<td class='right'>".$h((string)$expiredUnused)."</td>";
  echo "<td><span class='".$pill."'>".$h($status)."</span></td>";
  echo "</tr>";
}
echo "</tbody></table></div>";

// Missing voters sample
if (!empty($missingList)) {
  echo "<div class='hr'></div>";
  echo "<div><strong>Votants manquants (extrait)</strong><div class='tiny muted'>Attendus – reçus. (limité à 50 lignes)</div></div>";
  echo "<div style='overflow:auto; margin-top:8px;'><table class='table'><thead><tr><th>Résolution</th><th>Member ID</th><th>Nom</th></tr></thead><tbody>";
  foreach ($missingList as $m) {
    echo "<tr><td class='tiny'>".$h($m['motion_title'])." <span class='muted'>#".$h($m['motion_id'])."</span></td><td class='tiny muted'>".$h($m['member_id'])."</td><td>".$h($m['voter_name'])."</td></tr>";
  }
  echo "</tbody></table></div>";
}

// Unexpected ballots
if (!empty($unexpectedList)) {
  echo "<div class='hr'></div>";
  echo "<div><strong>Votes hors statut</strong><div class='tiny muted'>Ballots déposés par un membre non éligible (selon la règle en vigueur). Vérifier présences / procurations.</div></div>";
  echo "<div style='overflow:auto; margin-top:8px;'><table class='table'><thead><tr><th>Date</th><th>Résolution</th><th>Member</th><th>Nom</th><th>Choix</th><th>Source</th></tr></thead><tbody>";
  foreach ($unexpectedList as $u) {
    echo "<tr>";
    echo "<td class='tiny muted'>".$h($u['cast_at'])."</td>";
    echo "<td class='tiny'>".$h($u['motion_title'])." <span class='muted'>#".$h($u['motion_id'])."</span></td>";
    echo "<td class='tiny muted'>".$h($u['member_id'])."</td>";
    echo "<td>".$h($u['voter_name'])."</td>";
    echo "<td>".$h($u['value'])."</td>";
    echo "<td class='tiny muted'>".$h($u['source'])."</td>";
    echo "</tr>";
  }
  echo "</tbody></table></div>";
}

// Expired tokens
if (!empty($expiredTokensList)) {
  echo "<div class='hr'></div>";
  echo "<div><strong>Tokens expirés non utilisés</strong><div class='tiny muted'>Souvent signe de latence / réouverture / QR distribués trop tôt.</div></div>";
  echo "<div style='overflow:auto; margin-top:8px;'><table class='table'><thead><tr><th>Résolution</th><th>Member ID</th><th>Token</th><th>Expire</th></tr></thead><tbody>";
  foreach ($expiredTokensList as $t) {
    echo "<tr>";
    echo "<td class='tiny'>".$h($t['motion_title'])." <span class='muted'>#".$h($t['motion_id'])."</span></td>";
    echo "<td class='tiny muted'>".$h($t['member_id'])."</td>";
    echo "<td class='tiny'><code>".$h($t['token_hash_prefix'])."…</code></td>";
    echo "<td class='tiny muted'>".$h($t['expires_at'])."</td>";
    echo "</tr>";
  }
  echo "</tbody></table></div>";
}

// Proxies overview
try {
  $proxyRows = db_select_all(
    "SELECT pr.receiver_member_id AS grantee_member_id, r.full_name AS grantee_name, COUNT(*) AS proxies_received
     FROM proxies pr
     LEFT JOIN members r ON r.id = pr.receiver_member_id
     WHERE pr.meeting_id = ?
     GROUP BY pr.receiver_member_id, r.full_name
     ORDER BY proxies_received DESC, r.full_name ASC",
    [$meetingId]
  );
} catch (Throwable $e) { $proxyRows = []; }

if (!empty($proxyRows)) {
  echo "<div class='hr'></div>";
  echo "<div><strong>Procurations (vue synthèse)</strong><div class='tiny muted'>Nombre de procurations reçues par mandataire.</div></div>";
  echo "<div style='overflow:auto; margin-top:8px;'><table class='table'><thead><tr><th>Mandataire</th><th class='right'>Nb procurations</th></tr></thead><tbody>";
  foreach ($proxyRows as $p) {
    $count = (int)($p['proxies_received'] ?? 0);
    $badge = $count >= 2 ? "badge is-warn" : "badge";
    echo "<tr><td>".$h($p['grantee_name'] ?? $p['grantee_member_id'])."</td><td class='right'><span class='".$badge."'>".$h((string)$count)."</span></td></tr>";
  }
  echo "</tbody></table></div>";
}

echo "</div>";
