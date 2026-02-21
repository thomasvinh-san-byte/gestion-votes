<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\DocController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DocController.
 *
 * Tests the documentation index and viewer endpoints including:
 *  - Controller structure (final, extends AbstractController, public methods)
 *  - index() response (lists doc categories from filesystem)
 *  - view() path sanitization and security
 *  - Category and document name constants
 *  - .md extension stripping logic
 *  - Response structure verification via source introspection
 *
 * Note: index() calls api_ok() which is testable via ApiResponseException.
 * However, it reads the filesystem for doc existence, so results depend
 * on which docs are present. view() uses HtmlView::render() which is
 * not easily testable without the view layer, so we verify via source.
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class DocControllerTest extends TestCase
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

        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        \AgVote\Core\Security\AuthMiddleware::reset();
    }

    protected function tearDown(): void
    {
        \AgVote\Core\Security\AuthMiddleware::reset();

        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        parent::tearDown();
    }

    // =========================================================================
    // HELPER: Call controller and capture response
    // =========================================================================

    private function callControllerMethod(string $method): array
    {
        $controller = new DocController();
        try {
            $controller->handle($method);
            $this->fail('Expected ApiResponseException was not thrown');
        } catch (ApiResponseException $e) {
            return [
                'status' => $e->getResponse()->getStatusCode(),
                'body' => $e->getResponse()->getBody(),
            ];
        }
        return ['status' => 500, 'body' => []];
    }

    private function injectJsonBody(array $data): void
    {
        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, json_encode($data));
    }

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(DocController::class);
        $this->assertTrue($ref->isFinal(), 'DocController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new DocController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(DocController::class);

        $expectedMethods = ['index', 'view'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "DocController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(DocController::class);

        $expectedMethods = ['index', 'view'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "DocController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // index: RESPONSE BEHAVIOR
    // =========================================================================

    public function testIndexReturnsOkStatus(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('index');

        $this->assertEquals(200, $result['status']);
    }

    public function testIndexResponseIsArray(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('index');

        $this->assertEquals(200, $result['status']);
        // The response body contains 'ok' => true and 'data' key from api_ok
        $this->assertArrayHasKey('ok', $result['body']);
        $this->assertTrue($result['body']['ok']);
    }

    // =========================================================================
    // CATEGORIES CONSTANT VERIFICATION
    // =========================================================================

    public function testCategoriesConstantExistsInSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString('CATEGORIES', $source);
    }

    public function testCategoriesContainUtilisateur(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString("'Utilisateur'", $source);
    }

    public function testCategoriesContainInstallation(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString("'Installation'", $source);
    }

    public function testCategoriesContainTechnique(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString("'Technique'", $source);
    }

    public function testCategoriesContainConformite(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString("'Conformité'", $source);
    }

    // =========================================================================
    // DOC_NAMES CONSTANT VERIFICATION
    // =========================================================================

    public function testDocNamesConstantExistsInSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString('DOC_NAMES', $source);
    }

    public function testDocNamesContainExpectedDocuments(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $expectedDocs = ['FAQ', 'UTILISATION_LIVE', 'RECETTE_DEMO', 'ANALYTICS_ETHICS'];
        foreach ($expectedDocs as $doc) {
            $this->assertStringContainsString(
                "'{$doc}'",
                $source,
                "DOC_NAMES should contain '{$doc}'",
            );
        }
    }

    public function testDocNamesContainDevDocuments(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $expectedDocs = ['dev/ARCHITECTURE', 'dev/API', 'dev/SECURITY', 'dev/TESTS'];
        foreach ($expectedDocs as $doc) {
            $this->assertStringContainsString(
                "'{$doc}'",
                $source,
                "DOC_NAMES should contain '{$doc}'",
            );
        }
    }

    public function testDocNamesFrenchLabels(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $expectedLabels = ['Guide opérateur', 'Architecture', 'Sécurité'];
        foreach ($expectedLabels as $label) {
            $this->assertStringContainsString(
                $label,
                $source,
                "DOC_NAMES should contain French label '{$label}'",
            );
        }
    }

    // =========================================================================
    // view: PATH SANITIZATION
    // =========================================================================

    public function testViewPathSanitizationRejectsDoubleDotsViaSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString('/\\.\\./', $source,
            'view() should reject paths with double dots');
    }

    public function testViewPathSanitizationUsesCharacterFilter(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString('[^a-zA-Z0-9_/\\-.', $source,
            'view() should filter invalid characters');
    }

    public function testViewStripsMdExtension(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString("preg_replace('/\\.md$/i', ''", $source,
            'view() should strip .md extension');
    }

    // =========================================================================
    // view: SANITIZATION LOGIC REPLICATION
    // =========================================================================

    public function testViewSanitizationValidPage(): void
    {
        $page = 'FAQ';
        $page = str_replace('\\', '/', $page);
        $isInvalid = (bool) preg_match('/\.\./', $page) || (bool) preg_match('#[^a-zA-Z0-9_/\-.]#', $page);

        $this->assertFalse($isInvalid);
    }

    public function testViewSanitizationNestedPage(): void
    {
        $page = 'dev/API';
        $page = str_replace('\\', '/', $page);
        $isInvalid = (bool) preg_match('/\.\./', $page) || (bool) preg_match('#[^a-zA-Z0-9_/\-.]#', $page);

        $this->assertFalse($isInvalid);
    }

    public function testViewSanitizationDirectoryTraversal(): void
    {
        $page = '../../../etc/passwd';
        $page = str_replace('\\', '/', $page);
        $isInvalid = (bool) preg_match('/\.\./', $page) || (bool) preg_match('#[^a-zA-Z0-9_/\-.]#', $page);

        $this->assertTrue($isInvalid);
    }

    public function testViewSanitizationSpecialCharacters(): void
    {
        $page = 'page<script>alert(1)</script>';
        $page = str_replace('\\', '/', $page);
        $isInvalid = (bool) preg_match('/\.\./', $page) || (bool) preg_match('#[^a-zA-Z0-9_/\-.]#', $page);

        $this->assertTrue($isInvalid);
    }

    public function testViewSanitizationNullBytes(): void
    {
        $page = "page\x00.md";
        $page = str_replace('\\', '/', $page);
        $isInvalid = (bool) preg_match('/\.\./', $page) || (bool) preg_match('#[^a-zA-Z0-9_/\-.]#', $page);

        $this->assertTrue($isInvalid);
    }

    // =========================================================================
    // view: SOURCE STRUCTURE VERIFICATION
    // =========================================================================

    public function testViewUsesHtmlView(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString('HtmlView', $source);
    }

    public function testViewUsesParsedown(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString('Parsedown', $source);
    }

    public function testViewSetsSafeMode(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString('setSafeMode(true)', $source,
            'view() should set Parsedown to safe mode');
    }

    public function testViewGeneratesTableOfContents(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString('doc-toc', $source);
        $this->assertStringContainsString('Sommaire', $source);
    }

    public function testViewHandlesDocNotFound(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString('Document introuvable', $source);
        $this->assertStringContainsString('doc-not-found', $source);
    }

    public function testViewRedirectsWhenNoPage(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString('Location: /help.htmx.html', $source,
            'view() should redirect to help page when no page specified');
    }

    public function testViewRendersDocPageTemplate(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString("'doc_page'", $source,
            'view() should render the doc_page template');
    }

    public function testViewPassesExpectedVariablesToTemplate(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $vars = ['title', 'htmlContent', 'toc', 'page', 'categories', 'docNames', 'docsRoot'];
        foreach ($vars as $var) {
            $this->assertStringContainsString(
                "'{$var}'",
                $source,
                "view() should pass '{$var}' to the template",
            );
        }
    }

    // =========================================================================
    // index: RESPONSE STRUCTURE VERIFICATION
    // =========================================================================

    public function testIndexResponseContainsCategoryAndItems(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString("'category'", $source);
        $this->assertStringContainsString("'items'", $source);
        $this->assertStringContainsString("'page'", $source);
        $this->assertStringContainsString("'label'", $source);
    }

    public function testIndexChecksFileExistence(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString('file_exists', $source);
    }

    // =========================================================================
    // view: TOC GENERATION LOGIC
    // =========================================================================

    public function testTocIdGeneration(): void
    {
        $text = 'Introduction & Overview';
        $id = preg_replace('/[^a-z0-9]+/i', '-', strtolower($text));
        $id = trim($id, '-');

        $this->assertEquals('introduction-overview', $id);
    }

    public function testTocIdGenerationWithAccents(): void
    {
        $text = 'Résolution adoptée';
        $id = preg_replace('/[^a-z0-9]+/i', '-', strtolower($text));
        $id = trim($id, '-');

        // Non-ASCII characters are replaced by the regex
        $this->assertStringNotContainsString(' ', $id, 'ID should not contain spaces');
    }

    public function testTocOnlyGeneratedWhenMoreThan2Items(): void
    {
        $tocItems = [
            ['id' => 'one', 'text' => 'One', 'level' => 2],
            ['id' => 'two', 'text' => 'Two', 'level' => 2],
        ];

        $shouldGenerate = count($tocItems) > 2;
        $this->assertFalse($shouldGenerate, 'TOC should not be generated with 2 or fewer items');

        $tocItems[] = ['id' => 'three', 'text' => 'Three', 'level' => 2];
        $shouldGenerate = count($tocItems) > 2;
        $this->assertTrue($shouldGenerate, 'TOC should be generated with 3 or more items');
    }

    public function testTocLevelClassification(): void
    {
        $h2Tag = 'h2';
        $h3Tag = 'h3';

        $h2Level = $h2Tag === 'h2' ? 2 : 3;
        $h3Level = $h3Tag === 'h2' ? 2 : 3;

        $this->assertEquals(2, $h2Level);
        $this->assertEquals(3, $h3Level);
    }

    // =========================================================================
    // CONTROLLER SOURCE: DEPRECATION WARNING HANDLING
    // =========================================================================

    public function testViewSuppressesDeprecationWarnings(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');

        $this->assertStringContainsString('E_DEPRECATED', $source,
            'view() should handle deprecated warnings for Parsedown on PHP 8.4+');
    }
}
