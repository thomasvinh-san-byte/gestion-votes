<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\InvitationRepository;
use AgVote\Service\OfficialResultsService;
use AgVote\Service\VoteEngine;
use AgVote\Service\MeetingReportService;

api_require_role('auditor');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') api_fail('missing_meeting_id', 400);

$showVoters = ((string)($_GET['show_voters'] ?? '') === '1');

$tenant = api_current_tenant_id();

$meetingRepo     = new MeetingRepository();
$motionRepo      = new MotionRepository();
$attendanceRepo  = new AttendanceRepository();
$ballotRepo      = new BallotRepository();
$policyRepo      = new PolicyRepository();
$invitationRepo  = new InvitationRepository();

$meeting = $meetingRepo->findByIdForTenant($meetingId, $tenant);
if (!$meeting) api_fail('meeting_not_found', 404);

$regen = ((string)($_GET['regen'] ?? '') === '1');

// Si la séance est archivée et qu'un snapshot PV existe, on le sert (audit défendable)
// regen=1 permet de recalculer à la demande (debug/contrôle)
if (!$regen) {
  try {
    $snap = $meetingRepo->findPVSnapshot($meetingId);
    if ($snap && !empty($snap['html'])) {
      header('Content-Type: text/html; charset=utf-8');
      echo (string)$snap['html'];
      exit;
    }
  } catch (Throwable $e) { /* ignore */ }
}


OfficialResultsService::ensureSchema();

$motions = $motionRepo->listForReport($meetingId);

$attendance = $attendanceRepo->listForReport($meetingId, $tenant);

$proxies = [];
try {
  $proxies = $meetingRepo->listProxiesForReport($meetingId);
} catch (Throwable $e) { $proxies = []; }

$tokens = [];
try {
  $tokens = $invitationRepo->listTokensForReport($meetingId);
} catch (Throwable $e) { $tokens = []; }

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function policyLabel(?array $votePolicy, ?array $quorumPolicy): string {
  $parts = [];
  if ($quorumPolicy) $parts[] = "Quorum: ".($quorumPolicy['denominator'] ?? '—')." ≥ ".($quorumPolicy['threshold'] ?? '—');
  else $parts[] = "Quorum: —";
  if ($votePolicy) {
    $abst = !empty($votePolicy['abstention_as_against']) ? " (abst→contre)" : "";
    $parts[] = "Majorité: ".($votePolicy['base'] ?? '—')." ≥ ".($votePolicy['threshold'] ?? '—').$abst;
  } else $parts[] = "Majorité: —";
  return implode(" · ", $parts);
}

// VoteEngine methods are static

$rowsHtml = '';
foreach ($motions as $m) {
  $mid = (string)$m['id'];

  $votePolicy = null;
  if (!empty($m['vote_policy_id'])) $votePolicy = $policyRepo->findVotePolicy($m['vote_policy_id']);
  $quorumPolicy = null;
  if (!empty($m['quorum_policy_id'])) $quorumPolicy = $policyRepo->findQuorumPolicy($m['quorum_policy_id']);

  $src = (string)($m['official_source'] ?? '');
  $hasOfficial = $src !== '' && $m['official_total'] !== null;

  $detail = ['quorum_met'=>null,'quorum_ratio'=>null,'majority_ratio'=>null,'majority_threshold'=>null,'majority_base'=>null];

  if (!$hasOfficial && $m['closed_at'] !== null) {
    try {
      $o = OfficialResultsService::computeOfficialTallies($mid);
      $src = $o['source'];
      $of = $o['for']; $og = $o['against']; $oa = $o['abstain']; $ot = $o['total'];
      $dec = $o['decision']; $reas = $o['reason'];
      $note = ' (calculé)';
    } catch (Throwable $e) {
      $src = '—'; $of=$og=$oa=$ot=0.0; $dec='—'; $reas='error'; $note=' (erreur calc)';
    }
  } else {
    $of = (float)($m['official_for'] ?? 0);
    $og = (float)($m['official_against'] ?? 0);
    $oa = (float)($m['official_abstain'] ?? 0);
    $ot = (float)($m['official_total'] ?? 0);
    $dec = (string)($m['decision'] ?? '—');
    $reas = (string)($m['decision_reason'] ?? '');
    $note = '';
  }

  try {
    if ($src === 'evote') {
      $r = VoteEngine::computeMotionResult($mid);
      $detail['quorum_met'] = $r['quorum']['met'] ?? null;
      $detail['quorum_ratio'] = $r['quorum']['ratio'] ?? null;
      $detail['majority_ratio'] = $r['decision']['ratio'] ?? ($r['majority']['ratio'] ?? null);
      $detail['majority_threshold'] = $r['decision']['threshold'] ?? ($r['majority']['threshold'] ?? null);
      $detail['majority_base'] = $r['decision']['base'] ?? ($r['majority']['base'] ?? null);
    }
  } catch (Throwable $e) { /* ignore */ }

  $pol = policyLabel($votePolicy, $quorumPolicy);
  $detailLines = [];
  if ($detail['quorum_met'] !== null) {
    $qm = $detail['quorum_met'] ? 'oui' : 'non';
    $qr = ($detail['quorum_ratio'] !== null) ? number_format((float)$detail['quorum_ratio'], 4, '.', '') : '—';
    $detailLines[] = "Quorum atteint: $qm (ratio: $qr)";
  }
  if ($detail['majority_ratio'] !== null) {
    $mr = number_format((float)$detail['majority_ratio'], 4, '.', '');
    $mt = ($detail['majority_threshold'] !== null) ? number_format((float)$detail['majority_threshold'], 4, '.', '') : '—';
    $mb = $detail['majority_base'] ?? '—';
    $detailLines[] = "Majorité: base $mb · ratio $mr · seuil $mt";
  }

  $detailHtml = '';
  if ($pol || $detailLines) {
    $detailHtml .= "<div class='muted tiny'>".h($pol)."</div>";
    if ($detailLines) $detailHtml .= "<div class='muted tiny'>".h(implode(' · ', $detailLines))."</div>";
  }

  $rowsHtml .= '<tr>';
  $rowsHtml .= '<td><strong>'.h($m['title'] ?? 'Motion').'</strong><div class="muted">'.h($m['description'] ?? '').'</div>'.$detailHtml.'</td>';
  $rowsHtml .= '<td><span class="badge">'.h($src).$note.'</span></td>';
  $rowsHtml .= '<td class="num">'.h((string)$of).'</td>';
  $rowsHtml .= '<td class="num">'.h((string)$og).'</td>';
  $rowsHtml .= '<td class="num">'.h((string)$oa).'</td>';
  $rowsHtml .= '<td class="num">'.h((string)$ot).'</td>';
  $rowsHtml .= '<td><span class="badge '.($dec==='adopted'?'success':($dec==='rejected'?'danger':'muted')).'">'.h($dec).'</span><div class="muted tiny">'.h($reas).'</div></td>';
  $rowsHtml .= '</tr>';
}

// Annex A
$attRows = '';
$presentCount = 0; $presentWeight = 0.0; $totalWeight = 0.0;
foreach ($attendance as $r) {
  $mode = (string)($r['mode'] ?? '');
  $name = (string)($r['full_name'] ?? '');
  $vp = (float)($r['voting_power'] ?? 0);
  $totalWeight += $vp;
  $isPresent = in_array($mode, ['present','remote','proxy'], true);
  if ($isPresent) { $presentCount++; $presentWeight += $vp; }
  $attRows .= "<tr><td>".h($name)."</td><td>".h($mode ?: 'absent')."</td><td class='num'>".h((string)$vp)."</td><td class='tiny muted'>".h((string)($r['checked_in_at'] ?? ''))."</td></tr>";
}
$attSummary = "Présents: $presentCount (poids ".number_format($presentWeight, 2, '.', '').") · Poids total: ".number_format($totalWeight, 2, '.', '')."";

// Annex B
$proxyRows = '';
foreach ($proxies as $p) {
  $proxyRows .= "<tr><td>".h($p['giver_name'] ?? '')."</td><td>".h($p['receiver_name'] ?? '')."</td><td class='tiny muted'>".h((string)($p['revoked_at'] ?? ''))."</td></tr>";
}
$proxySummary = $proxies ? "Procurations: ".count($proxies) : "Procurations: 0";

// Annex C
$tokenRows = '';
foreach ($tokens as $t) {
  $tokenRows .= "<tr><td>".h($t['full_name'] ?? '')."</td><td class='tiny muted'>".h((string)($t['created_at'] ?? ''))."</td><td class='tiny muted'>".h((string)($t['last_used_at'] ?? ''))."</td><td class='tiny muted'>".h((string)($t['revoked_at'] ?? ''))."</td></tr>";
}
$tokenSummary = $tokens ? "Tokens: ".count($tokens) : "Tokens: 0";

// Annex D
$votesByMotionHtml = '';
if ($showVoters) {
  foreach ($motions as $m) {
    $mid = (string)$m['id'];
    $title = (string)($m['title'] ?? 'Motion');
    $isClosed = ($m['closed_at'] !== null);

    $ballots = $ballotRepo->listDetailedForMotion($mid);

    $rows = '';
    $i = 0;
    foreach ($ballots as $b) {
      $i++;
      $choice = (string)($b['choice'] ?? '');
      $w = (float)($b['effective_power'] ?? 0);
      $isProxy = !empty($b['is_proxy_vote']);
      $giver = (string)($b['giver_name'] ?? '');
      $receiver = (string)($b['receiver_name'] ?? '');

      $who = $isProxy
        ? (h($giver ?: '—')." (mandant) ← ".h($receiver ?: '—')." (mandataire)")
        : h($giver ?: '—');

      $rows .= "<tr><td class='num'>".h((string)$i)."</td><td>".h($choice)."</td><td class='num'>".h((string)$w)."</td><td>".($isProxy ? "proxy" : "direct")."</td><td>".$who."</td></tr>";
    }

    $votesByMotionHtml .= "<h3 style='font-size:14px;margin:16px 0 6px;'>".h($title)."</h3>";
    if (!$isClosed) $votesByMotionHtml .= "<div class='muted tiny'>Attention: motion non clôturée; la liste peut évoluer.</div>";
    if (!$rows) $votesByMotionHtml .= "<div class='muted tiny'>Aucun bulletin enregistré.</div>";
    else {
      $votesByMotionHtml .= "<table class='tbl'><thead><tr>
        <th class='num'>#</th><th>Choix</th><th class='num'>Poids</th><th>Type</th><th>Votant</th>
      </tr></thead><tbody>".$rows."</tbody></table>";
    }
  }
} else {
  $votesByMotionHtml = "<div class='muted tiny'>Annexe D masquée (par défaut). Ajoutez <span class='mono'>?show_voters=1</span> pour inclure les votants nominativement (usage interne Trust).</div>";
}

$html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>PV — {$meetingId}</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:24px;color:#111827}
h1{font-size:20px;margin:0 0 4px}
h2{font-size:16px;margin:22px 0 6px}
.muted{color:#6b7280;font-size:12px}
.tiny{font-size:12px}
.mono{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace}
.badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;background:#f3f4f6;border:1px solid #e5e7eb}
.badge.success{background:#dcfce7;border-color:#bbf7d0}
.badge.danger{background:#fee2e2;border-color:#fecaca}
.badge.muted{background:#f3f4f6;border-color:transparent;color:#6b7280}
.tbl{width:100%;border-collapse:collapse;margin-top:10px}
.tbl th,.tbl td{border-bottom:1px solid #eef2f7;padding:8px 8px;vertical-align:top}
.tbl th{text-align:left;font-size:12px;color:#6b7280}
.num{text-align:right;font-variant-numeric:tabular-nums}
.toolbar{position:sticky;top:0;background:#fff;padding:10px 0;border-bottom:1px solid #eef2f7;margin-bottom:12px}
.btn{padding:8px 12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;cursor:pointer}
@media print{.toolbar{display:none} body{margin:0}}
</style>
</head>
<body>
<div class="toolbar"><button class="btn" onclick="window.print()">Imprimer</button></div>
<h1>Procès-verbal</h1>
<div class="muted">
Séance: <strong>{$meeting['title']}</strong> · Statut: <strong>{$meeting['status']}</strong> · Président: <strong>{$meeting['president_name']}</strong><br>
Créée: {$meeting['created_at']} · Validée: {$meeting['validated_at']} · Archivée: {$meeting['archived_at']}
</div>

<h2>Résolutions</h2>
<table class="tbl">
  <thead>
    <tr>
      <th>Motion</th>
      <th>Source officielle</th>
      <th class="num">Pour</th>
      <th class="num">Contre</th>
      <th class="num">Abst.</th>
      <th class="num">Total</th>
      <th>Décision</th>
    </tr>
  </thead>
  <tbody>
    {$rowsHtml}
  </tbody>
</table>

<h2>Annexe A — Présences</h2>
<div class="muted">{$attSummary}</div>
<table class="tbl">
  <thead><tr><th>Membre</th><th>Statut</th><th class="num">Pouvoir</th><th>Check-in</th></tr></thead>
  <tbody>{$attRows}</tbody>
</table>

<h2>Annexe B — Procurations</h2>
<div class="muted">{$proxySummary}</div>
<table class="tbl">
  <thead><tr><th>Mandant</th><th>Mandataire</th><th>Révoquée le</th></tr></thead>
  <tbody>{$proxyRows}</tbody>
</table>

<h2>Annexe C — Tokens (invitations)</h2>
<div class="muted">{$tokenSummary}</div>
<table class="tbl">
  <thead><tr><th>Membre</th><th>Créé le</th><th>Dernière utilisation</th><th>Révoqué le</th></tr></thead>
  <tbody>{$tokenRows}</tbody>
</table>

<h2>Annexe D — Détails des votes</h2>
{$votesByMotionHtml}

<div class="muted tiny" style="margin-top:10px;">
Les valeurs officielles proviennent des colonnes official_* après consolidation/validation. Annexe D est optionnelle (usage interne).
</div>
</body>
</html>
HTML;

header('Content-Type: text/html; charset=utf-8');
echo $html;
