<?php

declare(strict_types=1);

namespace AgVote\Service;

/** Stateless HTML generation for meeting reports — no repository dependencies. */
final class ReportGenerator {
    /** Assemble the full HTML report document from pre-computed data. */
    public function assembleReportHtml(
        string $title,
        string $status,
        string $president,
        string $createdAt,
        string $validatedAt,
        string $archivedAt,
        string $rowsHtml,
        string $attRows,
        string $attSummary,
        string $proxyRows,
        string $proxySummary,
        string $tokenRows,
        string $tokenSummary,
        string $votesByMotionHtml,
    ): string {
        return <<<HTML
            <!DOCTYPE html>
            <html lang="fr">
            <head>
            <meta charset="UTF-8">
            <title>PV — {$title}</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:24px;color:#111827}h1{font-size:20px;margin:0 0 4px}h2{font-size:16px;margin:22px 0 6px}.muted{color:#6b7280;font-size:12px}.tiny{font-size:12px}.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace}.badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;background:#f3f4f6;border:1px solid #e5e7eb}.badge.success{background:#dcfce7;border-color:#bbf7d0}.badge.danger{background:#fee2e2;border-color:#fecaca}.badge.muted{background:#f3f4f6;border-color:transparent;color:#6b7280}.tbl{width:100%;border-collapse:collapse;margin-top:10px}.tbl th,.tbl td{border-bottom:1px solid #eef2f7;padding:8px;vertical-align:top}.tbl th{text-align:left;font-size:12px;color:#6b7280}.num{text-align:right;font-variant-numeric:tabular-nums}.toolbar{position:sticky;top:0;background:#fff;padding:10px 0;border-bottom:1px solid #eef2f7;margin-bottom:12px}.btn{padding:8px 12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;cursor:pointer}@media print{.toolbar{display:none}body{margin:0}}</style>
            </head>
            <body>
            <div class="toolbar"><button class="btn" onclick="window.print()">Imprimer</button></div>
            <h1>Procès-verbal</h1>
            <div class="muted">
            Séance: <strong>{$title}</strong> · Statut: <strong>{$status}</strong> · Président: <strong>{$president}</strong><br>
            Créée: {$createdAt} · Validée: {$validatedAt} · Archivée: {$archivedAt}
            </div>

            <h2>Résolutions</h2>
            <table class="tbl">
              <thead><tr><th>Motion</th><th>Source officielle</th><th class="num">Pour</th><th class="num">Contre</th><th class="num">Abst.</th><th class="num">Total</th><th>Décision</th></tr></thead>
              <tbody>{$rowsHtml}</tbody>
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
    }

    /** Assemble the simple generated-report HTML from meeting + motions data. */
    public function assembleGeneratedReportHtml(string $meetingId, array $meeting, array $motions): string {
        ob_start();
        echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">
<title>PV – Séance ' . htmlspecialchars($meetingId) . '</title>
<style>body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;font-size:14px;color:#111}h1,h2{margin:0 0 8px}table{border-collapse:collapse;width:100%;margin:12px 0}th,td{border:1px solid #ccc;padding:6px 8px;text-align:left}th{background:#f0f0f0}.small{font-size:12px;color:#555}</style>
</head><body>
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
        return ob_get_clean() ?: '';
    }

    /** @param array<int,array<string,mixed>> $motions */
    public function buildMotionRows(array $motions, array $policiesByMotion, array $officialsByMotion): string {
        $rowsHtml = '';
        foreach ($motions as $m) {
            $mid = (string) $m['id'];

            $votePolicy = $policiesByMotion[$mid]['votePolicy'] ?? null;
            $quorumPolicy = $policiesByMotion[$mid]['quorumPolicy'] ?? null;

            $off = $officialsByMotion[$mid] ?? [];
            $src = (string) ($off['source'] ?? '—');
            $of = (float) ($off['for'] ?? 0);
            $og = (float) ($off['against'] ?? 0);
            $oa = (float) ($off['abstain'] ?? 0);
            $ot = (float) ($off['total'] ?? 0);
            $dec = (string) ($off['decision'] ?? '—');
            $reas = (string) ($off['reason'] ?? '');
            $note = (string) ($off['note'] ?? '');
            $detail = $off['detail'] ?? ['quorum_met' => null, 'quorum_ratio' => null, 'majority_ratio' => null, 'majority_threshold' => null, 'majority_base' => null];

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
        return $rowsHtml;
    }

    /** @return array{0: string, 1: string} */
    public function buildAttendanceSection(array $attendance): array {
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
        $attSummary = "Présents: {$presentCount} (poids " . number_format($presentWeight, 2, '.', '') . ') · Poids total: ' . number_format($totalWeight, 2, '.', '');
        return [$attRows, $attSummary];
    }

    /** @return array{0: string, 1: string} */
    public function buildProxiesSection(array $proxies): array {
        $proxyRows = '';
        foreach ($proxies as $p) {
            $proxyRows .= '<tr><td>' . self::h($p['giver_name'] ?? '') . '</td><td>' . self::h($p['receiver_name'] ?? '') . "</td><td class='tiny muted'>" . self::h((string) ($p['revoked_at'] ?? '')) . '</td></tr>';
        }
        $proxySummary = $proxies ? 'Procurations: ' . count($proxies) : 'Procurations: 0';
        return [$proxyRows, $proxySummary];
    }

    /** @return array{0: string, 1: string} */
    public function buildTokensSection(array $tokens): array {
        $tokenRows = '';
        foreach ($tokens as $t) {
            $tokenRows .= '<tr><td>' . self::h($t['full_name'] ?? '') . "</td><td class='tiny muted'>" . self::h((string) ($t['created_at'] ?? '')) . "</td><td class='tiny muted'>" . self::h((string) ($t['last_used_at'] ?? '')) . "</td><td class='tiny muted'>" . self::h((string) ($t['revoked_at'] ?? '')) . '</td></tr>';
        }
        $tokenSummary = $tokens ? 'Tokens: ' . count($tokens) : 'Tokens: 0';
        return [$tokenRows, $tokenSummary];
    }

    /** @param array<string,array<int,array<string,mixed>>> $ballotsByMotion Pre-fetched [motionId => ballots[]] */
    public function buildVoteDetailsSection(array $motions, array $ballotsByMotion, bool $showVoters): string {
        if (!$showVoters) {
            return "<div class='muted tiny'>Annexe D masquée (par défaut). Ajoutez <span class='mono'>?show_voters=1</span> pour inclure les votants nominativement (usage interne Trust).</div>";
        }

        $votesByMotionHtml = '';
        foreach ($motions as $m) {
            $mid = (string) $m['id'];
            $title = (string) ($m['title'] ?? 'Motion');
            $isClosed = ($m['closed_at'] !== null);

            $ballots = $ballotsByMotion[$mid] ?? [];

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
        return $votesByMotionHtml;
    }

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
