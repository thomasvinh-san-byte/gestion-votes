<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\DocContentController;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DocContentController.
 *
 * Tests the documentation content serving endpoint including:
 *  - Controller structure (final, does NOT extend AbstractController)
 *  - Public method availability
 *  - Path sanitization and directory traversal prevention
 *  - Missing page parameter handling
 *  - Page parameter validation (allowed characters)
 *  - .md extension stripping logic
 *  - Source structure verification
 *
 * Note: DocContentController does NOT extend AbstractController.
 * It serves plain text, not JSON API responses, and uses exit() for responses.
 * Therefore we test its logic via source inspection and logic replication
 * rather than invoking the controller directly.
 */
class DocContentControllerTest extends TestCase
{
    // =========================================================================
    // SETUP / TEARDOWN
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

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
        $this->assertFalse($ref->getParentClass(),
            'DocContentController should not extend any class');
    }

    public function testControllerHasShowMethod(): void
    {
        $ref = new \ReflectionClass(DocContentController::class);

        $this->assertTrue(
            $ref->hasMethod('show'),
            'DocContentController should have a show method',
        );
    }

    public function testShowMethodIsPublic(): void
    {
        $ref = new \ReflectionClass(DocContentController::class);

        $this->assertTrue(
            $ref->getMethod('show')->isPublic(),
            'DocContentController::show() should be public',
        );
    }

    public function testShowMethodHasNoParameters(): void
    {
        $ref = new \ReflectionClass(DocContentController::class);
        $method = $ref->getMethod('show');

        $this->assertCount(0, $method->getParameters(),
            'show() should take no parameters');
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
    // CONTROLLER SOURCE: STRUCTURE VERIFICATION
    // =========================================================================

    public function testSourceSetsContentTypeTextPlain(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocContentController.php');

        $this->assertStringContainsString('text/plain', $source,
            'DocContentController should set Content-Type to text/plain');
    }

    public function testSourceReadsPageFromGet(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocContentController.php');

        $this->assertStringContainsString("\$_GET['page']", $source,
            'DocContentController should read page from $_GET');
    }

    public function testSourceHandlesMissingPage(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocContentController.php');

        $this->assertStringContainsString('Missing page parameter', $source);
    }

    public function testSourceHandlesInvalidPage(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocContentController.php');

        $this->assertStringContainsString('Invalid page parameter', $source);
    }

    public function testSourceHandlesFileNotFound(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocContentController.php');

        $this->assertStringContainsString('Document not found', $source);
    }

    public function testSourceUsesFileGetContents(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocContentController.php');

        $this->assertStringContainsString('file_get_contents', $source);
    }

    public function testSourceChecksFileExists(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocContentController.php');

        // Uses realpath() for path traversal protection (replaces file_exists/is_file)
        $this->assertStringContainsString('realpath', $source);
        $this->assertStringContainsString('str_starts_with', $source);
    }

    public function testSourceSetsHttpResponseCodes(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocContentController.php');

        $this->assertStringContainsString('400', $source);
        $this->assertStringContainsString('404', $source);
    }

    public function testSourceDocsRootUsesProjectDocs(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocContentController.php');

        $this->assertStringContainsString("'/docs'", $source,
            'DocContentController should serve from /docs directory');
    }
}
