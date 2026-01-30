<?php
declare(strict_types=1);

/**
 * meeting_generate_report_pdf.php - Génère le PV en PDF
 * 
 * GET /api/v1/meeting_generate_report_pdf.php?meeting_id={uuid}
 * 
 * Utilise Dompdf pour convertir le HTML en PDF.
 */

require __DIR__ . '/../../../app/api.php';

// Charger Dompdf
require_once __DIR__ . '/../../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MotionRepository;

api_require_role(['president', 'admin', 'operator', 'auditor']);

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('invalid_meeting_id', 400);
}

$tenantId = api_current_tenant_id();

// Charger la séance
$meeting = (new MeetingRepository())->findWithValidator($meetingId);
if (!$meeting || (string)($meeting['tenant_id'] ?? '') !== $tenantId) {
    api_fail('meeting_not_found', 404);
}

if (empty($meeting['validated_at'])) {
    api_fail('meeting_not_validated', 409, [
        'detail' => 'La séance doit être validée avant de générer le PV définitif.'
    ]);
}

// Charger les présences
$attendances = (new AttendanceRepository())->listForReport($meetingId, $tenantId);

// Charger les résolutions
$motions = (new MotionRepository())->listForReport($meetingId);

// Charger les procurations
$proxies = (new MeetingRepository())->listProxiesForReport($meetingId);

// Générer le HTML
$html = '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Procès-verbal - ' . htmlspecialchars($meeting['title'] ?? 'Séance') . '</title>
<style>
    @page { margin: 2cm; }
    body {
        font-family: "DejaVu Sans", Arial, sans-serif;
        font-size: 11pt;
        line-height: 1.5;
        color: #333;
    }
    h1 {
        font-size: 18pt;
        color: #1e3a5f;
        border-bottom: 2px solid #1e3a5f;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    h2 {
        font-size: 14pt;
        color: #2563eb;
        margin-top: 25px;
        margin-bottom: 10px;
    }
    h3 {
        font-size: 12pt;
        color: #374151;
        margin-top: 15px;
        margin-bottom: 8px;
    }
    .header-info {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 4px;
    }
    .header-info p {
        margin: 5px 0;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
    }
    th, td {
        border: 1px solid #d1d5db;
        padding: 8px 12px;
        text-align: left;
    }
    th {
        background: #f3f4f6;
        font-weight: bold;
    }
    tr:nth-child(even) {
        background: #f9fafb;
    }
    .result-box {
        background: #f0fdf4;
        border: 1px solid #86efac;
        padding: 10px 15px;
        margin: 10px 0;
        border-radius: 4px;
    }
    .result-rejected {
        background: #fef2f2;
        border-color: #fca5a5;
    }
    .badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 10pt;
        font-weight: bold;
    }
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .footer {
        margin-top: 40px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
        font-size: 9pt;
        color: #6b7280;
    }
    .signature-box {
        margin-top: 40px;
        border: 1px dashed #9ca3af;
        padding: 20px;
        text-align: center;
    }
</style>
</head>
<body>';

// En-tête
$html .= '<h1>PROCÈS-VERBAL DE SÉANCE</h1>';
$html .= '<div class="header-info">';
$html .= '<p><strong>Titre :</strong> ' . htmlspecialchars($meeting['title'] ?? '—') . '</p>';
$html .= '<p><strong>Date :</strong> ' . ($meeting['scheduled_at'] ? date('d/m/Y à H:i', strtotime($meeting['scheduled_at'])) : '—') . '</p>';
$html .= '<p><strong>Lieu :</strong> ' . htmlspecialchars($meeting['location'] ?? '—') . '</p>';
$html .= '<p><strong>Président :</strong> ' . htmlspecialchars($meeting['president_name'] ?? '—') . '</p>';
$html .= '<p><strong>Validé le :</strong> ' . date('d/m/Y à H:i', strtotime($meeting['validated_at'])) . '</p>';
$html .= '</div>';

// Présences
$html .= '<h2>1. Feuille de présence</h2>';
$presentCount = count(array_filter($attendances, fn($a) => in_array($a['mode'], ['present', 'remote'])));
$totalPower = array_sum(array_column($attendances, 'voting_power'));
$presentPower = array_sum(array_map(fn($a) => in_array($a['mode'], ['present', 'remote', 'proxy']) ? ($a['voting_power'] ?? 0) : 0, $attendances));

$html .= '<p><strong>' . $presentCount . '</strong> membres présents sur <strong>' . count($attendances) . '</strong> inscrits';
$html .= ' — Pouvoir représenté : <strong>' . number_format($presentPower, 2) . '</strong> / ' . number_format($totalPower, 2) . '</p>';

$html .= '<table>';
$html .= '<tr><th>Nom</th><th>Pouvoir</th><th>Statut</th><th>Arrivée</th></tr>';
foreach ($attendances as $a) {
    $statusLabel = match($a['mode'] ?? 'absent') {
        'present' => 'Présent',
        'remote' => 'À distance',
        'proxy' => 'Représenté',
        'excused' => 'Excusé',
        default => 'Absent'
    };
    $html .= '<tr>';
    $html .= '<td>' . htmlspecialchars($a['full_name']) . '</td>';
    $html .= '<td>' . number_format($a['voting_power'] ?? 1, 2) . '</td>';
    $html .= '<td>' . $statusLabel . '</td>';
    $html .= '<td>' . ($a['checked_in_at'] ? date('H:i', strtotime($a['checked_in_at'])) : '—') . '</td>';
    $html .= '</tr>';
}
$html .= '</table>';

// Procurations
if (!empty($proxies)) {
    $html .= '<h2>2. Procurations</h2>';
    $html .= '<table>';
    $html .= '<tr><th>Mandant</th><th>Mandataire</th></tr>';
    foreach ($proxies as $p) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($p['giver_name']) . '</td>';
        $html .= '<td>' . htmlspecialchars($p['receiver_name']) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';
}

// Résolutions
$html .= '<h2>' . (empty($proxies) ? '2' : '3') . '. Résolutions</h2>';

foreach ($motions as $i => $m) {
    $num = $i + 1;
    $html .= '<h3>Résolution n°' . $num . ' : ' . htmlspecialchars($m['title']) . '</h3>';
    
    if (!empty($m['description'])) {
        $html .= '<p>' . nl2br(htmlspecialchars($m['description'])) . '</p>';
    }
    
    if ($m['secret']) {
        $html .= '<p><span class="badge badge-warning">Vote secret</span></p>';
    }
    
    // Résultats
    $html .= '<table>';
    $html .= '<tr><th>Vote</th><th>Poids</th></tr>';
    $html .= '<tr><td>✅ Pour</td><td>' . number_format((float)($m['official_for'] ?? 0), 2) . '</td></tr>';
    $html .= '<tr><td>❌ Contre</td><td>' . number_format((float)($m['official_against'] ?? 0), 2) . '</td></tr>';
    $html .= '<tr><td>⚪ Abstention</td><td>' . number_format((float)($m['official_abstain'] ?? 0), 2) . '</td></tr>';
    $html .= '<tr><td><strong>Total</strong></td><td><strong>' . number_format((float)($m['official_total'] ?? 0), 2) . '</strong></td></tr>';
    $html .= '</table>';
    
    // Décision
    $decisionClass = ($m['decision'] === 'adopted') ? '' : 'result-rejected';
    $decisionLabel = ($m['decision'] === 'adopted') ? '✓ ADOPTÉE' : '✗ REJETÉE';
    $html .= '<div class="result-box ' . $decisionClass . '">';
    $html .= '<strong>Décision :</strong> ' . $decisionLabel;
    if (!empty($m['decision_reason'])) {
        $html .= ' — ' . htmlspecialchars($m['decision_reason']);
    }
    $html .= '</div>';
}

// Signature
$html .= '<div class="signature-box">';
$html .= '<p>Le Président de séance</p>';
$html .= '<p style="margin-top: 40px;"><strong>' . htmlspecialchars($meeting['president_name'] ?? '—') . '</strong></p>';
$html .= '<p style="font-size: 9pt; color: #6b7280;">Fait le ' . date('d/m/Y à H:i', strtotime($meeting['validated_at'])) . '</p>';
$html .= '</div>';

// Footer
$html .= '<div class="footer">';
$html .= '<p>Document généré automatiquement par AG-VOTE le ' . date('d/m/Y à H:i') . '</p>';
$html .= '<p>Identifiant séance : ' . htmlspecialchars($meetingId) . '</p>';
$html .= '</div>';

$html .= '</body></html>';

// Générer le PDF avec Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Calculer le hash pour intégrité
$pdfContent = $dompdf->output();
$hash = hash('sha256', $pdfContent);

// Persister le rapport HTML + hash d'intégrité du PDF
(new MeetingRepository())->upsertReportFull($meetingId, $html, $hash);

// Envoyer le PDF
$filename = 'PV_' . preg_replace('/[^a-zA-Z0-9]/', '_', $meeting['title'] ?? 'seance') . '_' . date('Ymd') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
header('X-Content-Type-Options: nosniff');
header('X-Report-SHA256: ' . $hash);

echo $pdfContent;
