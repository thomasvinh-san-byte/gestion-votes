<?php
declare(strict_types=1);

/**
 * Export CSV des présences (émargement)
 *
 * Génère un fichier CSV avec les données de présence formatées en français
 * pour utilisation directe dans Excel ou autre tableur.
 *
 * Disponible uniquement après validation de la séance.
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Service\ExportService;

api_require_role('operator');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "missing_meeting_id";
    exit;
}

// Exports autorisés uniquement après validation (exigence conformité)
$meetingRepo = new MeetingRepository();
$mt = $meetingRepo->findByIdForTenant($meetingId, api_current_tenant_id());
if (!$mt) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "meeting_not_found";
    exit;
}
if (empty($mt['validated_at'])) {
    http_response_code(409);
    header('Content-Type: text/plain; charset=utf-8');
    echo "meeting_not_validated";
    exit;
}

try {
    $attendanceRepo = new AttendanceRepository();
    $rows = $attendanceRepo->listExportForMeeting($meetingId);

    $export = new ExportService();

    // Génération du fichier
    $filename = $export->generateFilename('presences', $mt['title'] ?? '');
    $export->initCsvOutput($filename);

    $out = $export->openCsvOutput();

    // En-têtes français
    $export->writeCsvRow($out, $export->getAttendanceHeaders());

    // Données formatées
    foreach ($rows as $r) {
        $export->writeCsvRow($out, $export->formatAttendanceRow($r));
    }

    fclose($out);
} catch (Throwable $e) {
    error_log('Error in export_attendance_csv.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "server_error";
}
