<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\RepositoryFactory;
use finfo;

/**
 * ImportService - Thin facade for file imports (CSV, XLSX).
 *
 * Delegates file reading to CsvImporter/XlsxImporter and process methods
 * to the appropriate importer. Retains shared utilities (column maps,
 * value parsers, validation) used by both importers and the controller.
 */
final class ImportService {

    private ?RepositoryFactory $repos;
    private ?CsvImporter $csvImporter = null;
    private ?XlsxImporter $xlsxImporter = null;

    public function __construct(?RepositoryFactory $repos = null) {
        $this->repos = $repos ?? RepositoryFactory::getInstance();
    }

    private function csv(): CsvImporter {
        return $this->csvImporter ??= new CsvImporter($this->repos);
    }

    private function xlsx(): XlsxImporter {
        return $this->xlsxImporter ??= new XlsxImporter($this->repos);
    }

    // ========================================================================
    // CONSTANTS
    // ========================================================================

    /** Maximum file size (5 MB) */
    public const MAX_FILE_SIZE = 5 * 1024 * 1024;

    /** Allowed MIME types for CSV (delegated from CsvImporter) */
    public const CSV_MIME_TYPES = CsvImporter::CSV_MIME_TYPES;

    /** Allowed MIME types for XLSX (delegated from XlsxImporter) */
    public const XLSX_MIME_TYPES = XlsxImporter::XLSX_MIME_TYPES;

    // ========================================================================
    // FILE VALIDATION
    // ========================================================================

    /**
     * Validates an uploaded file.
     *
     * @param array $file $_FILES entry
     * @param string $expectedExtension 'csv' or 'xlsx'
     * @return array ['ok' => bool, 'error' => ?string]
     */
    public static function validateUploadedFile(array $file, string $expectedExtension): array {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Fichier manquant ou erreur upload.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== $expectedExtension) {
            return ['ok' => false, 'error' => "Extension attendue: .{$expectedExtension}, reçue: .{$ext}"];
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['ok' => false, 'error' => 'Fichier trop volumineux (max 5 MB).'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        $allowedMimes = $expectedExtension === 'xlsx' ? self::XLSX_MIME_TYPES : self::CSV_MIME_TYPES;
        if (!in_array($mime, $allowedMimes, true)) {
            return ['ok' => false, 'error' => "Type MIME non autorisé: {$mime}"];
        }

        return ['ok' => true, 'error' => null];
    }

    // ========================================================================
    // FILE READING DELEGATION
    // ========================================================================

    /** @return array ['headers' => array, 'rows' => array, 'separator' => string, 'error' => ?string] */
    public static function readCsvFile(string $filePath): array {
        return CsvImporter::readFile($filePath);
    }

    /** @return array ['headers' => array, 'rows' => array, 'error' => ?string] */
    public static function readXlsxFile(string $filePath, int $sheetIndex = 0): array {
        return XlsxImporter::readFile($filePath, $sheetIndex);
    }

    // ========================================================================
    // COLUMN MAPS
    // ========================================================================

    /**
     * Gets column indices from headers based on column map.
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

    public static function getAttendancesColumnMap(): array {
        return [
            'name' => ['name', 'nom', 'full_name', 'nom_complet', 'membre'],
            'email' => ['email', 'mail', 'e-mail'],
            'mode' => ['mode', 'statut', 'status', 'presence', 'présence', 'etat', 'état'],
            'notes' => ['notes', 'commentaire', 'comment', 'remarque'],
        ];
    }

    public static function getMotionsColumnMap(): array {
        return [
            'title' => ['title', 'titre', 'intitule', 'intitulé', 'resolution', 'résolution'],
            'description' => ['description', 'texte', 'content', 'contenu', 'detail', 'détail'],
            'position' => ['position', 'ordre', 'order', 'rang', 'index', 'n°', 'numero', 'numéro'],
            'secret' => ['secret', 'vote_secret', 'secret_vote', 'scrutin_secret'],
        ];
    }

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

    public static function parseBoolean(string $val): bool {
        $val = strtolower(trim($val));
        return in_array($val, ['1', 'true', 'oui', 'yes', 'actif', 'active', 'o', 'y'], true);
    }

    public static function parseVotingPower(string $val): float {
        $val = str_replace(',', '.', trim($val));
        $power = (float) $val;
        return $power > 0 ? $power : 1.0;
    }

    // ========================================================================
    // DUPLICATE EMAIL CHECK
    // ========================================================================

    /**
     * Pre-scan rows for duplicate email addresses.
     *
     * @param array[] $rows     CSV/XLSX rows as indexed arrays
     * @param array   $colIndex Column index map
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
    // PROCESS METHOD DELEGATION
    // ========================================================================

    /** @return array{imported: int, skipped: int, errors: array} */
    public function processMemberImport(array $rows, array $colIndex, bool $hasName, bool $hasFirstLast, string $tenantId): array {
        return $this->csv()->processMemberImport($rows, $colIndex, $hasName, $hasFirstLast, $tenantId);
    }

    /** @return array{imported: int, skipped: int, errors: array, preview: array} */
    public function processAttendanceImport(array $rows, array $colIndex, string $tenantId, string $meetingId, bool $dryRun = false): array {
        return $this->csv()->processAttendanceImport($rows, $colIndex, $tenantId, $meetingId, $dryRun);
    }

    /** @return array{imported: int, skipped: int, errors: array, preview: array} */
    public function processProxyImport(array $rows, array $colIndex, string $tenantId, string $meetingId, bool $dryRun, int $maxPerReceiver, array &$proxiesPerReceiver, array &$existingGivers): array {
        return $this->xlsx()->processProxyImport($rows, $colIndex, $tenantId, $meetingId, $dryRun, $maxPerReceiver, $proxiesPerReceiver, $existingGivers);
    }

    /** @return array{imported: int, skipped: int, errors: array, preview: array} */
    public function processMotionImport(array $rows, array $colIndex, string $tenantId, string $meetingId, bool $dryRun = false, int &$nextPosition = 1): array {
        return $this->xlsx()->processMotionImport($rows, $colIndex, $tenantId, $meetingId, $dryRun, $nextPosition);
    }
}
