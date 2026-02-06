<?php
declare(strict_types=1);

/**
 * motions_import_xlsx.php - Import Excel des résolutions
 *
 * Format XLSX attendu (première ligne = en-têtes):
 *   title,description,position
 *   ou
 *   titre,description,ordre
 *
 * Colonnes reconnues:
 *   - title/titre: Titre de la résolution (requis)
 *   - description/texte/content: Description/texte intégral (optionnel)
 *   - position/ordre/order: Position dans l'ordre du jour (optionnel, auto-incrémenté)
 *   - secret/vote_secret: Vote secret (optionnel, défaut: false)
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Service\ImportService;

// =============================================================================
// SÉCURITÉ
// =============================================================================

api_rate_limit('xlsx_import', 10, 3600);
api_require_role(['operator', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_fail('method_not_allowed', 405);
}

// =============================================================================
// PARAMÈTRES
// =============================================================================

$meetingId = trim($_POST['meeting_id'] ?? '');
if (!api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id requis et valide.']);
}

$tenantId = api_current_tenant_id();

// Vérifier que la séance existe et est modifiable
$meetingRepo = new MeetingRepository();
$meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);

if (!$meeting) {
    api_fail('meeting_not_found', 404);
}

// Bloquer si séance validée/archivée
if (in_array($meeting['status'], ['validated', 'archived'], true)) {
    api_fail('meeting_locked', 403, ['detail' => 'Séance validée ou archivée, modifications interdites.']);
}

// Mode dry-run (validation sans insertion)
$dryRun = filter_var($_POST['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

// =============================================================================
// VALIDATION FICHIER
// =============================================================================

$file = $_FILES['file'] ?? $_FILES['xlsx_file'] ?? null;
if (!$file) {
    api_fail('upload_error', 400, ['detail' => 'Fichier manquant.']);
}

$validation = ImportService::validateUploadedFile($file, 'xlsx');
if (!$validation['ok']) {
    api_fail('invalid_file', 400, ['detail' => $validation['error']]);
}

// =============================================================================
// LECTURE XLSX
// =============================================================================

$result = ImportService::readXlsxFile($file['tmp_name']);
if ($result['error']) {
    api_fail('file_read_error', 400, ['detail' => $result['error']]);
}

$headers = $result['headers'];
$rows = $result['rows'];

// Mapping colonnes
$columnMap = ImportService::getMotionsColumnMap();
$colIndex = ImportService::mapColumns($headers, $columnMap);

if (!isset($colIndex['title'])) {
    api_fail('missing_title_column', 400, [
        'detail' => 'Colonne "title" ou "titre" requise.',
        'found' => $headers
    ]);
}

// =============================================================================
// IMPORT
// =============================================================================

$motionRepo = new MotionRepository();

// Récupérer la position max actuelle
$existingMotions = $motionRepo->countForMeeting($meetingId);
$nextPosition = $existingMotions + 1;

$imported = 0;
$skipped = 0;
$errors = [];
$preview = [];

if (!$dryRun) {
    db()->beginTransaction();
}

try {
    foreach ($rows as $lineIndex => $row) {
        $lineNumber = $lineIndex + 2;

        // Extraire les données
        $title = trim($row[$colIndex['title']] ?? '');
        $description = isset($colIndex['description']) ? trim($row[$colIndex['description']] ?? '') : null;

        // Position: utiliser la valeur XLSX ou auto-incrémenter
        $position = null;
        if (isset($colIndex['position'])) {
            $posVal = trim($row[$colIndex['position']] ?? '');
            if ($posVal !== '' && is_numeric($posVal)) {
                $position = (int)$posVal;
            }
        }
        if ($position === null) {
            $position = $nextPosition++;
        } else {
            $nextPosition = max($nextPosition, $position + 1);
        }

        // Secret vote
        $secret = false;
        if (isset($colIndex['secret'])) {
            $secret = ImportService::parseBoolean($row[$colIndex['secret']] ?? '0');
        }

        // Validation
        if (empty($title) || mb_strlen($title) < 2) {
            $errors[] = ['line' => $lineNumber, 'error' => 'Titre invalide ou trop court'];
            $skipped++;
            continue;
        }

        if (mb_strlen($title) > 500) {
            $errors[] = ['line' => $lineNumber, 'error' => 'Titre trop long (max 500 caractères)'];
            $skipped++;
            continue;
        }

        if ($dryRun) {
            $preview[] = [
                'line' => $lineNumber,
                'title' => $title,
                'description' => $description ? mb_substr($description, 0, 100) . (mb_strlen($description) > 100 ? '...' : '') : null,
                'position' => $position,
                'secret' => $secret,
            ];
        } else {
            // Créer la résolution avec UUID généré
            $motionId = $motionRepo->generateUuid();
            $motionRepo->create(
                $motionId,
                $tenantId,
                $meetingId,
                null, // agenda_id
                $title,
                $description ?? '',
                $secret,
                null, // vote_policy_id
                null  // quorum_policy_id
            );
            // Mettre à jour la position séparément
            $motionRepo->updatePosition($motionId, $tenantId, $position);
        }

        $imported++;
    }

    if (!$dryRun) {
        db()->commit();
    }

} catch (\Throwable $e) {
    if (!$dryRun) {
        db()->rollBack();
    }
    api_fail('import_failed', 500, ['detail' => $e->getMessage()]);
}

// Audit
if (!$dryRun && $imported > 0) {
    audit_log('motions_import_xlsx', 'motion', $meetingId, [
        'imported' => $imported,
        'skipped' => $skipped,
        'filename' => $file['name'],
    ], $meetingId);
}

$response = [
    'imported' => $imported,
    'skipped' => $skipped,
    'errors' => array_slice($errors, 0, 20),
    'dry_run' => $dryRun,
];

if ($dryRun) {
    $response['preview'] = array_slice($preview, 0, 50);
}

api_ok($response);
