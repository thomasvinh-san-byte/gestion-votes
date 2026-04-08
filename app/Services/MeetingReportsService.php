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

/**
 * Business logic for all meeting report endpoints.
 *
 * Extracted from MeetingReportsController to enable unit testing of HTML/PDF generation,
 * snapshot caching, data assembly, and report dispatch independent of HTTP context.
 *
 * Endpoints covered: report, generatePdf, generateReport
 */
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

    /**
     * Build the full HTML report for the report() endpoint.
     *
     * Returns cached snapshot when available (and regen=false).
     * On cache miss, assembles motions + attendance + proxies + tokens into HTML.
     *
     * @return string HTML document
     */
    public function buildReportHtml(
        string $meetingId,
        string $tenantId,
        bool $showVoters = false,
        bool $regen = false,
    ): string {
        // Serve cached snapshot if available (audit-defensible)
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

        $rowsHtml = $this->buildMotionRows($motions, $tenantId);

        // Annex A: Attendance
        [$attRows, $attSummary] = $this->buildAttendanceSection($attendance);

        // Annex B: Proxies
        [$proxyRows, $proxySummary] = $this->buildProxiesSection($proxies);

        // Annex C: Tokens
        [$tokenRows, $tokenSummary] = $this->buildTokensSection($tokens);

        // Annex D: Vote details
        $votesByMotionHtml = $this->buildVoteDetailsSection($motions, $tenantId, $showVoters);

        $meeting = $this->meetingRepo->findByIdForTenant($meetingId, $tenantId) ?? [];
        $title = self::h((string) ($meeting['title'] ?? ''));
        $status = self::h((string) ($meeting['status'] ?? ''));
        $president = self::h((string) ($meeting['president_name'] ?? ''));
        $createdAt = self::h((string) ($meeting['created_at'] ?? ''));
        $validatedAt = self::h((string) ($meeting['validated_at'] ?? ''));
        $archivedAt = self::h((string) ($meeting['archived_at'] ?? ''));

        return <<<HTML
            <!DOCTYPE html>
            <html lang="fr">
            <head>
            <meta charset="UTF-8">
            <title>PV — {$title}</title>
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
            Séance: <strong>{$title}</strong> · Statut: <strong>{$status}</strong> · Président: <strong>{$president}</strong><br>
            Créée: {$createdAt} · Validée: {$validatedAt} · Archivée: {$archivedAt}
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
    }

    /**
     * Build the HTML document used for PDF generation via DOMPDF.
     *
     * Includes org name header, attendance table, quorum block, proxies,
     * motions with vote tallies, dual signature blocks (Le Président / Le Secrétaire).
     *
     * @param array<string,mixed> $meeting  Meeting record (must contain title, president_name, etc.)
     * @param array<int,array<string,mixed>> $attendances  From attendance()->listForReport()
     * @param array<int,array<string,mixed>> $motions  From motion()->listForReport()
     * @param array<int,array<string,mixed>> $proxies  From proxy()->listForReport()
     * @param string $orgName  Tenant org name from settings
     * @param bool $isPreview  Whether to add BROUILLON watermark
     * @return string HTML document
     */
    public function buildPdfHtml(
        array $meeting,
        array $attendances,
        array $motions,
        array $proxies,
        string $orgName,
        bool $isPreview,
    ): string {
        $meetingId = (string) ($meeting['id'] ?? '');
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
    .draft-watermark { position: fixed; top: 40%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 80pt; color: rgba(220, 38, 38, 0.15); font-weight: bold; z-index: -1; white-space: nowrap; }
    .draft-banner { background: #fef2f2; border: 2px solid #dc2626; color: #dc2626; padding: 10px 15px; margin-bottom: 20px; border-radius: 4px; text-align: center; font-weight: bold; }
</style>
</head>
<body>';

        if ($isPreview) {
            $html .= '<div class="draft-watermark">BROUILLON</div>';
            $html .= '<div class="draft-banner">DOCUMENT BROUILLON - NON VALIDE - A TITRE INDICATIF UNIQUEMENT</div>';
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
        $html .= ' — Pouvoir représenté : <strong>' . number_format((float) $presentPower, 2) . '</strong> / ' . number_format((float) $totalPower, 2) . '</p>';

        $html .= '<table><tr><th>Nom</th><th>Pouvoir</th><th>Statut</th><th>Arrivée</th></tr>';
        foreach ($attendances as $a) {
            $statusLabel = match ($a['mode'] ?? 'absent') {
                'present' => 'Présent', 'remote' => 'À distance', 'proxy' => 'Représenté', 'excused' => 'Excusé', default => 'Absent'
            };
            $html .= '<tr><td>' . htmlspecialchars($a['full_name']) . '</td>';
            $html .= '<td>' . number_format((float) ($a['voting_power'] ?? 1), 2) . '</td>';
            $html .= '<td>' . $statusLabel . '</td>';
            $html .= '<td>' . ($a['checked_in_at'] ? date('H:i', strtotime($a['checked_in_at'])) : '—') . '</td></tr>';
        }
        $html .= '</table>';

        // Meeting-level quorum block — required by Loi 1901
        $quorumRatio = ($totalPower > 0) ? round(($presentPower / $totalPower) * 100, 1) : 0;
        $html .= '<div class="result-box"><strong>Quorum de la séance :</strong> ' . $quorumRatio . '% des voix représentées (' . number_format((float) $presentPower, 2) . ' / ' . number_format((float) $totalPower, 2) . ')</div>';

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
            $html .= '<tr><td>Pour</td><td>' . number_format((float) ($m['official_for'] ?? 0), 2) . '</td></tr>';
            $html .= '<tr><td>Contre</td><td>' . number_format((float) ($m['official_against'] ?? 0), 2) . '</td></tr>';
            $html .= '<tr><td>Abstention</td><td>' . number_format((float) ($m['official_abstain'] ?? 0), 2) . '</td></tr>';
            $html .= '<tr><td><strong>Total</strong></td><td><strong>' . number_format((float) ($m['official_total'] ?? 0), 2) . '</strong></td></tr>';
            $html .= '</table>';

            $decisionClass = ($m['decision'] === 'adopted') ? '' : 'result-rejected';
            $dl = match ($m['decision'] ?? '') {
                'adopted' => 'ADOPTEE', 'rejected' => 'REJETEE', 'no_quorum' => 'SANS QUORUM',
                'no_votes' => 'SANS VOTES', 'no_policy' => 'SANS REGLE', 'cancelled' => 'ANNULEE',
                'pending' => 'EN ATTENTE', default => strtoupper($m['decision'] ?? '—'),
            };
            $html .= '<div class="result-box ' . $decisionClass . '"><strong>Décision :</strong> ' . $dl;
            if (!empty($m['decision_reason'])) {
                $html .= ' — ' . htmlspecialchars($m['decision_reason']);
            }
            $html .= '</div>';
        }

        // Dual signature block — Le Président de séance / Le Secrétaire de séance
        $html .= '<table style="width:100%;margin-top:40px;border:none"><tr>';
        $html .= '<td style="width:50%;text-align:center;padding:20px;border:none">';
        $html .= '<p>Le Président de séance</p>';
        $html .= '<p style="margin-top:60px;border-top:1px solid #333">' . htmlspecialchars($meeting['president_name'] ?? '') . '</p>';
        $html .= '</td>';
        $html .= '<td style="width:50%;text-align:center;padding:20px;border:none">';
        $html .= '<p>Le Secrétaire de séance</p>';
        $html .= '<p style="margin-top:60px;border-top:1px solid #333">&nbsp;</p>';
        $html .= '</td>';
        $html .= '</tr></table>';
        if ($isPreview) {
            $html .= '<p style="font-size: 9pt; color: #dc2626; text-align:center;">Document brouillon - Généré le ' . date('d/m/Y à H:i') . '</p>';
        } else {
            $html .= '<p style="font-size: 9pt; color: #6b7280; text-align:center;">Fait le ' . date('d/m/Y à H:i', strtotime($meeting['validated_at'])) . '</p>';
        }

        $html .= '<div class="footer"><p>Document généré automatiquement par AG-VOTE le ' . date('d/m/Y à H:i') . '</p>';
        $html .= '<p>Identifiant séance : ' . htmlspecialchars($meetingId) . '</p></div>';
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Generate PDF bytes from a meeting using DOMPDF.
     *
     * Fetches data, builds HTML via buildPdfHtml(), invokes DOMPDF, and optionally
     * stores the snapshot in the meetingReport repository.
     *
     * @param array<string,mixed> $meeting  Pre-validated meeting record from controller
     * @return array{pdf: string, hash: string, filename: string, html: string}
     */
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

    /**
     * Build the validated-meeting report HTML for the generateReport() endpoint.
     *
     * @param array<string,mixed> $meeting  Pre-validated meeting record from controller
     * @return array{html: string, hash: string}
     */
    public function buildGeneratedReportHtml(string $meetingId, string $tenantId, array $meeting): array {
        $motions = $this->motionRepo->listForReportGeneration($meetingId, $tenantId);

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
        $html = ob_get_clean() ?: '';
        $hash = hash('sha256', $html);

        $this->meetingReportRepo->upsertHash($meetingId, $hash, $tenantId);

        return ['html' => $html, 'hash' => $hash];
    }

    // =========================================================================
    // Private helpers for buildReportHtml
    // =========================================================================

    /** @param array<int,array<string,mixed>> $motions */
    private function buildMotionRows(array $motions, string $tenantId): string {
        $rowsHtml = '';
        foreach ($motions as $m) {
            $mid = (string) $m['id'];

            $votePolicy = null;
            if (!empty($m['vote_policy_id'])) {
                $votePolicy = $this->policyRepo->findVotePolicy($m['vote_policy_id'], $tenantId);
            }
            $quorumPolicy = null;
            if (!empty($m['quorum_policy_id'])) {
                $quorumPolicy = $this->policyRepo->findQuorumPolicy($m['quorum_policy_id'], $tenantId);
            }

            $src = (string) ($m['official_source'] ?? '');
            $hasOfficial = $src !== '' && $m['official_total'] !== null;

            $detail = ['quorum_met' => null, 'quorum_ratio' => null, 'majority_ratio' => null, 'majority_threshold' => null, 'majority_base' => null];

            if (!$hasOfficial && $m['closed_at'] !== null) {
                try {
                    $o = (new OfficialResultsService())->computeOfficialTallies($mid, $tenantId);
                    $src = $o['source'];
                    $of = $o['for'];
                    $og = $o['against'];
                    $oa = $o['abstain'];
                    $ot = $o['total'];
                    $dec = $o['decision'];
                    $reas = $o['reason'];
                    $note = ' (calculé)';
                } catch (Throwable) {
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
                    $r = (new VoteEngine())->computeMotionResult($mid, $tenantId);
                    $detail['quorum_met'] = $r['quorum']['met'] ?? null;
                    $detail['quorum_ratio'] = $r['quorum']['ratio'] ?? null;
                    $detail['majority_ratio'] = $r['decision']['ratio'] ?? ($r['majority']['ratio'] ?? null);
                    $detail['majority_threshold'] = $r['decision']['threshold'] ?? ($r['majority']['threshold'] ?? null);
                    $detail['majority_base'] = $r['decision']['base'] ?? ($r['majority']['base'] ?? null);
                }
            } catch (Throwable) {
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
        return $rowsHtml;
    }

    /**
     * @param array<int,array<string,mixed>> $attendance
     * @return array{0: string, 1: string}
     */
    private function buildAttendanceSection(array $attendance): array {
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

    /**
     * @param array<int,array<string,mixed>> $proxies
     * @return array{0: string, 1: string}
     */
    private function buildProxiesSection(array $proxies): array {
        $proxyRows = '';
        foreach ($proxies as $p) {
            $proxyRows .= '<tr><td>' . self::h($p['giver_name'] ?? '') . '</td><td>' . self::h($p['receiver_name'] ?? '') . "</td><td class='tiny muted'>" . self::h((string) ($p['revoked_at'] ?? '')) . '</td></tr>';
        }
        $proxySummary = $proxies ? 'Procurations: ' . count($proxies) : 'Procurations: 0';
        return [$proxyRows, $proxySummary];
    }

    /**
     * @param array<int,array<string,mixed>> $tokens
     * @return array{0: string, 1: string}
     */
    private function buildTokensSection(array $tokens): array {
        $tokenRows = '';
        foreach ($tokens as $t) {
            $tokenRows .= '<tr><td>' . self::h($t['full_name'] ?? '') . "</td><td class='tiny muted'>" . self::h((string) ($t['created_at'] ?? '')) . "</td><td class='tiny muted'>" . self::h((string) ($t['last_used_at'] ?? '')) . "</td><td class='tiny muted'>" . self::h((string) ($t['revoked_at'] ?? '')) . '</td></tr>';
        }
        $tokenSummary = $tokens ? 'Tokens: ' . count($tokens) : 'Tokens: 0';
        return [$tokenRows, $tokenSummary];
    }

    /**
     * @param array<int,array<string,mixed>> $motions
     */
    private function buildVoteDetailsSection(array $motions, string $tenantId, bool $showVoters): string {
        if (!$showVoters) {
            return "<div class='muted tiny'>Annexe D masquée (par défaut). Ajoutez <span class='mono'>?show_voters=1</span> pour inclure les votants nominativement (usage interne Trust).</div>";
        }

        $votesByMotionHtml = '';
        foreach ($motions as $m) {
            $mid = (string) $m['id'];
            $title = (string) ($m['title'] ?? 'Motion');
            $isClosed = ($m['closed_at'] !== null);

            $ballots = $this->ballotRepo->listDetailedForMotion($mid, $tenantId);

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

    // =========================================================================
    // Static label helpers
    // =========================================================================

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
