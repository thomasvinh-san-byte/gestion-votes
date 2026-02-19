<?php
declare(strict_types=1);

/**
 * Export Excel des votes (bulletins nominatifs)
 *
 * Génère un fichier Excel avec les votes individuels formatés en français.
 *
 * Disponible uniquement après validation de la séance.
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\BallotRepository;
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
    $ballotRepo = new BallotRepository();
    $rows = $ballotRepo->listVotesExportForMeeting($meetingId);

    // Formater les données (ignorer les lignes sans votant)
    $formattedRows = [];
    foreach ($rows as $r) {
        if (!empty($r['voter_name'])) {
            $formattedRows[] = ExportService::formatVoteRow($r);
        }
    }

    // Génération du fichier
    $filename = ExportService::generateFilename('votes', $mt['title'] ?? '', 'xlsx');
    ExportService::initXlsxOutput($filename);

    $spreadsheet = ExportService::createSpreadsheet(
        ExportService::getVotesHeaders(),
        $formattedRows,
        'Votes'
    );
    ExportService::outputSpreadsheet($spreadsheet);
} catch (Throwable $e) {
    error_log('Error in export_votes_xlsx.php: ' . $e->getMessage());
    api_fail('server_error', 500, ['detail' => $e->getMessage()]);
}
