<?php

declare(strict_types=1);

namespace AgVote\Service;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * ExportService - Thin I/O facade for CSV and XLSX exports.
 *
 * Value translation, formatting, row formatting, and header definitions
 * are delegated to ValueTranslator. This class handles only I/O output
 * (CSV streaming, XLSX streaming, PhpSpreadsheet workbooks) and filename generation.
 */
final class ExportService {
    private ?ValueTranslator $translator = null;

    private function translator(): ValueTranslator {
        return $this->translator ??= new ValueTranslator();
    }

    // ========================================================================
    // DELEGATION STUBS — preserve public API for callers
    // ========================================================================

    public function translateAttendanceMode(?string $mode): string { return $this->translator()->translateAttendanceMode($mode); }
    public function translateDecision(?string $decision): string { return $this->translator()->translateDecision($decision); }
    public function translateVoteChoice(?string $choice): string { return $this->translator()->translateVoteChoice($choice); }
    public function translateMeetingStatus(?string $status): string { return $this->translator()->translateMeetingStatus($status); }
    public function translateVoteSource(?string $source): string { return $this->translator()->translateVoteSource($source); }
    public function translateBoolean($value): string { return $this->translator()->translateBoolean($value); }
    public function formatDate(?string $datetime, bool $includeTime = true): string { return $this->translator()->formatDate($datetime, $includeTime); }
    public function formatTime(?string $datetime): string { return $this->translator()->formatTime($datetime); }
    public function formatNumber($value, int $decimals = 2): string { return $this->translator()->formatNumber($value, $decimals); }
    public function formatPercent($value): string { return $this->translator()->formatPercent($value); }
    public function formatAttendanceRow(array $row): array { return $this->translator()->formatAttendanceRow($row); }
    public function formatVoteRow(array $row): array { return $this->translator()->formatVoteRow($row); }
    public function formatMemberRow(array $row): array { return $this->translator()->formatMemberRow($row); }
    public function formatMotionResultRow(array $row): array { return $this->translator()->formatMotionResultRow($row); }
    public function formatProxyRow(array $row): array { return $this->translator()->formatProxyRow($row); }
    public function formatAuditRow(array $r): array { return $this->translator()->formatAuditRow($r); }
    public function getAttendanceHeaders(): array { return $this->translator()->getAttendanceHeaders(); }
    public function getVotesHeaders(): array { return $this->translator()->getVotesHeaders(); }
    public function getMembersHeaders(): array { return $this->translator()->getMembersHeaders(); }
    public function getMotionResultsHeaders(): array { return $this->translator()->getMotionResultsHeaders(); }
    public function getProxiesHeaders(): array { return $this->translator()->getProxiesHeaders(); }
    public function getAuditHeaders(): array { return $this->translator()->getAuditHeaders(); }

    // ========================================================================
    // EXPORT CSV
    // ========================================================================

    private function safeFilename(string $filename): string {
        return str_replace(['"', "\r", "\n", "\0", '\\'], '', $filename);
    }

    public function initCsvOutput(string $filename): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->safeFilename($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-cache, no-store, must-revalidate');
    }

    /** @return resource */
    public function openCsvOutput() {
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        return $out;
    }

    /**
     * F15: prevent CSV/XLSX formula injection.
     *
     * Excel, LibreOffice, and Numbers all interpret a leading `=`, `+`, `-`,
     * `@`, tab, or CR as a formula trigger. A user-controlled cell that
     * starts with `=cmd|...` is evaluated by Excel on open, executing
     * arbitrary commands on the admin's machine. We prefix any such cell
     * with `'` (apostrophe) which the spreadsheet parser treats as
     * "literal text", neutralizing the formula.
     */
    private function sanitizeCsvCell(mixed $value): string {
        $str = (string) $value;
        if ($str === '') {
            return $str;
        }
        $first = $str[0];
        if (in_array($first, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $str;
        }
        return $str;
    }

    /** @param resource $handle */
    public function writeCsvRow($handle, array $row, string $separator = ';'): void {
        fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], $row), $separator);
    }

    public function generateFilename(string $type, string $meetingTitle = '', string $extension = 'csv'): string {
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
        if ($meetingTitle !== '') {
            $prefix .= '_' . $this->sanitizeFilename($meetingTitle);
        }
        return "{$prefix}_" . date('Y-m-d') . ".{$extension}";
    }

    public function sanitizeFilename(string $str): string {
        $str = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $str);
        $str = preg_replace('/[^a-z0-9\-_]/i', '_', $str);
        $str = preg_replace('/_+/', '_', $str);
        return substr(trim($str, '_'), 0, 50);
    }

    // ========================================================================
    // EXPORT XLSX STREAMING (OpenSpout)
    // ========================================================================

    public function streamXlsx(string $filename, array $headers, iterable $rows, callable $formatter, string $sheetTitle = 'Donnees'): void {
        while (ob_get_level() > 0) { ob_end_clean(); }
        $this->initXlsxOutput($filename);

        $writer = new Writer();
        $writer->openToFile('php://output');
        $writer->getCurrentSheet()->setName(mb_substr($sheetTitle, 0, 31));
        $writer->addRow(Row::fromValues($headers));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_values($formatter($row))));
        }
        $writer->close();
    }

    public function streamFullXlsx(
        string $filename,
        array $meeting,
        iterable $attendanceRows,
        iterable $motionRows,
        iterable $voteRows,
        bool $includeVotes = true,
    ): void {
        while (ob_get_level() > 0) { ob_end_clean(); }
        $this->initXlsxOutput($filename);

        $writer = new Writer();
        $writer->openToFile('php://output');

        $writer->getCurrentSheet()->setName('Resume');
        $writer->addRow(Row::fromValues(['Information', 'Valeur']));
        $summaryData = [
            ['Seance', $meeting['title'] ?? ''],
            ['Date', $this->formatDate($meeting['scheduled_at'] ?? null, false)],
            ['Statut', $this->translateMeetingStatus($meeting['status'] ?? '')],
            ['Validee le', $this->formatDate($meeting['validated_at'] ?? null)],
        ];
        foreach ($summaryData as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $sheet2 = $writer->addNewSheetAndMakeItCurrent();
        $sheet2->setName('Emargement');
        $writer->addRow(Row::fromValues($this->getAttendanceHeaders()));
        foreach ($attendanceRows as $row) {
            $writer->addRow(Row::fromValues(array_values($this->formatAttendanceRow($row))));
        }

        $sheet3 = $writer->addNewSheetAndMakeItCurrent();
        $sheet3->setName('Resultats');
        $writer->addRow(Row::fromValues($this->getMotionResultsHeaders()));
        foreach ($motionRows as $row) {
            $writer->addRow(Row::fromValues(array_values($this->formatMotionResultRow($row))));
        }

        if ($includeVotes) {
            $sheet4 = $writer->addNewSheetAndMakeItCurrent();
            $sheet4->setName('Votes');
            $writer->addRow(Row::fromValues($this->getVotesHeaders()));
            foreach ($voteRows as $row) {
                if (!empty($row['voter_name'])) {
                    $writer->addRow(Row::fromValues(array_values($this->formatVoteRow($row))));
                }
            }
        }

        $writer->close();
    }

    // ========================================================================
    // EXPORT XLSX STREAMING (OpenSpout, single + multi-sheet)
    // ========================================================================

    public function initXlsxOutput(string $filename): void {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $this->safeFilename($filename) . '"');
        header('Cache-Control: max-age=0');
        header('X-Content-Type-Options: nosniff');
    }

    /**
     * Stream a single-sheet XLSX report directly to php://output.
     *
     * Replaces the legacy createSpreadsheet/outputSpreadsheet pair (which
     * built a full PhpSpreadsheet workbook in memory before serializing).
     */
    public function streamReportXlsx(array $headers, array $rows, string $sheetTitle, string $filename): void {
        $this->initXlsxOutput($filename);

        $writer = new Writer();
        $writer->openToFile('php://output');
        $writer->getCurrentSheet()->setName(mb_substr($sheetTitle, 0, 31));

        $headerStyle = $this->headerStyle();
        $writer->addRow(Row::fromValues(
            array_map(fn ($h) => $this->sanitizeCsvCell($h), $headers),
            $headerStyle,
        ));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(
                array_map(fn ($v) => $this->sanitizeCsvCell($v), array_values($row)),
            ));
        }

        $writer->close();
    }

    /**
     * Stream a multi-sheet XLSX (full meeting export) directly to php://output.
     */
    public function streamFullExportXlsx(array $meeting, array $attendanceRows, array $motionRows, array $voteRows, string $filename): void {
        $this->initXlsxOutput($filename);

        $writer = new Writer();
        $writer->openToFile('php://output');

        $summaryRows = [
            ['Information', 'Valeur'],
            ['Séance', $meeting['title'] ?? ''],
            ['Date', $this->formatDate($meeting['scheduled_at'] ?? null, false)],
            ['Statut', $this->translateMeetingStatus($meeting['status'] ?? '')],
            ['Validée le', $this->formatDate($meeting['validated_at'] ?? null)],
            ['Président', $meeting['president_name'] ?? ''],
            ['', ''],
            ['Membres présents', count(array_filter($attendanceRows, fn ($r) => ($r['attendance_mode'] ?? '') === 'present'))],
            ['Membres à distance', count(array_filter($attendanceRows, fn ($r) => ($r['attendance_mode'] ?? '') === 'remote'))],
            ['Membres représentés', count(array_filter($attendanceRows, fn ($r) => ($r['attendance_mode'] ?? '') === 'proxy'))],
            ['', ''],
            ['Résolutions', count($motionRows)],
            ['Résolutions adoptées', count(array_filter($motionRows, fn ($r) => ($r['decision'] ?? '') === 'adopted'))],
            ['Résolutions rejetées', count(array_filter($motionRows, fn ($r) => ($r['decision'] ?? '') === 'rejected'))],
        ];
        $this->addSheetToWriter($writer, 'Résumé', $summaryRows[0], array_slice($summaryRows, 1), false);

        $this->addSheetToWriter(
            $writer,
            'Émargement',
            $this->getAttendanceHeaders(),
            array_map([$this, 'formatAttendanceRow'], $attendanceRows),
        );

        $this->addSheetToWriter(
            $writer,
            'Résolutions',
            $this->getMotionResultsHeaders(),
            array_map([$this, 'formatMotionResultRow'], $motionRows),
        );

        $votesFormatted = [];
        foreach ($voteRows as $r) {
            if (!empty($r['voter_name'])) {
                $votesFormatted[] = $this->formatVoteRow($r);
            }
        }
        if (!empty($votesFormatted)) {
            $this->addSheetToWriter(
                $writer,
                'Votes',
                $this->getVotesHeaders(),
                $votesFormatted,
            );
        }

        $writer->close();
    }

    private function addSheetToWriter(Writer $writer, string $title, array $headers, array $rows, bool $newSheet = true): void {
        if ($newSheet) {
            $writer->addNewSheetAndMakeItCurrent();
        }
        $writer->getCurrentSheet()->setName(mb_substr($title, 0, 31));

        $headerStyle = $this->headerStyle();
        $writer->addRow(Row::fromValues($headers, $headerStyle));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_values($row)));
        }
    }

    private function headerStyle(): Style {
        return (new Style())
            ->setFontBold()
            ->setBackgroundColor(Color::rgb(0xE0, 0xE0, 0xE0));
    }
}
