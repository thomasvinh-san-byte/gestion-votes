<?php
declare(strict_types=1);

/**
 * Export Excel des présences (émargement)
 *
 * Génère un fichier Excel avec les données de présence formatées en français.
 *
 * Disponible uniquement après validation de la séance.
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Service\ExportService;

api_require_role('operator');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400);
}

// Exports autorisés uniquement après validation
$meetingRepo = new MeetingRepository();
$mt = $meetingRepo->findByIdForTenant($meetingId, api_current_tenant_id());
if (!$mt) api_fail('meeting_not_found', 404);
if (empty($mt['validated_at'])) api_fail('meeting_not_validated', 409);

try {
    $attendanceRepo = new AttendanceRepository();
    $rows = $attendanceRepo->listExportForMeeting($meetingId);

    // Formater les données
    $formattedRows = array_map([ExportService::class, 'formatAttendanceRow'], $rows);

    // Génération du fichier
    $filename = ExportService::generateFilename('presences', $mt['title'] ?? '', 'xlsx');
    ExportService::initXlsxOutput($filename);

    $spreadsheet = ExportService::createSpreadsheet(
        ExportService::getAttendanceHeaders(),
        $formattedRows,
        'Émargement'
    );
    ExportService::outputSpreadsheet($spreadsheet);
} catch (Throwable $e) {
    error_log('Error in export_attendance_xlsx.php: ' . $e->getMessage());
    api_fail('server_error', 500);
}
