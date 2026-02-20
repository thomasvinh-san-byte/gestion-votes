<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\ManualActionRepository;
use AgVote\Repository\PolicyRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class MeetingReportService
{
    public static function renderHtml(string $meetingId, bool $showVoters = false): string
    {
        $meetingId = trim($meetingId);
        if ($meetingId === '') throw new InvalidArgumentException('meeting_id obligatoire');

        $tenant = (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);

        $meetingRepo = new MeetingRepository();
        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenant);
        if (!$meeting) throw new RuntimeException('meeting_not_found');

        $motionRepo = new MotionRepository();
        $motions = $motionRepo->listForReport($meetingId);

        $attRepo = new AttendanceRepository();
        $attendance = $attRepo->listForReport($meetingId, $tenant);

        $manualActions = [];
        try {
            $maRepo = new ManualActionRepository();
            $manualActions = $maRepo->listForMeeting($meetingId);
        } catch (Throwable $e) { $manualActions = []; }

        $policyRepo = new PolicyRepository();

        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = '';
        $html .= "<!doctype html><html lang=\"fr\"><head><meta charset=\"utf-8\">";
        $html .= "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">";
        $html .= "<title>PV — ".$h($meeting['title'])."</title>";
        $html .= "<style>
            body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:24px;color:#111}
            h1{margin:0 0 6px 0;font-size:22px}
            .muted{color:#666}
            .tiny{font-size:12px}
            .card{border:1px solid #e6e6e6;border-radius:10px;padding:14px;margin:12px 0}
            table{width:100%;border-collapse:collapse}
            th,td{border-bottom:1px solid #eee;padding:8px 6px;vertical-align:top}
            th{text-align:left;font-size:12px;color:#666}
            .badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #ddd;font-size:12px}
            .ok{border-color:#b7e1c1}
            .ko{border-color:#f3b3b3}
            .right{text-align:right}
            .hr{height:1px;background:#eee;margin:10px 0}
            @media print {.no-print{display:none} body{margin:8mm}}
        </style></head><body>";

        $html .= "<div class=\"no-print\" style=\"display:flex;gap:8px;align-items:center;justify-content:space-between\">";
        $html .= "<div><h1>PV / Rapport</h1><div class=\"muted tiny\">Prévisualisation. Utilisez Imprimer pour PDF.</div></div>";
        $html .= "<button onclick=\"window.print()\">Imprimer</button>";
        $html .= "</div>";

        $html .= "<div class=\"card\">";
        $html .= "<div style=\"display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap\">";
        $html .= "<div><div class=\"tiny muted\">Séance</div><div><strong>".$h($meeting['title'])."</strong></div></div>";
        $html .= "<div><div class=\"tiny muted\">Président</div><div>".$h($meeting['president_name'] ?: '—')."</div></div>";
        $html .= "<div><div class=\"tiny muted\">Statut</div><div>".$h(self::statusLabel($meeting['status']))."</div></div>";
        $html .= "<div><div class=\"tiny muted\">Validée</div><div>".$h($meeting['validated_at'] ?: '—')."</div></div>";
        $html .= "</div></div>";

        // Attendance summary
        $present = 0; $remote=0; $proxy=0; $absent=0;
        foreach ($attendance as $a) {
            $mode = (string)($a['mode'] ?? '');
            $out = $a['checked_out_at'] !== null;
            if ($out) { $absent++; continue; }
            if ($mode === 'present') $present++;
            elseif ($mode === 'remote') $remote++;
            elseif ($mode === 'proxy') $proxy++;
            else $absent++;
        }
        $html .= "<div class=\"card\"><div style=\"display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap\">";
        $html .= "<div><div class=\"tiny muted\">Présents</div><div><strong>$present</strong></div></div>";
        $html .= "<div><div class=\"tiny muted\">Distants</div><div><strong>$remote</strong></div></div>";
        $html .= "<div><div class=\"tiny muted\">Représentés</div><div><strong>$proxy</strong></div></div>";
        $html .= "</div></div>";

        // Motions
        $html .= "<div class=\"card\"><div style=\"display:flex;justify-content:space-between;align-items:center\">";
        $html .= "<div><strong>Résolutions</strong><div class=\"muted tiny\">Résultats, sources, règles, justifications</div></div></div>";
        $html .= "<div class=\"hr\"></div>";
        $html .= "<table><thead><tr>
                    <th>Résolution</th>
                    <th>Source</th>
                    <th class=\"right\">Pour</th>
                    <th class=\"right\">Contre</th>
                    <th class=\"right\">Abst.</th>
                    <th class=\"right\">Total</th>
                    <th>Décision</th>
                  </tr></thead><tbody>";

        foreach ($motions as $m) {
            $mid = (string)$m['id'];

            // Ensure official tallies
            $src = (string)($m['official_source'] ?? '');
            $hasOfficial = ($src !== '') && ($m['official_total'] !== null);

            if (!$hasOfficial && $m['closed_at'] !== null) {
                $o = OfficialResultsService::computeOfficialTallies($mid);
                $src = $o['source'];
                $of = (float)$o['for']; $og = (float)$o['against']; $oa = (float)$o['abstain']; $ot = (float)$o['total'];
                $dec = (string)$o['decision']; $reas = (string)$o['reason'];
            } else {
                $of = (float)($m['official_for'] ?? 0);
                $og = (float)($m['official_against'] ?? 0);
                $oa = (float)($m['official_abstain'] ?? 0);
                $ot = (float)($m['official_total'] ?? 0);
                $dec = (string)($m['decision'] ?? '—');
                $reas = (string)($m['decision_reason'] ?? '');
                if ($src === '') $src = '—';
            }

            // Policy labels + justifications
            $votePolicy = null;
            if (!empty($m['vote_policy_id'])) $votePolicy = $policyRepo->findVotePolicy($m['vote_policy_id']);
            $quorumPolicy = null;
            if (!empty($m['quorum_policy_id'])) $quorumPolicy = $policyRepo->findQuorumPolicy($m['quorum_policy_id']);

            $policyLine = self::policyLine($votePolicy, $quorumPolicy);

            $quorumJust = null;
            try { $qr = QuorumEngine::computeForMotion($mid); $quorumJust = (string)($qr['justification'] ?? null); }
            catch (Throwable $e) { $quorumJust = null; }

            $majorityJust = null;
            try {
                $vr = VoteEngine::computeMotionResult($mid);
                $maj = $vr['majority'] ?? null;
                if (is_array($maj)) {
                    $majorityJust = self::majorityLine($maj);
                }
            } catch (Throwable $e) { $majorityJust = null; }

            $just = $policyLine;
            if ($quorumJust) $just .= " · " . $quorumJust;
            if ($majorityJust) $just .= " · " . $majorityJust;

            $badgeClass = ($dec === 'adopted') ? 'ok' : (($dec === 'rejected') ? 'ko' : '');
            $html .= "<tr>";
            $html .= "<td><div><strong>".$h($m['title'])."</strong></div><div class=\"muted tiny\">".$h($just)."</div></td>";
            $html .= "<td>".$h($src)."</td>";
            $html .= "<td class=\"right\">".$h(self::fmt($of))."</td>";
            $html .= "<td class=\"right\">".$h(self::fmt($og))."</td>";
            $html .= "<td class=\"right\">".$h(self::fmt($oa))."</td>";
            $html .= "<td class=\"right\">".$h(self::fmt($ot))."</td>";
            $html .= "<td><span class=\"badge $badgeClass\">".$h(self::decisionLabel($dec))."</span><div class=\"muted tiny\">".$h($reas)."</div></td>";
            $html .= "</tr>";
        }

        $html .= "</tbody></table></div>";

        // Manual actions annex
        if (!empty($manualActions)) {
            $html .= "<div class=\"card\"><strong>Annexe – Actions manuelles (mode dégradé)</strong>";
            $html .= "<div class=\"muted tiny\">Journal append-only des actions opérateur.</div><div class=\"hr\"></div>";
            $html .= "<table><thead><tr><th>Date</th><th>Type</th><th>Valeur</th><th>Justification</th></tr></thead><tbody>";
            foreach ($manualActions as $a) {
                $html .= "<tr><td class=\"tiny\">".$h($a['created_at'])."</td><td>".$h($a['action_type'])."</td><td class=\"tiny\">".$h($a['value'])."</td><td class=\"tiny\">".$h($a['justification'])."</td></tr>";
            }
            $html .= "</tbody></table></div>";
        }

        if ($showVoters) {
            $html .= "<div class=\"card\"><strong>Annexe – Présences</strong><div class=\"hr\"></div>";
            $html .= "<table><thead><tr><th>Nom</th><th class=\"right\">Pouvoir</th><th>Mode</th></tr></thead><tbody>";
            foreach ($attendance as $a) {
                $html .= "<tr><td>".$h($a['full_name'])."</td><td class=\"right\">".$h(self::fmt((float)$a['voting_power']))."</td><td>".$h(self::modeLabel($a['mode'] ?? ''))."</td></tr>";
            }
            $html .= "</tbody></table></div>";
        }

        $html .= "</body></html>";
        return $html;
    }

    private static function fmt(float $n): string
    {
        if (abs($n - round($n)) < 0.000001) return (string)intval(round($n));
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    }

    private static function policyLine(?array $votePolicy, ?array $quorumPolicy): string
    {
        $parts = [];
        if ($quorumPolicy) {
            $parts[] = "Quorum: ".($quorumPolicy['denominator'] ?? '—')." ≥ ".($quorumPolicy['threshold'] ?? '—');
        } else {
            $parts[] = "Quorum: —";
        }
        if ($votePolicy) {
            $abst = !empty($votePolicy['abstention_as_against']) ? " (abst→contre)" : "";
            $parts[] = "Majorité: ".($votePolicy['base'] ?? '—')." ≥ ".($votePolicy['threshold'] ?? '—').$abst;
        } else {
            $parts[] = "Majorité: —";
        }
        return implode(" · ", $parts);
    }

    /** @param array<string,mixed> $maj */
    private static function majorityLine(array $maj): ?string
    {
        $base = $maj['base'] ?? null;
        $ratio = $maj['ratio'] ?? null;
        $thr = $maj['threshold'] ?? null;
        if ($base === null || $ratio === null || $thr === null) return null;
        return "Majorité (".$base."): ratio ".self::fmt((float)$ratio)." / seuil ".self::fmt((float)$thr);
    }

    private static function decisionLabel(string $dec): string
    {
        return match($dec) {
            'adopted' => 'Adoptée',
            'rejected' => 'Rejetée',
            'no_quorum' => 'Sans quorum',
            'no_votes' => 'Sans votes',
            'no_policy' => 'Sans règle',
            'cancelled' => 'Annulée',
            'pending' => 'En attente',
            default => $dec,
        };
    }

    private static function statusLabel(string $status): string
    {
        return match($status) {
            'draft' => 'Brouillon',
            'scheduled' => 'Programmée',
            'frozen' => 'Figée',
            'live' => 'En cours',
            'closed' => 'Clôturée',
            'validated' => 'Validée',
            'archived' => 'Archivée',
            default => $status,
        };
    }

    private static function modeLabel(string $mode): string
    {
        return match($mode) {
            'present' => 'Présent',
            'remote' => 'À distance',
            'proxy' => 'Représenté',
            'excused' => 'Excusé',
            'absent', '' => 'Absent',
            default => $mode,
        };
    }
}
