<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\InvitationRepository;
use AgVote\Repository\MeetingReportRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\ProxyRepository;
use AgVote\Service\MailerService;
use AgVote\Service\MeetingReportService;
use AgVote\Service\OfficialResultsService;
use AgVote\Service\VoteEngine;
use Dompdf\Dompdf;
use Dompdf\Options;
use Throwable;

/**
 * Consolidates 5 meeting report endpoints.
 *
 * Shared pattern: meeting validation, report generation (HTML/PDF), MeetingRepository.
 */
final class MeetingReportsController extends AbstractController {
    public function report(): void {
        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        $showVoters = (api_query('show_voters') === '1');
        $tenant = api_current_tenant_id();

        $meetingRepo = new MeetingRepository();
        $motionRepo = new MotionRepository();
        $attendanceRepo = new AttendanceRepository();
        $ballotRepo = new BallotRepository();
        $policyRepo = new PolicyRepository();
        $invitationRepo = new InvitationRepository();

        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenant);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        $regen = (api_query('regen') === '1');

        // Serve cached snapshot if available (audit-defensible)
        if (!$regen) {
            try {
                $snap = (new MeetingReportRepository())->findSnapshot($meetingId, $tenant);
                if ($snap && !empty($snap['html'])) {
                    header('Content-Type: text/html; charset=utf-8');
                    echo (string) $snap['html'];
                    exit;
                }
            } catch (Throwable $e) {
                if ($e instanceof \AgVote\Core\Http\ApiResponseException) {
                    throw $e;
                }
            }
        }

        $motions = $motionRepo->listForReport($meetingId);
        $attendance = $attendanceRepo->listForReport($meetingId, $tenant);

        $proxies = [];
        try {
            $proxies = (new ProxyRepository())->listForReport($meetingId);
        } catch (Throwable $e) {
            if ($e instanceof \AgVote\Core\Http\ApiResponseException) {
                throw $e;
            }
        }

        $tokens = [];
        try {
            $tokens = $invitationRepo->listTokensForReport($meetingId);
        } catch (Throwable $e) {
            if ($e instanceof \AgVote\Core\Http\ApiResponseException) {
                throw $e;
            }
        }

        $rowsHtml = '';
        foreach ($motions as $m) {
            $mid = (string) $m['id'];

            $votePolicy = null;
            if (!empty($m['vote_policy_id'])) {
                $votePolicy = $policyRepo->findVotePolicy($m['vote_policy_id']);
            }
            $quorumPolicy = null;
            if (!empty($m['quorum_policy_id'])) {
                $quorumPolicy = $policyRepo->findQuorumPolicy($m['quorum_policy_id']);
            }

            $src = (string) ($m['official_source'] ?? '');
            $hasOfficial = $src !== '' && $m['official_total'] !== null;

            $detail = ['quorum_met' => null, 'quorum_ratio' => null, 'majority_ratio' => null, 'majority_threshold' => null, 'majority_base' => null];

            if (!$hasOfficial && $m['closed_at'] !== null) {
                try {
                    $o = (new OfficialResultsService())->computeOfficialTallies($mid);
                    $src = $o['source'];
                    $of = $o['for'];
                    $og = $o['against'];
                    $oa = $o['abstain'];
                    $ot = $o['total'];
                    $dec = $o['decision'];
                    $reas = $o['reason'];
                    $note = ' (calculé)';
                } catch (Throwable $e) {
                    if ($e instanceof \AgVote\Core\Http\ApiResponseException) {
                        throw $e;
                    }
                    $src = '—';
                    $of = $og = $oa = $ot = 0.0;
                    $dec = '—';
                    $reas = 'error';
                    $note = ' (erreur calc)';
                }
            } else {
                $of = (float) ($m['official_for'] ?? 0);
                $og = (float) ($m['official_against'] ?? 0);
                $oa = (float) ($m['official_abstain'] ?? 0);
                $ot = (float) ($m['official_total'] ?? 0);
                $dec = (string) ($m['decision'] ?? '—');
                $reas = (string) ($m['decision_reason'] ?? '');
                $note = '';
            }

            try {
                if ($src === 'evote') {
                    $r = (new VoteEngine())->computeMotionResult($mid);
                    $detail['quorum_met'] = $r['quorum']['met'] ?? null;
                    $detail['quorum_ratio'] = $r['quorum']['ratio'] ?? null;
                    $detail['majority_ratio'] = $r['decision']['ratio'] ?? ($r['majority']['ratio'] ?? null);
                    $detail['majority_threshold'] = $r['decision']['threshold'] ?? ($r['majority']['threshold'] ?? null);
                    $detail['majority_base'] = $r['decision']['base'] ?? ($r['majority']['base'] ?? null);
                }
            } catch (Throwable $e) {
                if ($e instanceof \AgVote\Core\Http\ApiResponseException) {
                    throw $e;
                }
            }

            $pol = self::policyLabel($votePolicy, $quorumPolicy);
            $detailLines = [];
            if ($detail['quorum_met'] !== null) {
                $qm = $detail['quorum_met'] ? 'oui' : 'non';
                $qr = ($detail['quorum_ratio'] !== null) ? number_format((float) $detail['quorum_ratio'], 4, '.', '') : '—';
                $detailLines[] = "Quorum atteint: {$qm} (ratio: {$qr})";
            }
            if ($detail['majority_ratio'] !== null) {
                $mr = number_format((float) $detail['majority_ratio'], 4, '.', '');
                $mt = ($detail['majority_threshold'] !== null) ? number_format((float) $detail['majority_threshold'], 4, '.', '') : '—';
                $mb = $detail['majority_base'] ?? '—';
                $detailLines[] = "Majorité: base {$mb} · ratio {$mr} · seuil {$mt}";
            }

            $detailHtml = '';
            if ($pol || $detailLines) {
                $detailHtml .= "<div class='muted tiny'>" . self::h($pol) . '</div>';
                if ($detailLines) {
                    $detailHtml .= "<div class='muted tiny'>" . self::h(implode(' · ', $detailLines)) . '</div>';
                }
            }

            $rowsHtml .= '<tr>';
            $rowsHtml .= '<td><strong>' . self::h($m['title'] ?? 'Motion') . '</strong><div class="muted">' . self::h($m['description'] ?? '') . '</div>' . $detailHtml . '</td>';
            $rowsHtml .= '<td><span class="badge">' . self::h($src) . $note . '</span></td>';
            $rowsHtml .= '<td class="num">' . self::h(self::fmtNum($of)) . '</td>';
            $rowsHtml .= '<td class="num">' . self::h(self::fmtNum($og)) . '</td>';
            $rowsHtml .= '<td class="num">' . self::h(self::fmtNum($oa)) . '</td>';
            $rowsHtml .= '<td class="num">' . self::h(self::fmtNum($ot)) . '</td>';
            $rowsHtml .= '<td><span class="badge ' . ($dec === 'adopted' ? 'success' : ($dec === 'rejected' ? 'danger' : 'muted')) . '">' . self::h(self::decisionLabel($dec)) . '</span><div class="muted tiny">' . self::h($reas) . '</div></td>';
            $rowsHtml .= '</tr>';
        }

        // Annex A: Attendance
        $attRows = '';
        $presentCount = 0;
        $presentWeight = 0.0;
        $totalWeight = 0.0;
        foreach ($attendance as $r) {
            $mode = (string) ($r['mode'] ?? '');
            $name = (string) ($r['full_name'] ?? '');
            $vp = (float) ($r['voting_power'] ?? 0);
            $totalWeight += $vp;
            $isPresent = in_array($mode, ['present', 'remote', 'proxy'], true);
            if ($isPresent) {
                $presentCount++;
                $presentWeight += $vp;
            }
            $attRows .= '<tr><td>' . self::h($name) . '</td><td>' . self::h(self::modeLabel($mode)) . "</td><td class='num'>" . self::h(self::fmtNum($vp)) . "</td><td class='tiny muted'>" . self::h((string) ($r['checked_in_at'] ?? '')) . '</td></tr>';
        }
        $attSummary = "Présents: {$presentCount} (poids " . number_format($presentWeight, 2, '.', '') . ') · Poids total: ' . number_format($totalWeight, 2, '.', '') . '';

        // Annex B: Proxies
        $proxyRows = '';
        foreach ($proxies as $p) {
            $proxyRows .= '<tr><td>' . self::h($p['giver_name'] ?? '') . '</td><td>' . self::h($p['receiver_name'] ?? '') . "</td><td class='tiny muted'>" . self::h((string) ($p['revoked_at'] ?? '')) . '</td></tr>';
        }
        $proxySummary = $proxies ? 'Procurations: ' . count($proxies) : 'Procurations: 0';

        // Annex C: Tokens
        $tokenRows = '';
        foreach ($tokens as $t) {
            $tokenRows .= '<tr><td>' . self::h($t['full_name'] ?? '') . "</td><td class='tiny muted'>" . self::h((string) ($t['created_at'] ?? '')) . "</td><td class='tiny muted'>" . self::h((string) ($t['last_used_at'] ?? '')) . "</td><td class='tiny muted'>" . self::h((string) ($t['revoked_at'] ?? '')) . '</td></tr>';
        }
        $tokenSummary = $tokens ? 'Tokens: ' . count($tokens) : 'Tokens: 0';

        // Annex D: Vote details
        $votesByMotionHtml = '';
        if ($showVoters) {
            foreach ($motions as $m) {
                $mid = (string) $m['id'];
                $title = (string) ($m['title'] ?? 'Motion');
                $isClosed = ($m['closed_at'] !== null);

                $ballots = $ballotRepo->listDetailedForMotion($mid, $tenant);

                $rows = '';
                $i = 0;
                foreach ($ballots as $b) {
                    $i++;
                    $choice = (string) ($b['choice'] ?? '');
                    $w = (float) ($b['effective_power'] ?? 0);
                    $isProxy = !empty($b['is_proxy_vote']);
                    $giver = (string) ($b['giver_name'] ?? '');
                    $receiver = (string) ($b['receiver_name'] ?? '');

                    $who = $isProxy
                        ? (self::h($giver ?: '—') . ' (mandant) ← ' . self::h($receiver ?: '—') . ' (mandataire)')
                        : self::h($giver ?: '—');

                    $rows .= "<tr><td class='num'>" . self::h((string) $i) . '</td><td>' . self::h(self::choiceLabel($choice)) . "</td><td class='num'>" . self::h(self::fmtNum($w)) . '</td><td>' . ($isProxy ? 'Procuration' : 'Direct') . '</td><td>' . $who . '</td></tr>';
                }

                $votesByMotionHtml .= "<h3 style='font-size:14px;margin:16px 0 6px;'>" . self::h($title) . '</h3>';
                if (!$isClosed) {
                    $votesByMotionHtml .= "<div class='muted tiny'>Attention: motion non clôturée; la liste peut évoluer.</div>";
                }
                if (!$rows) {
                    $votesByMotionHtml .= "<div class='muted tiny'>Aucun bulletin enregistré.</div>";
                } else {
                    $votesByMotionHtml .= "<table class='tbl'><thead><tr>
                        <th class='num'>#</th><th>Choix</th><th class='num'>Poids</th><th>Type</th><th>Votant</th>
                    </tr></thead><tbody>" . $rows . '</tbody></table>';
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
            <title>PV — {$meeting['title']}</title>
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

        audit_log('report.view_html', 'meeting', $meetingId, [
            'show_voters' => $showVoters,
            'regenerated' => $regen,
        ], $meetingId);

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }

    public function generatePdf(): void {
        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('invalid_meeting_id', 400);
        }

        $isPreview = api_query('preview') !== '' || api_query('draft') !== '';
        $tenantId = api_current_tenant_id();

        $meeting = (new MeetingRepository())->findWithValidator($meetingId);
        if (!$meeting || (string) ($meeting['tenant_id'] ?? '') !== $tenantId) {
            api_fail('meeting_not_found', 404);
        }

        if (!$isPreview && empty($meeting['validated_at'])) {
            api_fail('meeting_not_validated', 409, [
                'detail' => 'La séance doit être validée avant de générer le PV définitif. Utilisez ?preview=1 pour un brouillon.',
            ]);
        }

        $attendances = (new AttendanceRepository())->listForReport($meetingId, $tenantId);
        $motions = (new MotionRepository())->listForReport($meetingId);
        $proxies = (new ProxyRepository())->listForReport($meetingId);

        // Build the full HTML for PDF
        $html = '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Procès-verbal - ' . htmlspecialchars($meeting['title'] ?? 'Séance') . '</title>
<style>
    @page { margin: 2cm; }
    body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 11pt; line-height: 1.5; color: #333; }
    h1 { font-size: 18pt; color: #1e3a5f; border-bottom: 2px solid #1e3a5f; padding-bottom: 10px; margin-bottom: 20px; }
    h2 { font-size: 14pt; color: #2563eb; margin-top: 25px; margin-bottom: 10px; }
    h3 { font-size: 12pt; color: #374151; margin-top: 15px; margin-bottom: 8px; }
    .header-info { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
    .header-info p { margin: 5px 0; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { border: 1px solid #d1d5db; padding: 8px 12px; text-align: left; }
    th { background: #f3f4f6; font-weight: bold; }
    tr:nth-child(even) { background: #f9fafb; }
    .result-box { background: #f0fdf4; border: 1px solid #86efac; padding: 10px 15px; margin: 10px 0; border-radius: 4px; }
    .result-rejected { background: #fef2f2; border-color: #fca5a5; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10pt; font-weight: bold; }
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 9pt; color: #6b7280; }
    .signature-box { margin-top: 40px; border: 1px dashed #9ca3af; padding: 20px; text-align: center; }
    .draft-watermark { position: fixed; top: 40%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 80pt; color: rgba(220, 38, 38, 0.15); font-weight: bold; z-index: -1; white-space: nowrap; }
    .draft-banner { background: #fef2f2; border: 2px solid #dc2626; color: #dc2626; padding: 10px 15px; margin-bottom: 20px; border-radius: 4px; text-align: center; font-weight: bold; }
</style>
</head>
<body>';

        if ($isPreview) {
            $html .= '<div class="draft-watermark">BROUILLON</div>';
            $html .= '<div class="draft-banner">⚠️ DOCUMENT BROUILLON - NON VALIDÉ - À TITRE INDICATIF UNIQUEMENT</div>';
        }

        $html .= '<h1>PROCÈS-VERBAL DE SÉANCE' . ($isPreview ? ' (BROUILLON)' : '') . '</h1>';
        $html .= '<div class="header-info">';
        $html .= '<p><strong>Titre :</strong> ' . htmlspecialchars($meeting['title'] ?? '—') . '</p>';
        $html .= '<p><strong>Date :</strong> ' . ($meeting['scheduled_at'] ? date('d/m/Y à H:i', strtotime($meeting['scheduled_at'])) : '—') . '</p>';
        $html .= '<p><strong>Lieu :</strong> ' . htmlspecialchars($meeting['location'] ?? '—') . '</p>';
        $html .= '<p><strong>Président :</strong> ' . htmlspecialchars($meeting['president_name'] ?? '—') . '</p>';
        if ($isPreview) {
            $html .= '<p><strong>Statut :</strong> <span style="color:#dc2626;">Non validé (brouillon)</span></p>';
        } else {
            $html .= '<p><strong>Validé le :</strong> ' . date('d/m/Y à H:i', strtotime($meeting['validated_at'])) . '</p>';
        }
        $html .= '</div>';

        // Attendance section
        $html .= '<h2>1. Feuille de présence</h2>';
        $pCount = count(array_filter($attendances, fn ($a) => in_array($a['mode'], ['present', 'remote'])));
        $totalPower = array_sum(array_column($attendances, 'voting_power'));
        $presentPower = array_sum(array_map(fn ($a) => in_array($a['mode'], ['present', 'remote', 'proxy']) ? ($a['voting_power'] ?? 0) : 0, $attendances));

        $html .= '<p><strong>' . $pCount . '</strong> membres présents sur <strong>' . count($attendances) . '</strong> inscrits';
        $html .= ' — Pouvoir représenté : <strong>' . number_format($presentPower, 2) . '</strong> / ' . number_format($totalPower, 2) . '</p>';

        $html .= '<table><tr><th>Nom</th><th>Pouvoir</th><th>Statut</th><th>Arrivée</th></tr>';
        foreach ($attendances as $a) {
            $statusLabel = match ($a['mode'] ?? 'absent') {
                'present' => 'Présent', 'remote' => 'À distance', 'proxy' => 'Représenté', 'excused' => 'Excusé', default => 'Absent'
            };
            $html .= '<tr><td>' . htmlspecialchars($a['full_name']) . '</td>';
            $html .= '<td>' . number_format($a['voting_power'] ?? 1, 2) . '</td>';
            $html .= '<td>' . $statusLabel . '</td>';
            $html .= '<td>' . ($a['checked_in_at'] ? date('H:i', strtotime($a['checked_in_at'])) : '—') . '</td></tr>';
        }
        $html .= '</table>';

        // Proxies
        if (!empty($proxies)) {
            $html .= '<h2>2. Procurations</h2><table><tr><th>Mandant</th><th>Mandataire</th></tr>';
            foreach ($proxies as $p) {
                $html .= '<tr><td>' . htmlspecialchars($p['giver_name']) . '</td><td>' . htmlspecialchars($p['receiver_name']) . '</td></tr>';
            }
            $html .= '</table>';
        }

        // Motions
        $sectionNum = empty($proxies) ? '2' : '3';
        $html .= "<h2>{$sectionNum}. Résolutions</h2>";

        foreach ($motions as $i => $m) {
            $num = $i + 1;
            $html .= '<h3>Résolution n°' . $num . ' : ' . htmlspecialchars($m['title']) . '</h3>';
            if (!empty($m['description'])) {
                $html .= '<p>' . nl2br(htmlspecialchars($m['description'])) . '</p>';
            }
            if ($m['secret']) {
                $html .= '<p><span class="badge badge-warning">Vote secret</span></p>';
            }

            $html .= '<table><tr><th>Vote</th><th>Poids</th></tr>';
            $html .= '<tr><td>✅ Pour</td><td>' . number_format((float) ($m['official_for'] ?? 0), 2) . '</td></tr>';
            $html .= '<tr><td>❌ Contre</td><td>' . number_format((float) ($m['official_against'] ?? 0), 2) . '</td></tr>';
            $html .= '<tr><td>⚪ Abstention</td><td>' . number_format((float) ($m['official_abstain'] ?? 0), 2) . '</td></tr>';
            $html .= '<tr><td><strong>Total</strong></td><td><strong>' . number_format((float) ($m['official_total'] ?? 0), 2) . '</strong></td></tr>';
            $html .= '</table>';

            $decisionClass = ($m['decision'] === 'adopted') ? '' : 'result-rejected';
            $dl = match ($m['decision'] ?? '') {
                'adopted' => '✓ ADOPTÉE', 'rejected' => '✗ REJETÉE', 'no_quorum' => '⚠ SANS QUORUM',
                'no_votes' => '⚠ SANS VOTES', 'no_policy' => '⚠ SANS RÈGLE', 'cancelled' => '✗ ANNULÉE',
                'pending' => '… EN ATTENTE', default => '? ' . strtoupper($m['decision'] ?? '—'),
            };
            $html .= '<div class="result-box ' . $decisionClass . '"><strong>Décision :</strong> ' . $dl;
            if (!empty($m['decision_reason'])) {
                $html .= ' — ' . htmlspecialchars($m['decision_reason']);
            }
            $html .= '</div>';
        }

        // Signature
        $html .= '<div class="signature-box"><p>Le Président de séance</p>';
        $html .= '<p style="margin-top: 40px;"><strong>' . htmlspecialchars($meeting['president_name'] ?? '—') . '</strong></p>';
        if ($isPreview) {
            $html .= '<p style="font-size: 9pt; color: #dc2626;">Document brouillon - Généré le ' . date('d/m/Y à H:i') . '</p>';
        } else {
            $html .= '<p style="font-size: 9pt; color: #6b7280;">Fait le ' . date('d/m/Y à H:i', strtotime($meeting['validated_at'])) . '</p>';
        }
        $html .= '</div>';

        $html .= '<div class="footer"><p>Document généré automatiquement par AG-VOTE le ' . date('d/m/Y à H:i') . '</p>';
        $html .= '<p>Identifiant séance : ' . htmlspecialchars($meetingId) . '</p></div>';
        $html .= '</body></html>';

        // Generate PDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfContent = $dompdf->output();
        $hash = hash('sha256', $pdfContent);

        if (!$isPreview) {
            (new MeetingReportRepository())->upsertFull($meetingId, $html, $hash, $tenantId);
        }

        $prefix = $isPreview ? 'BROUILLON_PV_' : 'PV_';
        $filename = $prefix . preg_replace('/[^a-zA-Z0-9]/', '_', $meeting['title'] ?? 'seance') . '_' . date('Ymd') . '.pdf';

        audit_log('report.generate_pdf', 'meeting', $meetingId, [
            'preview' => $isPreview,
            'sha256' => $hash,
        ], $meetingId);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('X-Content-Type-Options: nosniff');
        header('X-Report-SHA256: ' . $hash);

        echo $pdfContent;
    }

    public function generateReport(): void {
        $in = api_request('GET');
        $meetingId = trim((string) ($in['meeting_id'] ?? '')) ?: api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('invalid_meeting_id', 400);
        }

        $meetingRepo = new MeetingRepository();
        $motionRepo = new MotionRepository();

        $meeting = $meetingRepo->findWithValidator($meetingId);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }
        if ($meeting['validated_at'] === null) {
            api_fail('meeting_not_validated', 409);
        }

        $motions = $motionRepo->listForReportGeneration($meetingId);

        ob_start();
        echo '<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>PV – Séance ' . htmlspecialchars($meetingId) . '</title>
<style>
body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;font-size:14px;color:#111}
h1,h2{margin:0 0 8px}
table{border-collapse:collapse;width:100%;margin:12px 0}
th,td{border:1px solid #ccc;padding:6px 8px;text-align:left}
th{background:#f0f0f0}
.small{font-size:12px;color:#555}
</style>
</head>
<body>
<h1>Procès-verbal de séance</h1>
<p class="small">
Séance ID : ' . htmlspecialchars($meetingId) . '<br>
Validée le : ' . htmlspecialchars($meeting['validated_at']) . '<br>
Par : ' . htmlspecialchars($meeting['validated_by'] ?? '—') . '
</p>';

        foreach ($motions as $i => $mo) {
            $t = json_decode($mo['evote_results'] ?? '{}', true);
            echo '<h2>Résolution ' . ($i + 1) . ' – ' . htmlspecialchars($mo['title']) . '</h2>';
            echo '<table><tr><th>Vote</th><th>Nombre</th><th>Pondération</th></tr>';
            foreach (['for' => 'Pour', 'against' => 'Contre', 'abstain' => 'Abstention', 'nsp' => 'Blanc'] as $k => $lbl) {
                echo '<tr><td>' . $lbl . '</td><td>' . (int) ($t[$k]['count'] ?? 0) . '</td><td>' . (float) ($t[$k]['weight'] ?? 0) . '</td></tr>';
            }
            echo '</table>';
        }

        echo '</body></html>';
        $html = ob_get_clean();
        $hash = hash('sha256', $html);

        $tenantId = api_current_tenant_id();
        (new MeetingReportRepository())->upsertHash($meetingId, $hash, $tenantId);

        audit_log('report.generate_html', 'meeting', $meetingId, [
            'sha256' => $hash,
        ], $meetingId);

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }

    public function sendReport(): void {
        $input = api_request('POST');

        $meetingId = trim((string) ($input['meeting_id'] ?? ''));
        $toEmail = trim((string) ($input['email'] ?? ''));

        if ($meetingId === '' || $toEmail === '') {
            api_fail('missing_meeting_or_email', 400);
        }
        if (!api_is_uuid($meetingId)) {
            api_fail('invalid_meeting_id', 400);
        }

        api_guard_meeting_not_validated($meetingId);

        global $config;

        $tenantId = api_current_tenant_id();
        $repo = new MeetingRepository();
        $meeting = $repo->findByIdForTenant($meetingId, $tenantId);
        $meetingTitle = (string) (($meeting['title'] ?? '') ?: $meetingId);

        $appUrl = (string) (($config['app']['url'] ?? '') ?: 'http://localhost:8080');
        $reportUrl = rtrim($appUrl, '/') . '/api/v1/meeting_report.php?meeting_id=' . rawurlencode($meetingId);

        $mailer = new MailerService($config ?? []);
        if (!$mailer->isConfigured()) {
            api_fail('smtp_not_configured', 400);
        }

        ob_start();
        include __DIR__ . '/../Templates/email_report.php';
        $html = ob_get_clean();

        $subject = 'PV / Résultats – ' . $meetingTitle;
        $res = $mailer->send($toEmail, $subject, $html);

        if (!$res['ok']) {
            api_fail('mail_send_failed', 500);
        }

        audit_log('report.send', 'meeting', $meetingId, [
            'email' => $toEmail,
        ], $meetingId);

        api_ok(['ok' => true]);
    }

    public function exportPvHtml(): void {
        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'missing_meeting_id';
            exit;
        }

        $showVoters = (api_query('show_voters') === '1');

        $html = (new MeetingReportService())->renderHtml($meetingId, $showVoters);

        audit_log('report.export_pv_html', 'meeting', $meetingId, [
            'show_voters' => $showVoters,
        ], $meetingId);

        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo $html;
    }

    // --- Private helpers for report() HTML generation ---

    private static function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function decisionLabel(string $dec): string {
        return match ($dec) {
            'adopted' => 'Adoptée', 'rejected' => 'Rejetée', 'no_quorum' => 'Sans quorum',
            'no_votes' => 'Sans votes', 'no_policy' => 'Sans règle', 'cancelled' => 'Annulée',
            'pending' => 'En attente', default => $dec,
        };
    }

    private static function fmtNum(float $n): string {
        if (abs($n - round($n)) < 0.000001) {
            return (string) intval(round($n));
        }
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    }

    private static function modeLabel(string $mode): string {
        return match ($mode) {
            'present' => 'Présent', 'remote' => 'À distance', 'proxy' => 'Représenté',
            'excused' => 'Excusé', 'absent', '' => 'Absent', default => $mode,
        };
    }

    private static function choiceLabel(string $choice): string {
        return match ($choice) {
            'for' => 'Pour', 'against' => 'Contre', 'abstain' => 'Abstention',
            'nsp' => 'Ne se prononce pas', 'blank' => 'Blanc', default => $choice,
        };
    }

    private static function policyLabel(?array $votePolicy, ?array $quorumPolicy): string {
        $parts = [];
        if ($quorumPolicy) {
            $parts[] = 'Quorum: ' . ($quorumPolicy['denominator'] ?? '—') . ' ≥ ' . ($quorumPolicy['threshold'] ?? '—');
        } else {
            $parts[] = 'Quorum: —';
        }
        if ($votePolicy) {
            $abst = !empty($votePolicy['abstention_as_against']) ? ' (abst→contre)' : '';
            $parts[] = 'Majorité: ' . ($votePolicy['base'] ?? '—') . ' ≥ ' . ($votePolicy['threshold'] ?? '—') . $abst;
        } else {
            $parts[] = 'Majorité: —';
        }
        return implode(' · ', $parts);
    }
}
