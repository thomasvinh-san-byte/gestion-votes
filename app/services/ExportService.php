<?php
declare(strict_types=1);

namespace AgVote\Service;

/**
 * ExportService - Service centralisé pour les exports
 *
 * Gère le formatage des données pour les utilisateurs non-techniques :
 * - Labels en français
 * - Dates au format français
 * - Traduction des valeurs (modes, décisions, etc.)
 * - Pas d'identifiants techniques (UUIDs)
 */
final class ExportService
{
    // ========================================================================
    // TRADUCTIONS DES VALEURS
    // ========================================================================

    /** Modes de présence */
    public const ATTENDANCE_MODES = [
        'present' => 'Présent',
        'remote' => 'À distance',
        'proxy' => 'Représenté',
        'excused' => 'Excusé',
        'absent' => 'Absent',
        '' => 'Non renseigné',
    ];

    /** Décisions de vote */
    public const DECISIONS = [
        'adopted' => 'Adoptée',
        'rejected' => 'Rejetée',
        'pending' => 'En attente',
        'cancelled' => 'Annulée',
        '' => 'Non décidée',
    ];

    /** Choix de vote */
    public const VOTE_CHOICES = [
        'for' => 'Pour',
        'against' => 'Contre',
        'abstain' => 'Abstention',
        'nsp' => 'Ne se prononce pas',
        'blank' => 'Blanc',
        '' => 'Non exprimé',
    ];

    /** Statuts de séance */
    public const MEETING_STATUSES = [
        'draft' => 'Brouillon',
        'scheduled' => 'Programmée',
        'frozen' => 'Figée',
        'live' => 'En cours',
        'closed' => 'Clôturée',
        'validated' => 'Validée',
        'archived' => 'Archivée',
    ];

    /** Sources de vote */
    public const VOTE_SOURCES = [
        'electronic' => 'Électronique',
        'manual' => 'Manuel',
        'paper' => 'Papier',
        'degraded' => 'Mode dégradé',
        '' => 'Non spécifié',
    ];

    /** Booléens */
    public const BOOLEANS = [
        true => 'Oui',
        false => 'Non',
        '1' => 'Oui',
        '0' => 'Non',
        't' => 'Oui',
        'f' => 'Non',
    ];

    // ========================================================================
    // FORMATAGE DES VALEURS
    // ========================================================================

    /**
     * Traduit un mode de présence
     */
    public static function translateAttendanceMode(?string $mode): string
    {
        $mode = strtolower(trim((string)$mode));
        return self::ATTENDANCE_MODES[$mode] ?? $mode;
    }

    /**
     * Traduit une décision
     */
    public static function translateDecision(?string $decision): string
    {
        $decision = strtolower(trim((string)$decision));
        return self::DECISIONS[$decision] ?? $decision;
    }

    /**
     * Traduit un choix de vote
     */
    public static function translateVoteChoice(?string $choice): string
    {
        $choice = strtolower(trim((string)$choice));
        return self::VOTE_CHOICES[$choice] ?? $choice;
    }

    /**
     * Traduit un statut de séance
     */
    public static function translateMeetingStatus(?string $status): string
    {
        $status = strtolower(trim((string)$status));
        return self::MEETING_STATUSES[$status] ?? $status;
    }

    /**
     * Traduit une source de vote
     */
    public static function translateVoteSource(?string $source): string
    {
        $source = strtolower(trim((string)$source));
        return self::VOTE_SOURCES[$source] ?? $source;
    }

    /**
     * Traduit un booléen
     */
    public static function translateBoolean($value): string
    {
        if (is_bool($value)) {
            return self::BOOLEANS[$value];
        }
        $strVal = strtolower(trim((string)$value));
        return self::BOOLEANS[$strVal] ?? $strVal;
    }

    /**
     * Formate une date au format français
     *
     * @param string|null $datetime Date ISO 8601 ou timestamp
     * @param bool $includeTime Inclure l'heure
     * @return string Date formatée (ex: "15/01/2024" ou "15/01/2024 14:30")
     */
    public static function formatDate(?string $datetime, bool $includeTime = true): string
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
     * Formate une heure uniquement
     */
    public static function formatTime(?string $datetime): string
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
     * Formate un nombre (pouvoir de vote, poids, etc.)
     * Affiche les entiers sans décimales, les décimaux avec jusqu'à 4 chiffres
     */
    public static function formatNumber($value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        $num = (float)$value;

        // Si c'est un entier, pas de décimales
        if (abs($num - round($num)) < 0.000001) {
            return number_format((int)round($num), 0, ',', ' ');
        }

        // Sinon, afficher avec décimales et supprimer les zéros trailing
        $formatted = number_format($num, $decimals, ',', ' ');
        return rtrim(rtrim($formatted, '0'), ',');
    }

    /**
     * Formate un pourcentage
     */
    public static function formatPercent($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return self::formatNumber((float)$value * 100, 1) . ' %';
    }

    // ========================================================================
    // EXPORT CSV
    // ========================================================================

    /**
     * Initialise un export CSV avec BOM UTF-8 pour Excel
     */
    public static function initCsvOutput(string $filename): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-cache, no-store, must-revalidate');
    }

    /**
     * Crée un handle de sortie CSV avec BOM UTF-8
     *
     * @return resource
     */
    public static function openCsvOutput()
    {
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM pour Excel
        return $out;
    }

    /**
     * Écrit une ligne CSV
     *
     * @param resource $handle
     * @param array $row
     * @param string $separator
     */
    public static function writeCsvRow($handle, array $row, string $separator = ';'): void
    {
        fputcsv($handle, $row, $separator);
    }

    /**
     * Génère un nom de fichier pour l'export
     */
    public static function generateFilename(string $type, string $meetingTitle = '', string $extension = 'csv'): string
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

        // Nettoyer le titre pour le nom de fichier
        if ($meetingTitle !== '') {
            $cleanTitle = self::sanitizeFilename($meetingTitle);
            $prefix .= '_' . $cleanTitle;
        }

        $date = date('Y-m-d');
        return "{$prefix}_{$date}.{$extension}";
    }

    /**
     * Nettoie une chaîne pour l'utiliser dans un nom de fichier
     */
    public static function sanitizeFilename(string $str): string
    {
        // Remplacer les accents
        $str = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $str);
        // Garder uniquement lettres, chiffres, tirets
        $str = preg_replace('/[^a-z0-9\-_]/i', '_', $str);
        // Éviter les underscores multiples
        $str = preg_replace('/_+/', '_', $str);
        // Limiter la longueur
        return substr(trim($str, '_'), 0, 50);
    }

    // ========================================================================
    // HELPERS POUR LES EXPORTS SPÉCIFIQUES
    // ========================================================================

    /**
     * Prépare une ligne d'export pour les présences
     */
    public static function formatAttendanceRow(array $row): array
    {
        return [
            (string)($row['full_name'] ?? ''),
            self::formatNumber($row['voting_power'] ?? 1),
            self::translateAttendanceMode($row['attendance_mode'] ?? $row['mode'] ?? ''),
            self::formatDate($row['checked_in_at'] ?? null),
            self::formatDate($row['checked_out_at'] ?? null),
            (string)($row['proxy_to_name'] ?? ''),
            self::formatNumber($row['proxies_received'] ?? 0, 0),
        ];
    }

    /**
     * Prépare une ligne d'export pour les votes
     */
    public static function formatVoteRow(array $row): array
    {
        return [
            (string)($row['motion_title'] ?? ''),
            (int)($row['motion_position'] ?? $row['position'] ?? 0),
            (string)($row['voter_name'] ?? ''),
            self::translateVoteChoice($row['value'] ?? ''),
            self::formatNumber($row['weight'] ?? 1),
            self::translateBoolean($row['is_proxy_vote'] ?? false),
            (string)($row['proxy_source_name'] ?? ''),
            self::formatDate($row['cast_at'] ?? null),
            self::translateVoteSource($row['source'] ?? ''),
        ];
    }

    /**
     * Prépare une ligne d'export pour les membres
     */
    public static function formatMemberRow(array $row): array
    {
        return [
            (string)($row['full_name'] ?? ''),
            (string)($row['email'] ?? ''),
            self::formatNumber($row['voting_power'] ?? 1),
            self::translateBoolean($row['is_active'] ?? true),
            self::translateAttendanceMode($row['attendance_mode'] ?? ''),
            self::formatDate($row['checked_in_at'] ?? null),
            self::formatDate($row['checked_out_at'] ?? null),
            (string)($row['proxy_to_name'] ?? ''),
        ];
    }

    /**
     * Prépare une ligne d'export pour les résolutions
     */
    public static function formatMotionResultRow(array $row): array
    {
        return [
            (int)($row['position'] ?? 0),
            (string)($row['title'] ?? ''),
            self::formatDate($row['opened_at'] ?? null),
            self::formatDate($row['closed_at'] ?? null),
            self::formatNumber($row['w_for'] ?? $row['official_for'] ?? 0),
            self::formatNumber($row['w_against'] ?? $row['official_against'] ?? 0),
            self::formatNumber($row['w_abstain'] ?? $row['official_abstain'] ?? 0),
            self::formatNumber($row['w_nsp'] ?? 0),
            self::formatNumber($row['w_total'] ?? $row['official_total'] ?? 0),
            (int)($row['ballots_count'] ?? 0),
            self::translateDecision($row['decision'] ?? ''),
            (string)($row['decision_reason'] ?? ''),
        ];
    }

    /**
     * Prépare une ligne d'export pour les procurations
     */
    public static function formatProxyRow(array $row): array
    {
        return [
            (string)($row['grantor_name'] ?? ''),
            (string)($row['grantee_name'] ?? ''),
            self::formatNumber($row['grantor_voting_power'] ?? 1),
            self::formatDate($row['created_at'] ?? null),
            self::translateBoolean($row['is_active'] ?? true),
        ];
    }

    // ========================================================================
    // EN-TÊTES CSV POUR CHAQUE TYPE D'EXPORT
    // ========================================================================

    public static function getAttendanceHeaders(): array
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

    public static function getVotesHeaders(): array
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

    public static function getMembersHeaders(): array
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

    public static function getMotionResultsHeaders(): array
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

    public static function getProxiesHeaders(): array
    {
        return [
            'Mandant',
            'Mandataire',
            'Pouvoir de vote',
            'Date',
            'Active',
        ];
    }

    // ========================================================================
    // EXPORT XLSX (PhpSpreadsheet)
    // ========================================================================

    /**
     * Crée un classeur Excel avec une feuille de données
     *
     * @param array $headers En-têtes des colonnes
     * @param array $rows Données (tableau de tableaux)
     * @param string $sheetTitle Titre de la feuille
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    public static function createSpreadsheet(array $headers, array $rows, string $sheetTitle = 'Données'): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31)); // Excel limite à 31 caractères

        // En-têtes
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

        // Données
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

        // Figer la première ligne
        $sheet->freezePane('A2');

        return $spreadsheet;
    }

    /**
     * Ajoute une feuille à un classeur existant
     */
    public static function addSheet(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet,
        array $headers,
        array $rows,
        string $sheetTitle
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));

        // En-têtes
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

        // Données
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

        // Figer la première ligne
        $sheet->freezePane('A2');
    }

    /**
     * Initialise les headers HTTP pour un export XLSX
     */
    public static function initXlsxOutput(string $filename): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('X-Content-Type-Options: nosniff');
    }

    /**
     * Envoie un classeur Excel au navigateur
     */
    public static function outputSpreadsheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
    {
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
    }

    /**
     * Crée un export complet multi-feuilles pour une séance
     *
     * @param array $meeting Données de la séance
     * @param array $attendanceRows Données de présence
     * @param array $motionRows Résultats des résolutions
     * @param array $voteRows Votes individuels (optionnel)
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    public static function createFullExportSpreadsheet(
        array $meeting,
        array $attendanceRows,
        array $motionRows,
        array $voteRows = []
    ): \PhpOffice\PhpSpreadsheet\Spreadsheet {
        // Feuille résumé
        $summaryHeaders = ['Information', 'Valeur'];
        $summaryData = [
            ['Séance', $meeting['title'] ?? ''],
            ['Date', self::formatDate($meeting['scheduled_at'] ?? null, false)],
            ['Statut', self::translateMeetingStatus($meeting['status'] ?? '')],
            ['Validée le', self::formatDate($meeting['validated_at'] ?? null)],
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

        $spreadsheet = self::createSpreadsheet($summaryHeaders, $summaryData, 'Résumé');

        // Feuille présences
        $attendanceFormatted = array_map([self::class, 'formatAttendanceRow'], $attendanceRows);
        self::addSheet($spreadsheet, self::getAttendanceHeaders(), $attendanceFormatted, 'Émargement');

        // Feuille résolutions
        $motionsFormatted = array_map([self::class, 'formatMotionResultRow'], $motionRows);
        self::addSheet($spreadsheet, self::getMotionResultsHeaders(), $motionsFormatted, 'Résolutions');

        // Feuille votes (si fournie)
        if (!empty($voteRows)) {
            $votesFormatted = [];
            foreach ($voteRows as $r) {
                if (!empty($r['voter_name'])) {
                    $votesFormatted[] = self::formatVoteRow($r);
                }
            }
            if (!empty($votesFormatted)) {
                self::addSheet($spreadsheet, self::getVotesHeaders(), $votesFormatted, 'Votes');
            }
        }

        // Revenir à la première feuille
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
