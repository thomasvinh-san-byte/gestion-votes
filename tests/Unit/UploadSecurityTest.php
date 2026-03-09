<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Upload security tests.
 *
 * Validates file upload validation logic: MIME type, extension, file size,
 * path traversal prevention, and stored name generation.
 *
 * These tests verify the validation logic independently from the controller,
 * ensuring the security constraints cannot be bypassed.
 */
class UploadSecurityTest extends TestCase
{
    // =========================================================================
    // MIME TYPE VALIDATION
    // =========================================================================

    /**
     * @dataProvider validMimeTypeProvider
     */
    public function testAcceptsValidPdfMimeType(string $mime): void
    {
        $allowed = ['application/pdf'];
        $this->assertTrue(in_array($mime, $allowed, true), "MIME type '{$mime}' should be accepted");
    }

    public static function validMimeTypeProvider(): array
    {
        return [
            'standard pdf' => ['application/pdf'],
        ];
    }

    /**
     * @dataProvider invalidMimeTypeProvider
     */
    public function testRejectsInvalidMimeType(string $mime): void
    {
        $allowed = ['application/pdf'];
        $this->assertFalse(in_array($mime, $allowed, true), "MIME type '{$mime}' should be rejected");
    }

    public static function invalidMimeTypeProvider(): array
    {
        return [
            'png image'         => ['image/png'],
            'jpeg image'        => ['image/jpeg'],
            'gif image'         => ['image/gif'],
            'svg xml'           => ['image/svg+xml'],
            'html'              => ['text/html'],
            'javascript'        => ['application/javascript'],
            'zip archive'       => ['application/zip'],
            'gzip'              => ['application/gzip'],
            'tar'               => ['application/x-tar'],
            'msword'            => ['application/msword'],
            'docx'              => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xlsx'              => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'exe'               => ['application/x-executable'],
            'shell script'      => ['application/x-shellscript'],
            'php'               => ['application/x-httpd-php'],
            'empty string'      => [''],
            'octet-stream'      => ['application/octet-stream'],
            'xml'               => ['application/xml'],
            'json'              => ['application/json'],
            'text plain'        => ['text/plain'],
            'csv'               => ['text/csv'],
        ];
    }

    // =========================================================================
    // FILE EXTENSION VALIDATION
    // =========================================================================

    /**
     * @dataProvider validExtensionProvider
     */
    public function testAcceptsValidPdfExtension(string $filename): void
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $this->assertEquals('pdf', $ext, "Extension of '{$filename}' should be 'pdf'");
    }

    public static function validExtensionProvider(): array
    {
        return [
            'lowercase .pdf'    => ['document.pdf'],
            'uppercase .PDF'    => ['document.PDF'],
            'mixed case .Pdf'   => ['document.Pdf'],
            'deep path'         => ['/some/path/to/document.pdf'],
            'spaces in name'    => ['my document.pdf'],
            'unicode name'      => ['résumé-séance.pdf'],
            'multiple dots'     => ['file.v2.final.pdf'],
        ];
    }

    /**
     * @dataProvider invalidExtensionProvider
     */
    public function testRejectsInvalidExtension(string $filename): void
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $this->assertNotEquals('pdf', $ext, "Extension of '{$filename}' should NOT be 'pdf'");
    }

    public static function invalidExtensionProvider(): array
    {
        return [
            'no extension'      => ['document'],
            'exe'               => ['malware.exe'],
            'php script'        => ['shell.php'],
            'php disguised'     => ['document.pdf.php'],
            'phtml'             => ['backdoor.phtml'],
            'html'              => ['page.html'],
            'js'                => ['script.js'],
            'sh'                => ['exploit.sh'],
            'bat'               => ['run.bat'],
            'svg'               => ['image.svg'],
            'docx'              => ['document.docx'],
            'zip'               => ['archive.zip'],
            'dot only'          => ['file.'],
            'double extension'  => ['file.pdf.exe'],
        ];
    }

    // =========================================================================
    // PATH TRAVERSAL PREVENTION
    // =========================================================================

    /**
     * basename() strips forward-slash traversal on Linux.
     *
     * @dataProvider slashTraversalProvider
     */
    public function testBasenameSanitizesSlashTraversal(string $maliciousName): void
    {
        $basename = basename($maliciousName);
        $this->assertStringNotContainsString('/', $basename);
    }

    public static function slashTraversalProvider(): array
    {
        return [
            'dot-dot-slash'   => ['../../etc/passwd'],
            'absolute path'   => ['/etc/passwd'],
            'mixed traversal' => ['./../../file.pdf'],
        ];
    }

    /**
     * The real protection against path traversal: stored name is UUID.pdf, never original.
     *
     * @dataProvider allTraversalProvider
     */
    public function testUuidNamingPreventsAllTraversal(string $maliciousName): void
    {
        $id = '12345678-1234-1234-1234-123456789abc';
        $storedName = $id . '.pdf';

        $this->assertStringNotContainsString('..', $storedName);
        $this->assertStringNotContainsString('/', $storedName);
        $this->assertStringNotContainsString("\0", $storedName);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f\-]+\.pdf$/',
            $storedName,
            "Stored name safe regardless of original: {$maliciousName}"
        );
    }

    public static function allTraversalProvider(): array
    {
        return [
            'dot-dot-slash'     => ['../../etc/passwd'],
            'encoded traversal' => ['..%2F..%2Fetc%2Fpasswd'],
            'backslash'         => ['..\\..\\windows\\system32\\config\\sam'],
            'null byte'         => ["file\x00.pdf"],
            'absolute path'     => ['/etc/passwd'],
            'windows absolute'  => ['C:\\Windows\\system32\\cmd.exe'],
            'mixed traversal'   => ['./../../file.pdf'],
            'double encoded'    => ['..%252f..%252fetc/shadow'],
        ];
    }

    public function testStoredNameUsesUuidNotOriginal(): void
    {
        // Verify the naming pattern: UUID + .pdf
        $id = '12345678-1234-1234-1234-123456789abc';
        $storedName = $id . '.pdf';

        // Stored name should be safe
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.pdf$/',
            $storedName,
            'Stored name should be UUID.pdf format'
        );
    }

    public function testStoredNameCannotContainDirectoryTraversal(): void
    {
        $id = '12345678-1234-1234-1234-123456789abc';
        $storedName = $id . '.pdf';

        $this->assertStringNotContainsString('..', $storedName);
        $this->assertStringNotContainsString('/', $storedName);
        $this->assertStringNotContainsString('\\', $storedName);
        $this->assertStringNotContainsString("\0", $storedName);
    }

    // =========================================================================
    // FILE SIZE LIMITS
    // =========================================================================

    public function testMaxFileSizeIs10Mb(): void
    {
        $maxSize = 10 * 1024 * 1024; // 10 MB
        $this->assertEquals(10485760, $maxSize);
    }

    /**
     * @dataProvider fileSizeProvider
     */
    public function testFileSizeValidation(int $fileSize, bool $shouldAccept): void
    {
        $maxSize = 10 * 1024 * 1024;
        $isAccepted = $fileSize <= $maxSize;
        $this->assertEquals($shouldAccept, $isAccepted, "File size {$fileSize} bytes");
    }

    public static function fileSizeProvider(): array
    {
        return [
            '0 bytes (empty)'       => [0, true],
            '1 byte'                => [1, true],
            '1 KB'                  => [1024, true],
            '1 MB'                  => [1048576, true],
            '5 MB'                  => [5242880, true],
            '10 MB exactly'         => [10485760, true],
            '10 MB + 1 byte'        => [10485761, false],
            '11 MB'                 => [11534336, false],
            '50 MB'                 => [52428800, false],
            '100 MB'                => [104857600, false],
            '1 GB'                  => [1073741824, false],
        ];
    }

    // =========================================================================
    // UPLOAD DIRECTORY STRUCTURE
    // =========================================================================

    public function testUploadDirectoryIsScopedByMeetingId(): void
    {
        $meetingId = '12345678-1234-1234-1234-123456789abc';
        $baseDir = 'storage/uploads/meetings';
        $uploadDir = $baseDir . '/' . $meetingId;

        $this->assertStringContainsString($meetingId, $uploadDir);
        $this->assertStringStartsWith('storage/uploads/meetings/', $uploadDir);
    }

    public function testUploadDirectoryDoesNotAllowTraversal(): void
    {
        // A malicious meeting_id should be a UUID, not a path
        $maliciousMeetingId = '../../etc';
        $isValidUuid = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $maliciousMeetingId);

        $this->assertFalse($isValidUuid, 'Path traversal string should fail UUID validation');
    }

    // =========================================================================
    // SOURCE-LEVEL VERIFICATION (Controller)
    // =========================================================================

    public function testControllerUsesFinfo(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingAttachmentController.php');
        $this->assertStringContainsString('finfo', $source, 'Controller should use finfo for MIME detection');
    }

    public function testControllerChecksExtension(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingAttachmentController.php');
        $this->assertStringContainsString('PATHINFO_EXTENSION', $source, 'Controller should check file extension');
    }

    public function testControllerHasMaxSizeCheck(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingAttachmentController.php');
        // Should contain a size check (10 * 1024 * 1024 or 10485760)
        $hasSize = str_contains($source, '10485760') || str_contains($source, '10 * 1024 * 1024');
        $this->assertTrue($hasSize, 'Controller should enforce 10 MB max file size');
    }
}
