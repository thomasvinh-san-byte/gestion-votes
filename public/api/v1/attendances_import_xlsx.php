<?php
declare(strict_types=1);

/**
 * attendances_import_xlsx.php - Import Excel des présences
 *
 * Format XLSX attendu (première ligne = en-têtes):
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
use AgVote\Service\ImportService;

// =============================================================================
// SÉCURITÉ
// =============================================================================

api_rate_limit('xlsx_import', 10, 3600);
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
$columnMap = ImportService::getAttendancesColumnMap();
$colIndex = ImportService::mapColumns($headers, $columnMap);

if (!isset($colIndex['name']) && !isset($colIndex['email'])) {
    api_fail('missing_identifier', 400, [
        'detail' => 'Colonne "name", "nom" ou "email" requise pour identifier les membres.',
        'found' => $headers
    ]);
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
$preview = [];

if (!$dryRun) {
    db()->beginTransaction();
}

try {
    foreach ($rows as $lineIndex => $row) {
        $lineNumber = $lineIndex + 2;

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
            $parsedMode = ImportService::parseAttendanceMode($modeRaw);
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
    api_fail('import_failed', 500, ['detail' => $e->getMessage()]);
}

// Audit
if (!$dryRun && $imported > 0) {
    audit_log('attendances_import_xlsx', 'attendance', $meetingId, [
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
