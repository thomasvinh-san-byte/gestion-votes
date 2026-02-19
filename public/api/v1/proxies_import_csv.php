<?php
declare(strict_types=1);

/**
 * proxies_import_csv.php - Import CSV des procurations
 *
 * Format CSV attendu (première ligne = en-têtes):
 *   giver_name,giver_email,receiver_name,receiver_email
 *   ou
 *   mandant_nom,mandant_email,mandataire_nom,mandataire_email
 *
 * Colonnes reconnues:
 *   - giver_name/mandant_nom/donneur: Nom du mandant (requis si pas d'email)
 *   - giver_email/mandant_email: Email du mandant (requis si pas de nom)
 *   - receiver_name/mandataire_nom/receveur: Nom du mandataire (requis si pas d'email)
 *   - receiver_email/mandataire_email: Email du mandataire (requis si pas de nom)
 *
 * Règles appliquées:
 *   - Pas d'auto-délégation (mandant = mandataire)
 *   - Plafond de procurations par mandataire (défaut: 3)
 *   - Pas de chaîne de procurations
 *   - Membre mandant et mandataire doivent exister
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\ProxyRepository;

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

// Plafond de procurations par mandataire
$maxProxiesPerReceiver = (int)(getenv('PROXY_MAX_PER_RECEIVER') ?: 3);

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
    'giver_name' => ['giver_name', 'mandant_nom', 'mandant', 'donneur', 'donneur_nom', 'from_name', 'de'],
    'giver_email' => ['giver_email', 'mandant_email', 'donneur_email', 'from_email'],
    'receiver_name' => ['receiver_name', 'mandataire_nom', 'mandataire', 'receveur', 'receveur_nom', 'to_name', 'vers'],
    'receiver_email' => ['receiver_email', 'mandataire_email', 'receveur_email', 'to_email'],
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

// Vérifier qu'on a au moins un identifiant pour le giver et le receiver
$hasGiver = isset($colIndex['giver_name']) || isset($colIndex['giver_email']);
$hasReceiver = isset($colIndex['receiver_name']) || isset($colIndex['receiver_email']);

if (!$hasGiver || !$hasReceiver) {
    fclose($handle);
    api_fail('missing_columns', 400, [
        'detail' => 'Colonnes requises: (giver_name OU giver_email) ET (receiver_name OU receiver_email).',
        'found' => $headers
    ]);
}

// =============================================================================
// IMPORT
// =============================================================================

$memberRepo = new MemberRepository();
$proxyRepo = new ProxyRepository();

// Précharger les membres
$allMembers = $memberRepo->listByTenant($tenantId);
$membersByEmail = [];
$membersByName = [];
foreach ($allMembers as $m) {
    if (!empty($m['email'])) {
        $membersByEmail[strtolower($m['email'])] = $m;
    }
    $membersByName[mb_strtolower($m['full_name'])] = $m;
}

// Précharger les procurations existantes pour vérifier le plafond
$existingProxies = $proxyRepo->listForMeeting($meetingId, $tenantId);
$proxiesPerReceiver = [];
$existingGivers = [];
foreach ($existingProxies as $p) {
    $receiverId = $p['receiver_member_id'];
    $giverId = $p['giver_member_id'];
    $proxiesPerReceiver[$receiverId] = ($proxiesPerReceiver[$receiverId] ?? 0) + 1;
    $existingGivers[$giverId] = $receiverId;
}

// Helper pour trouver un membre
$findMember = function(array $row, string $nameField, string $emailField) use ($colIndex, $membersByEmail, $membersByName): ?array {
    if (isset($colIndex[$emailField])) {
        $email = strtolower(trim($row[$colIndex[$emailField]] ?? ''));
        if ($email !== '' && isset($membersByEmail[$email])) {
            return $membersByEmail[$email];
        }
    }
    if (isset($colIndex[$nameField])) {
        $name = mb_strtolower(trim($row[$colIndex[$nameField]] ?? ''));
        if ($name !== '' && isset($membersByName[$name])) {
            return $membersByName[$name];
        }
    }
    return null;
};

$imported = 0;
$skipped = 0;
$errors = [];
$lineNumber = 1;
$preview = [];

// Compteur temporaire pour le dry-run
$tempProxiesPerReceiver = $proxiesPerReceiver;
$tempExistingGivers = $existingGivers;

if (!$dryRun) {
    db()->beginTransaction();
}

try {
    while (($row = fgetcsv($handle, 0, $separator)) !== false) {
        $lineNumber++;

        if (empty(array_filter($row))) continue;

        // Trouver le mandant (giver)
        $giver = $findMember($row, 'giver_name', 'giver_email');
        if (!$giver) {
            $identifier = $row[$colIndex['giver_email'] ?? $colIndex['giver_name'] ?? 0] ?? 'inconnu';
            $errors[] = ['line' => $lineNumber, 'error' => "Mandant introuvable: {$identifier}"];
            $skipped++;
            continue;
        }

        // Trouver le mandataire (receiver)
        $receiver = $findMember($row, 'receiver_name', 'receiver_email');
        if (!$receiver) {
            $identifier = $row[$colIndex['receiver_email'] ?? $colIndex['receiver_name'] ?? 0] ?? 'inconnu';
            $errors[] = ['line' => $lineNumber, 'error' => "Mandataire introuvable: {$identifier}"];
            $skipped++;
            continue;
        }

        // Règle: pas d'auto-délégation
        if ($giver['id'] === $receiver['id']) {
            $errors[] = ['line' => $lineNumber, 'error' => 'Auto-délégation interdite'];
            $skipped++;
            continue;
        }

        // Règle: le mandant n'a pas déjà une procuration active
        if (isset($tempExistingGivers[$giver['id']])) {
            $errors[] = ['line' => $lineNumber, 'error' => "Le mandant {$giver['full_name']} a déjà une procuration active"];
            $skipped++;
            continue;
        }

        // Règle: pas de chaîne (le receiver ne doit pas être lui-même un giver)
        if (isset($tempExistingGivers[$receiver['id']])) {
            $errors[] = ['line' => $lineNumber, 'error' => "Chaîne de procuration interdite: {$receiver['full_name']} est déjà mandant"];
            $skipped++;
            continue;
        }

        // Règle: plafond par receiver
        $currentCount = $tempProxiesPerReceiver[$receiver['id']] ?? 0;
        if ($currentCount >= $maxProxiesPerReceiver) {
            $errors[] = ['line' => $lineNumber, 'error' => "Plafond atteint: {$receiver['full_name']} a déjà {$currentCount} procurations (max: {$maxProxiesPerReceiver})"];
            $skipped++;
            continue;
        }

        if ($dryRun) {
            $preview[] = [
                'line' => $lineNumber,
                'giver_id' => $giver['id'],
                'giver_name' => $giver['full_name'],
                'receiver_id' => $receiver['id'],
                'receiver_name' => $receiver['full_name'],
            ];
            // Mettre à jour les compteurs temporaires
            $tempProxiesPerReceiver[$receiver['id']] = $currentCount + 1;
            $tempExistingGivers[$giver['id']] = $receiver['id'];
        } else {
            // Créer la procuration
            $proxyRepo->upsertProxy(
                $tenantId,
                $meetingId,
                $giver['id'],
                $receiver['id']
            );
            // Mettre à jour les compteurs
            $proxiesPerReceiver[$receiver['id']] = $currentCount + 1;
            $existingGivers[$giver['id']] = $receiver['id'];
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
    audit_log('proxies_import', 'proxy', $meetingId, [
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
    'max_proxies_per_receiver' => $maxProxiesPerReceiver,
];

if ($dryRun) {
    $response['preview'] = array_slice($preview, 0, 50);
}

api_ok($response);
