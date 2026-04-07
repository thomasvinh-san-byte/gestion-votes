<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\RepositoryFactory;
use finfo;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * ImportService - Centralized service for file imports (CSV, XLSX)
 *
 * Handles file reading, parsing, and business logic for import operations.
 * Can be instantiated with a RepositoryFactory for testability (nullable DI).
 */
final class ImportService {

    // ========================================================================
    // INSTANCE PROPERTIES AND CONSTRUCTOR
    // ========================================================================

    private ?RepositoryFactory $repos;

    public function __construct(?RepositoryFactory $repos = null) {
        $this->repos = $repos ?? RepositoryFactory::getInstance();
    }
    // ========================================================================
    // CONSTANTS
    // ========================================================================

    /** Maximum file size (5 MB) */
    public const MAX_FILE_SIZE = 5 * 1024 * 1024;

    /** Allowed MIME types for CSV */
    public const CSV_MIME_TYPES = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel',
    ];

    /** Allowed MIME types for XLSX */
    public const XLSX_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/octet-stream',
        'application/zip',
    ];

    // ========================================================================
    // FILE VALIDATION
    // ========================================================================

    /**
     * Validates an uploaded file
     *
     * @param array $file $_FILES entry
     * @param string $expectedExtension 'csv' or 'xlsx'
     *
     * @return array ['ok' => bool, 'error' => ?string]
     */
    public static function validateUploadedFile(array $file, string $expectedExtension): array {
        // Check upload error
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Fichier manquant ou erreur upload.'];
        }

        // Check extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== $expectedExtension) {
            return ['ok' => false, 'error' => "Extension attendue: .{$expectedExtension}, reçue: .{$ext}"];
        }

        // Check size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['ok' => false, 'error' => 'Fichier trop volumineux (max 5 MB).'];
        }

        // Check MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        $allowedMimes = $expectedExtension === 'xlsx' ? self::XLSX_MIME_TYPES : self::CSV_MIME_TYPES;
        if (!in_array($mime, $allowedMimes, true)) {
            return ['ok' => false, 'error' => "Type MIME non autorisé: {$mime}"];
        }

        return ['ok' => true, 'error' => null];
    }

    // ========================================================================
    // XLSX READING
    // ========================================================================

    /**
     * Reads an XLSX file and returns rows as arrays
     *
     * @param string $filePath Path to the XLSX file
     * @param int $sheetIndex Sheet index to read (default: 0 = first sheet)
     *
     * @return array ['headers' => array, 'rows' => array, 'error' => ?string]
     */
    public static function readXlsxFile(string $filePath, int $sheetIndex = 0): array {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getSheet($sheetIndex);

            $rows = [];
            $headers = [];
            $rowIndex = 0;

            foreach ($sheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $value = $cell->getValue();
                    // Handle formulas
                    if ($cell->isFormula()) {
                        $value = $cell->getCalculatedValue();
                    }
                    $rowData[] = $value !== null ? (string) $value : '';
                }

                if ($rowIndex === 0) {
                    // First row = headers
                    $headers = array_map(fn ($h) => strtolower(trim($h)), $rowData);
                } else {
                    // Skip empty rows
                    if (!empty(array_filter($rowData, fn ($v) => trim($v) !== ''))) {
                        $rows[] = $rowData;
                    }
                }
                $rowIndex++;
            }

            return [
                'headers' => $headers,
                'rows' => $rows,
                'error' => null,
            ];

        } catch (Throwable $e) {
            return [
                'headers' => [],
                'rows' => [],
                'error' => 'Erreur lecture fichier Excel: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Gets column indices from headers based on column map
     *
     * @param array $headers Normalized headers from file
     * @param array $columnMap Map of field => [alias1, alias2, ...]
     *
     * @return array Map of field => column index
     */
    public static function mapColumns(array $headers, array $columnMap): array {
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
        return $colIndex;
    }

    // ========================================================================
    // CSV READING
    // ========================================================================

    /**
     * Reads a CSV file and returns rows as arrays
     *
     * @param string $filePath Path to the CSV file
     *
     * @return array ['headers' => array, 'rows' => array, 'separator' => string, 'error' => ?string]
     */
    public static function readCsvFile(string $filePath): array {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return [
                'headers' => [],
                'rows' => [],
                'separator' => ',',
                'error' => 'Impossible d\'ouvrir le fichier.',
            ];
        }

        // Detect and normalize encoding before fgetcsv (which is not encoding-aware)
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
        if ($encoding !== false && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Write normalized content to temp file for fgetcsv
        $tmpPath = tempnam(sys_get_temp_dir(), 'csv_enc_');
        file_put_contents($tmpPath, $content);
        $handle = @fopen($tmpPath, 'r');
        if (!$handle) {
            @unlink($tmpPath);
            return [
                'headers' => [],
                'rows' => [],
                'separator' => ',',
                'error' => 'Impossible d\'ouvrir le fichier.',
            ];
        }

        try {
            // Detect separator
            $firstLine = fgets($handle);
            rewind($handle);
            $separator = ($firstLine !== false && strpos($firstLine, ';') !== false) ? ';' : ',';

            // Read headers
            $headers = fgetcsv($handle, 0, $separator);
            if (!$headers) {
                return [
                    'headers' => [],
                    'rows' => [],
                    'separator' => $separator,
                    'error' => 'En-têtes CSV invalides.',
                ];
            }

            $headers = array_map(fn ($h) => strtolower(trim($h)), $headers);

            // Read rows
            $rows = [];
            while (($row = fgetcsv($handle, 0, $separator)) !== false) {
                if (!empty(array_filter($row))) {
                    $rows[] = $row;
                }
            }

            return [
                'headers' => $headers,
                'rows' => $rows,
                'separator' => $separator,
                'error' => null,
            ];
        } finally {
            fclose($handle);
            @unlink($tmpPath);
        }
    }

    // ========================================================================
    // COLUMN MAPS FOR EACH ENTITY
    // ========================================================================

    /**
     * Column map for members import
     */
    public static function getMembersColumnMap(): array {
        return [
            'name' => ['name', 'nom', 'full_name', 'nom_complet'],
            'first_name' => ['first_name', 'prenom', 'prénom'],
            'last_name' => ['last_name', 'nom_famille'],
            'email' => ['email', 'mail', 'e-mail'],
            'voting_power' => ['voting_power', 'ponderation', 'pondération', 'weight', 'tantiemes', 'tantièmes', 'poids'],
            'is_active' => ['is_active', 'actif', 'active'],
            'groups' => ['groups', 'groupes', 'group', 'groupe', 'college', 'collège', 'categorie', 'catégorie'],
        ];
    }

    /**
     * Column map for attendances import
     */
    public static function getAttendancesColumnMap(): array {
        return [
            'name' => ['name', 'nom', 'full_name', 'nom_complet', 'membre'],
            'email' => ['email', 'mail', 'e-mail'],
            'mode' => ['mode', 'statut', 'status', 'presence', 'présence', 'etat', 'état'],
            'notes' => ['notes', 'commentaire', 'comment', 'remarque'],
        ];
    }

    /**
     * Column map for motions import
     */
    public static function getMotionsColumnMap(): array {
        return [
            'title' => ['title', 'titre', 'intitule', 'intitulé', 'resolution', 'résolution'],
            'description' => ['description', 'texte', 'content', 'contenu', 'detail', 'détail'],
            'position' => ['position', 'ordre', 'order', 'rang', 'index', 'n°', 'numero', 'numéro'],
            'secret' => ['secret', 'vote_secret', 'secret_vote', 'scrutin_secret'],
        ];
    }

    /**
     * Column map for proxies import
     */
    public static function getProxiesColumnMap(): array {
        return [
            'giver_name' => ['giver_name', 'mandant_nom', 'mandant', 'donneur', 'donneur_nom', 'from_name', 'de'],
            'giver_email' => ['giver_email', 'mandant_email', 'donneur_email', 'from_email'],
            'receiver_name' => ['receiver_name', 'mandataire_nom', 'mandataire', 'receveur', 'receveur_nom', 'to_name', 'vers'],
            'receiver_email' => ['receiver_email', 'mandataire_email', 'receveur_email', 'to_email'],
        ];
    }

    // ========================================================================
    // VALUE PARSING HELPERS
    // ========================================================================

    /**
     * Parse attendance mode from various formats
     */
    public static function parseAttendanceMode(string $val): ?string {
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

    /**
     * Parse boolean from various formats
     */
    public static function parseBoolean(string $val): bool {
        $val = strtolower(trim($val));
        return in_array($val, ['1', 'true', 'oui', 'yes', 'actif', 'active', 'o', 'y'], true);
    }

    /**
     * Parse voting power (float)
     */
    public static function parseVotingPower(string $val): float {
        // Handle French decimal separator
        $val = str_replace(',', '.', trim($val));
        $power = (float) $val;
        return $power > 0 ? $power : 1.0;
    }

    // ========================================================================
    // DUPLICATE EMAIL CHECK (static — no repo needed)
    // ========================================================================

    /**
     * Pre-scan rows for duplicate email addresses.
     *
     * Called before any DB transaction so no partial inserts occur.
     * Case-insensitive. Empty emails are skipped (not treated as duplicates).
     *
     * @param array[] $rows     CSV/XLSX rows as indexed arrays
     * @param array   $colIndex Column index map from ImportService::mapColumns()
     *
     * @return array List of duplicate email addresses found (empty if none)
     */
    public static function checkDuplicateEmails(array $rows, array $colIndex): array {
        if (!isset($colIndex['email'])) {
            return [];
        }
        $emailIdx = $colIndex['email'];
        $seen = [];
        $duplicates = [];
        foreach ($rows as $row) {
            $raw = strtolower(trim((string) ($row[$emailIdx] ?? '')));
            if ($raw === '') {
                continue;
            }
            if (isset($seen[$raw])) {
                $duplicates[] = $raw;
            } else {
                $seen[$raw] = true;
            }
        }
        return array_values(array_unique($duplicates));
    }

    // ========================================================================
    // INSTANCE PROCESS METHODS (business logic extracted from ImportController)
    // ========================================================================

    /**
     * Processes member import rows: creates or updates members and assigns groups.
     *
     * @return array{imported: int, skipped: int, errors: array}
     */
    public function processMemberImport(
        array $rows,
        array $colIndex,
        bool $hasName,
        bool $hasFirstLast,
        string $tenantId,
    ): array {
        $memberRepo = $this->repos->member();
        $groupRepo = $this->repos->memberGroup();

        $existingGroups = [];
        foreach ($groupRepo->listForTenant($tenantId, false) as $g) {
            $existingGroups[mb_strtolower($g['name'])] = $g['id'];
        }

        $findOrCreateGroup = function (string $name) use ($groupRepo, $tenantId, &$existingGroups): ?string {
            $name = trim($name);
            if ($name === '') {
                return null;
            }
            $key = mb_strtolower($name);
            if (isset($existingGroups[$key])) {
                return $existingGroups[$key];
            }
            $group = $groupRepo->create($tenantId, $name);
            $existingGroups[$key] = $group['id'];
            return $group['id'];
        };

        $seenEmails = [];
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $lineIndex => $row) {
            $lineNumber = $lineIndex + 2;

            $data = [];
            if ($hasName && isset($colIndex['name'])) {
                $data['full_name'] = trim($row[$colIndex['name']] ?? '');
            } elseif ($hasFirstLast) {
                $data['full_name'] = trim(trim($row[$colIndex['first_name']] ?? '') . ' ' . trim($row[$colIndex['last_name']] ?? ''));
            }

            if (isset($colIndex['email'])) {
                $data['email'] = strtolower(trim($row[$colIndex['email']] ?? ''));
            }

            // In-batch duplicate email detection
            if (!empty($data['email'])) {
                if (isset($seenEmails[$data['email']])) {
                    $errors[] = ['line' => $lineNumber, 'error' => "Email en double dans le fichier: {$data['email']} (déjà à la ligne {$seenEmails[$data['email']]})"];
                    $skipped++;
                    continue;
                }
                $seenEmails[$data['email']] = $lineNumber;
            }
            $data['voting_power'] = isset($colIndex['voting_power'])
                ? self::parseVotingPower($row[$colIndex['voting_power']] ?? '1') : 1.0;
            $data['is_active'] = isset($colIndex['is_active'])
                ? self::parseBoolean($row[$colIndex['is_active']] ?? '1') : true;

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

            $existing = null;
            if (!empty($data['email'])) {
                $existing = $memberRepo->findByEmail($tenantId, $data['email']);
            }
            if (!$existing) {
                $existing = $memberRepo->findByFullName($tenantId, $data['full_name']);
            }

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
                $memberId = $existing['id'];
                $memberRepo->updateImport($memberId, $data['full_name'], $data['email'] ?: null, $data['voting_power'], $data['is_active'], $tenantId);
            } else {
                $memberId = $memberRepo->createImport($tenantId, $data['full_name'], $data['email'] ?: null, $data['voting_power'], $data['is_active']);
            }

            if (!empty($groupNames) && $memberId) {
                $groupIds = [];
                foreach ($groupNames as $gn) {
                    $gid = $findOrCreateGroup($gn);
                    if ($gid) {
                        $groupIds[] = $gid;
                    }
                }
                if (!empty($groupIds)) {
                    $groupRepo->setMemberGroups($memberId, $groupIds);
                }
            }

            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Processes attendance import rows: upserts attendance records or builds preview.
     *
     * @return array{imported: int, skipped: int, errors: array, preview: array}
     */
    public function processAttendanceImport(
        array $rows,
        array $colIndex,
        string $tenantId,
        string $meetingId,
        bool $dryRun = false,
    ): array {
        [$membersByEmail, $membersByName] = $this->buildMemberLookups($tenantId);
        $attendanceRepo = $this->repos->attendance();

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $preview = [];

        foreach ($rows as $lineIndex => $row) {
            $lineNumber = $lineIndex + 2;

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

            $mode = 'present';
            if (isset($colIndex['mode'])) {
                $modeRaw = trim($row[$colIndex['mode']] ?? '');
                $parsedMode = self::parseAttendanceMode($modeRaw);
                if ($parsedMode === null && $modeRaw !== '') {
                    $errors[] = ['line' => $lineNumber, 'error' => "Mode invalide: {$modeRaw}"];
                    $skipped++;
                    continue;
                }
                $mode = $parsedMode ?? 'present';
            }

            $notes = null;
            if (isset($colIndex['notes'])) {
                $notes = trim($row[$colIndex['notes']] ?? '') ?: null;
            }

            if ($dryRun) {
                $preview[] = ['line' => $lineNumber, 'member_id' => $member['id'], 'member_name' => $member['full_name'], 'mode' => $mode, 'notes' => $notes];
            } else {
                $attendanceRepo->upsert($tenantId, $meetingId, $member['id'], $mode, (float) ($member['voting_power'] ?? 1), $notes);
            }
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors, 'preview' => $preview];
    }

    /**
     * Processes proxy import rows: creates proxies or builds preview with validation.
     *
     * @param array &$proxiesPerReceiver Running count of proxies per receiver ID
     * @param array &$existingGivers     Map of giver_id => receiver_id for already-assigned proxies
     *
     * @return array{imported: int, skipped: int, errors: array, preview: array}
     */
    public function processProxyImport(
        array $rows,
        array $colIndex,
        string $tenantId,
        string $meetingId,
        bool $dryRun,
        int $maxPerReceiver,
        array &$proxiesPerReceiver,
        array &$existingGivers,
    ): array {
        [$membersByEmail, $membersByName] = $this->buildMemberLookups($tenantId);
        $findMember = $this->buildProxyMemberFinder($colIndex, $membersByEmail, $membersByName);
        $proxyRepo = $this->repos->proxy();

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $preview = [];

        foreach ($rows as $lineIndex => $row) {
            $lineNumber = $lineIndex + 2;

            $giver = $findMember($row, 'giver_name', 'giver_email');
            if (!$giver) {
                $identifier = $row[$colIndex['giver_email'] ?? $colIndex['giver_name'] ?? 0] ?? 'inconnu';
                $errors[] = ['line' => $lineNumber, 'error' => "Mandant introuvable: {$identifier}"];
                $skipped++;
                continue;
            }

            $receiver = $findMember($row, 'receiver_name', 'receiver_email');
            if (!$receiver) {
                $identifier = $row[$colIndex['receiver_email'] ?? $colIndex['receiver_name'] ?? 0] ?? 'inconnu';
                $errors[] = ['line' => $lineNumber, 'error' => "Mandataire introuvable: {$identifier}"];
                $skipped++;
                continue;
            }

            if ($giver['id'] === $receiver['id']) {
                $errors[] = ['line' => $lineNumber, 'error' => 'Auto-délégation interdite'];
                $skipped++;
                continue;
            }
            if (isset($existingGivers[$giver['id']])) {
                $errors[] = ['line' => $lineNumber, 'error' => "Le mandant {$giver['full_name']} a déjà une procuration active"];
                $skipped++;
                continue;
            }
            if (isset($existingGivers[$receiver['id']])) {
                $errors[] = ['line' => $lineNumber, 'error' => "Chaîne de procuration interdite: {$receiver['full_name']} est déjà mandant"];
                $skipped++;
                continue;
            }

            $currentCount = $proxiesPerReceiver[$receiver['id']] ?? 0;
            if ($currentCount >= $maxPerReceiver) {
                $errors[] = ['line' => $lineNumber, 'error' => "Plafond atteint: {$receiver['full_name']} a déjà {$currentCount} procurations (max: {$maxPerReceiver})"];
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $preview[] = ['line' => $lineNumber, 'giver_id' => $giver['id'], 'giver_name' => $giver['full_name'], 'receiver_id' => $receiver['id'], 'receiver_name' => $receiver['full_name']];
            } else {
                $proxyRepo->upsertProxy($tenantId, $meetingId, $giver['id'], $receiver['id']);
            }
            $proxiesPerReceiver[$receiver['id']] = $currentCount + 1;
            $existingGivers[$giver['id']] = $receiver['id'];
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors, 'preview' => $preview];
    }

    /**
     * Processes motion import rows: creates motions or builds preview.
     *
     * @param int &$nextPosition Running position counter (modified in place)
     *
     * @return array{imported: int, skipped: int, errors: array, preview: array}
     */
    public function processMotionImport(
        array $rows,
        array $colIndex,
        string $tenantId,
        string $meetingId,
        bool $dryRun = false,
        int &$nextPosition = 1,
    ): array {
        $motionRepo = $this->repos->motion();

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $preview = [];

        foreach ($rows as $lineIndex => $row) {
            $lineNumber = $lineIndex + 2;

            $title = trim($row[$colIndex['title']] ?? '');
            $description = isset($colIndex['description']) ? trim($row[$colIndex['description']] ?? '') : null;

            $position = null;
            if (isset($colIndex['position'])) {
                $posVal = trim($row[$colIndex['position']] ?? '');
                if ($posVal !== '' && is_numeric($posVal)) {
                    $position = (int) $posVal;
                }
            }
            if ($position === null) {
                $position = $nextPosition++;
            } else {
                $nextPosition = max($nextPosition, $position + 1);
            }

            $secret = false;
            if (isset($colIndex['secret'])) {
                $secret = self::parseBoolean($row[$colIndex['secret']] ?? '0');
            }

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
                    'line' => $lineNumber, 'title' => $title,
                    'description' => $description ? mb_substr($description, 0, 100) . (mb_strlen($description) > 100 ? '...' : '') : null,
                    'position' => $position, 'secret' => $secret,
                ];
            } else {
                $motionId = $motionRepo->generateUuid();
                $motionRepo->create($motionId, $tenantId, $meetingId, null, $title, $description ?? '', $secret, null, null);
                $motionRepo->updatePosition($motionId, $tenantId, $position);
            }
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors, 'preview' => $preview];
    }

    // ========================================================================
    // PRIVATE INSTANCE HELPERS
    // ========================================================================

    /**
     * Builds email and name lookup maps for all members in a tenant.
     *
     * @return array{0: array, 1: array} [$membersByEmail, $membersByName]
     */
    private function buildMemberLookups(string $tenantId): array {
        $allMembers = $this->repos->member()->listByTenant($tenantId);
        $membersByEmail = [];
        $membersByName = [];
        foreach ($allMembers as $m) {
            if (!empty($m['email'])) {
                $membersByEmail[strtolower($m['email'])] = $m;
            }
            $membersByName[mb_strtolower($m['full_name'])] = $m;
        }
        return [$membersByEmail, $membersByName];
    }

    /**
     * Creates a callable that finds a member by name/email fields in a proxy row.
     */
    private function buildProxyMemberFinder(array $colIndex, array $membersByEmail, array $membersByName): callable {
        return function (array $row, string $nameField, string $emailField) use ($colIndex, $membersByEmail, $membersByName): ?array {
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
    }
}
