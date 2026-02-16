<?php
/**
 * Documentation Viewer — AG-VOTE
 *
 * Renders Markdown documentation files within the application's design system.
 * Reads .md files from the /docs directory server-side (bypassing .htaccess block),
 * parses them with Parsedown, and displays them in a styled page with sidebar navigation.
 *
 * Usage: /doc.php?page=FAQ  or  /doc.php?page=dev/API
 */

declare(strict_types=1);

// ─── Bootstrap (lightweight, no DB needed) ───
$docsRoot = __DIR__ . '/docs';

// ─── Autoload ───
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo 'Erreur : vendor/autoload.php introuvable. Exécutez composer install.';
    exit;
}
require_once $autoload;

// ─── Validate page parameter ───
$page = $_GET['page'] ?? '';

if ($page === '') {
    // Redirect to the help page if no page specified
    header('Location: /help.htmx.html');
    exit;
}

// Security: sanitize path — only allow alphanumeric, hyphens, underscores, slashes, dots
// Prevent directory traversal
$page = str_replace('\\', '/', $page);
if (preg_match('/\.\./', $page) || preg_match('#[^a-zA-Z0-9_/\-.]#', $page)) {
    http_response_code(400);
    echo 'Paramètre page invalide.';
    exit;
}

// Strip .md extension if provided (we'll add it back)
$page = preg_replace('/\.md$/i', '', $page);

$filePath = $docsRoot . '/' . $page . '.md';

if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    // Show a nice 404 within the app shell
    $title = 'Document introuvable';
    $htmlContent = '<div class="doc-not-found"><h2>Document introuvable</h2>'
        . '<p>Le fichier <code>' . htmlspecialchars($page . '.md') . '</code> n\'existe pas.</p>'
        . '<a href="/help.htmx.html" class="btn btn-primary">Retour à l\'aide</a></div>';
    $toc = '';
} else {
    // ─── Read and parse Markdown ───
    $markdown = file_get_contents($filePath);

    $parsedown = new Parsedown();
    $parsedown->setSafeMode(true);
    // Parsedown 1.7 triggers PHP 8.4 deprecation warnings (implicit nullable params)
    $prevLevel = error_reporting(E_ALL & ~E_DEPRECATED);
    $htmlContent = $parsedown->text($markdown);
    error_reporting($prevLevel);

    // ─── Extract title from first H1 ───
    $title = $page;
    if (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $htmlContent, $matches)) {
        $title = strip_tags($matches[1]);
    }

    // ─── Generate Table of Contents from H2/H3 headings ───
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
        $htmlContent
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

// ─── Build the document index for the sidebar ───
$docIndex = [];
$categories = [
    'Utilisateur' => ['FAQ', 'UTILISATION_LIVE', 'RECETTE_DEMO', 'ANALYTICS_ETHICS'],
    'Technique'   => ['dev/INSTALLATION', 'dev/ARCHITECTURE', 'dev/API', 'dev/SECURITY', 'dev/TESTS'],
    'Conformité'  => ['dev/CONFORMITE_CDC', 'dev/AUDIT_RAPPORT', 'dev/MIGRATION', 'dev/WEB_COMPONENTS'],
];

// Friendly names for doc files
$docNames = [
    'FAQ' => 'FAQ',
    'UTILISATION_LIVE' => 'Guide opérateur',
    'RECETTE_DEMO' => 'Démo guidée',
    'ANALYTICS_ETHICS' => 'Éthique & RGPD',
    'README' => 'Introduction',
    'dev/INSTALLATION' => 'Installation',
    'dev/ARCHITECTURE' => 'Architecture',
    'dev/API' => 'Référence API',
    'dev/SECURITY' => 'Sécurité',
    'dev/TESTS' => 'Tests',
    'dev/CONFORMITE_CDC' => 'Conformité CDC',
    'dev/AUDIT_RAPPORT' => 'Rapport d\'audit',
    'dev/MIGRATION' => 'Migrations',
    'dev/WEB_COMPONENTS' => 'Web Components',
    'dev/PLAN_HARMONISATION' => 'Plan harmonisation',
    'dev/cahier_des_charges' => 'Cahier des charges',
    'AUDIT_REPORT_2026-02-06' => 'Audit 2026-02-06',
];
?>
<!doctype html>
<html lang="fr">
<head>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= htmlspecialchars($title) ?> — Documentation AG-VOTE">
  <title><?= htmlspecialchars($title) ?> — Documentation AG-VOTE</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="stylesheet" href="/assets/css/doc.css">
</head>
<body>
  <div class="app-shell">
    <aside class="app-sidebar" data-include-sidebar data-page="help"></aside>

    <div class="doc-page-header">
      <div class="doc-breadcrumb">
        <a href="/help.htmx.html">Aide</a>
        <svg class="icon icon-xs" aria-hidden="true"><use href="/assets/icons.svg#icon-chevron-right"></use></svg>
        <span>Documentation</span>
<?php if (str_contains($page, '/')): ?>
        <svg class="icon icon-xs" aria-hidden="true"><use href="/assets/icons.svg#icon-chevron-right"></use></svg>
        <span><?= htmlspecialchars(dirname($page)) ?></span>
<?php endif; ?>
      </div>
      <h1><?= htmlspecialchars($title) ?></h1>
    </div>

    <main class="app-main doc-main">
      <div class="doc-layout">
        <!-- LEFT: Doc Index -->
        <aside class="doc-sidebar">
          <nav class="doc-index" aria-label="Index des documents">
            <h4>Documentation</h4>
<?php foreach ($categories as $catName => $catDocs): ?>
            <div class="doc-index-section">
              <div class="doc-index-title"><?= htmlspecialchars($catName) ?></div>
              <ul>
<?php foreach ($catDocs as $doc):
    $isActive = ($doc === $page) ? ' class="active"' : '';
    $label = $docNames[$doc] ?? basename($doc);
    $exists = file_exists($docsRoot . '/' . $doc . '.md');
?>
<?php if ($exists): ?>
                <li<?= $isActive ?>><a href="/doc.php?page=<?= urlencode($doc) ?>"><?= htmlspecialchars($label) ?></a></li>
<?php endif; ?>
<?php endforeach; ?>
              </ul>
            </div>
<?php endforeach; ?>
          </nav>
        </aside>

        <!-- CENTER: rendered Markdown -->
        <article class="doc-content prose">
          <?= $htmlContent ?>
        </article>

        <!-- RIGHT: Table of Contents (sticky) -->
<?php if ($toc): ?>
        <aside class="doc-toc-rail" aria-label="Sommaire">
          <?= $toc ?>
        </aside>
<?php endif; ?>
      </div>
    </main>
  </div>

  <script src="/assets/js/core/utils.js"></script>
  <script src="/assets/js/core/shared.js"></script>
  <script src="/assets/js/core/shell.js"></script>
  <script src="/assets/js/pages/auth-ui.js"></script>

  <script>
  (function() {
    'use strict';

    // Smooth scroll for TOC links
    document.querySelectorAll('.doc-toc a, .doc-content a[href^="#"]').forEach(function(link) {
      link.addEventListener('click', function(e) {
        var href = this.getAttribute('href');
        if (href && href.startsWith('#')) {
          var target = document.getElementById(href.slice(1));
          if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            history.replaceState(null, '', href);
          }
        }
      });
    });

    // Highlight current TOC item on scroll
    var tocLinks = document.querySelectorAll('.doc-toc a');
    if (tocLinks.length) {
      var headings = [];
      tocLinks.forEach(function(link) {
        var id = link.getAttribute('href').slice(1);
        var el = document.getElementById(id);
        if (el) headings.push({ el: el, link: link });
      });

      function updateActiveToc() {
        var scrollTop = window.scrollY + 100;
        var current = null;
        for (var i = headings.length - 1; i >= 0; i--) {
          if (headings[i].el.offsetTop <= scrollTop) {
            current = headings[i];
            break;
          }
        }
        tocLinks.forEach(function(l) { l.classList.remove('active'); });
        if (current) current.link.classList.add('active');
      }

      window.addEventListener('scroll', updateActiveToc, { passive: true });
      updateActiveToc();
    }

    // Convert internal doc links (relative .md links) to doc.php links
    document.querySelectorAll('.doc-content a').forEach(function(link) {
      var href = link.getAttribute('href');
      if (href && href.match(/\.md$/i) && !href.match(/^https?:\/\//)) {
        // Resolve relative path
        var currentDir = '<?= htmlspecialchars(dirname($page)) ?>';
        var resolved = href;
        if (currentDir !== '.' && !href.startsWith('/')) {
          resolved = currentDir + '/' + href;
        }
        resolved = resolved.replace(/\.md$/i, '');
        link.setAttribute('href', '/doc.php?page=' + encodeURIComponent(resolved));
        link.removeAttribute('target');
      }
    });
  })();
  </script>
</body>
</html>
