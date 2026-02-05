<?php
declare(strict_types=1);

/**
 * API pour les rapports agreges multi-seances.
 *
 * GET /reports_aggregate.php
 *   ?report_type=participation|decisions|voting_power|proxies|quorum|summary
 *   &from_date=2024-01-01
 *   &to_date=2024-12-31
 *   &meeting_ids[]=uuid1&meeting_ids[]=uuid2
 *   &format=json|csv|xlsx
 *
 * GET /reports_aggregate.php?list_meetings=1 - Liste les seances disponibles
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\AggregateReportRepository;
use AgVote\Service\ExportService;

api_require_role(['operator', 'admin', 'auditor']);

$repo = new AggregateReportRepository();
$tenantId = api_current_tenant_id();

// Liste des seances disponibles
if (isset($_GET['list_meetings'])) {
    $fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : null;
    $toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : null;

    $meetings = $repo->listAvailableMeetings($tenantId, $fromDate, $toDate);
    api_ok(['meetings' => $meetings]);
}

// Parametres
$reportType = trim($_GET['report_type'] ?? 'summary');
$format = strtolower(trim($_GET['format'] ?? 'json'));
$fromDate = isset($_GET['from_date']) && $_GET['from_date'] !== '' ? trim($_GET['from_date']) : null;
$toDate = isset($_GET['to_date']) && $_GET['to_date'] !== '' ? trim($_GET['to_date']) : null;
$meetingIds = $_GET['meeting_ids'] ?? [];

// Valider les meeting_ids
if (!empty($meetingIds) && is_array($meetingIds)) {
    $meetingIds = array_filter($meetingIds, fn($id) => api_is_uuid($id));
    if (empty($meetingIds)) {
        $meetingIds = null;
    }
} else {
    $meetingIds = null;
}

// Valider le type de rapport
$validTypes = ['participation', 'decisions', 'voting_power', 'proxies', 'quorum', 'summary'];
if (!in_array($reportType, $validTypes, true)) {
    api_fail('invalid_report_type', 400, [
        'detail' => 'Type de rapport invalide.',
        'valid_types' => $validTypes,
    ]);
}

// Valider le format
$validFormats = ['json', 'csv', 'xlsx'];
if (!in_array($format, $validFormats, true)) {
    api_fail('invalid_format', 400, [
        'detail' => 'Format invalide.',
        'valid_formats' => $validFormats,
    ]);
}

// Obtenir les donnees
$data = match ($reportType) {
    'participation' => $repo->getParticipationReport($tenantId, $meetingIds, $fromDate, $toDate),
    'decisions' => $repo->getDecisionsReport($tenantId, $meetingIds, $fromDate, $toDate),
    'voting_power' => $repo->getVotingPowerReport($tenantId, $meetingIds, $fromDate, $toDate),
    'proxies' => $repo->getProxiesReport($tenantId, $meetingIds, $fromDate, $toDate),
    'quorum' => $repo->getQuorumReport($tenantId, $meetingIds, $fromDate, $toDate),
    'summary' => $repo->getSummary($tenantId, $meetingIds, $fromDate, $toDate),
    default => [],
};

// Audit
audit_log('report_aggregate_view', 'report', null, [
    'report_type' => $reportType,
    'format' => $format,
    'from_date' => $fromDate,
    'to_date' => $toDate,
    'meeting_count' => $meetingIds ? count($meetingIds) : 'all',
]);

// Format JSON
if ($format === 'json') {
    api_ok([
        'report_type' => $reportType,
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'meeting_count' => $meetingIds ? count($meetingIds) : null,
        'generated_at' => date('c'),
        'data' => $data,
    ]);
}

// Formats CSV et XLSX
$exportService = new ExportService();

$headers = getReportHeaders($reportType);
$rows = formatReportRows($reportType, $data, $exportService);
$filename = "rapport_{$reportType}_" . date('Y-m-d');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

    $output = fopen('php://output', 'w');
    $exportService->writeCsvBom($output);
    $exportService->writeCsvRow($output, $headers);

    foreach ($rows as $row) {
        $exportService->writeCsvRow($output, $row);
    }

    fclose($output);
    exit;
}

if ($format === 'xlsx') {
    $spreadsheet = $exportService->createSpreadsheet("Rapport {$reportType}");
    $sheet = $spreadsheet->getActiveSheet();

    // En-tetes
    foreach ($headers as $col => $header) {
        $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
    }

    // Donnees
    $rowNum = 2;
    foreach ($rows as $row) {
        foreach ($row as $col => $value) {
            $sheet->setCellValueByColumnAndRow($col + 1, $rowNum, $value);
        }
        $rowNum++;
    }

    $exportService->autoSizeColumns($sheet);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Retourne les en-tetes pour chaque type de rapport.
 */
function getReportHeaders(string $type): array
{
    return match ($type) {
        'participation' => [
            'Nom', 'Email', 'Pouvoir de vote', 'Séances totales',
            'Présent(e)', 'Par procuration', 'Excusé(e)', 'Absent(e)',
            'Taux de participation (%)'
        ],
        'decisions' => [
            'Séance', 'Date', 'N° résolution', 'Titre', 'Décision',
            'Pour', 'Contre', 'Abstention', 'Votants',
            'Poids Pour', 'Poids Contre', 'Poids Abstention'
        ],
        'voting_power' => [
            'Séance', 'Date', 'Présents', 'Pouvoir représenté',
            'Pouvoir total', 'Représentation (%)'
        ],
        'proxies' => [
            'Séance', 'Date', 'Nb procurations', 'Mandants uniques',
            'Mandataires uniques', 'Pouvoir délégué', 'Pouvoir total',
            'Délégation (%)', 'Max par mandataire'
        ],
        'quorum' => [
            'Séance', 'Date', 'Statut', 'Membres totaux', 'Présents',
            'Pouvoir présent', 'Seuil quorum', 'Unité', 'Quorum atteint'
        ],
        'summary' => [
            'Métrique', 'Valeur'
        ],
        default => [],
    };
}

/**
 * Formate les lignes pour l'export.
 */
function formatReportRows(string $type, array $data, ExportService $svc): array
{
    $rows = [];

    if ($type === 'summary') {
        // Format special pour le resume
        $rows[] = ['Nombre de séances', $data['total_meetings'] ?? 0];
        $rows[] = ['Nombre de résolutions', $data['total_motions'] ?? 0];
        $rows[] = ['Résolutions adoptées', $data['adopted_count'] ?? 0];
        $rows[] = ['Résolutions rejetées', $data['rejected_count'] ?? 0];
        $rows[] = ['Autres décisions', $data['other_count'] ?? 0];
        $rows[] = ['Présence moyenne', $data['avg_attendance'] ?? 0];
        $rows[] = ['Première séance', $svc->formatDate($data['first_meeting'] ?? null)];
        $rows[] = ['Dernière séance', $svc->formatDate($data['last_meeting'] ?? null)];
        return $rows;
    }

    foreach ($data as $row) {
        $formattedRow = match ($type) {
            'participation' => [
                $row['full_name'] ?? '',
                $row['email'] ?? '',
                $svc->formatNumber($row['voting_power'] ?? 1),
                $row['total_meetings'] ?? 0,
                $row['attended_present'] ?? 0,
                $row['attended_proxy'] ?? 0,
                $row['excused'] ?? 0,
                $row['absent'] ?? 0,
                $svc->formatPercent($row['participation_rate'] ?? 0),
            ],
            'decisions' => [
                $row['meeting_title'] ?? '',
                $svc->formatDate($row['scheduled_at'] ?? null),
                $row['position'] ?? '',
                $row['motion_title'] ?? '',
                $svc->translateDecision($row['decision'] ?? null),
                $row['for_count'] ?? 0,
                $row['against_count'] ?? 0,
                $row['abstain_count'] ?? 0,
                $row['total_voters'] ?? 0,
                $svc->formatNumber($row['for_weight'] ?? 0),
                $svc->formatNumber($row['against_weight'] ?? 0),
                $svc->formatNumber($row['abstain_weight'] ?? 0),
            ],
            'voting_power' => [
                $row['meeting_title'] ?? '',
                $svc->formatDate($row['scheduled_at'] ?? null),
                $row['present_count'] ?? 0,
                $svc->formatNumber($row['present_power'] ?? 0),
                $svc->formatNumber($row['total_power'] ?? 0),
                $svc->formatPercent($row['power_represented_pct'] ?? 0),
            ],
            'proxies' => [
                $row['meeting_title'] ?? '',
                $svc->formatDate($row['scheduled_at'] ?? null),
                $row['proxy_count'] ?? 0,
                $row['unique_givers'] ?? 0,
                $row['unique_receivers'] ?? 0,
                $svc->formatNumber($row['delegated_power'] ?? 0),
                $svc->formatNumber($row['total_power'] ?? 0),
                $svc->formatPercent($row['delegated_pct'] ?? 0),
                $row['max_proxies_per_receiver'] ?? 0,
            ],
            'quorum' => [
                $row['meeting_title'] ?? '',
                $svc->formatDate($row['scheduled_at'] ?? null),
                $svc->translateMeetingStatus($row['meeting_status'] ?? ''),
                $row['total_members'] ?? 0,
                $row['present_count'] ?? 0,
                $svc->formatNumber($row['present_power'] ?? 0),
                $row['quorum_threshold'] ?? '-',
                $row['quorum_unit'] ?? '-',
                $svc->formatBoolean($row['quorum_reached'] ?? true),
            ],
            default => [],
        };

        $rows[] = $formattedRow;
    }

    return $rows;
}
