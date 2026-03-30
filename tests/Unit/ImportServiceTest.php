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
}
