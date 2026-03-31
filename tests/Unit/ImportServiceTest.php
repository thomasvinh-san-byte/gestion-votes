<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Service\ImportService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ImportService static utility methods.
 *
 * No database connection needed — all methods are static helpers.
 * CSV tests create temporary files in setUp/tearDown.
 */
class ImportServiceTest extends TestCase {
    /** @var string Temporary directory for CSV test files */
    private string $tmpDir;

    protected function setUp(): void {
        $this->tmpDir = sys_get_temp_dir() . '/import_service_test_' . uniqid();
        mkdir($this->tmpDir, 0700, true);
    }

    protected function tearDown(): void {
        // Remove all temp files
        foreach (glob($this->tmpDir . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
    }

    // =========================================================================
    // validateUploadedFile tests
    // =========================================================================

    public function testValidateUploadedFileSuccess(): void {
        // Create a real temp file so finfo can read it
        $path = $this->tmpDir . '/test.csv';
        file_put_contents($path, "nom,email\nJohn,john@example.com\n");

        $file = [
            'name'     => 'members.csv',
            'tmp_name' => $path,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($path),
        ];

        $result = ImportService::validateUploadedFile($file, 'csv');

        $this->assertTrue($result['ok']);
        $this->assertNull($result['error']);
    }

    public function testValidateUploadedFileMissingTmpName(): void {
        $file = [
            'name'  => 'members.csv',
            'error' => UPLOAD_ERR_OK,
            'size'  => 100,
            // No tmp_name key
        ];

        $result = ImportService::validateUploadedFile($file, 'csv');

        $this->assertFalse($result['ok']);
        $this->assertNotNull($result['error']);
    }

    public function testValidateUploadedFileUploadError(): void {
        $file = [
            'name'     => 'members.csv',
            'tmp_name' => '/tmp/whatever',
            'error'    => UPLOAD_ERR_PARTIAL, // Not UPLOAD_ERR_OK
            'size'     => 100,
        ];

        $result = ImportService::validateUploadedFile($file, 'csv');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('manquant', $result['error']);
    }

    public function testValidateUploadedFileWrongExtension(): void {
        $path = $this->tmpDir . '/test.csv';
        file_put_contents($path, 'dummy');

        $file = [
            'name'     => 'members.xlsx', // Wrong extension
            'tmp_name' => $path,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($path),
        ];

        $result = ImportService::validateUploadedFile($file, 'csv');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Extension attendue', $result['error']);
    }

    public function testValidateUploadedFileTooLarge(): void {
        $path = $this->tmpDir . '/big.csv';
        file_put_contents($path, 'nom,email');

        $file = [
            'name'     => 'big.csv',
            'tmp_name' => $path,
            'error'    => UPLOAD_ERR_OK,
            'size'     => ImportService::MAX_FILE_SIZE + 1, // Over limit
        ];

        $result = ImportService::validateUploadedFile($file, 'csv');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('volumineux', $result['error']);
    }

    // =========================================================================
    // readCsvFile tests
    // =========================================================================

    public function testReadCsvFileCommaSeparated(): void {
        $path = $this->tmpDir . '/comma.csv';
        file_put_contents($path, "Nom,Email,Ponderation\nJohn,john@example.com,1.0\nJane,jane@example.com,2.0\n");

        $result = ImportService::readCsvFile($path);

        $this->assertNull($result['error']);
        $this->assertEquals(',', $result['separator']);
        $this->assertEquals(['nom', 'email', 'ponderation'], $result['headers']);
        $this->assertCount(2, $result['rows']);
    }

    public function testReadCsvFileSemicolonSeparated(): void {
        $path = $this->tmpDir . '/semicolon.csv';
        file_put_contents($path, "Nom;Email;Ponderation\nJohn;john@example.com;1.0\n");

        $result = ImportService::readCsvFile($path);

        $this->assertNull($result['error']);
        $this->assertEquals(';', $result['separator']);
        $this->assertEquals(['nom', 'email', 'ponderation'], $result['headers']);
        $this->assertCount(1, $result['rows']);
    }

    public function testReadCsvFileSkipsEmptyRows(): void {
        $path = $this->tmpDir . '/empty_rows.csv';
        // Two data rows, one blank line
        file_put_contents($path, "nom,email\nAlice,alice@example.com\n\nBob,bob@example.com\n");

        $result = ImportService::readCsvFile($path);

        $this->assertNull($result['error']);
        $this->assertCount(2, $result['rows'], 'Blank line must be filtered out');
    }

    public function testReadCsvFileInvalidPath(): void {
        $result = ImportService::readCsvFile('/nonexistent/path/does_not_exist.csv');

        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('Impossible', $result['error']);
    }

    public function testReadCsvFileEmptyFile(): void {
        $path = $this->tmpDir . '/empty.csv';
        file_put_contents($path, '');

        $result = ImportService::readCsvFile($path);

        $this->assertNotNull($result['error']);
    }

    // =========================================================================
    // mapColumns tests
    // =========================================================================

    public function testMapColumnsFindsMatchingAliases(): void {
        $headers   = ['nom', 'email', 'ponderation'];
        $columnMap = ImportService::getMembersColumnMap();

        $result = ImportService::mapColumns($headers, $columnMap);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('voting_power', $result);
        $this->assertEquals(0, $result['name']);           // 'nom' is at index 0
        $this->assertEquals(1, $result['email']);          // 'email' is at index 1
        $this->assertEquals(2, $result['voting_power']);   // 'ponderation' is at index 2
    }

    public function testMapColumnsSkipsMissingColumns(): void {
        // Only 'email' header present — 'name' and 'voting_power' should not be in result
        $headers   = ['email'];
        $columnMap = ImportService::getMembersColumnMap();

        $result = ImportService::mapColumns($headers, $columnMap);

        $this->assertArrayHasKey('email', $result);
        $this->assertArrayNotHasKey('name', $result);
        $this->assertArrayNotHasKey('voting_power', $result);
    }

    public function testMapColumnsReturnsEmptyForNoMatches(): void {
        $headers   = ['col_x', 'col_y', 'col_z'];
        $columnMap = ImportService::getMembersColumnMap();

        $result = ImportService::mapColumns($headers, $columnMap);

        $this->assertEmpty($result);
    }

    // =========================================================================
    // Column map getter tests
    // =========================================================================

    public function testColumnMapsReturnNonEmptyArrays(): void {
        $membersMap    = ImportService::getMembersColumnMap();
        $attendanceMap = ImportService::getAttendancesColumnMap();
        $motionsMap    = ImportService::getMotionsColumnMap();
        $proxiesMap    = ImportService::getProxiesColumnMap();

        $this->assertNotEmpty($membersMap);
        $this->assertNotEmpty($attendanceMap);
        $this->assertNotEmpty($motionsMap);
        $this->assertNotEmpty($proxiesMap);

        // Check expected keys in each map
        $this->assertArrayHasKey('name', $membersMap);
        $this->assertArrayHasKey('email', $membersMap);
        $this->assertArrayHasKey('voting_power', $membersMap);

        $this->assertArrayHasKey('mode', $attendanceMap);
        $this->assertArrayHasKey('title', $motionsMap);
        $this->assertArrayHasKey('giver_name', $proxiesMap);
        $this->assertArrayHasKey('receiver_name', $proxiesMap);
    }

    // =========================================================================
    // parseAttendanceMode tests
    // =========================================================================

    public function testParseAttendanceModePresent(): void {
        $presentValues = ['present', 'présent', 'p', '1', 'oui', 'yes'];
        foreach ($presentValues as $val) {
            $this->assertEquals('present', ImportService::parseAttendanceMode($val), "Expected 'present' for '{$val}'");
        }
    }

    public function testParseAttendanceModeRemote(): void {
        $remoteValues = ['remote', 'distant', 'visio'];
        foreach ($remoteValues as $val) {
            $this->assertEquals('remote', ImportService::parseAttendanceMode($val), "Expected 'remote' for '{$val}'");
        }
    }

    public function testParseAttendanceModeExcused(): void {
        $excusedValues = ['excused', 'excusé'];
        foreach ($excusedValues as $val) {
            $this->assertEquals('excused', ImportService::parseAttendanceMode($val), "Expected 'excused' for '{$val}'");
        }
    }

    public function testParseAttendanceModeAbsent(): void {
        $absentValues = ['absent', 'a', '0', '', 'non'];
        foreach ($absentValues as $val) {
            $this->assertEquals('absent', ImportService::parseAttendanceMode($val), "Expected 'absent' for '{$val}'");
        }
    }

    public function testParseAttendanceModeProxy(): void {
        $proxyValues = ['proxy', 'procuration'];
        foreach ($proxyValues as $val) {
            $this->assertEquals('proxy', ImportService::parseAttendanceMode($val), "Expected 'proxy' for '{$val}'");
        }
    }

    public function testParseAttendanceModeUnknown(): void {
        $this->assertNull(ImportService::parseAttendanceMode('xyz'));
        $this->assertNull(ImportService::parseAttendanceMode('unknown_mode'));
    }

    public function testParseAttendanceModeCaseInsensitive(): void {
        $this->assertEquals('present', ImportService::parseAttendanceMode('PRESENT'));
        $this->assertEquals('present', ImportService::parseAttendanceMode('Present'));
        $this->assertEquals('remote', ImportService::parseAttendanceMode('Remote'));
        $this->assertEquals('remote', ImportService::parseAttendanceMode('REMOTE'));
    }

    // =========================================================================
    // parseBoolean tests
    // =========================================================================

    public function testParseBooleanTrueValues(): void {
        $trueValues = ['1', 'true', 'oui', 'yes', 'actif', 'o', 'y'];
        foreach ($trueValues as $val) {
            $this->assertTrue(ImportService::parseBoolean($val), "Expected true for '{$val}'");
        }
    }

    public function testParseBooleanFalseValues(): void {
        $falseValues = ['0', 'false', 'non', 'no', ''];
        foreach ($falseValues as $val) {
            $this->assertFalse(ImportService::parseBoolean($val), "Expected false for '{$val}'");
        }
    }

    // =========================================================================
    // parseVotingPower tests
    // =========================================================================

    public function testParseVotingPowerInteger(): void {
        $this->assertEquals(5.0, ImportService::parseVotingPower('5'));
    }

    public function testParseVotingPowerFloat(): void {
        $this->assertEquals(3.5, ImportService::parseVotingPower('3.5'));
    }

    public function testParseVotingPowerFrenchDecimal(): void {
        // French comma decimal separator
        $this->assertEquals(3.5, ImportService::parseVotingPower('3,5'));
    }

    public function testParseVotingPowerZeroDefaultsToOne(): void {
        $this->assertEquals(1.0, ImportService::parseVotingPower('0'));
    }

    public function testParseVotingPowerNegativeDefaultsToOne(): void {
        $this->assertEquals(1.0, ImportService::parseVotingPower('-5'));
    }

    public function testParseVotingPowerEmptyDefaultsToOne(): void {
        $this->assertEquals(1.0, ImportService::parseVotingPower(''));
    }

    // =========================================================================
    // validateUploadedFile — xlsx path (MIME type check)
    // =========================================================================

    public function testValidateUploadedFileXlsxWrongMimeType(): void {
        // Create a file that looks like CSV (plain text) but has .xlsx extension
        $path = $this->tmpDir . '/test.xlsx';
        file_put_contents($path, "not,a,real,xlsx\n");

        $file = [
            'name'     => 'members.xlsx',
            'tmp_name' => $path,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($path),
        ];

        $result = ImportService::validateUploadedFile($file, 'xlsx');

        // The file is plain text, not a valid XLSX (application/zip) → MIME mismatch
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('MIME', $result['error']);
    }

    public function testValidateUploadedFileXlsxWrongExtension(): void {
        $path = $this->tmpDir . '/test.csv';
        file_put_contents($path, "nom,email\n");

        $file = [
            'name'     => 'members.csv', // .csv, but expected .xlsx
            'tmp_name' => $path,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($path),
        ];

        $result = ImportService::validateUploadedFile($file, 'xlsx');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Extension attendue', $result['error']);
    }

    // =========================================================================
    // readXlsxFile — error handling path
    // =========================================================================

    public function testReadXlsxFileNonExistentPathReturnsError(): void {
        $result = ImportService::readXlsxFile('/nonexistent/path/no_file.xlsx');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('headers', $result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertNotNull($result['error']);
        $this->assertEmpty($result['headers']);
        $this->assertEmpty($result['rows']);
    }

    public function testReadXlsxFileInvalidFileReturnsError(): void {
        // Create a file with random binary that no spreadsheet reader can parse
        $path = $this->tmpDir . '/invalid.xlsx';
        file_put_contents($path, random_bytes(64));

        $result = ImportService::readXlsxFile($path);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        // PhpSpreadsheet not installed → class not found error
        // PhpSpreadsheet installed → binary unreadable → reader error
        // Either way: error is set, OR headers/rows are empty (graceful degradation)
        $hasError = $result['error'] !== null;
        $isEmpty = empty($result['headers']) && empty($result['rows']);
        $this->assertTrue($hasError || $isEmpty, 'Invalid XLSX should produce an error or empty result');
    }

    public function testReadXlsxFileReturnsStructuredResult(): void {
        // Test that the return structure is always correct even on errors
        $result = ImportService::readXlsxFile('/tmp/does_not_exist_at_all.xlsx', 0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('headers', $result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertIsArray($result['headers']);
        $this->assertIsArray($result['rows']);
    }

    // =========================================================================
    // readCsvFile — encoding detection tests (IMP-01)
    // =========================================================================

    public function testReadCsvFileWindows1252(): void {
        // Build CSV content with Windows-1252 encoding:
        // Header: "nom,email"
        // Row 1: "Dupré,dupre@example.com"  — é = \xE9 in Win-1252
        // Row 2: "Müller,muller@example.com" — ü = \xFC in Win-1252
        $utf8Content = "nom,email\nDupr\xC3\xA9,dupre@example.com\nM\xC3\xBCller,muller@example.com\n";
        $win1252Content = mb_convert_encoding($utf8Content, 'Windows-1252', 'UTF-8');

        $path = $this->tmpDir . '/windows1252.csv';
        file_put_contents($path, $win1252Content);

        $result = ImportService::readCsvFile($path);

        $this->assertNull($result['error'], 'Should parse without error');
        $this->assertCount(2, $result['rows'], 'Should have 2 data rows');
        // Row 0, column 0 should be the correctly decoded UTF-8 name
        $this->assertStringContainsString('Dupr', $result['rows'][0][0]);
        $this->assertTrue(
            mb_check_encoding($result['rows'][0][0], 'UTF-8'),
            'Row 0 col 0 must be valid UTF-8'
        );
        $this->assertTrue(
            mb_check_encoding($result['rows'][1][0], 'UTF-8'),
            'Row 1 col 0 must be valid UTF-8'
        );
        // The decoded accented chars must match what we originally put in
        $this->assertEquals('Dupré', $result['rows'][0][0]);
        $this->assertEquals('Müller', $result['rows'][1][0]);
    }

    public function testReadCsvFileIso88591(): void {
        // Same test but using ISO-8859-1 encoding
        $utf8Content = "nom,email\nGérard,gerard@example.com\nÉlodie,elodie@example.com\n";
        $iso88591Content = mb_convert_encoding($utf8Content, 'ISO-8859-1', 'UTF-8');

        $path = $this->tmpDir . '/iso88591.csv';
        file_put_contents($path, $iso88591Content);

        $result = ImportService::readCsvFile($path);

        $this->assertNull($result['error'], 'Should parse without error');
        $this->assertCount(2, $result['rows']);
        $this->assertEquals('Gérard', $result['rows'][0][0]);
        $this->assertEquals('Élodie', $result['rows'][1][0]);
    }

    public function testReadCsvFileUtf8Unchanged(): void {
        // UTF-8 files must continue to work without regression
        $path = $this->tmpDir . '/utf8.csv';
        file_put_contents($path, "nom,email\nJean-François,jf@example.com\nÄnne,anne@example.com\n");

        $result = ImportService::readCsvFile($path);

        $this->assertNull($result['error']);
        $this->assertCount(2, $result['rows']);
        $this->assertEquals('Jean-François', $result['rows'][0][0]);
        $this->assertEquals('Änne', $result['rows'][1][0]);
    }

    public function testReadCsvFileAsciiOnlyUnchanged(): void {
        // ASCII-only CSV must still work correctly (no regression)
        $path = $this->tmpDir . '/ascii.csv';
        file_put_contents($path, "name,email\nAlice,alice@example.com\nBob,bob@example.com\n");

        $result = ImportService::readCsvFile($path);

        $this->assertNull($result['error']);
        $this->assertCount(2, $result['rows']);
        $this->assertEquals('Alice', $result['rows'][0][0]);
        $this->assertEquals('Bob', $result['rows'][1][0]);
    }
}
