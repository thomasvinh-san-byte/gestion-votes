<?php
declare(strict_types=1);

/**
 * members_import_csv.php - Import CSV des membres
 * 
 * Format CSV attendu (première ligne = en-têtes):
 *   name,email,voting_power,is_active
 *   ou
 *   nom,prenom,email,ponderation,type,adresse
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MemberRepository;

// =============================================================================
// SÉCURITÉ
// =============================================================================

// Rate limiting (10 imports/heure)
api_rate_limit('csv_import', 10, 3600);

// Auth (operator ou admin)
api_require_role(['operator', 'admin']);

// POST uniquement
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_fail('method_not_allowed', 405);
}

// =============================================================================
// VALIDATION FICHIER
// =============================================================================

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    api_fail('upload_error', 400, ['detail' => 'Fichier manquant ou erreur upload.']);
}

$file = $_FILES['file'];

// Extension
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    api_fail('invalid_file_type', 400, ['detail' => 'Seuls les fichiers CSV sont acceptés.']);
}

// Taille (max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    api_fail('file_too_large', 400, ['detail' => 'Max 5 MB.']);
}

// MIME type
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
    api_fail('file_read_error', 500);
}

// Détecte séparateur
$firstLine = fgets($handle);
rewind($handle);
$separator = strpos($firstLine, ';') !== false ? ';' : ',';

// Headers
$headers = fgetcsv($handle, 0, $separator);
if (!$headers) {
    fclose($handle);
    api_fail('invalid_csv', 400, ['detail' => 'En-têtes CSV invalides.']);
}

$headers = array_map(fn($h) => strtolower(trim($h)), $headers);

// Mapping colonnes
$columnMap = [
    'name' => ['name', 'nom', 'full_name', 'nom_complet'],
    'first_name' => ['first_name', 'prenom', 'prénom'],
    'last_name' => ['last_name', 'nom_famille'],
    'email' => ['email', 'mail', 'e-mail'],
    'voting_power' => ['voting_power', 'ponderation', 'pondération', 'weight', 'tantiemes'],
    'is_active' => ['is_active', 'actif', 'active'],
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

// Vérifie qu'on a name ou first_name+last_name
$hasName = isset($colIndex['name']);
$hasFirstLast = isset($colIndex['first_name']) && isset($colIndex['last_name']);
if (!$hasName && !$hasFirstLast) {
    fclose($handle);
    api_fail('missing_name_column', 400, [
        'detail' => 'Colonne "name" ou "first_name"+"last_name" requise.',
        'found' => $headers
    ]);
}

// =============================================================================
// IMPORT
// =============================================================================

$tenantId = api_current_tenant_id();
$imported = 0;
$skipped = 0;
$errors = [];
$lineNumber = 1;

$memberRepo = new MemberRepository();

db()->beginTransaction();

try {
    while (($row = fgetcsv($handle, 0, $separator)) !== false) {
        $lineNumber++;

        if (empty(array_filter($row))) continue;

        // Construit données
        $data = [];

        // Nom
        if ($hasName && isset($colIndex['name'])) {
            $data['full_name'] = trim($row[$colIndex['name']] ?? '');
        } elseif ($hasFirstLast) {
            $firstName = trim($row[$colIndex['first_name']] ?? '');
            $lastName = trim($row[$colIndex['last_name']] ?? '');
            $data['full_name'] = trim("{$firstName} {$lastName}");
        }

        // Autres champs
        if (isset($colIndex['email'])) {
            $data['email'] = strtolower(trim($row[$colIndex['email']] ?? ''));
        }
        if (isset($colIndex['voting_power'])) {
            $data['voting_power'] = (float)($row[$colIndex['voting_power']] ?? 1);
        } else {
            $data['voting_power'] = 1.0;
        }
        if (isset($colIndex['is_active'])) {
            $val = strtolower(trim($row[$colIndex['is_active']] ?? '1'));
            $data['is_active'] = in_array($val, ['1', 'true', 'oui', 'yes', 'actif'], true);
        } else {
            $data['is_active'] = true;
        }

        // Validation basique
        if (empty($data['full_name']) || mb_strlen($data['full_name']) < 2) {
            $errors[] = ['line' => $lineNumber, 'error' => 'Nom invalide'];
            $skipped++;
            continue;
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = ['line' => $lineNumber, 'error' => 'Email invalide'];
            $skipped++;
            continue;
        }

        // Check doublon
        $existing = null;
        if (!empty($data['email'])) {
            $existing = $memberRepo->findByEmail($tenantId, $data['email']);
        }
        if (!$existing) {
            $existing = $memberRepo->findByFullName($tenantId, $data['full_name']);
        }

        if ($existing) {
            // Update
            $memberRepo->updateImport($existing['id'], $data['full_name'], $data['email'] ?: null, $data['voting_power'], $data['is_active']);
        } else {
            // Insert
            $memberRepo->createImport($tenantId, $data['full_name'], $data['email'] ?: null, $data['voting_power'], $data['is_active']);
        }

        $imported++;
    }

    db()->commit();

} catch (\Throwable $e) {
    db()->rollBack();
    fclose($handle);
    api_fail('import_failed', 500, ['detail' => $e->getMessage()]);
}

fclose($handle);

// Audit
audit_log('members_import', 'member', null, [
    'imported' => $imported,
    'skipped' => $skipped,
    'filename' => $file['name'],
]);

api_ok([
    'imported' => $imported,
    'skipped' => $skipped,
    'errors' => array_slice($errors, 0, 20),
]);
