<?php
declare(strict_types=1);

/**
 * Export CSV des résultats des résolutions
 *
 * Génère un fichier CSV avec les résultats de vote de chaque résolution
 * formaté en français pour utilisation directe dans Excel ou autre tableur.
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

// Exports autorisés uniquement après validation (exigence conformité)
$meetingRepo = new MeetingRepository();
$mt = $meetingRepo->findByIdForTenant($meetingId, api_current_tenant_id());
if (!$mt) api_fail('meeting_not_found', 404);
if (empty($mt['validated_at'])) api_fail('meeting_not_validated', 409);

try {
    $export = new ExportService();

    // Génération du fichier
    $filename = $export->generateFilename('resultats', $mt['title'] ?? '');
    $export->initCsvOutput($filename);

    $out = $export->openCsvOutput();

    // En-têtes français
    $export->writeCsvRow($out, $export->getMotionResultsHeaders());

    // Données formatées
    $motionRepo = new MotionRepository();
    $rows = $motionRepo->listResultsExportForMeeting($meetingId);

    foreach ($rows as $r) {
        $export->writeCsvRow($out, $export->formatMotionResultRow($r));
    }

    fclose($out);
} catch (Throwable $e) {
    error_log('Error in export_motions_results_csv.php: ' . $e->getMessage());
    api_fail('server_error', 500, ['detail' => $e->getMessage()]);
}
