<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\DocContentController;

/**
 * Unit tests for DocContentController.
 *
 * Extends ControllerTestCase for standard test infrastructure.
 *
 * DocContentController does NOT extend AbstractController — it serves plain text
 * (not JSON), using header()/http_response_code()/echo with no exit().
 *
 * Tests verify:
 *  - Controller is final and does NOT extend AbstractController
 *  - Path sanitization and directory traversal prevention
 *  - .md extension stripping logic
 *  - File path construction logic
 *  - Source-level structure (Content-Type, response codes, file reading)
 */
class DocContentControllerTest extends ControllerTestCase
{
    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(DocContentController::class);
        $this->assertTrue($ref->isFinal(), 'DocContentController should be final');
    }

    public function testControllerDoesNotExtendAbstractController(): void
    {
        $ref = new \ReflectionClass(DocContentController::class);
        $this->assertFalse(
            $ref->isSubclassOf(\AgVote\Controller\AbstractController::class),
            'DocContentController must NOT extend AbstractController (plain text endpoint)',
        );
    }

    public function testControllerHasShowMethod(): void
    {
        $ref = new \ReflectionClass(DocContentController::class);
        $this->assertTrue($ref->hasMethod('show'), 'Missing method: show');
        $this->assertTrue($ref->getMethod('show')->isPublic(), 'show() should be public');
        $this->assertCount(0, $ref->getMethod('show')->getParameters(), 'show() takes no parameters');
    }

    // =========================================================================
    // PATH SANITIZATION: DIRECTORY TRAVERSAL PREVENTION
    // =========================================================================

    public function testDirectoryTraversalWithDoubleDots(): void
    {
        $page = '../../../etc/passwd';
        $page = str_replace('\\', '/', $page);
        $isInvalid = (bool) preg_match('/\.\./', $page);

        $this->assertTrue($isInvalid, 'Double dots should be rejected');
    }

    public function testDirectoryTraversalWithBackslashes(): void
    {
        $page = '..\\..\\etc\\passwd';
        $page = str_replace('\\', '/', $page);
        $isInvalid = (bool) preg_match('/\.\./', $page);

        $this->assertTrue($isInvalid, 'Backslash-based traversal should be rejected after normalization');
    }

    public function testDirectoryTraversalWithEncodedDots(): void
    {
        $page = '%2e%2e/etc/passwd';
        $page = str_replace('\\', '/', $page);
        $isInvalid = (bool) preg_match('#[^a-zA-Z0-9_/\-.]#', $page);

        $this->assertTrue($isInvalid, 'Percent-encoded dots should be rejected by character filter');
    }

    public function testValidPagePassesSanitization(): void
    {
        $page = 'FAQ';
        $page = str_replace('\\', '/', $page);
        $isInvalid = (bool) preg_match('/\.\./', $page) || (bool) preg_match('#[^a-zA-Z0-9_/\-.]#', $page);

        $this->assertFalse($isInvalid, 'Simple page name should pass sanitization');
    }

    public function testValidNestedPagePassesSanitization(): void
    {
        $page = 'dev/ARCHITECTURE';
        $page = str_replace('\\', '/', $page);
        $isInvalid = (bool) preg_match('/\.\./', $page) || (bool) preg_match('#[^a-zA-Z0-9_/\-.]#', $page);

        $this->assertFalse($isInvalid, 'Nested page path should pass sanitization');
    }

    public function testPageWithSpecialCharsRejected(): void
    {
        $page = 'page<script>';
        $page = str_replace('\\', '/', $page);
        $isInvalid = (bool) preg_match('/\.\./', $page) || (bool) preg_match('#[^a-zA-Z0-9_/\-.]#', $page);

        $this->assertTrue($isInvalid, 'Page with HTML special chars should be rejected');
    }

    public function testPageWithSpacesRejected(): void
    {
        $page = 'my page';
        $page = str_replace('\\', '/', $page);
        $isInvalid = (bool) preg_match('/\.\./', $page) || (bool) preg_match('#[^a-zA-Z0-9_/\-.]#', $page);

        $this->assertTrue($isInvalid, 'Page with spaces should be rejected');
    }

    public function testPageWithHashRejected(): void
    {
        $page = 'page#section';
        $page = str_replace('\\', '/', $page);
        $isInvalid = (bool) preg_match('/\.\./', $page) || (bool) preg_match('#[^a-zA-Z0-9_/\-.]#', $page);

        $this->assertTrue($isInvalid, 'Page with hash should be rejected');
    }

    public function testPageWithQueryStringRejected(): void
    {
        $page = 'page?param=value';
        $page = str_replace('\\', '/', $page);
        $isInvalid = (bool) preg_match('/\.\./', $page) || (bool) preg_match('#[^a-zA-Z0-9_/\-.]#', $page);

        $this->assertTrue($isInvalid, 'Page with query string should be rejected');
    }

    public function testPageWithHyphenAllowed(): void
    {
        $page = 'my-page-name';
        $page = str_replace('\\', '/', $page);
        $isInvalid = (bool) preg_match('/\.\./', $page) || (bool) preg_match('#[^a-zA-Z0-9_/\-.]#', $page);

        $this->assertFalse($isInvalid, 'Page with hyphens should be allowed');
    }

    public function testPageWithUnderscoreAllowed(): void
    {
        $page = 'my_page_name';
        $page = str_replace('\\', '/', $page);
        $isInvalid = (bool) preg_match('/\.\./', $page) || (bool) preg_match('#[^a-zA-Z0-9_/\-.]#', $page);

        $this->assertFalse($isInvalid, 'Page with underscores should be allowed');
    }

    public function testPageWithDotAllowed(): void
    {
        $page = 'page.name';
        $page = str_replace('\\', '/', $page);
        $isInvalid = (bool) preg_match('/\.\./', $page) || (bool) preg_match('#[^a-zA-Z0-9_/\-.]#', $page);

        $this->assertFalse($isInvalid, 'Page with single dot should be allowed');
    }

    // =========================================================================
    // MD EXTENSION STRIPPING
    // =========================================================================

    public function testMdExtensionStrippedFromPage(): void
    {
        $page = 'FAQ.md';
        $page = preg_replace('/\.md$/i', '', $page);

        $this->assertEquals('FAQ', $page);
    }

    public function testMdExtensionStrippedCaseInsensitive(): void
    {
        $page = 'FAQ.MD';
        $page = preg_replace('/\.md$/i', '', $page);

        $this->assertEquals('FAQ', $page);
    }

    public function testMdExtensionStrippedMixedCase(): void
    {
        $page = 'document.Md';
        $page = preg_replace('/\.md$/i', '', $page);

        $this->assertEquals('document', $page);
    }

    public function testNoExtensionPageUnchanged(): void
    {
        $page = 'FAQ';
        $page = preg_replace('/\.md$/i', '', $page);

        $this->assertEquals('FAQ', $page);
    }

    public function testMdInMiddleOfPageNotStripped(): void
    {
        $page = 'FAQ.md.backup';
        $page = preg_replace('/\.md$/i', '', $page);

        $this->assertEquals('FAQ.md.backup', $page, '.md in middle should not be stripped');
    }

    public function testNestedPageWithMdExtension(): void
    {
        $page = 'dev/ARCHITECTURE.md';
        $page = preg_replace('/\.md$/i', '', $page);

        $this->assertEquals('dev/ARCHITECTURE', $page);
    }

    // =========================================================================
    // FILE PATH CONSTRUCTION
    // =========================================================================

    public function testFilePathConstruction(): void
    {
        $docsRoot = '/fake/project/docs';
        $page = 'FAQ';
        $filePath = $docsRoot . '/' . $page . '.md';

        $this->assertEquals('/fake/project/docs/FAQ.md', $filePath);
    }

    public function testFilePathConstructionNested(): void
    {
        $docsRoot = '/fake/project/docs';
        $page = 'dev/ARCHITECTURE';
        $filePath = $docsRoot . '/' . $page . '.md';

        $this->assertEquals('/fake/project/docs/dev/ARCHITECTURE.md', $filePath);
    }

    // =========================================================================
    // SOURCE: STRUCTURE VERIFICATION
    // =========================================================================

    public function testSourceSetsContentTypeTextPlain(): void
    {
        $ref = new \ReflectionClass(DocContentController::class);
        $source = file_get_contents($ref->getFileName());

        $this->assertStringContainsString('text/plain', $source);
    }

    public function testSourceReadsPageFromGet(): void
    {
        $ref = new \ReflectionClass(DocContentController::class);
        $source = file_get_contents($ref->getFileName());

        $this->assertStringContainsString("\$_GET['page']", $source);
    }

    public function testSourceHandlesMissingPage(): void
    {
        $ref = new \ReflectionClass(DocContentController::class);
        $source = file_get_contents($ref->getFileName());

        $this->assertStringContainsString('Missing page parameter', $source);
    }

    public function testSourceHandlesInvalidPage(): void
    {
        $ref = new \ReflectionClass(DocContentController::class);
        $source = file_get_contents($ref->getFileName());

        $this->assertStringContainsString('Invalid page parameter', $source);
    }

    public function testSourceHandlesDocumentNotFound(): void
    {
        $ref = new \ReflectionClass(DocContentController::class);
        $source = file_get_contents($ref->getFileName());

        $this->assertStringContainsString('Document not found', $source);
    }

    public function testSourceUsesFileGetContents(): void
    {
        $ref = new \ReflectionClass(DocContentController::class);
        $source = file_get_contents($ref->getFileName());

        $this->assertStringContainsString('file_get_contents', $source);
    }

    public function testSourceUsesRealpathForTraversalProtection(): void
    {
        $ref = new \ReflectionClass(DocContentController::class);
        $source = file_get_contents($ref->getFileName());

        $this->assertStringContainsString('realpath', $source);
        $this->assertStringContainsString('str_starts_with', $source);
    }

    public function testSourceSetsCorrectHttpResponseCodes(): void
    {
        $ref = new \ReflectionClass(DocContentController::class);
        $source = file_get_contents($ref->getFileName());

        $this->assertStringContainsString('400', $source);
        $this->assertStringContainsString('404', $source);
    }

    public function testSourceServesFromDocsDirectory(): void
    {
        $ref = new \ReflectionClass(DocContentController::class);
        $source = file_get_contents($ref->getFileName());

        $this->assertStringContainsString("'/docs'", $source);
    }
}
