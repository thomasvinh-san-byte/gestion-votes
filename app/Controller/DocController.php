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
        'Utilisateur' => ['README', 'FAQ', 'GUIDE_FONCTIONNEL', 'UTILISATION_LIVE', 'RECETTE_DEMO'],
        'Installation' => ['INSTALL_MAC', 'DOCKER_INSTALL', 'DEPLOIEMENT_DOCKER', 'DEPLOIEMENT_RENDER', 'GUIDE_TEST_LOCAL', 'dev/INSTALLATION'],
        'Technique' => ['dev/ARCHITECTURE', 'dev/API', 'dev/SECURITY', 'dev/TESTS', 'dev/WEB_COMPONENTS'],
        'Conformité' => ['dev/ANALYTICS_ETHICS', 'dev/cahier_des_charges'],
    ];

    private const DOC_NAMES = [
        'README' => 'Introduction',
        'FAQ' => 'FAQ',
        'GUIDE_FONCTIONNEL' => 'Guide fonctionnel',
        'UTILISATION_LIVE' => 'Guide opérateur',
        'RECETTE_DEMO' => 'Démo guidée',
        'INSTALL_MAC' => 'Installation macOS',
        'DOCKER_INSTALL' => 'Installation Docker (Linux)',
        'DEPLOIEMENT_DOCKER' => 'Déploiement Docker',
        'DEPLOIEMENT_RENDER' => 'Déploiement Render',
        'GUIDE_TEST_LOCAL' => 'Tests en local',
        'dev/INSTALLATION' => 'Installation (développeur)',
        'dev/ARCHITECTURE' => 'Architecture',
        'dev/API' => 'Référence API',
        'dev/SECURITY' => 'Sécurité',
        'dev/TESTS' => 'Tests',
        'dev/WEB_COMPONENTS' => 'Web Components',
        'dev/ANALYTICS_ETHICS' => 'Éthique & RGPD',
        'dev/cahier_des_charges' => 'Cahier des charges',
    ];

    /**
     * JSON API: list available documentation.
     */
    public function index(): void {
        $docsRoot = dirname(__DIR__, 2) . '/docs';

        // Build index from CATEGORIES — only include docs that exist on disk
        $result = [];
        $listed = [];
        foreach (self::CATEGORIES as $catName => $docs) {
            $items = [];
            foreach ($docs as $doc) {
                if (file_exists($docsRoot . '/' . $doc . '.md')) {
                    $items[] = [
                        'page' => $doc,
                        'label' => self::DOC_NAMES[$doc],
                    ];
                }
                $listed[$doc] = true;
            }
            if ($items) {
                $result[] = ['category' => $catName, 'items' => $items];
            }
        }

        // Auto-discover docs not in CATEGORIES (prevent new .md files from being invisible)
        $extras = [];
        foreach (self::discoverDocs($docsRoot) as $doc) {
            if (!isset($listed[$doc])) {
                $extras[] = [
                    'page' => $doc,
                    'label' => self::DOC_NAMES[$doc] ?? self::humanize($doc),
                ];
            }
        }
        if ($extras) {
            $result[] = ['category' => 'Autres', 'items' => $extras];
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
            return;
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

        $categories = self::CATEGORIES;

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

    /**
     * Scan /docs for .md files, return page keys like 'FAQ' or 'dev/API'.
     * @return string[]
     */
    private static function discoverDocs(string $docsRoot): array {
        $docs = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($docsRoot, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
                $rel = substr($file->getPathname(), strlen($docsRoot) + 1);
                $rel = str_replace('\\', '/', $rel);
                $docs[] = preg_replace('/\.md$/i', '', $rel);
            }
        }
        sort($docs);
        return $docs;
    }

    /**
     * Derive a human-readable label from a doc page key.
     */
    private static function humanize(string $page): string {
        $name = basename($page);
        return ucfirst(str_replace('_', ' ', strtolower($name)));
    }
}
