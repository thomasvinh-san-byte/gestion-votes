<?php
declare(strict_types=1);

/**
 * members_import_csv.php - Import CSV des membres
 *
 * Format CSV attendu (première ligne = en-têtes):
 *   name,email,voting_power,is_active,groups
 *   ou
 *   nom,prenom,email,ponderation,type,adresse,groupes
 *
 * Colonnes reconnues:
 *   - name/nom/full_name: Nom complet (requis si pas first_name+last_name)
 *   - first_name/prenom + last_name: Alternative au nom complet
 *   - email/mail: Adresse email (optionnel)
 *   - voting_power/ponderation/tantiemes: Poids de vote (défaut: 1)
 *   - is_active/actif: Statut actif (défaut: true)
 *   - groups/groupes/college/categorie: Noms de groupes séparés par | ou ;
 *     Les groupes sont créés automatiquement s'ils n'existent pas.
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MemberRepository;
use AgVote\Repository\MemberGroupRepository;

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
// VALIDATION FICHIER OU CONTENU CSV
// =============================================================================

// Support 3 modes:
// 1. File upload via $_FILES['file'] or $_FILES['csv_file']
// 2. csv_content as FormData text field ($_POST['csv_content'])
// 3. csv_content as JSON body field
$file = $_FILES['file'] ?? $_FILES['csv_file'] ?? null;
$csvContent = $_POST['csv_content'] ?? null;

// Try JSON body if no FormData
if (!$file && !$csvContent) {
    $jsonBody = json_decode(file_get_contents('php://input'), true);
    $csvContent = $jsonBody['csv_content'] ?? null;
}

if ($file && $file['error'] === UPLOAD_ERR_OK) {
    // ── Mode fichier ──
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

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        api_fail('file_read_error', 500);
    }
} elseif ($csvContent && is_string($csvContent) && strlen($csvContent) > 0) {
    // ── Mode contenu texte (FormData ou JSON) ──
    if (strlen($csvContent) > 5 * 1024 * 1024) {
        api_fail('file_too_large', 400, ['detail' => 'Max 5 MB.']);
    }

    $tmpFile = tmpfile();
    fwrite($tmpFile, $csvContent);
    rewind($tmpFile);
    $handle = $tmpFile;
} else {
    api_fail('upload_error', 400, [
        'detail' => 'Fichier CSV manquant. Envoyez un fichier (file/csv_file) ou le contenu texte (csv_content).',
    ]);
}

// =============================================================================
// PARSING CSV
// =============================================================================

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
    'groups' => ['groups', 'groupes', 'group', 'groupe', 'college', 'collège', 'categorie', 'catégorie'],
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
$groupRepo = new MemberGroupRepository();

// Preload existing groups for lookup by name
$existingGroups = [];
foreach ($groupRepo->listForTenant($tenantId, false) as $g) {
    $existingGroups[mb_strtolower($g['name'])] = $g['id'];
}

// Helper to find or create group by name
$findOrCreateGroup = function(string $name) use ($groupRepo, $tenantId, &$existingGroups): ?string {
    $name = trim($name);
    if ($name === '') return null;

    $key = mb_strtolower($name);
    if (isset($existingGroups[$key])) {
        return $existingGroups[$key];
    }

    // Create group
    $group = $groupRepo->create($tenantId, $name);
    $existingGroups[$key] = $group['id'];
    return $group['id'];
};

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

        // Parse groups (separated by | or ,)
        $groupNames = [];
        if (isset($colIndex['groups'])) {
            $groupsRaw = trim($row[$colIndex['groups']] ?? '');
            if ($groupsRaw !== '') {
                // Split by | or ; (not comma since CSV may use comma as separator)
                $groupNames = preg_split('/[|;]/', $groupsRaw);
                $groupNames = array_filter(array_map('trim', $groupNames));
            }
        }

        $memberId = null;
        if ($existing) {
            // Update
            $memberId = $existing['id'];
            $memberRepo->updateImport($memberId, $data['full_name'], $data['email'] ?: null, $data['voting_power'], $data['is_active']);
        } else {
            // Insert
            $memberId = $memberRepo->createImport($tenantId, $data['full_name'], $data['email'] ?: null, $data['voting_power'], $data['is_active']);
        }

        // Assign groups
        if (!empty($groupNames) && $memberId) {
            $groupIds = [];
            foreach ($groupNames as $gn) {
                $gid = $findOrCreateGroup($gn);
                if ($gid) $groupIds[] = $gid;
            }
            if (!empty($groupIds)) {
                $groupRepo->setMemberGroups($memberId, $groupIds);
            }
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
