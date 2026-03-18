# Architecture Patterns — v4.0 "Clarity & Flow"

**Project:** AG-VOTE
**Researched:** 2026-03-18
**Scope:** v4.0 integration architecture — PDF upload, inline viewer, guided UX, copro transformation, design system evolution. SSE/API wiring already shipped in v3.0 and is NOT covered here.

---

## 1. PDF File Upload & Storage

### What Already Exists

The codebase already has a complete meeting-level attachment system:

- **Controller:** `app/Controller/MeetingAttachmentController.php` — upload, list, delete
- **Repository:** `app/Repository/MeetingAttachmentRepository.php` — CRUD
- **DB table:** `meeting_attachments` (migration 20260219)
- **Routes:** `GET/POST/DELETE /api/v1/meeting_attachments` (operator role)
- **Validation:** 10 MB limit, `application/pdf` only via `finfo`, `.pdf` extension check
- **Audit log:** `meeting_attachment_uploaded` and `meeting_attachment_deleted`

What is MISSING for v4.0:

- **Resolution-level documents.** The current table attaches PDFs to meetings, not to individual motions/resolutions. v4.0 needs `resolution_documents` at the motion level.
- **Secure serve endpoint.** Files are stored at `/tmp/ag-vote/uploads/meetings/{meeting_id}/{uuid}.pdf` but there is no PHP endpoint to serve them back. Only upload/delete exist. Voters cannot currently view attached PDFs.
- **Storage persistence.** `/tmp/` is ephemeral on restart (Docker, Render). Production must move to a persistent volume or object storage path.

### Proposed DB Schema: `resolution_documents`

```sql
-- Migration: resolution_documents
-- Attaches PDF documents to individual motions/resolutions
-- Voters can consult these before casting their ballot

CREATE TABLE IF NOT EXISTS resolution_documents (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    motion_id uuid NOT NULL REFERENCES motions(id) ON DELETE CASCADE,
    original_name text NOT NULL,
    stored_name text NOT NULL,       -- UUID-based, no user-controlled name on disk
    mime_type text NOT NULL DEFAULT 'application/pdf',
    file_size bigint NOT NULL DEFAULT 0,
    display_order integer NOT NULL DEFAULT 0,
    uploaded_by uuid REFERENCES users(id) ON DELETE SET NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_resolution_docs_motion
    ON resolution_documents(motion_id);
CREATE INDEX IF NOT EXISTS idx_resolution_docs_meeting
    ON resolution_documents(meeting_id);
CREATE INDEX IF NOT EXISTS idx_resolution_docs_tenant
    ON resolution_documents(tenant_id);

COMMENT ON TABLE resolution_documents IS
    'PDF documents attached to individual motions for voter consultation';
```

The `meeting_id` redundancy on `resolution_documents` avoids a JOIN when listing all docs for a meeting and simplifies access control (tenant_id + meeting_id is the standard isolation pair in this codebase).

### Storage Path Decision

**Current (meeting_attachments):** `/tmp/ag-vote/uploads/meetings/{meeting_id}/{uuid}.pdf`

**Problem:** `/tmp/` is wiped on container restart. This is acceptable for the current development state but is a production blocker.

**v4.0 decision:** Move to a configurable persistent path.

Recommended storage layout:
```
/var/agvote/uploads/           <- mounted Docker volume or bind mount
  meetings/{meeting_id}/       <- meeting-level attachments (existing)
  resolutions/{motion_id}/     <- resolution-level documents (new)
```

Configure via environment variable `AGVOTE_UPLOAD_DIR` (defaulting to `/var/agvote/uploads`). The controller reads this variable; the Dockerfile mounts a volume at that path.

```php
// In MeetingAttachmentController and new ResolutionDocumentController
$uploadBase = rtrim((string) getenv('AGVOTE_UPLOAD_DIR') ?: '/var/agvote/uploads', '/');
$uploadDir  = $uploadBase . '/resolutions/' . $motionId;
```

### Secure Serve Endpoint

Files must NOT be web-accessible directly (no nginx `location /uploads/`). They must be served through PHP which enforces auth.

**New route:** `GET /api/v1/resolution_document_serve`

```php
// ResolutionDocumentController::serve()
// 1. Verify session (api_current_user_id() or valid vote_token)
// 2. Check meeting access — operator/admin: any meeting; voter: only current meeting
// 3. Verify tenant_id matches document
// 4. readfile($path) with correct headers
```

Headers to emit:
```
Content-Type: application/pdf
Content-Disposition: inline; filename="{original_name}"
Content-Length: {file_size}
X-Content-Type-Options: nosniff
Cache-Control: private, no-store
```

Note: `Content-Disposition: inline` (not `attachment`) is intentional — it opens in the browser PDF viewer rather than forcing a download.

**Nginx:** No changes needed. The `/api/` location block already routes to PHP. Do NOT add a direct nginx `location /uploads/` block — that would bypass auth.

### File Constraints & Validation

Existing pattern in `MeetingAttachmentController` is correct. For resolution documents:

| Constraint | Value | Rationale |
|------------|-------|-----------|
| Max size | 10 MB | Consistent with meeting attachments |
| MIME check | `finfo(FILEINFO_MIME_TYPE)` | Server-side magic byte check, not Content-Type header |
| Extension | `.pdf` only | Belt-and-suspenders |
| Stored name | `{uuid}.pdf` | Prevents directory traversal, no user-controlled filename on disk |

Virus scanning is out of scope for v4.0. If added later, integrate ClamAV via `exec('clamscan ...')` after `move_uploaded_file` and before DB insert.

### New Files Needed

| File | Type | Purpose |
|------|------|---------|
| `app/Controller/ResolutionDocumentController.php` | PHP | Upload, list, delete, serve |
| `app/Repository/ResolutionDocumentRepository.php` | PHP | CRUD for resolution_documents |
| `database/migrations/YYYYMMDD_resolution_documents.sql` | SQL | New table |
| Routes in `app/routes.php` | PHP | `resolution_documents` GET/POST/DELETE, `resolution_document_serve` GET |

**Files modified:**
- `app/Core/Providers/RepositoryFactory.php` — register `resolutionDocument()` accessor
- `app/routes.php` — add 4 new routes
- `app/Controller/MeetingAttachmentController.php` — migrate storage path from `/tmp` to `AGVOTE_UPLOAD_DIR`
- `deploy/docker-compose.yml` — add volume mount for `AGVOTE_UPLOAD_DIR`
- `Dockerfile` — `mkdir -p /var/agvote/uploads` in build layer

**Risk:** LOW. Pattern is identical to `MeetingAttachmentController`. The serve endpoint is the only new pattern.

---

## 2. Inline PDF Viewer Integration

### Component Decision

**Verdict:** Build `ag-pdf-viewer` as a new Web Component.

Rationale:
- The existing component set (ag-modal, ag-popover) follows the Custom Elements pattern — a new component is idiomatic
- The viewer needs shared behavior (loading state, error state, mobile bottom sheet) across at least 3 pages: wizard, hub, vote.htmx.html
- Native browser PDF rendering via `<iframe src="..." type="application/pdf">` works in all modern browsers without a JS library

Do NOT add a third-party PDF library (pdf.js etc.) in v4.0. The serve endpoint returns a real PDF file; the browser's native PDF viewer handles rendering. This keeps dependencies at zero.

### Component API

```javascript
// Usage in HTML
<ag-pdf-viewer
  src="/api/v1/resolution_document_serve?id={uuid}"
  filename="deliberation-1.pdf"
  loading-text="Chargement..."
></ag-pdf-viewer>
```

Internal structure (Shadow DOM):
```
ag-pdf-viewer
  +-- .viewer-toolbar  (filename, close button, fullscreen toggle)
  +-- .viewer-iframe   (<iframe> with src bound to attr)
  +-- .viewer-error    (shown if iframe fails to load)
```

The component listens for attribute changes via `attributeChangedCallback` and updates the iframe src.

### Mobile Integration (Vote Screen)

The voter view (`vote.htmx.html`) is the primary mobile context (phone in-room voting).

Pattern: **bottom sheet slide-up panel**, not a full-screen modal. The existing `ag-modal` uses `position: fixed; inset: 0` — too heavy for mobile document preview.

`ag-pdf-viewer` should support a `mode` attribute:
- `mode="inline"` — embedded in page flow (desktop, hub, wizard)
- `mode="sheet"` — bottom sheet slide-up (default on mobile, vote page)

Bottom sheet CSS (to add to `design-system.css`):
```css
ag-pdf-viewer[mode="sheet"] {
  position: fixed;
  inset: auto 0 0 0;
  height: 80dvh;
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  z-index: var(--z-modal);
  transform: translateY(100%);
  transition: transform var(--duration-slow) var(--ease-out);
}

ag-pdf-viewer[mode="sheet"][open] {
  transform: translateY(0);
}
```

### Integration Points by Page

| Page | How PDF is triggered | Mode |
|------|---------------------|------|
| `wizard.htmx.html` | Preview button on resolution card | `inline` in panel |
| `hub.htmx.html` | "Voir document" on motion detail | `inline` in sidebar |
| `vote.htmx.html` | "Consulter le document" button | `sheet` (bottom slide-up) |
| `operator.htmx.html` | Document panel in motion details | `inline` |

### New Files

| File | Type | Purpose |
|------|------|---------|
| `public/assets/js/components/ag-pdf-viewer.js` | JS WC | The component |
| (CSS additions to `design-system.css`) | CSS | Bottom sheet, viewer toolbar styles |

**Files modified:**
- `public/assets/js/components/index.js` — register `ag-pdf-viewer`
- `public/vote.htmx.html` — add document button on motion card
- `public/wizard.htmx.html` — add document upload + preview on resolution step
- `public/assets/js/pages/vote.js` — trigger sheet open on button click
- `public/assets/js/pages/wizard.js` — document upload + preview wiring

**Risk:** LOW. Browser native PDF viewer is reliable. The bottom sheet is pure CSS. The component has no library dependencies.

---

## 3. Guided UX System Architecture

### What Already Exists

The design system already has the CSS infrastructure for guided tours:

- `design-system.css` lines 3741-3940: complete `.tour-overlay`, `.tour-spotlight-ring`, `.tour-bubble`, `.tour-progress`, `.tour-progress-dots` styles
- Z-index tokens already defined: `--z-tour-overlay: 9990`, `--z-tour-spotlight: 9991`, `--z-tour-bubble: 9992`
- `meetings.js` has an onboarding banner pattern with `localStorage` dismissal (`ag_meetings_ob_dismissed`)
- `page-components.js` has `CollapsibleSection` for expandable/collapsible sections
- `shared.js` has `emptyState()` helper for empty state rendering with SVG icons for meetings/members/votes/archives

What is MISSING: there is no `ag-guide` Web Component or `GuidedTour` JS class. The CSS exists but has no JS driver.

### Architecture Decision: Central Registry + Lazy JS Driver

**Approach:** One `ag-guide` Web Component that reads a JSON step config, injected per-page as a `<script>` block. This avoids rewriting the 29 existing page modules.

Integration pattern for an existing page module:
```html
<!-- Added to any .htmx.html page, no changes to existing JS -->
<script>
  window.AG_GUIDE_STEPS = [
    {
      target: '#btnCreateMeeting',
      title: 'Creer une seance',
      body: 'Commencez ici pour configurer votre assemblee generale.',
      position: 'bottom'
    },
    {
      target: '.sessions-table',
      title: 'Vos seances',
      body: 'Toutes vos seances apparaissent ici, avec leur statut en temps reel.',
      position: 'top'
    }
  ];
</script>
<ag-guide steps-var="AG_GUIDE_STEPS" storage-key="ag_guide_meetings_v1"></ag-guide>
```

The `storage-key` is written to `localStorage` with value `"done"` when the user completes or skips the tour. The component checks this on mount and silently does nothing if already dismissed.

### ag-guide Web Component API

```javascript
// Attributes
// steps-var: global JS variable name containing the steps array
// storage-key: localStorage key for dismissal state
// trigger: 'auto' (on page load, first visit only) | 'manual' (only via startTour())

// Public methods (callable from page module if needed)
document.querySelector('ag-guide').startTour();
document.querySelector('ag-guide').stopTour();

// Custom events
// guided-tour-complete -- fired when user finishes or skips
// guided-tour-step    -- fired on each step advance, detail: { step, total }
```

### ag-hint Web Component (Contextual Hints)

Simpler than `ag-guide` — a persistent but dismissible inline hint bubble for contextual help on specific fields/sections:

```html
<ag-hint storage-key="ag_hint_proxy_deadline_v1">
  Les procurations doivent etre deposees avant la date limite fixee dans les statuts.
</ag-hint>
```

CSS: renders as a small callout card with an "x" dismiss button. Dismissal persists in `localStorage`.

### Progressive Disclosure (Collapsible Advanced Sections)

`page-components.js` already has `CollapsibleSection`. The pattern is simply to wrap "advanced" form fields in a `<details>`-like element:

```html
<div class="advanced-section" data-collapsible>
  <button class="advanced-toggle" aria-expanded="false">
    Parametres avances
    <svg class="chevron">...</svg>
  </button>
  <div class="advanced-body" hidden>
    <!-- advanced fields -->
  </div>
</div>
```

`CollapsibleSection.initAll('.advanced-section')` is called from the page module after render. No new component needed. This is a CSS + existing JS pattern.

**Primary targets:** operator console, wizard step 2 (vote policy configuration), settings page vote/quorum policies. The basic happy path is exposed; edge cases are hidden behind "Parametres avances".

### ag-empty-state Web Component

The current `emptyState()` function in `shared.js` returns raw HTML strings. v4.0 benefit: convert to a Web Component with an action slot:

```html
<ag-empty-state icon="meetings" title="Aucune seance" description="Creez votre premiere assemblee generale.">
  <button slot="action" class="btn btn-primary" id="btnCreateMeeting">Nouvelle seance</button>
</ag-empty-state>
```

Backward compatible: existing `emptyState()` function stays. Pages migrate to the component progressively.

### Interaction with Existing Web Components

| Interaction | Resolution |
|-------------|-----------|
| Tour bubble overlaps ag-modal | `ag-guide` deactivates tour when a modal opens (listen for `ag-modal-open` custom event) |
| ag-toast during tour | No conflict — toast z-index (--z-toast: 800) is below tour z-indices (9990+) |
| ag-popover during tour | Popover closes on outside click; tour overlay blocks clicks, so popovers auto-close correctly |
| Tour target scrolled out of view | `ag-guide` calls `target.scrollIntoView({ behavior: 'smooth', block: 'center' })` before positioning bubble |

### New Files

| File | Type | Purpose |
|------|------|---------|
| `public/assets/js/components/ag-guide.js` | JS WC | Tour driver |
| `public/assets/js/components/ag-hint.js` | JS WC | Inline contextual hint |
| `public/assets/js/components/ag-empty-state.js` | JS WC | Stateful empty state |

**Files modified:**
- `public/assets/js/components/index.js` — register 3 new components
- Each `.htmx.html` page — add `<script>window.AG_GUIDE_STEPS = [...]</script>` + `<ag-guide>` element (no changes to existing page `.js` modules required)
- `public/assets/css/design-system.css` — add `ag-hint` and `ag-empty-state` base styles

**Risk:** MEDIUM. The tour spotlight clip-path approach requires accurate `getBoundingClientRect()` across all layouts. Test with sidebar-pinned vs collapsed, sticky headers, and scrolled views.

---

## 4. Copropriete Code Transformation

### Quantified Scope

Total copropriete-related references found across the entire codebase: **10 unique matching lines** across **9 distinct file locations** (pattern: `tantièmes|millièmes|copropriété|copropriete|tantieme|millieme|quote.part|pondéré|weighted`).

This is a very small footprint — far smaller than anticipated. The terminology was largely normalized to generic `voting_power` / "poids de vote" in earlier milestones.

### Specific Locations and Actions

| File | Line | Content | Action |
|------|------|---------|--------|
| `app/Services/ImportService.php` | 237 | Column alias map: `'tantiemes', 'tantièmes'` accepted as import column names | **Keep** — backward-compat import alias for existing CSV files |
| `app/Repository/AggregateReportRepository.php` | 99 | Comment: "evolution des tantiemes" | **Rename comment** only — no behavior change |
| `app/Services/OfficialResultsService.php` | 62 | Comment: "weighted vote totals" | **Keep** — neutral English, not copro-specific |
| `public/settings.htmx.html` | 104 | `<option value="tantiemes">Par tantièmes</option>` | **Rename display text** to "Par poids de vote" — keep `value="tantiemes"` for API compat |
| `public/settings.htmx.html` | 158 | Card: "Definit la ponderation des voix par lot" | **Reword** to "Definit le poids de vote de chaque membre" |
| `public/admin.htmx.html` | 634 | "Cles de repartition / ponderation des voix par lot" | **Reword** to "Ponderations des voix" |
| `public/help.htmx.html` | 194 | "reunions de copropriete" in app description | **Reword** to "assemblees generales" |
| `public/help.htmx.html` | 299, 432 | "tantiemes" in quorum help; data-search attribute | **Reword** display text, keep `data-search` alias |
| `public/assets/js/core/shell.js` | 683 | Sidebar: "Annuaire des copropriétaires" | **Rename** to "Annuaire des membres" |
| `public/assets/js/pages/settings.js` | 419 | JS-rendered option: "Tantièmes" | **Rename** to "Poids de vote" |
| `public/assets/js/pages/wizard.js` | 301, 342, 390, 392 | `m.lot`, `lot: r[1]`, `window.prompt('Numero de lot')` | **Remove lot field** from wizard member input |
| `public/index.html` | 291 | Feature block: "Pondération (tantièmes)" | **Reword** to "Poids de vote (ponderation)" |

### What Is Already Generic (No Changes Needed)

The following are already properly abstracted and need no v4.0 changes:

- `members.voting_power` column — generic, works for any organization
- `quorum_policies.denominator = 'eligible_weight'` — generic "eligible weight" terminology
- `BallotsService`, `VoteEngine`, `QuorumEngine` — use `weight` and `voting_power` throughout, no copro terms
- `attendances.effective_power` — generic override column
- Import column alias `ponderation`, `poids` — useful for any weighted-vote organization

### What to Transform vs Remove

The concept of **weighted voting** (`voting_power != 1.0`) is a genuine AG-standard feature (used for weighted voting by share capital, member categories, etc.). It must be kept and presented as generic.

The `lot` concept (copropriete property number) is the only genuinely copro-specific item in the codebase. It appears only in `wizard.js` as an optional prompt and display field.

**Action on `lot`:** Remove. Members can use `external_ref` for any organizational reference number.

Files affected for `lot` removal:
- `public/assets/js/pages/wizard.js` — 4 lines (remove prompt, column from CSV parser, display in member chip)
- `public/assets/css/wizard.css` — `.member-lot` class (remove)

### Total Effort Estimate

12 files, approximately 25 lines changed. This is NOT a major refactor. It is a vocabulary and display cleanup with zero backend logic changes.

---

## 5. Design System Evolution

### Current Token Inventory

Actual count of CSS custom properties in `design-system.css (:root)`: **255 properties** (the 64-token figure in `PROJECT.md` is stale — 64 was the v2.0 initial count before expansion).

### Token Categories Breakdown

| Category | Approx Count | Examples |
|----------|-------------|---------|
| Typography (fonts, sizes, weights, leading) | 17 | `--font-sans`, `--text-xs` through `--text-4xl`, `--leading-*`, `--font-*` |
| Spacing | 12 | `--space-0` through `--space-16`, `--space-md`, `--space-lg` |
| Border radius | 4 | `--radius-sm`, `--radius`, `--radius-lg`, `--radius-full` |
| Layout constants | 7 | `--header-height`, `--sidebar-width`, `--sidebar-rail`, `--drawer-width`, `--content-max` |
| Z-index scale | 18 | `--z-base` through `--z-skip`, 3 tour z-indices |
| Animation | 8 | `--duration-fast/normal/slow`, `--ease-*`, `--transition` |
| Colors (surfaces, text, borders) | 20 | `--color-bg`, `--color-surface`, `--color-text`, `--color-border` and variants |
| Colors (semantic states) | 24 | Primary, success, warning, danger, info with hover/subtle/border/text variants |
| Colors (accent, neutral, sidebar, misc) | 19 | Purple, accent, neutral, sidebar-*, tag-*, backdrop |
| Shadows | 8 | `--shadow-xs` through `--shadow-inner`, `--shadow-focus` |
| Focus ring | 3 | `--ring-width`, `--ring-color`, `--ring-offset` |
| Persona colors | 18 | 6 personas × 3 (base, subtle, text) |

The dark theme is a second `[data-theme="dark"]` block at line 310 that overrides color tokens only. Structural/spacing/z-index tokens are not overridden.

### v4.0 Token Strategy: Incremental Evolution

**Decision: Incremental addition, no big-bang replacement.**

29 page CSS files reference existing tokens. A big-bang renaming causes cascading breakage across all pages simultaneously. Incremental addition is zero-risk.

**Rule 1: Add, Don't Rename.** New tokens are added to `:root`. Old tokens are never removed in v4.0.

New tokens needed for v4.0 features:

```css
:root {
  /* PDF Viewer */
  --viewer-toolbar-height: 48px;
  --viewer-bg: #1a1a1a;
  --viewer-toolbar-bg: var(--color-surface-raised);

  /* Guided hints */
  --hint-bg: var(--color-primary-subtle);
  --hint-border: var(--color-primary);
  --hint-text: var(--color-primary-active);

  /* Bottom sheet (mobile PDF viewer) */
  --sheet-max-height: 90dvh;
  --sheet-handle-color: var(--color-border-strong);

  /* PC-first layout */
  --content-max-wide: 1600px;
  --panel-sidebar-width: 320px;
}
```

**Rule 2: CSS @layer for New vs Old.** Introduce CSS `@layer` for all new v4.0 component styles. This separates new additions from the v3.x base without breaking specificity.

```css
/* At top of design-system.css, after existing content */
@layer base, components, v4;

/* New component styles live in @layer v4 */
@layer v4 {
  ag-pdf-viewer { ... }
  ag-guide { ... }
  ag-hint { ... }
  ag-empty-state { ... }
}
```

The `v4` layer has lower cascade priority than unlayered styles (existing pages), meaning existing page-specific CSS still wins over the new component defaults. This is correct behavior — components provide defaults, pages override.

CSS `@layer` is supported in all modern browsers (Chrome 99+, Firefox 97+, Safari 15.4+). The existing codebase already uses `color-mix()` which requires the same browser versions.

**Rule 3: No per-page CSS for new components.** New Web Components define their styles in `design-system.css` under `@layer v4`. They do not get individual page CSS files.

**Rule 4: PC-first breakpoint strategy.** v4.0 shifts to PC-first (1024px+). New layout rules for non-voter screens assume desktop; media queries reduce for smaller screens. Only the voter screen (`vote.htmx.html`) is mobile-first.

```css
/* PC baseline — no media query, it IS the default for operator/admin screens */

/* Adaptive for smaller laptops if needed */
@media (max-width: 1023px) { ... }

/* Voter screen only — mobile first */
@media (max-width: 767px) { ... }
```

### Design System Files Affected

| File | Change |
|------|--------|
| `public/assets/css/design-system.css` | Add ~15 new tokens; add `@layer v4` block with new component styles |
| Existing page CSS files | No changes required for v4.0 |

**Risk:** LOW. Incremental addition preserves all existing pages. `@layer` is additive and does not change existing specificity.

---

## Component Dependency Order

```
1. DB migration: resolution_documents table
      |
      v
2. ResolutionDocumentRepository + Controller + routes
      |
      v
3. ag-pdf-viewer Web Component (depends on serve endpoint)
      |
      v
4. Wire PDF upload to wizard + vote page
      |
      +-- ag-hint Web Component (independent, no backend)
      |
      +-- ag-empty-state Web Component (independent, no backend)
      |
      v
5. ag-guide Web Component (benefits from stable page targets)
      |
6. Copro rename pass (independent, can run in parallel with 4-5)
      |
7. Design system token additions (should precede component styling)
```

---

## Risk Matrix

| Integration Point | Files Affected | New Files | Risk | Notes |
|-------------------|---------------|-----------|------|-------|
| resolution_documents DB + controller | 4 PHP + 1 SQL + routes.php + RepositoryFactory | 2 PHP, 1 SQL | LOW | Identical pattern to meeting_attachments |
| Secure serve endpoint | ResolutionDocumentController | — | LOW | readfile() with auth check, no new pattern |
| Storage path fix (/tmp to persistent) | Dockerfile, docker-compose.yml, both upload controllers | — | MEDIUM | Needs volume mount in all deploy configs |
| ag-pdf-viewer | 1 JS component + design-system.css | 1 JS | LOW | Native iframe, no library |
| Vote page PDF bottom sheet | vote.htmx.html, vote.js | — | MEDIUM | Mobile layout testing required |
| ag-guide tour driver | 1 JS component + all .htmx.html | 1 JS | MEDIUM | Clip-path positioning across layout states requires testing |
| ag-hint | 1 JS component | 1 JS | LOW | Static inline display, no positioning logic |
| ag-empty-state | 1 JS component | 1 JS | LOW | Slot-based, replaces emptyState() helper gradually |
| Copro label rename | 12 files, ~25 lines | 0 | LOW | No logic changes, vocabulary only |
| Wizard lot field removal | wizard.js (4 lines), wizard.css | 0 | LOW | UI-only change |
| Design system @layer | design-system.css | — | LOW | Additive, no existing specificity affected |
| New CSS tokens | design-system.css | — | LOW | Additive |

---

## Sources

All findings are HIGH confidence based on direct codebase inspection (no training-data assumptions):

- `app/Controller/MeetingAttachmentController.php` — existing upload/delete pattern
- `app/Repository/MeetingAttachmentRepository.php` — repository pattern
- `database/schema-master.sql` — full DB schema including meeting_attachments, motions, members
- `database/migrations/20260219_meeting_attachments.sql` — migration pattern
- `deploy/nginx.conf` — file serving constraints, security headers, location blocks
- `public/assets/css/design-system.css` — full token inventory (255 properties), existing tour CSS (lines 3741-3940)
- `public/assets/js/components/` — all 20 Web Components enumerated
- `public/assets/js/core/shared.js` — emptyState() helper, localStorage patterns
- `public/assets/js/core/page-components.js` — CollapsibleSection class
- Grep across all PHP, JS, CSS, HTML for copropriete/tantieme — 10 matching lines total, 9 files
- `app/routes.php` — route table and middleware patterns
- `app/Services/ImportService.php` — column alias map
- `public/assets/js/pages/meetings.js` — onboarding banner + localStorage dismissal pattern
- `public/assets/js/core/shell.js` — sidebar navigation including copro label
