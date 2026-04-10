<?php

declare(strict_types=1);

namespace AgVote\Service;

use OpenSpout\Common\Entity\Row;
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

    private function sanitizeCsvCell(mixed $value): string {
        $str = (string) $value;
        if ($str !== '' && in_array($str[0], ['=', '+', '-', '@'], true)) {
            return "\t" . $str;
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
    // EXPORT XLSX (PhpSpreadsheet)
    // ========================================================================

    /** @return \PhpOffice\PhpSpreadsheet\Spreadsheet */
    public function createSpreadsheet(array $headers, array $rows, string $sheetTitle = 'Données'): \PhpOffice\PhpSpreadsheet\Spreadsheet {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));
        $colIndex = 1;
        foreach ($headers as $header) {
            $cell = $sheet->getCellByColumnAndRow($colIndex, 1);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
            $cell->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E0E0E0');
            $colIndex++;
        }
        $rowIndex = 2;
        foreach ($rows as $row) {
            $colIndex = 1;
            foreach ($row as $value) {
                $sheet->getCellByColumnAndRow($colIndex, $rowIndex)->setValue($value);
                $colIndex++;
            }
            $rowIndex++;
        }
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');
        return $spreadsheet;
    }

    public function addSheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, array $headers, array $rows, string $sheetTitle): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));
        $colIndex = 1;
        foreach ($headers as $header) {
            $cell = $sheet->getCellByColumnAndRow($colIndex, 1);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
            $cell->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E0E0E0');
            $colIndex++;
        }
        $rowIndex = 2;
        foreach ($rows as $row) {
            $colIndex = 1;
            foreach ($row as $value) {
                $sheet->getCellByColumnAndRow($colIndex, $rowIndex)->setValue($value);
                $colIndex++;
            }
            $rowIndex++;
        }
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');
    }

    public function initXlsxOutput(string $filename): void {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $this->safeFilename($filename) . '"');
        header('Cache-Control: max-age=0');
        header('X-Content-Type-Options: nosniff');
    }

    public function outputSpreadsheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void {
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
    }

    /** @return \PhpOffice\PhpSpreadsheet\Spreadsheet */
    public function createFullExportSpreadsheet(array $meeting, array $attendanceRows, array $motionRows, array $voteRows = []): \PhpOffice\PhpSpreadsheet\Spreadsheet {
        $summaryHeaders = ['Information', 'Valeur'];
        $summaryData = [
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
        $spreadsheet = $this->createSpreadsheet($summaryHeaders, $summaryData, 'Résumé');
        $this->addSheet($spreadsheet, $this->getAttendanceHeaders(), array_map([$this, 'formatAttendanceRow'], $attendanceRows), 'Émargement');
        $this->addSheet($spreadsheet, $this->getMotionResultsHeaders(), array_map([$this, 'formatMotionResultRow'], $motionRows), 'Résolutions');
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
        $spreadsheet->setActiveSheetIndex(0);
        return $spreadsheet;
    }
}
