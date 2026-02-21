<?php

declare(strict_types=1);

namespace AgVote\Service;

use finfo;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * ImportService - Centralized service for file imports (CSV, XLSX)
 *
 * Handles file reading and parsing for import operations.
 */
final class ImportService {
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
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [
                'headers' => [],
                'rows' => [],
                'separator' => ',',
                'error' => 'Impossible d\'ouvrir le fichier.',
            ];
        }

        // Detect separator
        $firstLine = fgets($handle);
        rewind($handle);
        $separator = strpos($firstLine, ';') !== false ? ';' : ',';

        // Read headers
        $headers = fgetcsv($handle, 0, $separator);
        if (!$headers) {
            fclose($handle);
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

        fclose($handle);

        return [
            'headers' => $headers,
            'rows' => $rows,
            'separator' => $separator,
            'error' => null,
        ];
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
}
