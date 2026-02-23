(function() {
    'use strict';

    // =========================================================================
    // CONFIGURATION
    // =========================================================================

    var DOC_TITLES = {
      'ANALYTICS_ETHICS': '\u00c9thique des Analytics',
      'FAQ': 'Foire aux questions',
      'README': 'Documentation',
      'INSTALLATION': 'Guide d\'installation',
      'ARCHITECTURE': 'Architecture',
      'API': 'Documentation API',
      'SECURITY': 'S\u00e9curit\u00e9',
      'MIGRATION': 'Migration',
      'TESTS': 'Tests',
      'UTILISATION_LIVE': 'Utilisation en direct',
      'RECETTE_DEMO': 'Recette de d\u00e9monstration',
      'AUDIT_RAPPORT': 'Rapport d\'audit',
      'CONFORMITE_CDC': 'Conformit\u00e9 au cahier des charges',
      'WEB_COMPONENTS': 'Composants Web',
      'DOCKER_INSTALL': 'Installation Docker (Linux)',
      'INSTALL_MAC': 'Installation macOS',
    };

    // =========================================================================
    // HELPERS
    // =========================================================================

    function esc(str) {
      if (!str) return '';
      return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function getDocPage() {
      var params = new URLSearchParams(window.location.search);
      var file = params.get('file') || 'docs/README.md';
      return file.replace(/^docs\//, '').replace(/\.md$/i, '');
    }

    function getTitleFromPage(page) {
      var filename = page.split('/').pop();
      return DOC_TITLES[filename] || filename.replace(/_/g, ' ');
    }

    // =========================================================================
    // DOC INDEX (sidebar)
    // =========================================================================

    function loadDocIndex(currentPage) {
      var container = document.getElementById('docIndex');

      fetch('/api/v1/doc_index.php')
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (!data.ok || !data.data) throw new Error('Index unavailable');

          var html = '<h4>Documentation</h4>';
          data.data.forEach(function(cat) {
            html += '<div class="doc-index-section">';
            html += '<div class="doc-index-title">' + esc(cat.category) + '</div>';
            html += '<ul>';
            cat.items.forEach(function(item) {
              var isActive = (item.page === currentPage) ? ' class="active"' : '';
              html += '<li' + isActive + '><a href="/docs.htmx.html?file=docs/'
                + encodeURIComponent(item.page) + '.md">'
                + esc(item.label) + '</a></li>';
            });
            html += '</ul></div>';
          });
          container.innerHTML = html;
        })
        .catch(function() {
          container.innerHTML = '<h4>Documentation</h4><p class="text-muted text-sm" style="padding:0.5rem">Index non disponible</p>';
        });
    }

    // =========================================================================
    // TOC GENERATION
    // =========================================================================

    function generateTOC(contentEl) {
      var headings = contentEl.querySelectorAll('h2, h3');
      var tocRail = document.getElementById('docTocRail');
      var tocList = document.getElementById('tocList');

      if (headings.length < 3) {
        tocRail.style.display = 'none';
        return;
      }

      var html = '';
      headings.forEach(function(heading, index) {
        var id = heading.id || 'heading-' + index;
        heading.id = id;
        var cls = heading.tagName === 'H3' ? ' class="toc-sub"' : '';
        html += '<li' + cls + '><a href="#' + id + '">' + esc(heading.textContent) + '</a></li>';
      });
      tocList.innerHTML = html;
      tocRail.style.display = '';

      // Scroll spy
      var tocLinks = tocList.querySelectorAll('a');
      var headingEls = [];
      tocLinks.forEach(function(link) {
        var el = document.getElementById(link.getAttribute('href').slice(1));
        if (el) headingEls.push({ el: el, link: link });
      });

      function updateActive() {
        var scrollTop = window.scrollY + 120;
        var current = null;
        for (var i = headingEls.length - 1; i >= 0; i--) {
          if (headingEls[i].el.offsetTop <= scrollTop) {
            current = headingEls[i];
            break;
          }
        }
        tocLinks.forEach(function(l) { l.classList.remove('active'); });
        if (current) current.link.classList.add('active');
      }

      window.addEventListener('scroll', updateActive, { passive: true });
      updateActive();

      // Smooth scroll on click
      tocLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
          var target = document.getElementById(this.getAttribute('href').slice(1));
          if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            history.replaceState(null, '', this.getAttribute('href'));
          }
        });
      });
    }

    // =========================================================================
    // INTERNAL LINK REWRITING
    // =========================================================================

    function rewriteLinks(contentEl, currentPage) {
      var currentDir = currentPage.split('/').slice(0, -1).join('/');

      contentEl.querySelectorAll('a').forEach(function(link) {
        var href = link.getAttribute('href');
        if (!href) return;

        if (href.match(/\.md$/i) && !href.match(/^https?:\/\//)) {
          var resolved = href;
          if (href.startsWith('./')) {
            resolved = (currentDir ? currentDir + '/' : '') + href.substring(2);
          } else if (href.startsWith('../')) {
            var parts = currentDir ? currentDir.split('/') : [];
            parts.pop();
            resolved = (parts.length ? parts.join('/') + '/' : '') + href.substring(3);
          } else if (!href.startsWith('/') && currentDir) {
            resolved = currentDir + '/' + href;
          } else if (href.startsWith('/')) {
            resolved = href.substring(1);
          }
          if (!resolved.startsWith('docs/')) {
            resolved = 'docs/' + resolved;
          }
          link.setAttribute('href', '/docs.htmx.html?file=' + encodeURIComponent(resolved));
          link.removeAttribute('target');
          return;
        }

        if (href.match(/^https?:\/\//)) {
          link.setAttribute('target', '_blank');
          link.setAttribute('rel', 'noopener');
        }
      });
    }

    // =========================================================================
    // CODE BLOCK COPY BUTTONS
    // =========================================================================

    function addCopyButtons(contentEl) {
      contentEl.querySelectorAll('pre').forEach(function(pre) {
        var wrapper = document.createElement('div');
        wrapper.className = 'code-block-wrapper';
        pre.parentNode.insertBefore(wrapper, pre);
        wrapper.appendChild(pre);

        var copyBtn = document.createElement('button');
        copyBtn.className = 'code-copy-btn';
        copyBtn.textContent = 'Copier';
        copyBtn.title = 'Copier le code';
        copyBtn.addEventListener('click', function() {
          var code = pre.querySelector('code');
          var text = code ? code.textContent : pre.textContent;
          navigator.clipboard.writeText(text).then(function() {
            copyBtn.textContent = 'Copi\u00e9 !';
            setTimeout(function() { copyBtn.textContent = 'Copier'; }, 2000);
          });
        });
        wrapper.appendChild(copyBtn);
      });
    }

    // =========================================================================
    // LOAD DOCUMENT
    // =========================================================================

    function loadDocument() {
      var page = getDocPage();
      var title = getTitleFromPage(page);
      var container = document.getElementById('docContent');

      document.getElementById('docTitle').textContent = title;
      document.getElementById('breadcrumbCurrent').textContent = title;
      document.title = title + ' \u2014 Documentation AG-VOTE';

      var dirParts = page.split('/');
      if (dirParts.length > 1) {
        document.getElementById('breadcrumbDir').textContent = dirParts.slice(0, -1).join('/');
        document.getElementById('breadcrumbDir').style.display = '';
        document.getElementById('breadcrumbDirSep').style.display = '';
      }

      loadDocIndex(page);

      fetch('/api/v1/doc_content.php?page=' + encodeURIComponent(page))
        .then(function(resp) {
          if (!resp.ok) {
            throw new Error(resp.status === 404
              ? 'Document non trouv\u00e9 : ' + page + '.md'
              : 'Erreur serveur (' + resp.status + ')');
          }
          return resp.text();
        })
        .then(function(markdown) {
          var html = marked.parse(markdown);
          html = html.replace(/<(script|iframe|object|embed)[^>]*>[\s\S]*?<\/\1>/gi, '')
                     .replace(/<(script|iframe|object|embed)[^>]*\/?>/gi, '');
          container.innerHTML = '<div class="prose">' + html + '</div>';

          generateTOC(container);
          rewriteLinks(container, page);
          addCopyButtons(container);
        })
        .catch(function(err) {
          container.innerHTML =
            '<div class="doc-not-found">' +
            '<h2>Document non disponible</h2>' +
            '<p>' + esc(err.message) + '</p>' +
            '<a href="/help.htmx.html" class="btn btn-primary">Retour \u00e0 l\'aide</a>' +
            '</div>';
          document.getElementById('docTocRail').style.display = 'none';
        });
    }

    // =========================================================================
    // INIT
    // =========================================================================

    loadDocument();
  })();
