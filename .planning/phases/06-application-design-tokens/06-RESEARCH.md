# Phase 6: Application Design Tokens - Research

**Researched:** 2026-04-07
**Domain:** CSS design token enforcement, login layout redesign, HTMX loading states, semantic status badges
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Login 2-panel: 50/50 ratio, gradient orb branding left panel, form right panel
- Breakpoint 768px: collapses to single column, branding panel hidden
- Token approach: grep + replace per CSS file, colours first then spacing
- Priority pages: dashboard (app.css only), hub, meetings, operator
- Loading: skeleton pulse on `.htmx-request` element, non-intrusive
- Badge colours: OKLCH semantic aligned with design-system.css (success=vert, warning=orange, danger=rouge, info=bleu, neutral=gris)
- Badge shape: pill with compact padding, opacity fade-in 200ms
- No custom animation keyframes — reuse `skeleton-shimmer` from design-system.css

### Claude's Discretion
- Ordre exact de traitement des 25 fichiers CSS par-page
- Details d'implementation du skeleton pulse animation
- Nommage des classes CSS pour les badges de statut

### Deferred Ideas (OUT OF SCOPE)
- Dark mode parity audit (v1.2)
- Toast notification system (v1.2)
- Role-specific sidebar nav (v1.2)
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| DESIGN-01 | Application uniforme des design tokens de design-system.css sur tous les fichiers CSS par page | Token audit complete — 5 files have hardcoded colour values, ~20 files have raw px requiring evaluation; colour tokens replace 100% trivially, px values need judgment (layout widths vs spacing) |
| DESIGN-02 | Login 2-panels — branding gauche, formulaire droite, responsive <768px | login.html structure mapped; login.css already uses tokens; HTML restructuring needed to wrap into grid container with `.login-panel-brand` + `.login-panel-form` |
| DESIGN-03 | Loading states CSS pour .htmx-request — feedback visuel pendant les chargements | `.htmx-indicator`, `.skeleton`, `.spinner`, `.spinner-sm` already defined in design-system.css; no CSS pages currently wire `.htmx-request` skeleton; button loading uses JS `btnLoading()` helper in shared.js |
| DESIGN-04 | Status badges avec couleurs semantiques (actif, ferme, archive, en cours, etc.) | `.badge-{variant}` defined in design-system.css; `MEETING_STATUS_MAP` in shared.js uses correct BEM names; two issues found: hub.html uses `badge--neutral`/`badge--info` (double-dash, undefined), and QuorumController emits `badge success` (space-separated, unstyled) |
</phase_requirements>

---

## Summary

Phase 6 is primarily a **CSS cleanup + targeted HTML/JS fixes** phase — not a new feature build. The design system infrastructure (design-system.css v2.0, 5,278 lines) is complete with all tokens, components, and animation keyframes already declared. The work is enforcement and application.

**Scope is smaller than expected.** Only 5 per-page CSS files contain raw hex or oklch() literals (operator.css, settings.css, report.css, vote.css, audit.css — all minor, 1-2 occurrences each). Raw `px` values are widespread but most are layout widths (280px sidebar, 420px card) and icon sizes that should remain literal, not token-replaced. True spacing violations needing token substitution are concentrated in compact padding patterns (`2px 8px`, `5px 1rem`, etc.).

**Primary recommendation:** Execute in 4 waves — (1) login 2-panel HTML+CSS, (2) badge normalisation fixing the two known defects, (3) HTMX loading state wiring in HTML, (4) token cleanup sweep of priority CSS files.

---

## Standard Stack

### Core (already present — no installation needed)
| Component | Location | Purpose | Status |
|-----------|----------|---------|--------|
| design-system.css | `/public/assets/css/design-system.css` | All tokens, components, animations | Complete v2.0 |
| app.css | `/public/assets/css/app.css` | Entrypoint: imports design-system + pages.css | Complete |
| login.css | `/public/assets/css/login.css` | 553 lines, fully tokenised v4.3 | Needs 2-panel layout |
| `.badge-{variant}` | design-system.css lines 1713-1762 | Semantic badge component | Defined, partially misused |
| `.htmx-indicator` | design-system.css lines 3131-3150 | HTMX loading indicator | Defined, not wired in HTML |
| `.skeleton` / `.skeleton-row` | design-system.css lines 3546-3586 | Skeleton shimmer loader | Defined, not applied |
| `.spinner` / `.spinner-sm` | design-system.css lines 2342-2361 | Button spinner | Defined, used via JS `btnLoading()` |
| `MEETING_STATUS_MAP` | `/public/assets/js/core/shared.js` line 102 | Canonical status → badge class mapping | Correct BEM naming |

### CSS Layer Architecture
```
@layer base, components, v4;   ← declared in design-system.css
@layer pages { ... }           ← NOT YET DECLARED — must add to app.css or per-file
```
`@layer pages` is the correct override scope for justified page-level exceptions. It is not yet declared anywhere — the planner must include a Wave 0 task to declare it in app.css.

---

## Architecture Patterns

### Login 2-Panel Structure (DESIGN-02)

**Current HTML structure:**
```html
<div class="login-orb">              <!-- fixed, z-index 0 -->
<main class="login-page">           <!-- flex col center -->
  <div class="login-card">          <!-- 420px card -->
    <div class="login-brand">
    <form class="login-form">
  <div class="login-trust">
  <div id="demoPanel">
  <div class="login-footer">
```

**Required HTML restructuring:**
```html
<div class="login-orb" aria-hidden="true"></div>   <!-- stays: repositioned to branding panel -->
<main class="login-page">                          <!-- becomes: display:grid 1fr 1fr -->
  <div class="login-panel-brand">                  <!-- NEW wrapper, left panel -->
    <div class="login-brand">...</div>             <!-- logo + h1 + tagline, MOVED here -->
  </div>
  <div class="login-panel-form">                   <!-- NEW wrapper, right panel -->
    <div class="login-card">...</div>              <!-- unchanged internals -->
    <div class="login-trust">...</div>             <!-- moved inside right panel -->
    <div id="demoPanel">...</div>
    <div class="login-footer">...</div>
  </div>
</main>
```

**CSS changes (login.css only):**
```css
/* Replace .login-page layout */
.login-page {
  display: grid;
  grid-template-columns: 1fr 1fr;
  min-height: 100vh;
  /* remove: flex, align-items, justify-content, padding */
}

.login-panel-brand {
  position: relative;
  overflow: hidden;
  background: radial-gradient(...orb colours...);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-12);
}

.login-panel-form {
  background: var(--color-surface-raised);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--space-8);
}

/* Reposition orb to branding panel (no longer fixed full-page) */
.login-panel-brand .login-orb {
  position: absolute;
  /* ... */
}

/* Breakpoint */
@media (max-width: 768px) {
  .login-page { grid-template-columns: 1fr; }
  .login-panel-brand { display: none; }
}
```

**Preserved without change:** `.login-card`, `.login-brand`, `.login-form`, `.login-brand-mark`, `.login-tagline`, `.field-group`, `.field-input`, `.field-label`, `.field-eye`, all form internals.

### Badge Normalisation (DESIGN-04)

**Two defects found:**

**Defect 1 — hub.html uses `badge--neutral`/`badge--info` (double-dash):**
```html
<!-- hub.htmx.html lines 92-93, 129, 204, 213 -->
<span class="badge badge--neutral" id="hubTypeTag">-</span>
<span class="badge badge--info" id="hubStatusTag">En préparation</span>
```
These classes are undefined in design-system.css. Fix: rename to `badge-neutral` / `badge-info` in hub.htmx.html (5 occurrences).

**Defect 2 — QuorumController emits space-separated modifiers (not BEM):**
```php
// QuorumController.php lines 56-80
$badgeClass = 'success';  // → produces <span class="badge success">
// should be: 'badge-success'
```
The classes `.badge.success`, `.badge.danger`, `.badge.muted` are NOT defined anywhere. These badges render unstyled. Fix: update `$badgeClass` values to use full BEM class names (`badge-success`, `badge-danger`, `badge-neutral`) and remove `badge` from the wrapper element since it becomes redundant, OR keep `badge` base class and change modifier to `badge-success` (preferred).

**Canonical badge class reference (from shared.js MEETING_STATUS_MAP — use as source of truth):**
```javascript
draft:     'badge-neutral'
live:      'badge-danger badge-dot'
paused:    'badge-warning'
closed:    'badge-success'
validated: 'badge-success'
archived:  'badge-neutral'
scheduled: 'badge-info'
frozen:    'badge-info'
```
`pv_sent` status is NOT in MEETING_STATUS_MAP — add it: `pv_sent: { badge: 'badge-info', text: 'PV envoyé' }`.

**meetings.css `.meeting-card-status` variants** use correct token variables but semantic mapping differs slightly from UI-SPEC. Specific mismatch: `archived` uses `--color-accent-subtle`/`--color-accent-text` but UI-SPEC says `badge-neutral`. Align during cleanup pass.

### HTMX Loading States (DESIGN-03)

**Current state:** `.htmx-indicator` pattern is defined in design-system.css but zero HTML files currently use `htmx-indicator` class. The `btnLoading()` helper in shared.js injects a `.spinner.spinner-sm` — this already works for button loading. No skeleton wiring exists.

**Pattern to apply (from design-system.css):**
```css
/* Already defined — no new CSS needed */
.htmx-indicator { display: none; }
.htmx-request .htmx-indicator { display: inline-flex; }

.skeleton { /* shimmer animation */ }
.skeleton-row { /* table row placeholder */ }
```

**HTML wiring pattern for buttons (add to hx-* enabled buttons):**
```html
<button hx-get="/api/v1/..." hx-target="#list" class="btn btn-primary">
  <span class="htmx-indicator">
    <svg class="spinner spinner-sm" aria-label="Chargement…"></svg>
  </span>
  <span>Charger</span>
</button>
```

**HTML wiring pattern for list containers (add to hx-* target elements):**
```html
<div id="agendaList" hx-indicator="#agendaList">
  <!-- skeleton rows shown while .htmx-request is active -->
  <div class="htmx-indicator">
    <div class="skeleton-row"><div class="skeleton-cell skeleton"></div></div>
    <div class="skeleton-row"><div class="skeleton-cell skeleton"></div></div>
  </div>
</div>
```

**Pages that need loading state wiring** (operator, hub, members, meetings have async lists):
- `operator.htmx.html`: `#agendaList` (line 649 uses `aria-busy="true"` only, no skeleton)
- `members.htmx.html`: member card grid
- `hub.htmx.html`: motions list, checklist
- `meetings.htmx.html`: sessions list

### Token Enforcement Sweep (DESIGN-01)

**Colour violations — 5 files, trivial fixes:**

| File | Line | Violation | Fix |
|------|------|-----------|-----|
| operator.css | 1063 | `oklch(1 0 0 / 0.20)` | `var(--color-surface-raised)` or `color-mix()` |
| settings.css | 331 | `oklch(0 0 0 / 0.20)` in box-shadow | `var(--shadow-sm)` or existing shadow token |
| report.css | 180, 182 | `oklch(1 0 0 / 0.75)` and `oklch(1 0 0 / 0.15)` | `var(--color-text-on-dark)` + opacity, or `var(--color-bg-subtle)` |
| vote.css | 16, 1498 | `rgb(var(--shadow-color) / 0.08)` | `var(--shadow-sm)` |
| audit.css | 125 | `oklch(1 0 0 / 0.25)` | `var(--color-surface)` with opacity |

**Raw px spacing violations — judgment required:**

NOT violations (keep as-is):
- Layout structural widths: `280px` sidebar, `420px` card, `960px` track — these are fixed layout constraints, not spacing tokens
- Icon dimensions: `16px`, `24px`, `8px` — icon-size tokens don't exist in design-system.css
- `border-radius: 99px` / `border-radius: 999px` — equivalent to `var(--radius-full)`, replace

ARE violations (replace with tokens):
- `padding: 2px 8px` → `padding: var(--space-1) var(--space-2)`
- `padding: 5px 1rem` → `padding: var(--space-2) var(--space-4)` (approximate)
- `gap: 4px` → `gap: var(--space-1)`
- `gap: 6px` → `gap: var(--gap-xs)` (4px) or `var(--gap-sm)` (8px) by judgment
- `font-size: 18px` in meetings.css → `var(--text-lg)` or `var(--text-xl)`
- `font-size: 11px` in meetings.css → `var(--text-xs)` (12px, acceptable rounding)

**Dashboard has no dedicated CSS** — it loads only `app.css`. Dashboard-specific components (`.kpi-grid`, `.kpi-card`) are defined in design-system.css lines 2538-2570 and are already fully tokenised. Dashboard requires no per-file token pass.

### Horizontal-first enforcement

CSS files must not collapse layouts below 768px for the wide-screen content areas. When removing raw px, preserve multi-column grid definitions. Do not change `grid-template-columns: 280px 1fr` or `grid-template-columns: 1fr 1fr` structural values.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Skeleton animation | Custom keyframes | `skeleton-shimmer` in design-system.css line 3583 | Already defined and matches design tokens |
| Badge colours | Inline styles or new CSS | `.badge-{variant}` in design-system.css lines 1725-1753 | Semantic, dark mode aware, token-based |
| Button loading state | Custom JS spinner | `Shared.btnLoading()` in shared.js line 122 | Already used across pages |
| HTMX indicator | CSS `.is-loading` + display toggle | `.htmx-indicator` + `.htmx-request` in design-system.css | Native HTMX pattern, class applied by library |
| Layer override | `!important` | `@layer pages { }` | Cascade-correct, no specificity fighting |

---

## Common Pitfalls

### Pitfall 1: Double-dash badge classes silently fail
**What goes wrong:** `badge--neutral` renders as unstyled text — no browser error, no console warning.
**Why it happens:** hub.htmx.html uses BEM element-modifier syntax (`--`) but design-system.css uses single-hyphen variants (`badge-neutral`).
**How to avoid:** Always grep for `badge--` after any badge work to confirm zero occurrences.
**Warning signs:** Hub type tag and status tag appear with white background, no border-radius.

### Pitfall 2: QuorumController badge.success is completely unstyled
**What goes wrong:** Quorum status (reached/not reached) shows plain text in operator page, no colour.
**Why it happens:** PHP emits `<span class="badge success">` — `.badge.success` selector does not exist.
**How to avoid:** Fix PHP to emit `<span class="badge badge-success">` — the `.badge` base class + `.badge-success` modifier is the canonical pattern.

### Pitfall 3: login.css `.login-orb` is `position: fixed` scoped to viewport
**What goes wrong:** Moving orb into branding panel while keeping `position: fixed` makes it escape the panel and appear full-page.
**Why it happens:** Fixed positioning removes element from normal flow relative to its parent.
**How to avoid:** Change `.login-orb` to `position: absolute` and set `overflow: hidden` on `.login-panel-brand`.

### Pitfall 4: `@layer pages` not declared — overrides don't cascade correctly
**What goes wrong:** Rules inside `@layer pages { }` in a per-page CSS file have lower specificity than `@layer components { }` in design-system.css.
**Why it happens:** Layer order declaration must be established before use. `@layer base, components, v4` is declared in design-system.css but `pages` is not included.
**How to avoid:** Add `@layer base, components, v4, pages;` declaration to design-system.css (first line after existing layer declaration) OR to app.css before the design-system import. Layer order is established by first `@layer` declaration.

### Pitfall 5: Raw px replacement breaks structural layout
**What goes wrong:** Replacing `280px` sidebar width with a spacing token produces a collapsed layout.
**Why it happens:** `--space-*` tokens go up to `--space-16` (64px) — nothing near 280px. Layout widths are architectural, not spacing.
**How to avoid:** Only replace px values in `padding`, `gap`, `margin`, `border-radius`, and `font-size` properties. Never replace structural layout widths with spacing tokens.

### Pitfall 6: login.html trust signal + footer placement
**What goes wrong:** After wrapping form in `.login-panel-form`, the `.login-trust` and `.login-footer` divs remain outside `.login-card` but must now be inside `.login-panel-form`, not floating at page level.
**Why it happens:** Current HTML has trust + footer as siblings of `.login-card` inside `.login-page`. The new grid layout requires them inside the right panel.
**How to avoid:** Move `.login-trust`, `#demoPanel`, and `.login-footer` inside `.login-panel-form` during HTML restructuring.

---

## Code Examples

### Badge base class (from design-system.css)
```css
/* Source: design-system.css lines 1713-1753 */
.badge {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: var(--space-1) var(--space-2);
  font-size: var(--text-xs);
  font-weight: var(--font-medium);
  line-height: 1;
  border-radius: var(--radius-full);
  white-space: nowrap;
}
.badge-success { background: var(--color-success-subtle); color: var(--color-success-text); }
.badge-danger  { background: var(--color-danger-subtle);  color: var(--color-danger-text); }
.badge-warning { background: var(--color-warning-subtle); color: var(--color-warning-text); }
.badge-info    { background: var(--color-info-subtle);    color: var(--color-info-text); }
.badge-neutral { background: var(--color-bg-subtle);      color: var(--color-text-secondary); }
```

### HTMX indicator pattern (from design-system.css)
```css
/* Source: design-system.css lines 3131-3150 */
.htmx-indicator { display: none; }
.htmx-request .htmx-indicator { display: inline-flex; }
.htmx-request.htmx-indicator  { display: inline-flex; }
```

### Skeleton shimmer (from design-system.css)
```css
/* Source: design-system.css lines 3546-3586 */
.skeleton {
  background: linear-gradient(90deg,
    var(--color-bg-subtle) 25%, var(--color-border) 50%, var(--color-bg-subtle) 75%
  );
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.5s ease-in-out infinite;
  border-radius: var(--radius);
}
@keyframes skeleton-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
```

### @layer pages declaration (add to app.css)
```css
/* Extend layer order before design-system import */
@layer base, components, v4, pages;
@import url("/assets/css/design-system.css");
```

### QuorumController badge fix (PHP)
```php
// Before (broken — no CSS matches):
$badgeClass = 'success';
echo '<span class="badge ' . $badgeClass . '">';  // → class="badge success"

// After (correct BEM):
$badgeClass = 'badge-success';
echo '<span class="' . $badgeClass . '">';         // → class="badge-success"
// Or: keep badge base, change modifier:
$badgeClass = 'badge-success';
echo '<span class="badge ' . $badgeClass . '">';   // → class="badge badge-success"
```

---

## State of the Art

| Old Approach | Current Approach | Status |
|--------------|------------------|--------|
| Inline `oklch()` / hex colours | `var(--color-*)` tokens from design-system.css | 95% done — 5 files remain |
| `border-radius: 999px` | `var(--radius-full)` | Present in operator.css, wizard.css — replace |
| Full-page login card centered | 2-panel grid layout | Pending DESIGN-02 |
| No loading feedback | `.htmx-indicator` + `.skeleton` | Infrastructure present, wiring pending |
| Mixed badge class naming | `.badge.badge-{variant}` BEM pattern | 2 defects to fix (hub, QuorumController) |

---

## Open Questions

1. **`@layer pages` declaration placement**
   - What we know: must be declared before use, design-system.css declares `@layer base, components, v4`
   - What's unclear: whether to add `pages` to that declaration in design-system.css or add a separate `@layer base, components, v4, pages` to app.css before the import
   - Recommendation: add to `app.css` before the `@import` — avoids modifying the core design system file

2. **`pv_sent` status missing from MEETING_STATUS_MAP**
   - What we know: `meetings.css` has `.meeting-card-status.pv_sent` with accent colours; shared.js MEETING_STATUS_MAP has no entry for `pv_sent`
   - What's unclear: is `pv_sent` rendered via meetings.js `meeting-card-status` CSS (which handles it) or via operator-tabs.js `MEETING_STATUS_MAP` (which doesn't)?
   - Recommendation: add `pv_sent: { badge: 'badge-info', text: 'PV envoyé' }` to MEETING_STATUS_MAP as a defensive fix

3. **Scope of spacing token replacement in operator.css (76 raw px occurrences)**
   - What we know: many are layout widths (280px), icon sizes (8px dots, 7px dots), compact badge padding
   - What's unclear: which compact values (5px, 6px, 7px) have token equivalents close enough to use without visual regression
   - Recommendation: replace only clear matches (2px→space-1, 4px→space-1, 8px→space-2, 16px→space-4), leave sub-4px values and icon sizes as literals

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit ^10.5 (unit) + Playwright (e2e) |
| Config file | `phpunit.xml` + `tests/e2e/playwright.config.js` |
| Quick run command | `timeout 60 php vendor/bin/phpunit tests/Unit/QuorumControllerTest.php --no-coverage` |
| Full suite command | `php vendor/bin/phpunit tests/ --no-coverage` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| DESIGN-01 | No raw hex or oklch() literals in per-page CSS after sweep | manual / grep | `grep -rE '#[0-9a-fA-F]{3,6}\|rgba?\(' public/assets/css/ --include='*.css' \| grep -v design-system` | n/a — grep assertion |
| DESIGN-02 | Login has 2 panels side by side on desktop, stacks on <768px | e2e visual | `cd tests/e2e && npx playwright test specs/auth.spec.js` | ✅ |
| DESIGN-03 | Button loading spinner visible during async requests | manual + e2e | `cd tests/e2e && npx playwright test specs/ux-interactions.spec.js` | ✅ |
| DESIGN-04 | Status badges render with correct colour per status | e2e DOM assertion | `cd tests/e2e && npx playwright test specs/dashboard.spec.js specs/operator.spec.js` | ✅ |

### Sampling Rate
- **Per task commit:** `timeout 60 php vendor/bin/phpunit tests/Unit/QuorumControllerTest.php --no-coverage`
- **Per wave merge:** `cd tests/e2e && npx playwright test specs/auth.spec.js specs/ux-interactions.spec.js --reporter=dot`
- **Phase gate:** Full Playwright suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/e2e/specs/auth.spec.js` — add 2-panel viewport assertion (currently tests auth flow, not layout structure)
- [ ] `@layer pages` declaration — add to `app.css` before per-page CSS files reference it

*(Existing test infrastructure covers most phase requirements. Two additions needed.)*

---

## Sources

### Primary (HIGH confidence)
- Direct code inspection: `public/assets/css/design-system.css` — badge, spinner, skeleton, htmx-indicator definitions
- Direct code inspection: `public/assets/css/login.css` — current 553-line login styles, all tokenised
- Direct code inspection: `public/login.html` — HTML structure for 2-panel restructuring
- Direct code inspection: `public/assets/js/core/shared.js` line 102 — MEETING_STATUS_MAP canonical badge mapping
- Direct code inspection: `app/Controller/QuorumController.php` — broken badge class emission

### Secondary (MEDIUM confidence)
- Direct code inspection: `public/assets/js/pages/meetings.js` line 267 — `.meeting-card-status {status}` CSS class pattern confirmed
- Direct code inspection: `public/assets/js/pages/members.js` lines 574-576 — `.badge-success`/`.badge-neutral` pattern used correctly

### Tertiary (LOW confidence)
- None

---

## Metadata

**Confidence breakdown:**
- Badge defects (hub double-dash, QuorumController space-separated): HIGH — confirmed by code inspection
- Login 2-panel restructuring: HIGH — HTML/CSS structure fully mapped
- Token violation scope (5 colour files, selective px): HIGH — grep verified
- @layer pages cascade: HIGH — CSS spec, confirmed layer declaration absent
- HTMX wiring scope: MEDIUM — identified key pages, exact hx-indicator placement needs per-page review during execution

**Research date:** 2026-04-07
**Valid until:** 2026-05-07 (stable CSS domain, no breaking changes expected)
