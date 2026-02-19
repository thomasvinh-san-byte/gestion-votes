<?php
declare(strict_types=1);

/**
 * attendances_import_csv.php - Import CSV des présences
 *
 * Format CSV attendu (première ligne = en-têtes):
 *   name,email,mode
 *   ou
 *   nom,email,statut
 *
 * Colonnes reconnues:
 *   - name/nom/full_name: Nom du membre (requis si pas d'email)
 *   - email/mail: Email du membre (requis si pas de nom)
 *   - mode/statut/status/presence: Mode de présence (présent/remote/excused/absent)
 *   - notes/commentaire: Notes (optionnel)
 *
 * Modes acceptés:
 *   - present/présent/p/1 → present
 *   - remote/distant/d/distanciel → remote
 *   - excused/excusé/excuse/e → excused
 *   - absent/a/0/vide → absent (ou suppression de présence)
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\AttendanceRepository;

// =============================================================================
// SÉCURITÉ
// =============================================================================

api_rate_limit('csv_import', 10, 3600);
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

$meetingRepo = new MeetingRepository();
$meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);

if (!$meeting) {
    api_fail('meeting_not_found', 404);
}

if (in_array($meeting['status'], ['validated', 'archived'], true)) {
    api_fail('meeting_locked', 403, ['detail' => 'Séance validée ou archivée, modifications interdites.']);
}

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
    api_fail('file_read_error', 500, ['detail' => $e->getMessage()]);
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
    'name' => ['name', 'nom', 'full_name', 'nom_complet', 'membre'],
    'email' => ['email', 'mail', 'e-mail'],
    'mode' => ['mode', 'statut', 'status', 'presence', 'présence', 'etat', 'état'],
    'notes' => ['notes', 'commentaire', 'comment', 'remarque'],
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

if (!isset($colIndex['name']) && !isset($colIndex['email'])) {
    fclose($handle);
    api_fail('missing_identifier', 400, [
        'detail' => 'Colonne "name", "nom" ou "email" requise pour identifier les membres.',
        'found' => $headers
    ]);
}

// =============================================================================
// HELPERS
// =============================================================================

function parseMode(string $val): ?string {
    $val = mb_strtolower(trim($val));

    if (in_array($val, ['present', 'présent', 'p', '1', 'oui', 'yes'], true)) {
        return 'present';
    }
    if (in_array($val, ['remote', 'distant', 'd', 'distanciel', 'visio'], true)) {
        return 'remote';
    }
    if (in_array($val, ['excused', 'excusé', 'excuse', 'e', 'excusée'], true)) {
        return 'excused';
    }
    if (in_array($val, ['absent', 'a', '0', 'non', 'no', ''], true)) {
        return 'absent';
    }
    if (in_array($val, ['proxy', 'procuration', 'mandataire'], true)) {
        return 'proxy';
    }

    return null;
}

// =============================================================================
// IMPORT
// =============================================================================

$memberRepo = new MemberRepository();
$attendanceRepo = new AttendanceRepository();

// Précharger les membres pour lookup rapide
$allMembers = $memberRepo->listByTenant($tenantId);
$membersByEmail = [];
$membersByName = [];
foreach ($allMembers as $m) {
    if (!empty($m['email'])) {
        $membersByEmail[strtolower($m['email'])] = $m;
    }
    $membersByName[mb_strtolower($m['full_name'])] = $m;
}

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

        // Identifier le membre
        $member = null;

        if (isset($colIndex['email'])) {
            $email = strtolower(trim($row[$colIndex['email']] ?? ''));
            if ($email !== '' && isset($membersByEmail[$email])) {
                $member = $membersByEmail[$email];
            }
        }

        if (!$member && isset($colIndex['name'])) {
            $name = mb_strtolower(trim($row[$colIndex['name']] ?? ''));
            if ($name !== '' && isset($membersByName[$name])) {
                $member = $membersByName[$name];
            }
        }

        if (!$member) {
            $identifier = isset($colIndex['email']) ? ($row[$colIndex['email']] ?? '') : ($row[$colIndex['name']] ?? '');
            $errors[] = ['line' => $lineNumber, 'error' => "Membre introuvable: {$identifier}"];
            $skipped++;
            continue;
        }

        // Mode de présence
        $mode = 'present'; // défaut
        if (isset($colIndex['mode'])) {
            $modeRaw = trim($row[$colIndex['mode']] ?? '');
            $parsedMode = parseMode($modeRaw);
            if ($parsedMode === null && $modeRaw !== '') {
                $errors[] = ['line' => $lineNumber, 'error' => "Mode invalide: {$modeRaw}"];
                $skipped++;
                continue;
            }
            $mode = $parsedMode ?? 'present';
        }

        // Notes
        $notes = null;
        if (isset($colIndex['notes'])) {
            $notes = trim($row[$colIndex['notes']] ?? '') ?: null;
        }

        if ($dryRun) {
            $preview[] = [
                'line' => $lineNumber,
                'member_id' => $member['id'],
                'member_name' => $member['full_name'],
                'mode' => $mode,
                'notes' => $notes,
            ];
        } else {
            // Upsert la présence
            $effectivePower = (float)($member['voting_power'] ?? 1);
            $attendanceRepo->upsert(
                $tenantId,
                $meetingId,
                $member['id'],
                $mode,
                $effectivePower,
                $notes
            );
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
    audit_log('attendances_import', 'attendance', $meetingId, [
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
