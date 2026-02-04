<?php
declare(strict_types=1);

/**
 * Export Excel des résultats des résolutions
 *
 * Génère un fichier Excel avec les résultats de vote formatés en français.
 *
 * Disponible uniquement après validation de la séance.
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Service\ExportService;

api_require_role(['operator', 'admin', 'auditor']);

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400);
}

// Exports autorisés uniquement après validation
$meetingRepo = new MeetingRepository();
$mt = $meetingRepo->findByIdForTenant($meetingId, api_current_tenant_id());
if (!$mt) api_fail('meeting_not_found', 404);
if (empty($mt['validated_at'])) api_fail('meeting_not_validated', 409);

$motionRepo = new MotionRepository();
$rows = $motionRepo->listResultsExportForMeeting($meetingId);

// Formater les données
$formattedRows = array_map([ExportService::class, 'formatMotionResultRow'], $rows);

// Génération du fichier
$filename = ExportService::generateFilename('resultats', $mt['title'] ?? '', 'xlsx');
ExportService::initXlsxOutput($filename);

$spreadsheet = ExportService::createSpreadsheet(
    ExportService::getMotionResultsHeaders(),
    $formattedRows,
    'Résultats'
);
ExportService::outputSpreadsheet($spreadsheet);
