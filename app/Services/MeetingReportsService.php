<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\InvitationRepository;
use AgVote\Repository\MeetingReportRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\ProxyRepository;
use AgVote\Repository\SettingsRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Throwable;

/** Thin orchestrator: data fetching, caching, PDF rendering, delegation to ReportGenerator. */
final class MeetingReportsService {
    private MeetingRepository $meetingRepo;
    private MotionRepository $motionRepo;
    private AttendanceRepository $attendanceRepo;
    private BallotRepository $ballotRepo;
    private PolicyRepository $policyRepo;
    private ProxyRepository $proxyRepo;
    private InvitationRepository $invitationRepo;
    private MeetingReportRepository $meetingReportRepo;
    private SettingsRepository $settingsRepo;
    private ?ReportGenerator $generator = null;

    public function __construct(
        ?MeetingRepository $meetingRepo = null,
        ?MotionRepository $motionRepo = null,
        ?AttendanceRepository $attendanceRepo = null,
        ?BallotRepository $ballotRepo = null,
        ?PolicyRepository $policyRepo = null,
        ?ProxyRepository $proxyRepo = null,
        ?InvitationRepository $invitationRepo = null,
        ?MeetingReportRepository $meetingReportRepo = null,
        ?SettingsRepository $settingsRepo = null,
    ) {
        $factory = RepositoryFactory::getInstance();
        $this->meetingRepo = $meetingRepo ?? $factory->meeting();
        $this->motionRepo = $motionRepo ?? $factory->motion();
        $this->attendanceRepo = $attendanceRepo ?? $factory->attendance();
        $this->ballotRepo = $ballotRepo ?? $factory->ballot();
        $this->policyRepo = $policyRepo ?? $factory->policy();
        $this->proxyRepo = $proxyRepo ?? $factory->proxy();
        $this->invitationRepo = $invitationRepo ?? $factory->invitation();
        $this->meetingReportRepo = $meetingReportRepo ?? $factory->meetingReport();
        $this->settingsRepo = $settingsRepo ?? $factory->settings();
    }

    private function generator(): ReportGenerator {
        return $this->generator ??= new ReportGenerator();
    }

    /** Build the full HTML report for the report() endpoint. */
    public function buildReportHtml(
        string $meetingId,
        string $tenantId,
        bool $showVoters = false,
        bool $regen = false,
    ): string {
        if (!$regen) {
            try {
                $snap = $this->meetingReportRepo->findSnapshot($meetingId, $tenantId);
                if ($snap && !empty($snap['html'])) {
                    return (string) $snap['html'];
                }
            } catch (Throwable) {
            }
        }

        $motions = $this->motionRepo->listForReport($meetingId, $tenantId);
        $attendance = $this->attendanceRepo->listForReport($meetingId, $tenantId);

        $proxies = [];
        try {
            $proxies = $this->proxyRepo->listForReport($meetingId, $tenantId);
        } catch (Throwable) {
        }

        $tokens = [];
        try {
            $tokens = $this->invitationRepo->listTokensForReport($meetingId, $tenantId);
        } catch (Throwable) {
        }

        // Pre-fetch policies and official tallies per motion
        $policiesByMotion = [];
        $officialsByMotion = [];
        foreach ($motions as $m) {
            $mid = (string) $m['id'];
            $votePolicy = !empty($m['vote_policy_id']) ? $this->policyRepo->findVotePolicy($m['vote_policy_id'], $tenantId) : null;
            $quorumPolicy = !empty($m['quorum_policy_id']) ? $this->policyRepo->findQuorumPolicy($m['quorum_policy_id'], $tenantId) : null;
            $policiesByMotion[$mid] = ['votePolicy' => $votePolicy, 'quorumPolicy' => $quorumPolicy];

            $src = (string) ($m['official_source'] ?? '');
            $hasOfficial = $src !== '' && $m['official_total'] !== null;
            $detail = ['quorum_met' => null, 'quorum_ratio' => null, 'majority_ratio' => null, 'majority_threshold' => null, 'majority_base' => null];

            if (!$hasOfficial && $m['closed_at'] !== null) {
                try {
                    $o = (new OfficialResultsService())->computeOfficialTallies($mid, $tenantId);
                    [$src, $of, $og, $oa, $ot, $dec, $reas, $note] = [$o['source'], $o['for'], $o['against'], $o['abstain'], $o['total'], $o['decision'], $o['reason'], ' (calculé)'];
                } catch (Throwable) {
                    [$src, $of, $og, $oa, $ot, $dec, $reas, $note] = ['—', 0.0, 0.0, 0.0, 0.0, '—', 'error', ' (erreur calc)'];
                }
            } else {
                [$of, $og, $oa, $ot] = [(float) ($m['official_for'] ?? 0), (float) ($m['official_against'] ?? 0), (float) ($m['official_abstain'] ?? 0), (float) ($m['official_total'] ?? 0)];
                [$dec, $reas, $note] = [(string) ($m['decision'] ?? '—'), (string) ($m['decision_reason'] ?? ''), ''];
            }

            try {
                if ($src === 'evote') {
                    $r = (new VoteEngine())->computeMotionResult($mid, $tenantId);
                    $detail = ['quorum_met' => $r['quorum']['met'] ?? null, 'quorum_ratio' => $r['quorum']['ratio'] ?? null, 'majority_ratio' => $r['decision']['ratio'] ?? ($r['majority']['ratio'] ?? null), 'majority_threshold' => $r['decision']['threshold'] ?? ($r['majority']['threshold'] ?? null), 'majority_base' => $r['decision']['base'] ?? ($r['majority']['base'] ?? null)];
                }
            } catch (Throwable) {
            }

            $officialsByMotion[$mid] = [
                'source' => $src, 'for' => $of, 'against' => $og, 'abstain' => $oa,
                'total' => $ot, 'decision' => $dec, 'reason' => $reas, 'note' => $note, 'detail' => $detail,
            ];
        }

        $ballotsByMotion = [];
        foreach ($motions as $m) { $ballotsByMotion[(string) $m['id']] = $this->ballotRepo->listDetailedForMotion((string) $m['id'], $tenantId); }

        $gen = $this->generator();
        $rowsHtml = $gen->buildMotionRows($motions, $policiesByMotion, $officialsByMotion);
        [$attRows, $attSummary] = $gen->buildAttendanceSection($attendance);
        [$proxyRows, $proxySummary] = $gen->buildProxiesSection($proxies);
        [$tokenRows, $tokenSummary] = $gen->buildTokensSection($tokens);
        $votesByMotionHtml = $gen->buildVoteDetailsSection($motions, $ballotsByMotion, $showVoters);

        $meeting = $this->meetingRepo->findByIdForTenant($meetingId, $tenantId) ?? [];
        $h = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return $gen->assembleReportHtml(
            $h((string) ($meeting['title'] ?? '')),
            $h((string) ($meeting['status'] ?? '')),
            $h((string) ($meeting['president_name'] ?? '')),
            $h((string) ($meeting['created_at'] ?? '')),
            $h((string) ($meeting['validated_at'] ?? '')),
            $h((string) ($meeting['archived_at'] ?? '')),
            $rowsHtml, $attRows, $attSummary,
            $proxyRows, $proxySummary, $tokenRows, $tokenSummary,
            $votesByMotionHtml,
        );
    }

    /** Build the HTML document used for PDF generation via DOMPDF. */
    public function buildPdfHtml(
        array $meeting,
        array $attendances,
        array $motions,
        array $proxies,
        string $orgName,
        bool $isPreview,
    ): string {
        $meetingId = (string) ($meeting['id'] ?? '');
        $css = '@page{margin:2cm}body{font-family:"DejaVu Sans",Arial,sans-serif;font-size:11pt;line-height:1.5;color:#333}h1{font-size:18pt;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:10px;margin-bottom:20px}h2{font-size:14pt;color:#2563eb;margin-top:25px;margin-bottom:10px}h3{font-size:12pt;color:#374151;margin-top:15px;margin-bottom:8px}.header-info{background:#f8fafc;border:1px solid #e2e8f0;padding:15px;margin-bottom:20px;border-radius:4px}.header-info p{margin:5px 0}table{width:100%;border-collapse:collapse;margin:15px 0}th,td{border:1px solid #d1d5db;padding:8px 12px;text-align:left}th{background:#f3f4f6;font-weight:bold}tr:nth-child(even){background:#f9fafb}.result-box{background:#f0fdf4;border:1px solid #86efac;padding:10px 15px;margin:10px 0;border-radius:4px}.result-rejected{background:#fef2f2;border-color:#fca5a5}.badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:10pt;font-weight:bold}.badge-success{background:#dcfce7;color:#166534}.badge-danger{background:#fee2e2;color:#991b1b}.badge-warning{background:#fef3c7;color:#92400e}.footer{margin-top:40px;padding-top:20px;border-top:1px solid #e5e7eb;font-size:9pt;color:#6b7280}.draft-watermark{position:fixed;top:40%;left:50%;transform:translate(-50%,-50%) rotate(-45deg);font-size:80pt;color:rgba(220,38,38,0.15);font-weight:bold;z-index:-1;white-space:nowrap}.draft-banner{background:#fef2f2;border:2px solid #dc2626;color:#dc2626;padding:10px 15px;margin-bottom:20px;border-radius:4px;text-align:center;font-weight:bold}';
        $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Procès-verbal - ' . htmlspecialchars($meeting['title'] ?? 'Séance') . '</title><style>' . $css . '</style></head><body>';

        if ($isPreview) {
            $html .= '<div class="draft-watermark">BROUILLON</div><div class="draft-banner">DOCUMENT BROUILLON - NON VALIDE - A TITRE INDICATIF UNIQUEMENT</div>';
        }
        if ($orgName !== '') {
            $html .= '<h1 style="text-align:center;font-size:16pt;margin-bottom:5px">' . htmlspecialchars($orgName) . '</h1>';
        }
        $html .= '<h2 style="text-align:center;font-size:14pt;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:10px">PROCES-VERBAL DE SEANCE' . ($isPreview ? ' (BROUILLON)' : '') . '</h2>';
        $html .= '<div class="header-info">';
        $html .= '<p><strong>Titre :</strong> ' . htmlspecialchars($meeting['title'] ?? '—') . '</p>';
        $html .= '<p><strong>Date :</strong> ' . ($meeting['scheduled_at'] ? date('d/m/Y à H:i', strtotime($meeting['scheduled_at'])) : '—') . '</p>';
        $html .= '<p><strong>Lieu :</strong> ' . htmlspecialchars($meeting['location'] ?? '—') . '</p>';
        $html .= '<p><strong>Président :</strong> ' . htmlspecialchars($meeting['president_name'] ?? '—') . '</p>';
        $html .= $isPreview
            ? '<p><strong>Statut :</strong> <span style="color:#dc2626;">Non validé (brouillon)</span></p>'
            : '<p><strong>Validé le :</strong> ' . date('d/m/Y à H:i', strtotime($meeting['validated_at'])) . '</p>';
        $html .= '</div>';

        $html .= '<h2>1. Feuille de présence</h2>';
        $pCount = count(array_filter($attendances, fn ($a) => in_array($a['mode'], ['present', 'remote'])));
        $totalPower = array_sum(array_column($attendances, 'voting_power'));
        $presentPower = array_sum(array_map(fn ($a) => in_array($a['mode'], ['present', 'remote', 'proxy']) ? ($a['voting_power'] ?? 0) : 0, $attendances));

        $html .= '<p><strong>' . $pCount . '</strong> membres présents sur <strong>' . count($attendances) . '</strong> inscrits — Pouvoir représenté : <strong>' . number_format((float) $presentPower, 2) . '</strong> / ' . number_format((float) $totalPower, 2) . '</p>';

        $html .= '<table><tr><th>Nom</th><th>Pouvoir</th><th>Statut</th><th>Arrivée</th></tr>';
        foreach ($attendances as $a) {
            $sl = match ($a['mode'] ?? 'absent') { 'present' => 'Présent', 'remote' => 'À distance', 'proxy' => 'Représenté', 'excused' => 'Excusé', default => 'Absent' };
            $html .= '<tr><td>' . htmlspecialchars($a['full_name']) . '</td><td>' . number_format((float) ($a['voting_power'] ?? 1), 2) . '</td><td>' . $sl . '</td><td>' . ($a['checked_in_at'] ? date('H:i', strtotime($a['checked_in_at'])) : '—') . '</td></tr>';
        }
        $html .= '</table>';

        $quorumRatio = ($totalPower > 0) ? round(($presentPower / $totalPower) * 100, 1) : 0;
        $html .= '<div class="result-box"><strong>Quorum de la séance :</strong> ' . $quorumRatio . '% des voix représentées (' . number_format((float) $presentPower, 2) . ' / ' . number_format((float) $totalPower, 2) . ')</div>';

        if (!empty($proxies)) {
            $html .= '<h2>2. Procurations</h2><table><tr><th>Mandant</th><th>Mandataire</th></tr>';
            foreach ($proxies as $p) { $html .= '<tr><td>' . htmlspecialchars($p['giver_name']) . '</td><td>' . htmlspecialchars($p['receiver_name']) . '</td></tr>'; }
            $html .= '</table>';
        }
        $html .= '<h2>' . (empty($proxies) ? '2' : '3') . '. Résolutions</h2>';

        foreach ($motions as $i => $m) {
            $html .= '<h3>Résolution n°' . ($i + 1) . ' : ' . htmlspecialchars($m['title']) . '</h3>';
            if (!empty($m['description'])) { $html .= '<p>' . nl2br(htmlspecialchars($m['description'])) . '</p>'; }
            if ($m['secret']) { $html .= '<p><span class="badge badge-warning">Vote secret</span></p>'; }
            $fmt = fn (string $k): string => number_format((float) ($m[$k] ?? 0), 2);
            $html .= '<table><tr><th>Vote</th><th>Poids</th></tr><tr><td>Pour</td><td>' . $fmt('official_for') . '</td></tr><tr><td>Contre</td><td>' . $fmt('official_against') . '</td></tr><tr><td>Abstention</td><td>' . $fmt('official_abstain') . '</td></tr><tr><td><strong>Total</strong></td><td><strong>' . $fmt('official_total') . '</strong></td></tr></table>';
            $dc = ($m['decision'] === 'adopted') ? '' : 'result-rejected';
            $dl = match ($m['decision'] ?? '') { 'adopted' => 'ADOPTEE', 'rejected' => 'REJETEE', 'no_quorum' => 'SANS QUORUM', 'no_votes' => 'SANS VOTES', 'no_policy' => 'SANS REGLE', 'cancelled' => 'ANNULEE', 'pending' => 'EN ATTENTE', default => strtoupper($m['decision'] ?? '—') };
            $html .= '<div class="result-box ' . $dc . '"><strong>Décision :</strong> ' . $dl . (!empty($m['decision_reason']) ? ' — ' . htmlspecialchars($m['decision_reason']) : '') . '</div>';
        }

        $sigStyle = 'width:50%;text-align:center;padding:20px;border:none';
        $sigLine = 'margin-top:60px;border-top:1px solid #333';
        $html .= '<table style="width:100%;margin-top:40px;border:none"><tr>';
        $html .= '<td style="' . $sigStyle . '"><p>Le Président de séance</p><p style="' . $sigLine . '">' . htmlspecialchars($meeting['president_name'] ?? '') . '</p></td>';
        $html .= '<td style="' . $sigStyle . '"><p>Le Secrétaire de séance</p><p style="' . $sigLine . '">&nbsp;</p></td>';
        $html .= '</tr></table>';
        $html .= $isPreview
            ? '<p style="font-size:9pt;color:#dc2626;text-align:center">Document brouillon - Généré le ' . date('d/m/Y à H:i') . '</p>'
            : '<p style="font-size:9pt;color:#6b7280;text-align:center">Fait le ' . date('d/m/Y à H:i', strtotime($meeting['validated_at'])) . '</p>';
        $html .= '<div class="footer"><p>Document généré automatiquement par AG-VOTE le ' . date('d/m/Y à H:i') . '</p><p>Identifiant séance : ' . htmlspecialchars($meetingId) . '</p></div></body></html>';

        return $html;
    }

    /** @return array{pdf: string, hash: string, filename: string, html: string} */
    public function buildPdfBytes(
        string $meetingId,
        string $tenantId,
        bool $isPreview,
        bool $isInline,
        array $meeting,
    ): array {
        $orgName = $this->settingsRepo->get($tenantId, 'org_name') ?? '';
        $attendances = $this->attendanceRepo->listForReport($meetingId, $tenantId);
        $motions = $this->motionRepo->listForReport($meetingId, $tenantId);
        $proxies = $this->proxyRepo->listForReport($meetingId, $tenantId);

        $html = $this->buildPdfHtml($meeting, $attendances, $motions, $proxies, $orgName, $isPreview);

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
            $this->meetingReportRepo->upsertFull($meetingId, $html, $hash, $tenantId);
        }

        $prefix = $isPreview ? 'BROUILLON_PV_' : 'PV_';
        $safeTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', $meeting['title'] ?? 'seance');
        $filename = $prefix . $safeTitle . '_' . date('Ymd') . '.pdf';

        return [
            'pdf' => $pdfContent,
            'hash' => $hash,
            'filename' => $filename,
            'html' => $html,
            'meeting' => $meeting,
        ];
    }

    /** @return array{html: string, hash: string} */
    public function buildGeneratedReportHtml(string $meetingId, string $tenantId, array $meeting): array {
        $motions = $this->motionRepo->listForReportGeneration($meetingId, $tenantId);

        $html = $this->generator()->assembleGeneratedReportHtml($meetingId, $meeting, $motions);
        $hash = hash('sha256', $html);

        $this->meetingReportRepo->upsertHash($meetingId, $hash, $tenantId);

        return ['html' => $html, 'hash' => $hash];
    }
}
