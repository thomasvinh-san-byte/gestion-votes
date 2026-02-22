<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\View\HtmlView;
use Parsedown;

/**
 * Consolidates doc_index.php (JSON API) and doc.php (HTML viewer).
 */
final class DocController extends AbstractController {
    private const CATEGORIES = [
        'Utilisateur' => ['FAQ', 'UTILISATION_LIVE', 'RECETTE_DEMO', 'ANALYTICS_ETHICS'],
        'Installation' => ['INSTALL_MAC', 'DOCKER_INSTALL', 'dev/INSTALLATION'],
        'Technique' => ['dev/ARCHITECTURE', 'dev/API', 'dev/SECURITY', 'dev/TESTS'],
        'Conformité' => ['dev/MIGRATION', 'dev/WEB_COMPONENTS'],
    ];

    private const DOC_NAMES = [
        'FAQ' => 'FAQ',
        'UTILISATION_LIVE' => 'Guide opérateur',
        'RECETTE_DEMO' => 'Démo guidée',
        'ANALYTICS_ETHICS' => 'Éthique & RGPD',
        'README' => 'Introduction',
        'INSTALL_MAC' => 'Installation macOS',
        'DOCKER_INSTALL' => 'Installation Docker (Linux)',
        'dev/INSTALLATION' => 'Installation (développeur)',
        'dev/ARCHITECTURE' => 'Architecture',
        'dev/API' => 'Référence API',
        'dev/SECURITY' => 'Sécurité',
        'dev/TESTS' => 'Tests',
        'dev/MIGRATION' => 'Migrations',
        'dev/WEB_COMPONENTS' => 'Web Components',
    ];

    /**
     * JSON API: list available documentation.
     */
    public function index(): void {
        $docsRoot = dirname(__DIR__, 2) . '/docs';

        $result = [];
        foreach (self::CATEGORIES as $catName => $docs) {
            $items = [];
            foreach ($docs as $doc) {
                if (file_exists($docsRoot . '/' . $doc . '.md')) {
                    $items[] = [
                        'page' => $doc,
                        'label' => self::DOC_NAMES[$doc] ?? basename($doc),
                    ];
                }
            }
            if ($items) {
                $result[] = ['category' => $catName, 'items' => $items];
            }
        }

        api_ok($result);
    }

    /**
     * HTML view: render a Markdown documentation page.
     *
     * Accessed at: /doc.php?page={page}
     */
    public function view(): void {
        $docsRoot = dirname(__DIR__, 2) . '/docs';

        $page = api_query('page');
        if ($page === '') {
            header('Location: /help.htmx.html');
            exit;
        }

        // Security: sanitize path
        $page = str_replace('\\', '/', $page);
        if (preg_match('/\.\./', $page) || preg_match('#[^a-zA-Z0-9_/\-.]#', $page)) {
            HtmlView::text('Paramètre page invalide.', 400);
        }

        $page = preg_replace('/\.md$/i', '', $page);
        $filePath = $docsRoot . '/' . $page . '.md';

        // Defense-in-depth: verify resolved path stays inside /docs
        $docsRealRoot = realpath($docsRoot);
        $realPath = realpath($filePath);
        if ($docsRealRoot !== false && $realPath !== false && !str_starts_with($realPath, $docsRealRoot . '/')) {
            HtmlView::text('Chemin invalide.', 400);
        }

        if (!file_exists($filePath) || !is_file($filePath)) {
            $title = 'Document introuvable';
            $htmlContent = '<div class="doc-not-found"><h2>Document introuvable</h2>'
                . '<p>Le fichier <code>' . htmlspecialchars($page . '.md') . '</code> n\'existe pas.</p>'
                . '<a href="/help.htmx.html" class="btn btn-primary">Retour &agrave; l\'aide</a></div>';
            $toc = '';
        } else {
            // Suppress Parsedown deprecation warnings on PHP 8.4+
            $prevErrorLevel = error_reporting(E_ALL & ~E_DEPRECATED);

            $parsedown = new Parsedown();
            $parsedown->setSafeMode(true);
            $htmlContent = $parsedown->text(file_get_contents($filePath));

            error_reporting($prevErrorLevel);

            // Extract title from first H1
            $title = $page;
            if (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $htmlContent, $matches)) {
                $title = strip_tags($matches[1]);
            }

            // Generate Table of Contents from H2/H3 headings
            $toc = '';
            $tocItems = [];
            $htmlContent = preg_replace_callback(
                '/<(h[23])([^>]*)>(.*?)<\/\1>/i',
                function ($m) use (&$tocItems) {
                    $tag = strtolower($m[1]);
                    $text = strip_tags($m[3]);
                    $id = preg_replace('/[^a-z0-9]+/i', '-', strtolower($text));
                    $id = trim($id, '-');
                    $level = $tag === 'h2' ? 2 : 3;
                    $tocItems[] = ['id' => $id, 'text' => $text, 'level' => $level];
                    return "<{$tag}{$m[2]} id=\"{$id}\">{$m[3]}</{$tag}>";
                },
                $htmlContent,
            );

            if (count($tocItems) > 2) {
                $toc = '<nav class="doc-toc" aria-label="Sommaire"><h4>Sommaire</h4><ul>';
                foreach ($tocItems as $item) {
                    $indent = $item['level'] === 3 ? ' class="toc-sub"' : '';
                    $toc .= "<li{$indent}><a href=\"#{$item['id']}\">" . htmlspecialchars($item['text']) . '</a></li>';
                }
                $toc .= '</ul></nav>';
            }
        }

        // View categories for sidebar (subset matching original doc.php)
        $categories = [
            'Utilisateur' => ['FAQ', 'UTILISATION_LIVE', 'RECETTE_DEMO', 'ANALYTICS_ETHICS'],
            'Technique' => ['dev/INSTALLATION', 'dev/ARCHITECTURE', 'dev/API', 'dev/SECURITY', 'dev/TESTS'],
            'Conformité' => ['dev/MIGRATION', 'dev/WEB_COMPONENTS'],
        ];

        $statusCode = ($title === 'Document introuvable') ? 404 : 200;

        HtmlView::render('doc_page', [
            'title' => $title,
            'htmlContent' => $htmlContent,
            'toc' => $toc,
            'page' => $page,
            'categories' => $categories,
            'docNames' => self::DOC_NAMES,
            'docsRoot' => $docsRoot,
        ], $statusCode);
    }
}
