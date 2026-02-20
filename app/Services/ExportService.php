<?php
declare(strict_types=1);

namespace AgVote\Service;

/**
 * ExportService - Centralized service for exports
 *
 * Handles data formatting for non-technical users:
 * - French labels
 * - French date format
 * - Value translation (modes, decisions, etc.)
 * - No technical identifiers (UUIDs)
 */
final class ExportService
{
    // ========================================================================
    // VALUE TRANSLATIONS
    // ========================================================================

    /** Attendance modes */
    public const ATTENDANCE_MODES = [
        'present' => 'Présent',
        'remote' => 'À distance',
        'proxy' => 'Représenté',
        'excused' => 'Excusé',
        'absent' => 'Absent',
        '' => 'Non renseigné',
    ];

    /** Vote decisions */
    public const DECISIONS = [
        'adopted' => 'Adoptée',
        'rejected' => 'Rejetée',
        'pending' => 'En attente',
        'cancelled' => 'Annulée',
        '' => 'Non décidée',
    ];

    /** Vote choices */
    public const VOTE_CHOICES = [
        'for' => 'Pour',
        'against' => 'Contre',
        'abstain' => 'Abstention',
        'nsp' => 'Ne se prononce pas',
        'blank' => 'Blanc',
        '' => 'Non exprimé',
    ];

    /** Meeting statuses */
    public const MEETING_STATUSES = [
        'draft' => 'Brouillon',
        'scheduled' => 'Programmée',
        'frozen' => 'Figée',
        'live' => 'En cours',
        'closed' => 'Clôturée',
        'validated' => 'Validée',
        'archived' => 'Archivée',
    ];

    /** Vote sources */
    public const VOTE_SOURCES = [
        'electronic' => 'Électronique',
        'manual' => 'Manuel',
        'paper' => 'Papier',
        'degraded' => 'Mode dégradé',
        '' => 'Non spécifié',
    ];

    /** Booleans */
    public const BOOLEANS = [
        true => 'Oui',
        false => 'Non',
        '1' => 'Oui',
        '0' => 'Non',
        't' => 'Oui',
        'f' => 'Non',
    ];

    // ========================================================================
    // VALUE FORMATTING
    // ========================================================================

    /**
     * Translates an attendance mode
     */
    public function translateAttendanceMode(?string $mode): string
    {
        $mode = strtolower(trim((string)$mode));
        return self::ATTENDANCE_MODES[$mode] ?? $mode;
    }

    /**
     * Translates a decision
     */
    public function translateDecision(?string $decision): string
    {
        $decision = strtolower(trim((string)$decision));
        return self::DECISIONS[$decision] ?? $decision;
    }

    /**
     * Translates a vote choice
     */
    public function translateVoteChoice(?string $choice): string
    {
        $choice = strtolower(trim((string)$choice));
        return self::VOTE_CHOICES[$choice] ?? $choice;
    }

    /**
     * Translates a meeting status
     */
    public function translateMeetingStatus(?string $status): string
    {
        $status = strtolower(trim((string)$status));
        return self::MEETING_STATUSES[$status] ?? $status;
    }

    /**
     * Translates a vote source
     */
    public function translateVoteSource(?string $source): string
    {
        $source = strtolower(trim((string)$source));
        return self::VOTE_SOURCES[$source] ?? $source;
    }

    /**
     * Translates a boolean
     */
    public function translateBoolean($value): string
    {
        if (is_bool($value)) {
            return self::BOOLEANS[$value];
        }
        $strVal = strtolower(trim((string)$value));
        return self::BOOLEANS[$strVal] ?? $strVal;
    }

    /**
     * Formats a date in French format
     *
     * @param string|null $datetime ISO 8601 date or timestamp
     * @param bool $includeTime Include time
     * @return string Formatted date (e.g., "15/01/2024" or "15/01/2024 14:30")
     */
    public function formatDate(?string $datetime, bool $includeTime = true): string
    {
        if ($datetime === null || $datetime === '') {
            return '';
        }

        try {
            $dt = new \DateTime($datetime);
            if ($includeTime) {
                return $dt->format('d/m/Y H:i');
            }
            return $dt->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string)$datetime;
        }
    }

    /**
     * Formats time only
     */
    public function formatTime(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '';
        }

        try {
            $dt = new \DateTime($datetime);
            return $dt->format('H:i');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Formats a number (voting power, weight, etc.)
     * Displays integers without decimals, decimals with up to 4 digits
     */
    public function formatNumber($value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        $num = (float)$value;

        // If integer, no decimals
        if (abs($num - round($num)) < 0.000001) {
            return number_format((int)round($num), 0, ',', ' ');
        }

        // Otherwise, display with decimals and remove trailing zeros
        $formatted = number_format($num, $decimals, ',', ' ');
        return rtrim(rtrim($formatted, '0'), ',');
    }

    /**
     * Formats a percentage
     */
    public function formatPercent($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return $this->formatNumber((float)$value * 100, 1) . ' %';
    }

    // ========================================================================
    // EXPORT CSV
    // ========================================================================

    /**
     * Sanitizes a filename for Content-Disposition header
     */
    private function safeFilename(string $filename): string
    {
        return str_replace(['"', "\r", "\n", "\0", '\\'], '', $filename);
    }

    public function initCsvOutput(string $filename): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->safeFilename($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-cache, no-store, must-revalidate');
    }

    /**
     * Creates a CSV output handle with UTF-8 BOM
     *
     * @return resource
     */
    public function openCsvOutput()
    {
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM pour Excel
        return $out;
    }

    /**
     * Writes a CSV row
     *
     * @param resource $handle
     * @param array $row
     * @param string $separator
     */
    public function writeCsvRow($handle, array $row, string $separator = ';'): void
    {
        fputcsv($handle, $row, $separator);
    }

    /**
     * Generates a filename for export
     */
    public function generateFilename(string $type, string $meetingTitle = '', string $extension = 'csv'): string
    {
        $prefix = match($type) {
            'attendance', 'presences' => 'Emargement',
            'votes', 'ballots' => 'Votes',
            'members', 'membres' => 'Membres',
            'motions', 'resolutions' => 'Resolutions',
            'results', 'resultats' => 'Resultats',
            'audit' => 'Journal_audit',
            'proxies', 'procurations' => 'Procurations',
            'full', 'complet' => 'Export_complet',
            default => $type,
        };

        // Clean title for filename
        if ($meetingTitle !== '') {
            $cleanTitle = $this->sanitizeFilename($meetingTitle);
            $prefix .= '_' . $cleanTitle;
        }

        $date = date('Y-m-d');
        return "{$prefix}_{$date}.{$extension}";
    }

    /**
     * Sanitizes a string for use in a filename
     */
    public function sanitizeFilename(string $str): string
    {
        // Replace accents
        $str = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $str);
        // Keep only letters, numbers, hyphens
        $str = preg_replace('/[^a-z0-9\-_]/i', '_', $str);
        // Avoid multiple underscores
        $str = preg_replace('/_+/', '_', $str);
        // Limit length
        return substr(trim($str, '_'), 0, 50);
    }

    // ========================================================================
    // HELPERS FOR SPECIFIC EXPORTS
    // ========================================================================

    /**
     * Prepares an export row for attendance
     */
    public function formatAttendanceRow(array $row): array
    {
        return [
            (string)($row['full_name'] ?? ''),
            $this->formatNumber($row['voting_power'] ?? 1),
            $this->translateAttendanceMode($row['attendance_mode'] ?? $row['mode'] ?? ''),
            $this->formatDate($row['checked_in_at'] ?? null),
            $this->formatDate($row['checked_out_at'] ?? null),
            (string)($row['proxy_to_name'] ?? ''),
            $this->formatNumber($row['proxies_received'] ?? 0, 0),
        ];
    }

    /**
     * Prepares an export row for votes
     */
    public function formatVoteRow(array $row): array
    {
        return [
            (string)($row['motion_title'] ?? ''),
            (int)($row['motion_position'] ?? $row['position'] ?? 0),
            (string)($row['voter_name'] ?? ''),
            $this->translateVoteChoice($row['value'] ?? ''),
            $this->formatNumber($row['weight'] ?? 1),
            $this->translateBoolean($row['is_proxy_vote'] ?? false),
            (string)($row['proxy_source_name'] ?? ''),
            $this->formatDate($row['cast_at'] ?? null),
            $this->translateVoteSource($row['source'] ?? ''),
        ];
    }

    /**
     * Prepares an export row for members
     */
    public function formatMemberRow(array $row): array
    {
        return [
            (string)($row['full_name'] ?? ''),
            (string)($row['email'] ?? ''),
            $this->formatNumber($row['voting_power'] ?? 1),
            $this->translateBoolean($row['is_active'] ?? true),
            $this->translateAttendanceMode($row['attendance_mode'] ?? ''),
            $this->formatDate($row['checked_in_at'] ?? null),
            $this->formatDate($row['checked_out_at'] ?? null),
            (string)($row['proxy_to_name'] ?? ''),
        ];
    }

    /**
     * Prepares an export row for motions/resolutions
     */
    public function formatMotionResultRow(array $row): array
    {
        return [
            (int)($row['position'] ?? 0),
            (string)($row['title'] ?? ''),
            $this->formatDate($row['opened_at'] ?? null),
            $this->formatDate($row['closed_at'] ?? null),
            $this->formatNumber($row['w_for'] ?? $row['official_for'] ?? 0),
            $this->formatNumber($row['w_against'] ?? $row['official_against'] ?? 0),
            $this->formatNumber($row['w_abstain'] ?? $row['official_abstain'] ?? 0),
            $this->formatNumber($row['w_nsp'] ?? 0),
            $this->formatNumber($row['w_total'] ?? $row['official_total'] ?? 0),
            (int)($row['ballots_count'] ?? 0),
            $this->translateDecision($row['decision'] ?? ''),
            (string)($row['decision_reason'] ?? ''),
        ];
    }

    /**
     * Prepares an export row for proxies
     */
    public function formatProxyRow(array $row): array
    {
        return [
            (string)($row['grantor_name'] ?? ''),
            (string)($row['grantee_name'] ?? ''),
            $this->formatNumber($row['grantor_voting_power'] ?? 1),
            $this->formatDate($row['created_at'] ?? null),
            $this->translateBoolean($row['is_active'] ?? true),
        ];
    }

    // ========================================================================
    // CSV HEADERS FOR EACH EXPORT TYPE
    // ========================================================================

    public function getAttendanceHeaders(): array
    {
        return [
            'Nom',
            'Pouvoir de vote',
            'Mode de présence',
            'Arrivée',
            'Départ',
            'Représenté par',
            'Procurations détenues',
        ];
    }

    public function getVotesHeaders(): array
    {
        return [
            'Résolution',
            'N°',
            'Votant',
            'Vote',
            'Poids',
            'Par procuration',
            'Au nom de',
            'Date/Heure',
            'Mode',
        ];
    }

    public function getMembersHeaders(): array
    {
        return [
            'Nom',
            'Email',
            'Pouvoir de vote',
            'Actif',
            'Mode de présence',
            'Arrivée',
            'Départ',
            'Représenté par',
        ];
    }

    public function getMotionResultsHeaders(): array
    {
        return [
            'N°',
            'Résolution',
            'Ouverture',
            'Clôture',
            'Pour',
            'Contre',
            'Abstention',
            'NSPP',
            'Total exprimés',
            'Nb votants',
            'Décision',
            'Motif',
        ];
    }

    public function getProxiesHeaders(): array
    {
        return [
            'Mandant',
            'Mandataire',
            'Pouvoir de vote',
            'Date',
            'Active',
        ];
    }

    public function getAuditHeaders(): array
    {
        return [
            'Ballot ID',
            'Motion ID',
            'Résolution',
            'Member ID',
            'Votant',
            'Mode présence',
            'Choix',
            'Poids',
            'Proxy vote',
            'Proxy source member_id',
            'Horodatage vote',
            'Source vote',
            'Token ID',
            'Token hash (prefix)',
            'Token expires_at',
            'Token used_at',
            'Justification (manual)',
        ];
    }

    /**
     * Prepares an export row for ballot audit
     */
    public function formatAuditRow(array $r): array
    {
        return [
            (string)($r['ballot_id'] ?? ''),
            (string)($r['motion_id'] ?? ''),
            (string)($r['motion_title'] ?? ''),
            (string)($r['member_id'] ?? ''),
            (string)($r['voter_name'] ?? ''),
            $this->translateAttendanceMode($r['attendance_mode'] ?? ''),
            $this->translateVoteChoice($r['value'] ?? ''),
            (string)($r['weight'] ?? ''),
            $this->translateBoolean($r['is_proxy_vote'] ?? false),
            (string)($r['proxy_source_member_id'] ?? ''),
            $this->formatDate($r['cast_at'] ?? null),
            $this->translateVoteSource($r['source'] ?? ''),
            (string)($r['token_id'] ?? ''),
            (string)($r['token_hash_prefix'] ?? ''),
            $this->formatDate($r['token_expires_at'] ?? null),
            $this->formatDate($r['token_used_at'] ?? null),
            (string)($r['manual_justification'] ?? ''),
        ];
    }

    // ========================================================================
    // EXPORT XLSX (PhpSpreadsheet)
    // ========================================================================

    /**
     * Creates an Excel workbook with a data sheet
     *
     * @param array $headers Column headers
     * @param array $rows Data (array of arrays)
     * @param string $sheetTitle Sheet title
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    public function createSpreadsheet(array $headers, array $rows, string $sheetTitle = 'Données'): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31)); // Excel limits to 31 characters

        // Headers
        $colIndex = 1;
        foreach ($headers as $header) {
            $cell = $sheet->getCellByColumnAndRow($colIndex, 1);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
            $cell->getStyle()->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E0E0E0');
            $colIndex++;
        }

        // Data
        $rowIndex = 2;
        foreach ($rows as $row) {
            $colIndex = 1;
            foreach ($row as $value) {
                $sheet->getCellByColumnAndRow($colIndex, $rowIndex)->setValue($value);
                $colIndex++;
            }
            $rowIndex++;
        }

        // Auto-dimensionner les colonnes
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Freeze first row
        $sheet->freezePane('A2');

        return $spreadsheet;
    }

    /**
     * Adds a sheet to an existing workbook
     */
    public function addSheet(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet,
        array $headers,
        array $rows,
        string $sheetTitle
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));

        // Headers
        $colIndex = 1;
        foreach ($headers as $header) {
            $cell = $sheet->getCellByColumnAndRow($colIndex, 1);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
            $cell->getStyle()->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E0E0E0');
            $colIndex++;
        }

        // Data
        $rowIndex = 2;
        foreach ($rows as $row) {
            $colIndex = 1;
            foreach ($row as $value) {
                $sheet->getCellByColumnAndRow($colIndex, $rowIndex)->setValue($value);
                $colIndex++;
            }
            $rowIndex++;
        }

        // Auto-size columns
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Freeze first row
        $sheet->freezePane('A2');
    }

    /**
     * Initializes HTTP headers for XLSX export
     */
    public function initXlsxOutput(string $filename): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $this->safeFilename($filename) . '"');
        header('Cache-Control: max-age=0');
        header('X-Content-Type-Options: nosniff');
    }

    /**
     * Sends an Excel workbook to the browser
     */
    public function outputSpreadsheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
    {
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
    }

    /**
     * Creates a complete multi-sheet export for a meeting
     *
     * @param array $meeting Meeting data
     * @param array $attendanceRows Attendance data
     * @param array $motionRows Motion results
     * @param array $voteRows Individual votes (optional)
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    public function createFullExportSpreadsheet(
        array $meeting,
        array $attendanceRows,
        array $motionRows,
        array $voteRows = []
    ): \PhpOffice\PhpSpreadsheet\Spreadsheet {
        // Summary sheet
        $summaryHeaders = ['Information', 'Valeur'];
        $summaryData = [
            ['Séance', $meeting['title'] ?? ''],
            ['Date', $this->formatDate($meeting['scheduled_at'] ?? null, false)],
            ['Statut', $this->translateMeetingStatus($meeting['status'] ?? '')],
            ['Validée le', $this->formatDate($meeting['validated_at'] ?? null)],
            ['Président', $meeting['president_name'] ?? ''],
            ['', ''],
            ['Membres présents', count(array_filter($attendanceRows, fn($r) => ($r['attendance_mode'] ?? '') === 'present'))],
            ['Membres à distance', count(array_filter($attendanceRows, fn($r) => ($r['attendance_mode'] ?? '') === 'remote'))],
            ['Membres représentés', count(array_filter($attendanceRows, fn($r) => ($r['attendance_mode'] ?? '') === 'proxy'))],
            ['', ''],
            ['Résolutions', count($motionRows)],
            ['Résolutions adoptées', count(array_filter($motionRows, fn($r) => ($r['decision'] ?? '') === 'adopted'))],
            ['Résolutions rejetées', count(array_filter($motionRows, fn($r) => ($r['decision'] ?? '') === 'rejected'))],
        ];

        $spreadsheet = $this->createSpreadsheet($summaryHeaders, $summaryData, 'Résumé');

        // Attendance sheet
        $attendanceFormatted = array_map([$this, 'formatAttendanceRow'], $attendanceRows);
        $this->addSheet($spreadsheet, $this->getAttendanceHeaders(), $attendanceFormatted, 'Émargement');

        // Motions sheet
        $motionsFormatted = array_map([$this, 'formatMotionResultRow'], $motionRows);
        $this->addSheet($spreadsheet, $this->getMotionResultsHeaders(), $motionsFormatted, 'Résolutions');

        // Votes sheet (if provided)
        if (!empty($voteRows)) {
            $votesFormatted = [];
            foreach ($voteRows as $r) {
                if (!empty($r['voter_name'])) {
                    $votesFormatted[] = $this->formatVoteRow($r);
                }
            }
            if (!empty($votesFormatted)) {
                $this->addSheet($spreadsheet, $this->getVotesHeaders(), $votesFormatted, 'Votes');
            }
        }

        // Return to first sheet
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
