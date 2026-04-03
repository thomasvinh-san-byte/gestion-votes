# Phase 83: Component Geometry + Chrome Cleanup - Research

**Researched:** 2026-04-03
**Domain:** CSS design token consolidation, shadow scale, alpha borders, CSS skeleton shimmer
**Confidence:** HIGH

## Summary

Phase 83 makes three surgical changes to design-system.css (radius consolidation, shadow reduction, border alpha) and one JS-adjacent change to add shimmer placeholders on the dashboard. All changes are pure CSS token/value surgery — no HTML structure changes, no new components. The work is well-bounded: the locked decisions in CONTEXT.md specify exact token names, exact values, and exact scope.

The primary risk is breadth of touch: radius tokens are referenced in 23 Web Components (Shadow DOM fallback literals like `12px` and `8px` hardcoded in `ag-kpi.js`, `ag-modal.js`, `ag-kpi.js`, and `hub.css`), and shadow tokens are used across 15+ per-page CSS files. The shadow rename (xs → sm, md → md, xl → lg) will silently break anything currently using `--shadow-xl` or `--shadow-xs` unless remapped. Three files use tokens that are being dropped: `pages.css:504` uses `--shadow-xs`, `email-templates.css:198` uses `--shadow-2xl`, `validate.css:88` uses `--shadow-2xl`.

The skeleton shimmer for dashboard KPI cards is a new addition — the HTML already has static `-` placeholder text in `#kpiSeances`, `#kpiEnCours`, etc., and three `.skeleton.skeleton-session` div placeholders already exist in `#prochaines`. The infrastructure to extend skeletons to the KPI cards needs to be added in HTML + CSS (no existing `.skeleton-kpi` class exists). The `prefers-reduced-motion` global rule at line 2956 already suppresses all animations — the shimmer just needs to set a static background as fallback.

**Primary recommendation:** Execute in three sequential sub-tasks: (1) radius consolidation, (2) shadow rename + border alpha, (3) skeleton shimmer enhancement. Each is independently testable by visual inspection.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Radius Consolidation**
- `--radius-base` set to 8px (current `--radius-lg`) — most common value, matches modern UI patterns
- Remove all component-alias tokens (`--radius-btn`, `--radius-card`, `--radius-panel`, `--radius-modal`, `--radius-toast`, `--radius-tooltip`, `--radius-tag`, `--radius-input`) — use `--radius-base` directly everywhere
- Keep `--radius-full` (9999px) for pill shapes only (badge, chip, avatar) — different semantic intent
- Replace ALL hardcoded border-radius values in component CSS (3px, 6px, 2px, 9px, 50%) with `var(--radius-base)` or `var(--radius-full)` as appropriate

**Shadow & Border System**
- Reduce shadow scale to 3 named levels: `--shadow-sm` (current `--shadow-xs`), `--shadow-md` (current `--shadow-md`), `--shadow-lg` (current `--shadow-xl`)
- Drop intermediate shadow levels: `--shadow-2xs`, `--shadow-sm` (old), `--shadow` (unnumbered), `--shadow-lg` (old), `--shadow-2xl`
- Keep `--shadow-inner` and `--shadow-inset-sm` as utility shadows (pressed state, inset fields)
- Keep `--shadow-focus` and `--shadow-focus-danger` unchanged (accessibility, not elevation)
- Border alpha approach: `oklch(0 0 0 / 0.08)` for light mode, `oklch(1 1 0 / 0.08)` for dark mode — adapts to any background

**Skeleton Shimmer**
- Scope: dashboard KPI cards (4) + session list (first 3-5 placeholder rows)
- Implementation: CSS-only `@keyframes shimmer` with gradient pseudo-element, no JS component
- Trigger: `.loading` class on container, HTMX adds during swap via `hx-indicator`
- `prefers-reduced-motion`: static gray placeholder (no animation), still shows layout structure

### Claude's Discretion

(None specified in CONTEXT.md)

### Deferred Ideas (OUT OF SCOPE)
- Login page 2-panel redesign — user request from Phase 82 checkpoint, should be its own phase
- Shimmer on operator console session list — only dashboard specified in SC#4
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| COMP-01 | Single `--radius-base` token controls all border-radius values (consolidated from current multiple values) | Radius token map and hardcoded value audit documented below |
| COMP-02 | Shadow vocabulary reduced to 3 named levels (sm, md, lg) replacing current proliferation | Current 9-level scale + files using tokens-to-drop documented below |
| COMP-03 | Border colors use transparency instead of solid hex for subtle depth on any background | `--color-border` currently uses opaque stone palette values; alpha migration approach documented |
| COMP-04 | Skeleton shimmer loading replaces ag-spinner on dashboard and session list pages | Dashboard uses JS-populated KPI values and pre-existing `.skeleton` divs; shimmer infrastructure audit below |
</phase_requirements>

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| CSS Custom Properties (tokens) | CSS3 native | Token system already in use | No dependency, cascade-native |
| oklch() color function | CSS Color Level 4 | Alpha-based border colors | Already used throughout design-system.css (verified) |
| CSS `@keyframes` | CSS3 native | Shimmer animation | No JS needed for animation |
| `@media (prefers-reduced-motion)` | CSS3 Media Queries Level 4 | Accessibility guard for shimmer | Global rule already present at line 2956 |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| HTMX `hx-indicator` | Already in use | Trigger `.htmx-request` class on loading container | If shimmer is triggered by HTMX fetches rather than JS-controlled `.loading` class |

**No new installations required.** All work is pure CSS + HTML modifications.

---

## Architecture Patterns

### Recommended File Touch Order

```
1. design-system.css          # Token definitions (radius, shadow, color-border)
2. app.css                    # Skeleton extensions that reference base tokens
3. pages.css                  # Dashboard KPI skeleton + shadow fixes
4. Per-page CSS files         # Shadow token renames where needed
5. Web Components (.js)       # Hardcoded radius/shadow fallback literals
6. dashboard.htmx.html        # KPI skeleton placeholder HTML
```

### Pattern 1: Radius Token Consolidation

**What:** Replace the component-alias layer with a single `--radius-base` token. All component references become `var(--radius-base)` instead of `var(--radius-btn)`, `var(--radius-card)`, etc.

**When to use:** Any element that is a button, input, card, panel, modal, toast, tooltip, or tag.

**Token definition (design-system.css):**
```css
/* In @layer base :root */
--radius-base: var(--radius-lg);  /* 8px — replaces all component aliases */
/* Remove: --radius-btn, --radius-input, --radius-card, --radius-panel,
           --radius-modal, --radius-toast, --radius-tooltip, --radius-tag */
/* Keep:  --radius-full (9999px) for pill shapes */
```

**Usage in component CSS:**
```css
/* Before */
border-radius: var(--radius-card);   /* 12px via --radius-xl */
border-radius: var(--radius-btn);    /* 6px via --radius-md */
border-radius: var(--radius-input);  /* 8px via --radius-lg */

/* After */
border-radius: var(--radius-base);   /* 8px everywhere */
```

**Web component fallback update:**
```css
/* In ag-kpi.js shadowRoot style (current) */
border-radius: var(--radius-lg, 16px);   /* wrong fallback */

/* After */
border-radius: var(--radius-base, 8px);  /* correct fallback */
```

### Pattern 2: Shadow Scale Rename

**What:** Rename the surviving three shadow levels in design-system.css, update dark mode block, update all usage sites.

**Rename map:**

| Old token | New token | Old definition | Semantic role |
|-----------|-----------|----------------|---------------|
| `--shadow-xs` | `--shadow-sm` | `0 1px 2px rgb(...)` | Cards, panels (near-zero elevation) |
| `--shadow-md` | `--shadow-md` | `0 4px 12px rgb(...)` | Dropdowns, popovers |
| `--shadow-xl` | `--shadow-lg` | `0 16px 40px rgb(...)` | Modals, dialogs |

**Tokens to remove:** `--shadow-2xs`, `--shadow-sm` (old), `--shadow` (unnumbered), `--shadow-lg` (old), `--shadow-2xl`

**Usage sites using to-be-dropped tokens (must remap before deleting):**

| File | Line | Current | Remap to |
|------|------|---------|----------|
| `pages.css` | 504 | `var(--shadow-xs)` | `var(--shadow-sm)` |
| `email-templates.css` | 198 | `var(--shadow-2xl, fallback)` | `var(--shadow-lg)` |
| `validate.css` | 88 | `var(--shadow-2xl)` | `var(--shadow-lg)` |
| `ag-modal.js` | 117 | `var(--shadow-xl, var(--shadow-lg))` | `var(--shadow-lg)` |
| `design-system.css` | 2115 | `var(--shadow-xl)` | `var(--shadow-lg)` |
| `design-system.css` | 2184 | `var(--shadow-xl)` | `var(--shadow-lg)` |
| `design-system.css` | 4257 | `var(--shadow-xl, fallback)` | `var(--shadow-lg)` |

**Shadow tokens that already use correct new names (no rename needed but value changes):**

| Token | Currently used at | Will become |
|-------|-------------------|-------------|
| `--shadow-sm` | Multiple pages | Will be redefined as old `--shadow-xs` values |
| `--shadow-md` | Multiple pages | Value unchanged |
| `--shadow-lg` | Multiple pages | Will be redefined as old `--shadow-xl` values |

**Dark mode block** (lines 695-714) must be updated in the same edit.

### Pattern 3: Alpha-Based Border Color

**What:** Add a new semantic token `--color-border-alpha` in design-system.css that uses oklch alpha channels. Structural card/panel borders switch from `var(--color-border)` (opaque stone) to `var(--color-border-alpha)`.

**Token definitions:**
```css
/* In :root (light mode) */
--color-border-alpha: oklch(0 0 0 / 0.08);   /* dark alpha on any light surface */

/* In [data-theme="dark"] */
--color-border-alpha: oklch(1 1 0 / 0.08);   /* light alpha on any dark surface */
```

**Target selectors in pages.css (dashboard scope):**
```css
/* .kpi-card, .dashboard-sessions, .dashboard-aside — change border from: */
border: 1px solid var(--color-border);

/* to: */
border: 1px solid var(--color-border-alpha);
```

**Scope:** Structural cards and panels only. Do not change interactive borders (input focus, form validation) — those use `var(--color-border-focus)` or `var(--color-primary)` and must remain opaque.

### Pattern 4: Dashboard KPI Skeleton Shimmer

**What:** The dashboard currently shows `-` as KPI placeholder text while JS fetches `/api/v1/dashboard`. Replace KPI card interiors with shimmer placeholders until data loads.

**Existing infrastructure found:**
- `design-system.css` lines 3526-3566: `.skeleton` class with `skeleton-shimmer` keyframes (background-position animation)
- `design-system.css` line 4169: Duplicate `.skeleton` definition in `@layer v4` with `shimmer` keyframes
- `app.css` line 392: `.skeleton * { visibility: hidden; }` — children hidden during loading
- `pages.css` lines 1112-1118: `.skeleton-session` height 60px for session list placeholders
- `dashboard.htmx.html` lines 142-145: 3 `.skeleton.skeleton-session` divs already present in `#prochaines`
- `dashboard.htmx.html` lines 86-122: KPI cards show static `-` — no skeleton class yet

**Shimmer approach for KPI cards (CSS-only, no JS):**
```css
/* In pages.css — new skeleton-kpi class */
.skeleton-kpi {
  height: 80px;    /* matches kpi-card height */
  border-radius: var(--radius-base);
  /* inherits .skeleton shimmer animation */
}
```

```html
<!-- In dashboard.htmx.html — replace kpi-card content with skeleton initially -->
<!-- JS in dashboard.js removes .loading class after API response -->
<div class="dashboard-kpis loading">
  <div class="skeleton skeleton-kpi" aria-hidden="true"></div>
  <div class="skeleton skeleton-kpi" aria-hidden="true"></div>
  <div class="skeleton skeleton-kpi" aria-hidden="true"></div>
  <div class="skeleton skeleton-kpi" aria-hidden="true"></div>
</div>
<!-- OR: use .loading wrapper that shows/hides via CSS -->
```

**Simpler approach (recommended): Add `.loading` wrapper, swap via JS:**
- KPI cards already populated by `dashboard.js:loadDashboard()` → add `.loading` class to `.dashboard-kpis` on page load, remove after successful API call
- CSS: `.dashboard-kpis.loading .kpi-card { display: none; }` + skeleton divs visible only in loading state

**`prefers-reduced-motion` is already handled globally** at design-system.css line 2956:
```css
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
  }
}
```
This stops the shimmer animation automatically. The static skeleton background (gray block) still shows layout structure. **No per-skeleton override needed.**

### Anti-Patterns to Avoid

- **Do not rename `--shadow-sm` to something else first** — it is used in 10+ places. Rename by redefining the value and updating all references atomically, not by keeping the old token as an alias.
- **Do not change `border-radius: 50%`** on circular elements (avatars, pulse dots, scrollbar thumbs). These intentionally use 50% for circles, not `--radius-full`.
- **Do not change `--shadow-focus` or `--shadow-focus-danger`** — they are accessibility tokens, not elevation tokens.
- **Do not add `hx-indicator` to KPI cards** — they are populated by JS fetch, not HTMX. The shimmer should be JS-controlled (add/remove `.loading` class in `dashboard.js`).

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Shimmer animation | Custom JS-driven animation | CSS `@keyframes` on `.skeleton` | Already implemented in design-system.css; JS would add unnecessary overhead |
| Reduced-motion fallback | Per-component `@media` blocks | Global rule at line 2956 | Already handles all `*` — no extra work needed |
| Dark mode border adaptation | Per-page hex overrides | `oklch(L C H / alpha)` in `:root` and `[data-theme="dark"]` blocks | Alpha-based tokens adapt to any background; no per-page dark mode override needed |
| Shadow alias backward compat | Shadow alias chain (old → new) | Direct rename + update all usage | Aliases prevent tree-shaking and confuse future maintainers |

---

## Common Pitfalls

### Pitfall 1: Shadow Token Rename Order
**What goes wrong:** Rename `--shadow-sm` token name in `:root` before updating all usage sites that currently use `--shadow-sm` (old meaning). After rename, those sites silently reference the wrong (or missing) value.
**Why it happens:** The old `--shadow-sm` (0 1px 3px / 0.08) becomes the new `--shadow-sm` (old `--shadow-xs` / 0 1px 2px / 0.06). If a card was using the old `--shadow-sm` for "medium" elevation and now gets the lighter value, it looks flat.
**How to avoid:** Map all current usages before renaming. Pages using `var(--shadow-sm)` currently want "low elevation" — they should map to the new `--shadow-sm` (old xs). Pages using `var(--shadow-lg)` currently want "high elevation" — they should map to the new `--shadow-lg` (old xl). The design intent is preserved, just the value changes.
**Warning signs:** After the rename, cards appear to have less shadow than before.

### Pitfall 2: Circular Elements Caught in Radius Sweep
**What goes wrong:** Replacing all `border-radius: 50%` with `var(--radius-full)` breaks circular shapes because `--radius-full: 9999px` is not the same as 50% for elements whose dimensions are unknown.
**Why it happens:** `50%` produces a perfect circle for any square element; `9999px` produces a pill/stadium shape that visually looks like a circle only on small elements. On large containers, 9999px and 50% behave differently.
**How to avoid:** Keep `border-radius: 50%` on circular avatars, pulse dots, scrollbar thumbs, and spinner circles. Replace with `var(--radius-full)` only on pill-shaped badges, chips, and tags.
**Affected locations in design-system.css:** Lines 1180, 1740, 2327, 2489, 2745, 4160 — check each before replacing.

### Pitfall 3: Dark Mode Shadow Override Block Not Updated
**What goes wrong:** design-system.css has a second shadow token block inside `[data-theme="dark"]` at lines 695-714. If the `:root` shadow tokens are renamed but the dark mode block is not updated simultaneously, dark mode renders with stale/non-existent tokens.
**Why it happens:** Two blocks to update, easy to miss the second.
**How to avoid:** Always update `:root` shadow tokens and `[data-theme="dark"]` shadow override block in the same file edit.

### Pitfall 4: KPI Shimmer Replaces Content Permanently
**What goes wrong:** If the JS call in `dashboard.js` removes the loading state but doesn't also remove/replace the skeleton divs, users see both skeleton and real values.
**Why it happens:** Shimmer placeholder HTML is separate from the real content HTML. The JS must either replace skeleton HTML or use a CSS-class toggle to show/hide.
**How to avoid:** Preferred pattern: start the page with `.loading` on `.dashboard-kpis`, skeleton divs inside. After API returns, remove `.loading` (shows real `.kpi-card` elements, hides skeleton). Real `.kpi-card` elements should be in the HTML but hidden while `.loading` class is present.

### Pitfall 5: Web Component Fallback Literals Left Outdated
**What goes wrong:** Web components in Shadow DOM use fallback values like `var(--radius-lg, 16px)`. After removing `--radius-card` alias and centralizing to `--radius-base`, the fallback still says `16px` (wrong) or the component still references `var(--radius-card, 12px)` which no longer exists.
**Why it happens:** Shadow DOM components are separate files, not auto-checked by CSS linting.
**Affected components:**
- `ag-kpi.js`: uses `var(--radius-lg, 16px)` — fallback is wrong (should be 8px)
- `ag-modal.js`: uses `var(--radius-modal, 12px)` — will break when `--radius-modal` is removed
- `hub.css`: hardcoded `border-radius: 12px` at line 19, `14px` at line 40, `6px` at line 431
**How to avoid:** Grep for `radius-btn\|radius-card\|radius-panel\|radius-modal\|radius-toast\|radius-tooltip\|radius-tag\|radius-input` across all .js and .css files before declaring the alias tokens removed.

---

## Code Examples

### Existing Skeleton Infrastructure (verified)

```css
/* design-system.css line 3526 — existing base skeleton */
.skeleton {
  background: linear-gradient(90deg,
    var(--color-bg-subtle) 25%,
    var(--color-border) 50%,
    var(--color-bg-subtle) 75%
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

```css
/* app.css line 392 — content hidden during skeleton state */
.skeleton * { visibility: hidden; }
```

### Alpha Border Token Pattern (verified for oklch usage in this codebase)

```css
/* design-system.css — add to :root */
--color-border-alpha: oklch(0 0 0 / 0.08);

/* design-system.css — add to [data-theme="dark"] */
--color-border-alpha: oklch(1 1 0 / 0.08);
```

### Shadow Rename in Dark Mode Block (must mirror :root)

```css
/* [data-theme="dark"] block — update lines 695-714 */
--shadow-sm:  0 1px 2px rgb(var(--shadow-color) / 0.20),
              0 1px 1px rgb(var(--shadow-color) / 0.12);  /* was --shadow-xs */
--shadow-md:  0 4px 12px rgb(var(--shadow-color) / 0.34),
              0 2px 4px rgb(var(--shadow-color) / 0.20);  /* unchanged */
--shadow-lg:  0 16px 40px rgb(var(--shadow-color) / 0.50),
              0 6px 16px rgb(var(--shadow-color) / 0.26); /* was --shadow-xl */
/* Remove: --shadow-2xs, old --shadow-sm, --shadow, old --shadow-lg, --shadow-2xl */
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Multiple radius aliases per component | Single `--radius-base` token | Phase 83 | Adjusting one token changes all UI simultaneously |
| 9-level shadow scale | 3-level named semantic scale | Phase 83 | Forces intentional elevation choices, eliminates "which shadow?" decision |
| Opaque hex border colors | Alpha-based `oklch(L C H / alpha)` border | Phase 83 | Border subtlety adapts to any background — no separate dark mode border token needed |
| Spinner (`ag-spinner`) for dashboard loading | Skeleton shimmer placeholders | Phase 83 | Layout-preserving, faster perceived load, no jarring spinner-to-content flash |

---

## Open Questions

1. **Shadow intent for currently-`--shadow-sm` users in pages.css/hub.css/help.css**
   - What we know: These files use `var(--shadow-sm)` which currently maps to `0 1px 3px / 0.08` (low elevation).
   - What's unclear: After the rename, new `--shadow-sm` = old `--shadow-xs` (0 1px 2px / 0.06) — nearly identical, but slightly lighter. Likely fine but worth visual QA.
   - Recommendation: Accept the slight reduction; these are all card-level shadows where near-zero elevation is correct.

2. **`design-system.css` line 4257 shadow-xl fallback**
   - What we know: `box-shadow: var(--shadow-xl, 0 20px 60px rgba(0,0,0,.15))` — has a hardcoded rgba fallback.
   - What's unclear: Which component this applies to (need to read context around line 4257).
   - Recommendation: Update to `var(--shadow-lg)` with new fallback value matching new `--shadow-lg` definition.

3. **KPI skeleton: replace-on-load vs. always-present skeletons**
   - What we know: Session list already uses always-present `.skeleton.skeleton-session` divs that JS replaces. KPI cards use JS-inserted values into static placeholders showing `-`.
   - What's unclear: Whether to match the session list pattern (always-present skeletons, replaced by JS) or use a CSS-class toggle approach.
   - Recommendation: Use always-present skeletons for KPI cards (matches session list pattern already in codebase). dashboard.js should replace the 4 skeleton divs with real `.kpi-card` elements after API success, similar to how `prochaines.innerHTML` is set.

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit (unit tests only) |
| Config file | phpunit.xml |
| Quick run command | `timeout 60 php vendor/bin/phpunit tests/Unit/FichierConcerne.php --no-coverage` |
| Full suite command | `timeout 60 php vendor/bin/phpunit --no-coverage` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| COMP-01 | `--radius-base` token exists and all component aliases removed | manual-only (CSS visual) | Browser visual QA — no PHP unit test applicable | N/A |
| COMP-02 | Exactly 3 shadow tokens in :root (sm/md/lg + utilities) | manual-only (CSS visual) | Browser visual QA; grep can verify: `grep -c "^\s*--shadow-" design-system.css` | N/A |
| COMP-03 | Border on white vs light-gray background both show subtle edge | manual-only (CSS visual) | Browser visual QA — requires rendering context | N/A |
| COMP-04 | Skeleton shimmer appears on dashboard load, disappears after data | manual-only (browser) | Browser network throttle test | N/A |

**Note:** All phase 83 work is pure CSS/HTML — no PHP logic is affected. PHPUnit tests are not applicable. Validation is visual browser QA.

### Sampling Rate

- **Per task commit:** `php -l` syntax check on any modified PHP files (none expected this phase)
- **Per wave merge:** Browser visual QA on dashboard, light + dark mode, with and without `prefers-reduced-motion`
- **Phase gate:** All 4 success criteria verified visually before `/gsd:verify-work`

### Wave 0 Gaps

None — no new test files needed. This phase has no PHP logic changes.

---

## Sources

### Primary (HIGH confidence)
- Direct code audit: `/home/user/gestion_votes_php/public/assets/css/design-system.css` — shadow tokens lines 406-429, dark mode overrides lines 694-714, radius primitives lines 258-268, component aliases lines 526-536, skeleton lines 3526-3566
- Direct code audit: `/home/user/gestion_votes_php/public/assets/css/pages.css` — KPI card styles lines 1034-1081, skeleton-session lines 1112-1118
- Direct code audit: `/home/user/gestion_votes_php/public/assets/js/pages/dashboard.js` — KPI update logic lines 119-127, session list population lines 143-156
- Direct code audit: `/home/user/gestion_votes_php/public/dashboard.htmx.html` — KPI HTML lines 86-122, existing skeleton divs lines 142-145
- Direct code audit: `/home/user/gestion_votes_php/public/assets/js/components/ag-kpi.js`, `ag-modal.js`, `ag-spinner.js` — Shadow DOM radius/shadow literals

### Secondary (MEDIUM confidence)
- Cross-file grep for shadow token usage sites — confirmed specific files and line numbers requiring update
- Cross-file grep for `border-radius` hardcoded values — confirmed locations in web components

### Tertiary (LOW confidence)
- None

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all work uses CSS already present in the codebase; no external libraries
- Architecture: HIGH — token structure verified by direct file read; usage sites enumerated by grep
- Pitfalls: HIGH — each pitfall is grounded in specific observed code (verified file + line number)

**Research date:** 2026-04-03
**Valid until:** 2026-05-03 (stable CSS — no framework dependency)
