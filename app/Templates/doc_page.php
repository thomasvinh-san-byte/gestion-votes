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
