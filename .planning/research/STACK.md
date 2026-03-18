# Technology Stack Research — v4.0 "Clarity & Flow"

**Project:** AG-VOTE v4.0
**Domain:** Guided UX, inline PDF viewer, file upload, CSS polish — vanilla JS + no-framework PHP
**Researched:** 2026-03-18
**Overall Confidence:** HIGH (all major findings verified against official documentation, CDN files, or multiple authoritative sources)

---

> **SCOPE NOTICE:** This file covers v4.0 "Clarity & Flow" exclusively.
> v3.0 SSE/API research is archived in `.planning/milestones/v3.0-phases/`.
> This milestone adds new UI capabilities — tours, inline PDFs, file upload, CSS polish.
> The existing stack (PHP 8.4, PostgreSQL, Redis, vanilla JS, Web Components, IIFE pattern) is unchanged.

---

## 1. Guided Tour Library

### Recommendation: Driver.js v1.4.0

**Rationale:** MIT license (critical — Shepherd.js and Intro.js are AGPL), zero dependencies, smallest bundle, IIFE global available, actively maintained (v1.4.0 shipped November 2025). Fits the vanilla IIFE+var codebase without any adaptation.

### Comparison Matrix

| Criterion | Driver.js | Shepherd.js | Intro.js |
|-----------|-----------|-------------|----------|
| License | **MIT** | AGPL-3.0 / Commercial | AGPL-3.0 / Commercial |
| Commercial use | **Free** | Paid license needed | Paid license needed |
| IIFE global bundle | **Yes** (`driver.js.iife.js`) | Via CDN | Yes |
| Bundle size (minified) | **20.8 KB** | ~45 KB | ~25 KB |
| Bundle size (gzipped) | **~7 KB** | ~15 KB | ~9 KB |
| Zero dependencies | **Yes** | No (Floating UI) | Yes |
| Vanilla JS native | **Yes** | Yes (with wrappers) | Yes |
| GitHub stars (2026) | ~22k | ~12k | ~22k |
| Latest version | **1.4.0 (Nov 2025)** | 14.x | 7.x |
| Multi-page tours | Manual (hooks) | Manual (hooks) | Manual (hooks) |
| Popover positioning | Built-in | Floating UI | Built-in |
| Custom step CSS | Full control | Full control | Moderate |

**Winner: Driver.js** — license is the deciding factor. AGPL requires open-sourcing any software that incorporates it (including internal tools shipped to users). AG-VOTE is self-hosted and could be used commercially by clients. MIT is the only safe choice.

### CDN URLs

```html
<!-- Driver.js v1.4.0 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.4.0/dist/driver.css">
<script src="https://cdn.jsdelivr.net/npm/driver.js@1.4.0/dist/driver.js.iife.js"></script>
```

Files in distribution (v1.4.0):
- `driver.js.iife.js` — 20.8 KB minified (IIFE global, use this)
- `driver.js.mjs` — 26.6 KB (ES module, for bundlers)
- `driver.js.cjs` — 20.7 KB (CommonJS)
- `driver.css` — 3.85 KB

### IIFE Integration Pattern

Driver.js IIFE exposes `window.driver.js.driver`. Fits the IIFE+var pattern exactly:

```javascript
// In any page IIFE (wizard.js, hub.js, etc.)
(function () {
  'use strict';

  var driver = window.driver.js.driver;

  function initWizardTour() {
    var driverObj = driver({
      showProgress: true,
      animate: true,
      smoothScroll: true,
      onDestroyStarted: function () {
        // save tour-dismissed state to localStorage
        localStorage.setItem('ag-tour-wizard-dismissed', '1');
        driverObj.destroy();
      },
      steps: [
        {
          element: '#step-meeting-info',
          popover: {
            title: 'Informations de la réunion',
            description: 'Renseignez le titre, la date et le lieu de l\'assemblée.',
            side: 'right',
            align: 'start'
          }
        },
        {
          element: '#step-members',
          popover: {
            title: 'Liste des membres',
            description: 'Importez ou saisissez les membres avec leurs droits de vote.',
            side: 'left'
          }
        }
      ]
    });

    // Only show if not previously dismissed
    if (!localStorage.getItem('ag-tour-wizard-dismissed')) {
      driverObj.drive();
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    initWizardTour();
  });
})();
```

### Multi-Page Tour Strategy

Driver.js does not persist state across full page navigations natively. The recommended pattern for AG-VOTE:

1. Store tour progress in `localStorage` (key: `ag-tour-step`, value: step index)
2. On `onNextClick` of a cross-page step, call `window.location.href = '/next-page.html?tourStep=3'`
3. On the destination page, check URL param `tourStep` on load and resume from that step

```javascript
// Cross-page step — last step of page A
{
  element: '#btn-go-to-hub',
  popover: {
    title: 'Votre tableau de bord',
    description: 'Cliquez pour accéder au hub de session.',
    onNextClick: function () {
      localStorage.setItem('ag-tour-step', '1');
      window.location.href = '/hub.htmx.html?id=' + meetingId + '&tour=1';
    }
  }
}

// hub.js — resume tour if ?tour=1 in URL
var urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('tour') === '1') {
  startHubTour(parseInt(localStorage.getItem('ag-tour-step') || '0', 10));
}
```

### Confidence: HIGH

Verified against: official driverjs.com docs, jsDelivr CDN directory listing (confirmed 20.8 KB IIFE), Inline Manual comparison article (confirmed MIT vs AGPL licensing).

---

## 2. Inline PDF Viewer

### Recommendation: PDF.js v5 Prebuilt Web Viewer (iframe embed)

**Rationale:** PDF.js (Mozilla) is the gold standard for browser-native PDF rendering. The prebuilt web viewer approach — serving the prebuilt `web/viewer.html` on the same origin and embedding it in an iframe — requires zero JavaScript integration, works without any build step, and handles all rendering complexity internally. It is how Firefox renders PDFs natively.

### Options Compared

| Approach | Pros | Cons | Verdict |
|----------|------|------|---------|
| **PDF.js prebuilt viewer (iframe)** | Zero JS integration, full-featured toolbar, full text search, mobile-ready | iframe overhead, same-origin required | **RECOMMENDED** |
| PDF.js programmatic API | Maximum control, no iframe | Complex setup, worker required via CDN, ~500 KB transfer | Use for custom UI only |
| Native `<embed>` / `<object>` | Single tag | Browser-dependent, Chrome hides toolbar, no control, Safari downloads | Avoid |
| Native `<iframe src="doc.pdf">` | Single tag | Same as embed — no control, Chrome shows own toolbar | Avoid |
| pdf-lib | PDF creation/editing in JS | Not a viewer | Wrong tool |

### PDF.js Prebuilt Web Viewer Approach

**How it works:**
1. Download the prebuilt distribution from GitHub releases or install `pdfjs-dist` (it includes the viewer)
2. Copy the `web/` and `build/` directories to `public/assets/pdfjs/`
3. Serve at `/assets/pdfjs/web/viewer.html`
4. Embed in voter view via iframe with PDF URL as query param

**CDN options:**
- jsDelivr: `https://www.jsdelivr.com/package/npm/pdfjs-dist`
- unpkg: `https://unpkg.com/pdfjs-dist/`
- cdnjs: `https://cdnjs.com/libraries/pdf.js`

Current stable version: **v5.5.207** (as of March 2026)

**Self-hosting the viewer is strongly preferred** over CDN for the viewer HTML, because:
- Viewer HTML references relative paths for viewer assets
- Same-origin requirement for `workerSrc`
- PDF documents must be same-origin OR served with correct CORS headers

### Implementation for Voter View

```html
<!-- In voter view (vote.php / vote.html) -->
<div class="resolution-pdf-viewer" id="pdf-viewer-container" hidden>
  <iframe
    id="pdf-viewer-frame"
    title="Document de résolution"
    width="100%"
    height="600"
    loading="lazy"
    src=""
    aria-label="Visionneuse PDF"
  ></iframe>
</div>
```

```javascript
// In vote.js IIFE — open PDF viewer
function showResolutionPDF(pdfUrl) {
  var frame = document.getElementById('pdf-viewer-frame');
  var container = document.getElementById('pdf-viewer-container');

  // Encode the PDF URL and point to local viewer
  var viewerUrl = '/assets/pdfjs/web/viewer.html?file=' + encodeURIComponent(pdfUrl);
  frame.src = viewerUrl;
  container.hidden = false;
}
```

### Programmatic API (for custom viewer without iframe)

If a frameless embedded experience is needed (e.g., custom toolbar within the voting UI), use the programmatic API:

```html
<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@5.5.207/build/pdf.min.mjs" type="module"></script>
```

```javascript
// ES module approach (in a Web Component is ideal for isolation)
import * as pdfjsLib from 'https://cdn.jsdelivr.net/npm/pdfjs-dist@5.5.207/build/pdf.min.mjs';

pdfjsLib.GlobalWorkerOptions.workerSrc =
  'https://cdn.jsdelivr.net/npm/pdfjs-dist@5.5.207/build/pdf.worker.min.mjs';

async function renderPDF(url, canvas) {
  const loadingTask = pdfjsLib.getDocument(url);
  const pdf = await loadingTask.promise;
  const page = await pdf.getPage(1);
  const viewport = page.getViewport({ scale: 1.5 });
  const ctx = canvas.getContext('2d');
  canvas.width = viewport.width;
  canvas.height = viewport.height;
  await page.render({ canvasContext: ctx, viewport }).promise;
}
```

**Worker file is mandatory** — the `pdf.worker.min.mjs` must be available. Use CDN URL for the worker.

### Bundle Size (pdfjs-dist v5.x)

- `pdf.min.mjs` (display layer): ~180 KB minified, ~50 KB gzipped
- `pdf.worker.min.mjs` (render engine): ~800 KB minified, ~220 KB gzipped (loaded in a Web Worker, does not block main thread)
- Prebuilt viewer HTML + assets: ~150 KB additional

The worker is large but loads asynchronously in a Web Worker — it does not block rendering. For voter view where PDF consultation is optional and secondary to voting, lazy loading with `loading="lazy"` on the iframe is sufficient.

### Storage & Serving Strategy for PDFs

**Private storage (recommended):** PDFs are sensitive resolution documents. Store outside webroot.

```
/var/uploads/resolutions/{meeting_id}/{uuid}.pdf  (outside /public)
```

**Auth-gated serving via PHP:**

```php
// public/api/v1/resolution_pdf.php
// Validates session, checks member access to meeting, streams PDF
$meetingId = (int)($_GET['meeting_id'] ?? 0);
$fileId    = preg_replace('/[^a-f0-9\-]/', '', $_GET['file_id'] ?? '');

// Auth check + meeting membership check
// ...

$path = "/var/uploads/resolutions/{$meetingId}/{$fileId}.pdf";
if (!file_exists($path)) {
    http_response_code(404); exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="resolution.pdf"');
header('Cache-Control: private, max-age=3600');
readfile($path);
```

The voter calls `/api/v1/resolution_pdf.php?meeting_id=X&file_id=Y` which PHP streams after auth check. The PDF.js viewer receives this URL as its `file` param.

### Confidence: HIGH (MEDIUM for programmatic API approach)

Verified against: official PDF.js getting started docs, jsDelivr CDN listing, pdfjs-dist npm page, Mozilla GitHub repository.

---

## 3. File Upload

### Recommendation: FilePond v4.32.12

**Rationale:** MIT license, vanilla JS first (no framework required), CDN available, 21 KB gzipped for the core, excellent drag-and-drop UX out of the box, plugin system for PDF validation, works with any backend via standard multipart form POST.

### Options Compared

| Library | License | IIFE/CDN | Gzipped | DX | PHP compat | Verdict |
|---------|---------|----------|---------|-----|------------|---------|
| **FilePond** | MIT | Yes | ~21 KB | Excellent | Yes | **RECOMMENDED** |
| Dropzone.js | MIT | Yes | ~22 KB | Good | Yes | Alternative |
| Uppy | MIT | Yes | ~40 KB core | Good | Yes | Too heavy |
| Hand-rolled | — | — | ~2 KB | Dev effort | Yes | For simple cases |

**FilePond wins** because: polished default UI that matches top-1% visual quality goal, animation built in, image preview plugin, file validation plugin, accessible ARIA labels, maintains ARIA live region for screen readers.

### CDN URLs (FilePond v4.32.12)

```html
<!-- Core -->
<link rel="stylesheet"
  href="https://cdn.jsdelivr.net/npm/filepond@4.32.12/dist/filepond.min.css">
<script
  src="https://cdn.jsdelivr.net/npm/filepond@4.32.12/dist/filepond.min.js"></script>

<!-- Plugin: validate file type (whitelist PDF only) -->
<script
  src="https://cdn.jsdelivr.net/npm/filepond-plugin-file-validate-type@1.2.9/dist/filepond-plugin-file-validate-type.min.js"></script>

<!-- Plugin: validate file size (max 10 MB) -->
<script
  src="https://cdn.jsdelivr.net/npm/filepond-plugin-file-validate-size@2.2.9/dist/filepond-plugin-file-validate-size.min.js"></script>
```

File sizes (v4.32.12, from jsDelivr):
- `filepond.min.js` — 115.5 KB minified (gzipped: ~21 KB)
- `filepond.min.css` — 17.1 KB minified (gzipped: ~4 KB)

### IIFE Integration Pattern

FilePond exposes a `FilePond` global when loaded via script tag:

```javascript
// In wizard.js IIFE (resolution attachment step)
(function () {
  'use strict';

  function initFileUpload(inputEl) {
    // Register plugins before creating pond
    FilePond.registerPlugin(
      FilePondPluginFileValidateType,
      FilePondPluginFileValidateSize
    );

    var pond = FilePond.create(inputEl, {
      acceptedFileTypes: ['application/pdf'],
      labelFileTypeNotAllowed: 'Seuls les fichiers PDF sont acceptés',
      maxFileSize: '10MB',
      labelMaxFileSizeExceeded: 'Le fichier dépasse 10 Mo',
      allowMultiple: false,
      server: {
        // FilePond uploads to a dedicated endpoint during wizard session
        url: '/api/v1/resolution_upload.php',
        process: {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          // FilePond sends file, receives JSON with file_id
          onload: function (response) {
            var data = JSON.parse(response);
            // Store file_id for inclusion in wizard payload
            document.getElementById('resolution-file-id').value = data.file_id;
            return data.file_id;
          }
        },
        // Allow reverting (delete from server) if user removes file
        revert: '/api/v1/resolution_upload_revert.php'
      },
      labelIdle: 'Glissez le PDF ici ou <span class="filepond--label-action">parcourir</span>',
      styleButtonRemoveItemPosition: 'right'
    });

    return pond;
  }

  // Attach to input in resolution form step
  document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('resolution-pdf-input');
    if (el) initFileUpload(el);
  });
})();
```

### PHP Backend for File Upload

**Endpoint:** `public/api/v1/resolution_upload.php`

```php
<?php
// Only accept PDF; store outside webroot; return file_id
require_once __DIR__ . '/../../../app/bootstrap.php';

// Auth check
$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_fail('Method not allowed', 405);
}

$file = $_FILES['filepond'] ?? null; // FilePond's default field name is 'filepond'
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    api_fail('Upload failed', 400);
}

// 1. Size check (belt + suspenders — php.ini sets hard max)
if ($file['size'] > 10 * 1024 * 1024) {
    api_fail('File too large', 413);
}

// 2. MIME check via finfo (not $_FILES['type'] which is client-supplied and spoofable)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if ($mime !== 'application/pdf') {
    api_fail('Only PDF files are accepted', 415);
}

// 3. Magic bytes check (PDF starts with %PDF)
$handle = fopen($file['tmp_name'], 'rb');
$magic  = fread($handle, 4);
fclose($handle);
if ($magic !== '%PDF') {
    api_fail('Invalid PDF file', 415);
}

// 4. Generate safe filename
$fileId  = bin2hex(random_bytes(16)); // UUID-like, no extension in storage
$meetingId = (int)($_POST['meeting_id'] ?? 0);

$destDir = "/var/uploads/resolutions/{$meetingId}";
if (!is_dir($destDir)) {
    mkdir($destDir, 0750, true);
}

$destPath = "{$destDir}/{$fileId}.pdf";
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    api_fail('Storage error', 500);
}

// Return file_id to FilePond (FilePond stores this as the server-side ID)
api_ok(['file_id' => $fileId, 'meeting_id' => $meetingId]);
```

**php.ini / upload configuration** (in `deploy/php.ini`):

```ini
; Resolution PDF uploads (max 10 MB per file)
upload_max_filesize = 10M
post_max_size       = 12M
max_file_uploads    = 1
```

### Dropzone.js as Alternative

If FilePond's animation or styling proves incompatible with design goals, Dropzone.js v6 is a solid fallback:

```html
<link href="https://cdn.jsdelivr.net/npm/dropzone@6.0.0/dist/dropzone.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/dropzone@6.0.0/dist/dropzone.min.js"></script>
```

Dropzone attaches to a `<form>` element directly. Gzipped ~22 KB. The API is more verbose than FilePond but works identically with PHP backends. Dropzone v6 dropped jQuery dependency.

### Confidence: HIGH

Verified against: FilePond GitHub repo, jsDelivr CDN file listing (confirmed 115.5 KB min, 17.1 KB min CSS), OWASP file upload cheat sheet for PHP security pattern.

---

## 4. CSS Techniques for Top 1% Polish (2026 Production-Ready)

All techniques below are confirmed Baseline Available or Baseline Newly Available as of March 2026. No polyfills required for users on browsers released in the last 18 months.

### 4.1 View Transitions API

**Status: Baseline Newly Available (October 2025)**
**Browser support:** Chrome 111+, Edge 111+, Firefox 133+, Safari 18+

Same-document transitions are safe to use without fallback for a PC-first (1024px+) app in 2026. Cross-document transitions (between separate pages) work in Chrome 126+/Edge 126+/Safari 18.2+ but not yet Firefox — use feature detection.

**Use for:**
- Tab switching in operator console
- Wizard step transitions
- Modal open/close
- Panel slides

```javascript
// In IIFE code — wrap state changes in view transition
function switchTab(tabId) {
  if (!document.startViewTransition) {
    // Fallback: instant switch for older browsers
    _doTabSwitch(tabId);
    return;
  }

  document.startViewTransition(function () {
    _doTabSwitch(tabId);
  });
}
```

```css
/* Slide the new tab in from right */
::view-transition-new(main-content) {
  animation: slide-in-right 200ms var(--ease-out) both;
}
::view-transition-old(main-content) {
  animation: slide-out-left 200ms var(--ease-out) both;
}

@keyframes slide-in-right {
  from { transform: translateX(20px); opacity: 0; }
  to   { transform: translateX(0);    opacity: 1; }
}
@keyframes slide-out-left {
  from { transform: translateX(0);    opacity: 1; }
  to   { transform: translateX(-20px); opacity: 0; }
}

/* Assign view-transition-name to the element */
.main-content {
  view-transition-name: main-content;
}
```

### 4.2 @starting-style — Entry Animations Without JavaScript

**Status: Baseline Newly Available (August 2024)**
**Browser support:** Chrome 117+, Edge 117+, Firefox 129+, Safari 17.5+
**Global support: ~86% (March 2026)**

Replaces the setTimeout(0) hack for CSS transitions on newly inserted elements. Critical for modal open animations, toast entry, tour bubbles, dropdown appears.

```css
/* Modal slides up from below when opened */
dialog[open] {
  transform: translateY(0);
  opacity: 1;
  transition: transform 300ms var(--ease-out),
              opacity 300ms var(--ease-out),
              display 300ms allow-discrete,
              overlay 300ms allow-discrete;
}

@starting-style {
  dialog[open] {
    transform: translateY(24px);
    opacity: 0;
  }
}

/* Toast appears from bottom-right */
.ag-toast[data-visible="true"] {
  transform: translateY(0);
  opacity: 1;
  transition: transform 200ms var(--ease-out),
              opacity 200ms var(--ease-out),
              display 200ms allow-discrete;
}

@starting-style {
  .ag-toast[data-visible="true"] {
    transform: translateY(12px);
    opacity: 0;
  }
}
```

**Progressive enhancement:** browsers that don't support `@starting-style` show the element immediately without animation — acceptable graceful degradation.

### 4.3 CSS :has() Selector

**Status: Baseline (widely available)**
**Browser support:** Chrome 105+, Edge 105+, Firefox 121+, Safari 15.4+
**Global support: >92%**

Use for parent-based conditional styling without JavaScript class toggling:

```css
/* Form group shows error styling when its input is :invalid AND :not(:placeholder-shown) */
.form-group:has(input:invalid:not(:placeholder-shown)) .form-label {
  color: var(--color-danger);
}

/* Sidebar expanded state: body has sidebar-open class */
body:has(.sidebar[data-expanded="true"]) .main-content {
  margin-left: var(--sidebar-expanded);
  transition: margin-left var(--duration-slow) var(--ease-out);
}

/* Card gets hover ring only when it contains a focusable element that is focused */
.card:has(:focus-visible) {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

/* Wizard step indicator active state without JS class injection */
.wizard-nav:has([data-step="2"][aria-current="step"]) .step-indicator[data-for="2"] {
  background: var(--color-primary);
}
```

### 4.4 Container Queries

**Status: Baseline (widely available)**
**Browser support:** Chrome 105+, Edge 105+, Firefox 110+, Safari 16+
**Global support: >93%**

Essential for the 20 Web Components that must be responsive to their container, not the viewport. Each component declares its own containment.

```css
/* ag-kpi component — adapts layout based on its own width */
ag-kpi {
  container-type: inline-size;
  container-name: kpi-card;
}

@container kpi-card (min-width: 280px) {
  .kpi-value {
    font-size: var(--text-4xl);
  }
}

@container kpi-card (max-width: 160px) {
  .kpi-label {
    display: none; /* compact mode */
  }
}

/* ag-vote-button — stack vs inline depending on available space */
ag-vote-button {
  container-type: inline-size;
}

@container (max-width: 200px) {
  .vote-btn-grid {
    grid-template-columns: 1fr;
  }
}
```

### 4.5 CSS Scroll-Driven Animations

**Status: Baseline Newly Available (2025)**
**Browser support:** Chrome 115+, Edge 115+, Firefox 126+, Safari 18+

Use for:
- Sticky header shrink on scroll
- Progress indicator as user scrolls through wizard
- List item fade-in as they enter the viewport (no IntersectionObserver needed)

```css
/* Header shrinks as user scrolls down */
@keyframes shrink-header {
  from { height: 64px; box-shadow: none; }
  to   { height: 48px; box-shadow: var(--shadow-sm); }
}

.app-header {
  animation: shrink-header linear both;
  animation-timeline: scroll(root block);
  animation-range: 0px 80px;
}

/* List items fade in as they scroll into view */
@keyframes fade-in-up {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

.motion-card {
  animation: fade-in-up linear both;
  animation-timeline: view();
  animation-range: entry 0% entry 30%;
}
```

**Use `@supports` for progressive enhancement:**

```css
@supports (animation-timeline: scroll()) {
  .app-header {
    animation: shrink-header linear both;
    animation-timeline: scroll(root block);
    animation-range: 0px 80px;
  }
}
```

### 4.6 color-mix() for Dynamic Color Variations

**Status: Baseline (widely available)**
**Browser support:** Chrome 111+, Edge 111+, Firefox 113+, Safari 16.2+
**Global support: >92%**

Replaces SCSS color functions for generating tints, shades, and alpha variants from design tokens:

```css
:root {
  /* Base primary — one source of truth */
  --color-primary: oklch(55% 0.18 250);

  /* Derived variants via color-mix — no SCSS needed */
  --color-primary-subtle: color-mix(in oklch, var(--color-primary) 12%, white);
  --color-primary-hover:  color-mix(in oklch, var(--color-primary) 88%, black);
  --color-primary-active: color-mix(in oklch, var(--color-primary) 75%, black);
  --color-primary-muted:  color-mix(in oklch, var(--color-primary) 40%, transparent);

  /* Danger derived from one source */
  --color-danger: oklch(55% 0.22 25);
  --color-danger-subtle: color-mix(in oklch, var(--color-danger) 10%, white);
  --color-danger-hover:  color-mix(in oklch, var(--color-danger) 85%, black);
}

/* Usage — button states use derived tokens, not hardcoded hex */
.btn-primary {
  background: var(--color-primary);
}
.btn-primary:hover {
  background: var(--color-primary-hover);
}
.btn-primary:active {
  background: var(--color-primary-active);
}
```

**Note:** The existing design-system.css uses hardcoded hex values for all color variants. v4.0 should migrate to `color-mix()` derived values to make theming dynamic.

### 4.7 CSS Native Nesting (No Preprocessor)

**Status: Baseline Newly Available**
**Browser support:** Chrome 120+, Edge 120+, Firefox 117+, Safari 17.2+
**Global support: >88%**

The existing codebase uses no preprocessor. Native nesting enables component-scoped CSS without SCSS:

```css
/* ag-modal component — nested styles, no class explosion */
.ag-modal {
  display: grid;
  place-items: center;

  & .modal-panel {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
  }

  & .modal-header {
    display: flex;
    align-items: center;
    gap: var(--space-3);

    & h2 {
      font-size: var(--text-xl);
      font-weight: var(--font-semibold);
    }
  }

  &[data-size="lg"] .modal-panel {
    max-width: 720px;
  }

  &[data-size="sm"] .modal-panel {
    max-width: 400px;
  }
}
```

---

## 5. Animation Strategy

### Recommendation: Anime.js v4 for Complex Animations + CSS-only for Micro-interactions

**Decision framework:**
- **Pure CSS** (transitions + `@starting-style` + `animation`) for: button hover, focus rings, modal entry/exit, toast appear/dismiss, tab switch, spinner, progress fill
- **Anime.js v4** for: count-up numbers (KPI), staggered list entry, tour spotlight pulse, chart draw animation

### Anime.js v4

**Version:** 4.3.6 (latest, March 2026)
**License:** MIT
**Bundle size:** ~10 KB gzipped (entire library)

```html
<!-- UMD bundle for IIFE pattern -->
<script src="https://cdn.jsdelivr.net/npm/animejs@4.3.6/dist/bundles/anime.umd.min.js"></script>
```

**Global access in IIFE:**

```javascript
(function () {
  'use strict';

  // anime.umd.min.js exposes `anime` global
  var anim = anime;

  // Count-up animation for KPI numbers (dashboard)
  function animateKPI(el, targetValue) {
    var obj = { value: 0 };
    anim.animate(obj, {
      value: targetValue,
      duration: 800,
      easing: 'easeOutExpo',
      onUpdate: function () {
        el.textContent = Math.round(obj.value).toLocaleString('fr-FR');
      }
    });
  }

  // Staggered list entry for motion cards
  function animateMotionList(cards) {
    anim.animate(cards, {
      opacity: [0, 1],
      translateY: [12, 0],
      delay: anim.stagger(60),
      duration: 300,
      easing: 'easeOutQuart'
    });
  }

  // Pulse animation for the "vote open" indicator
  function startVotePulse(el) {
    anim.animate(el, {
      scale: [1, 1.15, 1],
      loop: true,
      duration: 1200,
      easing: 'easeInOutSine'
    });
  }
})();
```

**v4 API note:** The v4 `animate()` function replaced v3's `anime()`. UMD bundle exposes `anime` as a global with `animate`, `stagger`, `createTimeline`, etc. as named properties on it.

### CSS-only Micro-interactions (No Library)

All button, input, and state feedback should use CSS — zero JavaScript for simple interactions:

```css
/* Button press feedback — satisfying physical feel */
.btn {
  transition: transform var(--duration-fast) var(--ease-out),
              box-shadow var(--duration-fast) var(--ease-out),
              background var(--duration-normal) var(--ease-out);
}
.btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px color-mix(in oklch, var(--color-primary) 30%, transparent);
}
.btn:active {
  transform: translateY(0) scale(0.98);
  box-shadow: none;
}

/* Progress bar fill animation */
.progress-fill {
  transition: width 600ms var(--ease-out);
}

/* Checkbox check animation */
input[type="checkbox"]:checked + .check-icon {
  stroke-dashoffset: 0;
  transition: stroke-dashoffset 200ms var(--ease-out) 50ms;
}
input[type="checkbox"] + .check-icon {
  stroke-dasharray: 20;
  stroke-dashoffset: 20;
}

/* Vote button selection ring */
.vote-option {
  transition: border-color var(--duration-fast),
              box-shadow var(--duration-fast),
              transform var(--duration-fast) var(--ease-bounce);
}
.vote-option[aria-pressed="true"] {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px var(--color-primary-muted);
  transform: scale(1.02);
}
```

### GSAP — Explicitly Avoided

GSAP's free tier is fine for open-source but the licensing complexity (GreenSock Standard License vs GSAP Premium) creates confusion in a self-hosted product. Anime.js v4 covers all the same use cases for micro-interactions at 10 KB gzipped vs GSAP's ~50 KB gzipped. Motion One (from Framer Motion) is ES-module-only with no UMD build, making it incompatible with the IIFE pattern without a build step.

---

## 6. Design System Evolution

### Recommendation: CSS @layer + Semantic Token Tiers + color-mix() Derivation

The existing system (64 CSS custom properties, single `:root {}` block, no layers) is a flat token list. v4.0 should evolve it into a **3-tier token system** managed with `@layer`.

### CSS @layer for Specificity Management

**Status: Baseline (widely available)**
**Browser support:** Chrome 99+, Edge 99+, Firefox 97+, Safari 15.4+

```css
/* design-system.css — declare layer order first (order = precedence, last wins) */
@layer reset, tokens, base, components, utilities, overrides;

@layer reset {
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  /* ... existing reset ... */
}

@layer tokens {
  :root {
    /* Tier 1: Primitive values — raw values, never used in components directly */
    --primitive-stone-50: #FAFAF7;
    --primitive-stone-100: #EDECE6;
    --primitive-stone-200: #E5E3D8;
    --primitive-blue-500: oklch(55% 0.18 250);
    --primitive-red-500: oklch(55% 0.22 25);
    --primitive-green-500: oklch(55% 0.18 145);

    /* Tier 2: Semantic aliases — map primitives to meaning */
    --color-bg: var(--primitive-stone-100);
    --color-surface: var(--primitive-stone-50);
    --color-primary: var(--primitive-blue-500);
    --color-danger: var(--primitive-red-500);
    --color-success: var(--primitive-green-500);

    /* Tier 3: Derived via color-mix() — eliminates hardcoded shade variants */
    --color-primary-subtle: color-mix(in oklch, var(--color-primary) 12%, white);
    --color-primary-hover:  color-mix(in oklch, var(--color-primary) 88%, black);
    --color-danger-subtle:  color-mix(in oklch, var(--color-danger) 10%, white);
  }

  /* Dark theme — only Tier 2 overrides needed */
  [data-theme="dark"] {
    --primitive-stone-50: #1A1A17;
    --primitive-stone-100: #242420;
    --color-bg: var(--primitive-stone-100);
    --color-surface: var(--primitive-stone-50);
    /* color-mix() derivations automatically recalculate */
  }
}

@layer base {
  body { font-family: var(--font-sans); color: var(--color-text); /* ... */ }
  /* All current base styles from design-system.css */
}

@layer components {
  /* Each component CSS file can @layer into components */
  /* wizard.css, hub.css, etc. */
}

@layer utilities {
  .sr-only { /* ... */ }
  .truncate { /* ... */ }
}
```

**Why this matters for v4.0:** Tour overlay z-index (`--z-tour-overlay: 9990`) is already declared in the existing token system. The layer structure makes it easy to add tour-specific overrides in `@layer overrides` without fighting specificity.

### Open Props — Use as Inspiration, Not Dependency

Open Props (https://open-props.style/) is a CSS custom properties library with 500+ tokens in 4 KB Brotli-compressed. CDN: `@import "https://unpkg.com/open-props"`.

**Verdict for AG-VOTE:** Do NOT import Open Props as a dependency. The existing 64-token system is smaller and project-specific. Use Open Props as a reference for:
- Easing curve values (Open Props has named easings like `--ease-spring-3`)
- Shadow scale patterns
- Spacing scale validation

Instead of importing Open Props, copy the specific values you find useful into the existing `:root` block. This keeps the token system lean and fully owned.

### Existing Design Token Inventory (v3.0 → v4.0 Migration)

The existing system already has:
- `--z-tour-overlay: 9990`, `--z-tour-spotlight: 9991`, `--z-tour-bubble: 9992` — Driver.js overlay z-index pre-planned
- `--ease-bounce: cubic-bezier(0.34, 1.56, 0.64, 1)` — good for button press feedback
- `--duration-fast/normal/slow` — covers micro-interaction timing
- Complete color system for light/dark themes

**What to add for v4.0:**

```css
:root {
  /* Animation — scroll-driven + entry */
  --duration-enter: 250ms;
  --duration-exit: 180ms;
  --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1); /* alias of ease-bounce */

  /* PDF viewer */
  --pdf-viewer-height: 600px;
  --pdf-viewer-height-mobile: 400px;

  /* Upload zone */
  --upload-zone-height: 160px;
  --upload-zone-border: 2px dashed var(--color-border);
  --upload-zone-border-active: 2px dashed var(--color-primary);

  /* Tour */
  --tour-backdrop: rgba(0, 0, 0, 0.55);
  --tour-highlight-radius: var(--radius-lg);
  --tour-popover-width: 320px;
}
```

---

## 7. Browser Support Matrix for v4.0

AG-VOTE is PC-first (1024px+). Target audience: organization administrators and operators on modern desktop browsers. Voter view is the exception (mobile, in-room voting).

| Feature | Chrome | Firefox | Safari | Edge | Support % | v4.0 Decision |
|---------|--------|---------|--------|------|-----------|---------------|
| View Transitions (same-doc) | 111+ | 133+ | 18+ | 111+ | ~80% | **Yes** — progressive enhancement |
| View Transitions (cross-doc) | 126+ | No | 18.2+ | 126+ | ~60% | Use same-doc only |
| @starting-style | 117+ | 129+ | 17.5+ | 117+ | ~86% | **Yes** — graceful degradation |
| CSS :has() | 105+ | 121+ | 15.4+ | 105+ | >92% | **Yes** — baseline |
| Container Queries | 105+ | 110+ | 16+ | 105+ | >93% | **Yes** — baseline |
| Scroll-driven animations | 115+ | 126+ | 18+ | 115+ | ~85% | **Yes** — @supports guard |
| color-mix() | 111+ | 113+ | 16.2+ | 111+ | >92% | **Yes** — baseline |
| CSS Nesting | 120+ | 117+ | 17.2+ | 120+ | >88% | **Yes** — baseline |
| @layer | 99+ | 97+ | 15.4+ | 99+ | >96% | **Yes** — baseline |
| Driver.js | any modern | any modern | any modern | any modern | >99% | **Yes** |
| PDF.js v5 | 90+ | 90+ | 14+ | 90+ | >99% | **Yes** |
| FilePond | any modern | any modern | any modern | any modern | >99% | **Yes** |

---

## 8. Installation Summary

### CDN Script Tags (no npm, no build step)

Add to page templates as needed — not globally on every page.

```html
<!-- === GUIDED TOURS (wizard.html, hub.html, operator.html) === -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.4.0/dist/driver.css">
<script src="https://cdn.jsdelivr.net/npm/driver.js@1.4.0/dist/driver.js.iife.js"></script>

<!-- === FILE UPLOAD (wizard.html — resolution attachment step) === -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/filepond@4.32.12/dist/filepond.min.css">
<script src="https://cdn.jsdelivr.net/npm/filepond@4.32.12/dist/filepond.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/filepond-plugin-file-validate-type@1.2.9/dist/filepond-plugin-file-validate-type.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/filepond-plugin-file-validate-size@2.2.9/dist/filepond-plugin-file-validate-size.min.js"></script>

<!-- === ANIMATION (pages with KPI count-up or staggered lists) === -->
<script src="https://cdn.jsdelivr.net/npm/animejs@4.3.6/dist/bundles/anime.umd.min.js"></script>

<!-- === PDF VIEWER (voter view — vote.html) === -->
<!-- No script tag needed — viewer is an <iframe> pointing to /assets/pdfjs/web/viewer.html -->
<!-- One-time setup: copy pdfjs-dist prebuilt files to public/assets/pdfjs/ -->
```

### PHP Composer — No New Packages

No new Composer packages needed. `dompdf/dompdf` (already installed) handles PV generation. PDF upload and serving uses vanilla PHP.

### php.ini Changes (`deploy/php.ini`)

```ini
; Add for resolution PDF upload support
upload_max_filesize = 10M
post_max_size       = 12M
```

### nginx.conf Changes (`deploy/nginx.conf`)

```nginx
# Allow PHP to serve PDFs from outside webroot (/var/uploads)
# Add before catch-all PHP location block:
location /api/v1/resolution_pdf.php {
    try_files $uri =404;
    fastcgi_pass 127.0.0.1:9000;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
    fastcgi_read_timeout 30s;
    # No special rate limit — auth check is inside the PHP script
}
```

---

## 9. What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| Shepherd.js | AGPL license — requires commercial license for any revenue-generating use | Driver.js (MIT) |
| Intro.js | AGPL license — same issue | Driver.js (MIT) |
| `<embed>` / `<object>` for PDF | No control over UI; browser-dependent behavior; Chrome shows download prompt | PDF.js prebuilt viewer in iframe |
| Uppy | 40 KB+ core; tus.js dependency for chunked upload adds weight; designed for multi-provider cloud storage | FilePond (simpler for local PHP) |
| GSAP | License complexity for self-hosted product; 50 KB vs 10 KB anime.js | Anime.js v4 |
| Motion One | ES-module-only — no UMD/IIFE build available; requires bundler | Anime.js v4 |
| Open Props (as dependency) | 500+ tokens is overkill; adds name collision risk with existing `--color-*` tokens | Use as inspiration; copy specific values |
| CSS preprocessors (SCSS/Less) | No build step in existing stack; native CSS nesting + color-mix() replaces SCSS features | CSS native nesting + color-mix() |
| PDF.js `$_FILES['type']` MIME check | Client-supplied, trivially spoofed | `finfo_file()` + magic bytes check |

---

## 10. Alternatives Considered

| Category | Recommended | Alternative | Why Not |
|----------|-------------|-------------|---------|
| Guided tours | Driver.js (MIT) | Shepherd.js | AGPL license requires commercial license |
| Guided tours | Driver.js (MIT) | Intro.js | AGPL license + slightly larger bundle |
| PDF viewer | PDF.js prebuilt viewer | `<embed src="doc.pdf">` | No control over UI; browser-dependent; download prompts |
| PDF viewer | PDF.js prebuilt viewer | pdf-lib | pdf-lib is for creation/editing, not viewing |
| File upload | FilePond | Hand-rolled drag-drop | 20+ hours of work for accessible, animated equivalent |
| File upload | FilePond | Uppy | Larger bundle, cloud storage focus, unnecessary complexity for PHP backend |
| Animation | Anime.js v4 | GSAP | License complexity; 5x larger |
| Animation | Anime.js v4 | Motion One | ES-module only, no IIFE/UMD build |
| CSS tokens | Custom @layer system | Open Props | Name collisions; 500+ tokens is overkill |
| CSS color variants | color-mix() | SCSS darken()/lighten() | Would require build step; color-mix() is native and baseline |

---

## Sources

- [Driver.js Official Docs — Installation](https://driverjs.com/docs/installation) — HIGH confidence
- [Driver.js Async Tour Docs](https://driverjs.com/docs/async-tour) — HIGH confidence (multi-page pattern)
- [jsDelivr — driver.js@1.4.0 dist/ listing](https://cdn.jsdelivr.net/npm/driver.js@1.4.0/dist/) — HIGH confidence (actual file sizes)
- [Inline Manual: Driver.js vs Intro.js vs Shepherd.js vs Reactour](https://inlinemanual.com/blog/driverjs-vs-introjs-vs-shepherdjs-vs-reactour/) — HIGH confidence (license comparison)
- [npm trends: driver.js vs intro.js vs shepherd vs vue-tour](https://npmtrends.com/driver.js-vs-intro.js-vs-shepherd-vs-vue-tour) — MEDIUM confidence (popularity)
- [PDF.js Getting Started — Mozilla](https://mozilla.github.io/pdf.js/getting_started/) — HIGH confidence
- [jsDelivr — pdfjs-dist](https://www.jsdelivr.com/package/npm/pdfjs-dist) — HIGH confidence (version v5.5.207)
- [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html) — HIGH confidence (security pattern)
- [jsDelivr — filepond@4.32.12 dist/ listing](https://cdn.jsdelivr.net/npm/filepond@4.32.12/dist/) — HIGH confidence (actual file sizes: 115.5 KB min JS, 17 KB min CSS)
- [FilePond GitHub](https://github.com/pqina/filepond) — HIGH confidence (MIT license confirmed)
- [Dropzone.js CDN](https://www.jsdelivr.com/package/npm/dropzone) — HIGH confidence
- [Anime.js v4 Documentation](https://animejs.com/documentation/getting-started/installation/) — HIGH confidence
- [Anime.js UMD CDN URL](https://cdn.jsdelivr.net/npm/animejs/dist/bundles/anime.umd.min.js) — HIGH confidence
- [MDN — View Transition API](https://developer.mozilla.org/en-US/docs/Web/API/View_Transition_API) — HIGH confidence
- [web.dev — View Transitions Baseline Newly Available](https://web.dev/blog/same-document-view-transitions-are-now-baseline-newly-available) — HIGH confidence
- [web.dev — @starting-style Baseline Entry Animations](https://web.dev/blog/baseline-entry-animations) — HIGH confidence (Chrome 117+, Firefox 129+, Safari 17.5+)
- [CSS-Tricks — CSS Cascade Layers](https://css-tricks.com/css-cascade-layers/) — HIGH confidence
- [MDN — @layer](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@layer) — HIGH confidence (Chrome 99+, Firefox 97+, Safari 15.4+)
- [Open Props](https://open-props.style/) — HIGH confidence (4 KB Brotli, 500+ props)
- [Can I use — CSS Container Queries](https://caniuse.com/css-container-queries) — HIGH confidence
- [Scroll-Driven Animations — MDN](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Scroll-driven_animations) — HIGH confidence

---

*Stack research for: AG-VOTE v4.0 "Clarity & Flow" — Guided UX, Inline PDF, File Upload, CSS Polish*
*Researched: 2026-03-18*
*Previous version (v3.0 SSE/API wiring) archived in `.planning/milestones/v3.0-phases/`*
