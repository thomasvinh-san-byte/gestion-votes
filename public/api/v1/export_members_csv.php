<?php
declare(strict_types=1);

/**
 * Export CSV des membres
 *
 * Génère un fichier CSV avec la liste des membres et leur présence
 * formaté en français pour utilisation directe dans Excel ou autre tableur.
 *
 * Disponible uniquement après validation de la séance.
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
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
    // Génération du fichier
    $filename = ExportService::generateFilename('membres', $mt['title'] ?? '');
    ExportService::initCsvOutput($filename);

    $out = ExportService::openCsvOutput();

    // En-têtes français
    ExportService::writeCsvRow($out, ExportService::getMembersHeaders());

    // Données formatées
    $memberRepo = new MemberRepository();
    $rows = $memberRepo->listExportForMeeting($meetingId, api_current_tenant_id());

    foreach ($rows as $r) {
        ExportService::writeCsvRow($out, ExportService::formatMemberRow($r));
    }

    fclose($out);
} catch (Throwable $e) {
    error_log('Error in export_members_csv.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "server_error";
}
