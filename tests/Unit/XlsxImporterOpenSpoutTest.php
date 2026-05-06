<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Service\XlsxImporter;
use PHPUnit\Framework\TestCase;

/**
 * Round-trip test for the OpenSpout-backed XLSX import path.
 *
 * Replaces the PhpSpreadsheet IOFactory pipeline. Same external contract:
 * - first row → lowercase trimmed headers
 * - subsequent rows → array of strings
 * - empty rows skipped, formula values returned (handled by OpenSpout
 *   which evaluates simple values transparently).
 *
 * Refs: M-INFRA-CLEANUP / CLEANUP-INFRA-OPENSPOUT-IMPORT.
 */
final class XlsxImporterOpenSpoutTest extends TestCase {
    private string $fixturePath = '';

    protected function setUp(): void {
        $this->fixturePath = __DIR__ . '/../fixtures/import-members-3rows.xlsx';
        if (!is_file($this->fixturePath)) {
            $this->markTestSkipped('Fixture XLSX not present.');
        }
    }

    public function testReadFileReturnsHeadersAndRows(): void {
        $result = XlsxImporter::readFile($this->fixturePath);

        $this->assertNull($result['error']);
        $this->assertSame(['nom', 'email', 'poids'], $result['headers']);
        $this->assertCount(3, $result['rows']);
    }

    public function testReadFileLowercasesAndTrimsHeaders(): void {
        $result = XlsxImporter::readFile($this->fixturePath);

        $this->assertNotEmpty($result['headers']);
        foreach ($result['headers'] as $h) {
            $this->assertSame(mb_strtolower(trim($h)), $h, 'Headers must be lowercased and trimmed.');
        }
    }

    public function testReadFilePreservesUtf8Accents(): void {
        $result = XlsxImporter::readFile($this->fixturePath);

        // Third member has accented name "Émile" — must round-trip clean.
        $this->assertSame('Émile', $result['rows'][2][0]);
    }

    public function testReadFileCoercesNumericCellsToString(): void {
        // Voting power column was written as int; the importer's parsers
        // expect string input (parseVotingPower, parseBoolean, …).
        $result = XlsxImporter::readFile($this->fixturePath);

        $this->assertNotEmpty($result['rows']);
        foreach ($result['rows'] as $row) {
            $this->assertIsString($row[2]);
            $this->assertNotSame('', $row[2]);
        }
    }

    public function testReadFileReturnsErrorOnInvalidPath(): void {
        $result = XlsxImporter::readFile(__DIR__ . '/does-not-exist.xlsx');

        $this->assertNotNull($result['error']);
        $this->assertSame([], $result['headers']);
        $this->assertSame([], $result['rows']);
    }
}
