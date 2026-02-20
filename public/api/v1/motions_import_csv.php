<?php
declare(strict_types=1);

/**
 * motions_import_csv.php - Import CSV des résolutions
 *
 * Format CSV attendu (première ligne = en-têtes):
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

// =============================================================================
// SÉCURITÉ
// =============================================================================

api_rate_limit('csv_import', 10, 3600);
api_require_role(['operator', 'admin']);

api_request('POST');

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

$file = $_FILES['file'] ?? $_FILES['csv_file'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    api_fail('upload_error', 400, ['detail' => 'Fichier manquant ou erreur upload.']);
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    api_fail('invalid_file_type', 400, ['detail' => 'Seuls les fichiers CSV sont acceptés.']);
}

if ($file['size'] > 5 * 1024 * 1024) {
    api_fail('file_too_large', 400, ['detail' => 'Max 5 MB.']);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!in_array($mime, ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'], true)) {
    api_fail('invalid_mime_type', 400, ['detail' => "Type non autorisé: {$mime}"]);
}

// =============================================================================
// PARSING CSV
// =============================================================================

$handle = fopen($file['tmp_name'], 'r');
if (!$handle) {
    api_fail('file_read_error', 500, ['detail' => 'Impossible d\'ouvrir le fichier CSV']);
}

$firstLine = fgets($handle);
rewind($handle);
$separator = strpos($firstLine, ';') !== false ? ';' : ',';

$headers = fgetcsv($handle, 0, $separator);
if (!$headers) {
    fclose($handle);
    api_fail('invalid_csv', 400, ['detail' => 'En-têtes CSV invalides.']);
}

$headers = array_map(fn($h) => strtolower(trim($h)), $headers);

$columnMap = [
    'title' => ['title', 'titre', 'intitule', 'intitulé', 'resolution', 'résolution'],
    'description' => ['description', 'texte', 'content', 'contenu', 'detail', 'détail'],
    'position' => ['position', 'ordre', 'order', 'rang', 'index'],
    'secret' => ['secret', 'vote_secret', 'secret_vote', 'scrutin_secret'],
];

$colIndex = [];
foreach ($columnMap as $field => $aliases) {
    foreach ($aliases as $alias) {
        $idx = array_search($alias, $headers, true);
        if ($idx !== false) {
            $colIndex[$field] = $idx;
            break;
        }
    }
}

if (!isset($colIndex['title'])) {
    fclose($handle);
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
$lineNumber = 1;
$preview = [];

if (!$dryRun) {
    db()->beginTransaction();
}

try {
    while (($row = fgetcsv($handle, 0, $separator)) !== false) {
        $lineNumber++;

        if (empty(array_filter($row))) continue;

        // Extraire les données
        $title = trim($row[$colIndex['title']] ?? '');
        $description = isset($colIndex['description']) ? trim($row[$colIndex['description']] ?? '') : null;

        // Position: utiliser la valeur CSV ou auto-incrémenter
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
            $secretVal = strtolower(trim($row[$colIndex['secret']] ?? '0'));
            $secret = in_array($secretVal, ['1', 'true', 'oui', 'yes', 'secret'], true);
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
    fclose($handle);
    api_fail('import_failed', 500, ['detail' => $e->getMessage()]);
}

fclose($handle);

// Audit
if (!$dryRun && $imported > 0) {
    audit_log('motions_import', 'motion', $meetingId, [
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
