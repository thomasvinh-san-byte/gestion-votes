<?php
declare(strict_types=1);

/**
 * Export Excel complet (multi-feuilles)
 *
 * Génère un fichier Excel avec plusieurs feuilles :
 * - Résumé : Informations générales de la séance
 * - Émargement : Liste des présences
 * - Résolutions : Résultats des votes
 * - Votes : Détail des votes individuels (optionnel)
 *
 * Disponible uniquement après validation de la séance.
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Service\ExportService;

api_require_role(['operator', 'admin', 'auditor']);

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
$includeVotes = (bool)($_GET['include_votes'] ?? true);

if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400);
}

// Exports autorisés uniquement après validation (exigence conformité)
$meetingRepo = new MeetingRepository();
$mt = $meetingRepo->findByIdForTenant($meetingId, api_current_tenant_id());
if (!$mt) api_fail('meeting_not_found', 404);
if (empty($mt['validated_at'])) api_fail('meeting_not_validated', 409);

try {
    // Récupération des données
    $attendanceRepo = new AttendanceRepository();
    $attendanceRows = $attendanceRepo->listExportForMeeting($meetingId);

    $motionRepo = new MotionRepository();
    $motionRows = $motionRepo->listResultsExportForMeeting($meetingId);

    $voteRows = [];
    if ($includeVotes) {
        $ballotRepo = new BallotRepository();
        $voteRows = $ballotRepo->listVotesExportForMeeting($meetingId);
    }

    $export = new ExportService();

    // Génération du fichier
    $filename = $export->generateFilename('complet', $mt['title'] ?? '', 'xlsx');
    $export->initXlsxOutput($filename);

    $spreadsheet = $export->createFullExportSpreadsheet($mt, $attendanceRows, $motionRows, $voteRows);
    $export->outputSpreadsheet($spreadsheet);
} catch (Throwable $e) {
    error_log('Error in export_full_xlsx.php: ' . $e->getMessage());
    api_fail('server_error', 500, ['detail' => $e->getMessage()]);
}
