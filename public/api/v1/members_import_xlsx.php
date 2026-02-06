<?php
declare(strict_types=1);

/**
 * members_import_xlsx.php - Import Excel des membres
 *
 * Format XLSX attendu (première ligne = en-têtes):
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
$columnMap = ImportService::getMembersColumnMap();
$colIndex = ImportService::mapColumns($headers, $columnMap);

// Vérifie qu'on a name ou first_name+last_name
$hasName = isset($colIndex['name']);
$hasFirstLast = isset($colIndex['first_name']) && isset($colIndex['last_name']);
if (!$hasName && !$hasFirstLast) {
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
    foreach ($rows as $lineIndex => $row) {
        $lineNumber = $lineIndex + 2; // +2 because headers are line 1 and array is 0-indexed

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
            $data['voting_power'] = ImportService::parseVotingPower($row[$colIndex['voting_power']] ?? '1');
        } else {
            $data['voting_power'] = 1.0;
        }
        if (isset($colIndex['is_active'])) {
            $data['is_active'] = ImportService::parseBoolean($row[$colIndex['is_active']] ?? '1');
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

        // Parse groups (separated by | or ;)
        $groupNames = [];
        if (isset($colIndex['groups'])) {
            $groupsRaw = trim($row[$colIndex['groups']] ?? '');
            if ($groupsRaw !== '') {
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
    api_fail('import_failed', 500, ['detail' => $e->getMessage()]);
}

// Audit
audit_log('members_import_xlsx', 'member', null, [
    'imported' => $imported,
    'skipped' => $skipped,
    'filename' => $file['name'],
]);

api_ok([
    'imported' => $imported,
    'skipped' => $skipped,
    'errors' => array_slice($errors, 0, 20),
]);
