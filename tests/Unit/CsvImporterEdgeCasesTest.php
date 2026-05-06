<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Service\CsvImporter;
use PHPUnit\Framework\TestCase;

/**
 * Targeted edge-case coverage for CSV import reading.
 *
 * Stage 1 audit (CRITICAL-PATH-AUDIT.md étape 02) flagged latent edge
 * cases: encoding, BOM stripping, separator detection. The fixes in
 * CLEANUP-CHEMIN-IMPORT are guarded here so a regression breaks fast.
 *
 * Refs: M-INFRA-CLEANUP / CLEANUP-CHEMIN-IMPORT.
 */
final class CsvImporterEdgeCasesTest extends TestCase {
    private string $tmpFile = '';

    protected function tearDown(): void {
        if ($this->tmpFile !== '' && is_file($this->tmpFile)) {
            @unlink($this->tmpFile);
        }
    }

    private function writeFile(string $content): string {
        $this->tmpFile = (string) tempnam(sys_get_temp_dir(), 'csv_edge_');
        file_put_contents($this->tmpFile, $content);
        return $this->tmpFile;
    }

    public function testReadFileStripsUtf8Bom(): void {
        // Excel "CSV UTF-8 (séparateur point-virgule)" emits an EF BB BF BOM.
        // Without explicit stripping, the first header keeps it as a leading
        // glyph, defeating column lookup.
        $bom = "\xEF\xBB\xBF";
        $csv = $bom . "nom;email;poids\n"
             . "Alice;alice@example.org;1\n"
             . "Bob;bob@example.org;2\n";

        $result = CsvImporter::readFile($this->writeFile($csv));

        $this->assertNull($result['error']);
        $this->assertSame(';', $result['separator']);
        // First header must be the clean lowercase 'nom', not "\xef\xbb\xbfnom".
        $this->assertSame(['nom', 'email', 'poids'], $result['headers']);
    }

    public function testReadFileDetectsSemicolonSeparator(): void {
        // French Excel exports use ';' by default. The detector picks it up
        // when the first line contains at least one ';'.
        $csv = "nom;email\nAlice;alice@example.org\n";

        $result = CsvImporter::readFile($this->writeFile($csv));

        $this->assertSame(';', $result['separator']);
        $this->assertSame(['nom', 'email'], $result['headers']);
        $this->assertCount(1, $result['rows']);
    }

    public function testReadFileNormalizesWindows1252ToUtf8(): void {
        // Excel for Windows still emits cp1252 by default. mb_detect_encoding
        // routes us through mb_convert_encoding to get clean UTF-8.
        $latinContent = mb_convert_encoding(
            "nom,email\nÉmile,emile@example.org\n",
            'Windows-1252',
            'UTF-8',
        );
        $this->assertNotFalse($latinContent);

        $result = CsvImporter::readFile($this->writeFile($latinContent));

        $this->assertNull($result['error']);
        // The accented header must round-trip clean UTF-8 — no mojibake.
        $row = $result['rows'][0] ?? null;
        $this->assertNotNull($row);
        $this->assertSame('Émile', $row[0]);
    }
}
