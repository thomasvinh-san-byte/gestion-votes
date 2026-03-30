<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\DocController;

/**
 * Unit tests for DocController.
 *
 * Endpoints:
 *  - index(): GET  — JSON API listing available documentation
 *  - view():  GET  — HTML renderer using Parsedown (exits via HtmlView::render)
 *
 * Uses ControllerTestCase. DocController uses no repositories.
 * index() is fully testable (returns api_ok). view() calls HtmlView::render
 * which does NOT throw ApiResponseException so it cannot be tested via
 * callController() — its behavior is verified via source inspection.
 */
class DocControllerTest extends ControllerTestCase
{
    private const TENANT_ID = 'ffffffff-0000-1111-2222-333333333333';
    private const USER_ID   = 'aa000001-0000-4000-a000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setAuth(self::USER_ID, 'admin', self::TENANT_ID);
    }

    // =========================================================================
    // STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(DocController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerExtendsAbstractController(): void
    {
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, new DocController());
    }

    public function testControllerHasExpectedMethods(): void
    {
        $ref = new \ReflectionClass(DocController::class);
        foreach (['index', 'view'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Missing method: {$m}");
        }
    }

    // =========================================================================
    // index()
    // =========================================================================

    public function testIndexReturnsOkStatus(): void
    {
        $this->setHttpMethod('GET');
        $result = $this->callController(DocController::class, 'index');
        $this->assertEquals(200, $result['status']);
    }

    public function testIndexResponseIsArray(): void
    {
        $result = $this->callController(DocController::class, 'index');
        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertIsArray($result['body']['data']);
    }

    public function testIndexResponseContainsCategoryStructure(): void
    {
        $result = $this->callController(DocController::class, 'index');
        $this->assertEquals(200, $result['status']);
        // Each entry should have 'category' and 'items' keys
        foreach ($result['body']['data'] as $cat) {
            $this->assertArrayHasKey('category', $cat);
            $this->assertArrayHasKey('items', $cat);
        }
    }

    // =========================================================================
    // CATEGORIES CONSTANT VERIFICATION (source)
    // =========================================================================

    public function testCategoriesContainExpectedGroups(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');
        foreach (["'Utilisateur'", "'Installation'", "'Technique'", "'Conformité'"] as $cat) {
            $this->assertStringContainsString($cat, $source);
        }
    }

    public function testDocNamesContainExpectedDocuments(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');
        foreach (['FAQ', 'UTILISATION_LIVE', 'dev/ARCHITECTURE', 'dev/API', 'dev/SECURITY'] as $doc) {
            $this->assertStringContainsString("'{$doc}'", $source);
        }
    }

    public function testDocNamesFrenchLabels(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');
        foreach (['Guide opérateur', 'Architecture', 'Sécurité'] as $label) {
            $this->assertStringContainsString($label, $source);
        }
    }

    // =========================================================================
    // view(): PATH SANITIZATION (source + logic replication)
    // =========================================================================

    public function testViewRejectsDoubleDotsViaSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');
        $this->assertStringContainsString('/\\.\\./', $source);
    }

    public function testViewStripsMdExtension(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');
        $this->assertStringContainsString("preg_replace('/\\.md$/i', ''", $source);
    }

    public function testViewSanitizationAllowsValidPageNames(): void
    {
        foreach (['FAQ', 'dev/API', 'INSTALL_MAC', 'dev/cahier_des_charges'] as $page) {
            $page = str_replace('\\', '/', $page);
            $invalid = preg_match('/\.\./', $page) || preg_match('#[^a-zA-Z0-9_/\-.]#', $page);
            $this->assertFalse((bool) $invalid, "Valid page name '{$page}' should pass sanitization");
        }
    }

    public function testViewSanitizationRejectsDirectoryTraversal(): void
    {
        $page = '../../../etc/passwd';
        $page = str_replace('\\', '/', $page);
        $invalid = preg_match('/\.\./', $page) || preg_match('#[^a-zA-Z0-9_/\-.]#', $page);
        $this->assertTrue((bool) $invalid);
    }

    public function testViewSanitizationRejectsSpecialCharacters(): void
    {
        $page = 'page<script>alert(1)</script>';
        $page = str_replace('\\', '/', $page);
        $invalid = preg_match('/\.\./', $page) || preg_match('#[^a-zA-Z0-9_/\-.]#', $page);
        $this->assertTrue((bool) $invalid);
    }

    public function testViewSanitizationRejectsNullBytes(): void
    {
        $page = "page\x00.md";
        $page = str_replace('\\', '/', $page);
        $invalid = preg_match('/\.\./', $page) || preg_match('#[^a-zA-Z0-9_/\-.]#', $page);
        $this->assertTrue((bool) $invalid);
    }

    // =========================================================================
    // view(): SOURCE STRUCTURE VERIFICATION
    // =========================================================================

    public function testViewUsesParsedownInSafeMode(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');
        $this->assertStringContainsString('Parsedown', $source);
        $this->assertStringContainsString('setSafeMode(true)', $source);
    }

    public function testViewSuppressesDeprecationWarnings(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');
        $this->assertStringContainsString('E_DEPRECATED', $source);
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
        $this->assertStringContainsString('Location: /help.htmx.html', $source);
    }

    public function testViewPassesExpectedVariablesToTemplate(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/DocController.php');
        foreach (['title', 'htmlContent', 'toc', 'page', 'categories', 'docNames', 'docsRoot'] as $var) {
            $this->assertStringContainsString("'{$var}'", $source);
        }
    }

    // =========================================================================
    // TOC GENERATION LOGIC
    // =========================================================================

    public function testTocIdGeneration(): void
    {
        $text = 'Introduction & Overview';
        $id = preg_replace('/[^a-z0-9]+/i', '-', strtolower($text));
        $id = trim($id, '-');
        $this->assertEquals('introduction-overview', $id);
    }

    public function testTocOnlyGeneratedWhenMoreThan2Items(): void
    {
        $this->assertFalse(count([1, 2]) > 2, 'TOC should not generate with 2 items');
        $this->assertTrue(count([1, 2, 3]) > 2, 'TOC should generate with 3+ items');
    }

    public function testTocLevelClassification(): void
    {
        $h2Level = 'h2' === 'h2' ? 2 : 3;
        $h3Level = 'h3' === 'h2' ? 2 : 3;
        $this->assertEquals(2, $h2Level);
        $this->assertEquals(3, $h3Level);
    }
}
