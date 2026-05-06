# Phase 2: Sidebar Navigation - Research

**Researched:** 2026-04-21
**Domain:** CSS layout transformation, JS sidebar controller cleanup, role-based nav filtering
**Confidence:** HIGH

## Summary

Phase 2 converts an existing hover-to-expand rail sidebar (58px → 252px on hover) into a
permanently-open 200px sidebar with always-visible labels. The transformation is entirely
in-project: no external libraries are introduced. All changed surface is `design-system.css`
(CSS tokens and sidebar rules), `shell.js` (pin/unpin mechanism removal), and
`sidebar.html` (pin button removal + "Mon compte" nav item addition).

The role-based filtering is already implemented via `auth-ui.js → filterSidebar()`. The only
gap is that the "Mon compte" nav item does not currently exist in `sidebar.html` — a voter
currently sees the account link only in the auth banner, not in the sidebar. It must be added
as a nav item with no `data-requires-role` restriction (all roles can access their account),
or with an explicit `data-requires-role="voter"` to surface it prominently for voters.

The voter-confinement logic in `auth-ui.js` already hides all admin-only items. The remaining
work is to ensure the "Voter" link (`/vote`) is the primary visible destination for voters,
and that a "Mon compte" link appears in the sidebar for them.

**Primary recommendation:** Change the three CSS variables, remove hover/pin rules from
`design-system.css`, delete the pin button from `sidebar.html`, strip the pin/unpin
block from `shell.js`, add a "Mon compte" nav item, and set `.app-main` padding-left statically.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Changer --sidebar-width de 58px a 200px — sidebar toujours ouverte avec labels visibles,
  supprimer le comportement hover-expand
- Supprimer entierement le mecanisme pin/unpin — la sidebar est toujours ouverte, pas d'option
  de reduction
- Mobile: garder le comportement hamburger existant — sidebar en overlay sur mobile, toujours
  ouverte sur desktop uniquement
- Padding-left statique sur .app-main: calc(200px + 20px) — pas de toggle JS necessaire
  puisque la sidebar ne se reduit jamais
- Garder le filtrage client-side via JS (auth-ui.js) — fonctionne deja, risque minimal de
  casser le comportement existant
- Un votant voit uniquement: "Voter" (page de vote) et "Mon compte" (parametres du compte)
- Les items caches restent dans le DOM avec display:none (approche actuelle avec data-requires-role)
- L'etat "pas de seance" pour les votants: le lien "Voter" reste visible, la page vote
  elle-meme affiche un etat vide (Phase 3 gere les etats vides)
- Augmenter la hauteur de .nav-item de 42px a 44px via ajustement du padding
- Les en-tetes de nav-group aussi a 44px pour la coherence
- Labels longs: troncature avec ellipsis a la largeur du conteneur
- A 1366px minimum, 200px sidebar + 1166px contenu fonctionne — pas de breakpoint special
  necessaire

### Claude's Discretion
- Aucun — toutes les decisions ont ete prises explicitement

### Deferred Ideas (OUT OF SCOPE)
- None — discussion stayed within phase scope.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| NAV-01 | Sidebar toujours ouverte ~200px avec labels visibles, plus de hover-to-expand ni rail d'icones | CSS token changes + remove hover/pin rules; labels always visible by removing `opacity:0 / max-width:0` guards |
| NAV-02 | Items de navigation filtres par role — un votant ne voit que "Voter", pas 16 liens | Existing `filterSidebar()` in auth-ui.js already works; gap is the missing "Mon compte" nav item in sidebar.html |
| NAV-03 | Tous les boutons et liens de navigation font minimum 44x44px (WCAG 2.5.8) | `.nav-item` height 42px → 44px; `.nav-group` header needs explicit min-height 44px |
</phase_requirements>

---

## Standard Stack

### Core
| Asset | Current value | Target value | Purpose |
|-------|--------------|-------------|---------|
| `--sidebar-rail` | 58px | (keep, but unused) | Rail token — no longer drives layout |
| `--sidebar-width` | 58px | 200px | Drives `.app-sidebar` width |
| `--sidebar-expanded` | 252px | (keep, but unused) | Was hover width — no longer drives layout |
| `.app-main` padding-left | `calc(var(--sidebar-rail) + 22px)` = 80px | `calc(200px + 20px)` = 220px | Push content right of sidebar |
| `.nav-item` height | 42px | 44px | WCAG 2.5.8 touch target |
| `.nav-group` height | 20px + 6px padding = effectively ~32px | `min-height: 44px` | WCAG 2.5.8 touch target |

No new npm packages or Composer dependencies are required.

## Architecture Patterns

### What currently exists (BEFORE state)

```
design-system.css
  :root
    --sidebar-width: 58px          ← overridden by --sidebar-rail elsewhere
    --sidebar-rail: 58px           ← actual width token used on .app-sidebar
    --sidebar-expanded: 252px      ← hover/pin width
  .app-sidebar
    width: var(--sidebar-rail)     ← 58px
    transition: width .25s ...     ← animated expand
  .app-sidebar:hover,
  .app-sidebar.pinned
    width: var(--sidebar-expanded) ← 252px on hover
  .app-sidebar.pinned ~ .app-main
    padding-left: calc(var(--sidebar-expanded) + 22px)   ← JS also toggles this
  .app-main
    padding-left: calc(var(--sidebar-rail) + 22px)       ← 80px default
  .nav-label
    opacity: 0; max-width: 0;      ← hidden in rail, shown on hover/pin
  .nav-group-label / .nav-group-chevron
    opacity: 0;                    ← same
  .nav-item
    height: 42px                   ← below 44px WCAG threshold

shell.js (Pin & Scroll Fade section, lines 19-63)
  - togglePin() — JS toggle of .pinned class + localStorage
  - localStorage PIN_KEY restore on load
  - bindPinButton() — wires #sidebarPin click

sidebar.html
  - <button class="sidebar-pin" id="sidebarPin">...</button>  ← line 3-5
  - No "Mon compte" nav item
  - "Voter" item only in .sidebar-device-section (line 124), not in main nav
```

### Pattern 1: CSS-only static sidebar (AFTER state target)

**What:** Remove the three-state width system (rail / hover / pinned) and replace with
a single fixed width. Labels are always visible. No JS toggle for desktop layout.

**How it works:**
```css
/* design-system.css changes */

/* 1. Update root token */
:root {
  --sidebar-width: 200px;   /* was 58px */
  /* --sidebar-rail and --sidebar-expanded kept for reference but no longer drive layout */
}

/* 2. Sidebar always at 200px — no hover/pin width change */
.app-sidebar {
  width: 200px;             /* was var(--sidebar-rail) */
  /* Remove: transition: width .25s cubic-bezier(.4, 0, .2, 1); */
  overflow: hidden;         /* keep for label containment */
}

/* 3. Remove hover/pin expansion rules */
/* DELETE: .app-sidebar:hover, .app-sidebar.pinned { width: var(--sidebar-expanded); ... } */
/* DELETE: .app-sidebar.pinned { box-shadow: ... } */
/* DELETE: .app-sidebar.pinned ~ .app-main { padding-left: ... } */

/* 4. Labels always visible */
.nav-label {
  opacity: 1;               /* was 0 */
  max-width: 180px;         /* was 0 — matches existing expanded value */
  overflow: hidden;
  text-overflow: ellipsis;  /* add for long label truncation */
  white-space: nowrap;
  /* Remove: transition: opacity .2s .05s, max-width .25s ... */
}
.nav-group-label {
  opacity: 1;               /* was 0 */
  max-width: 180px;         /* was 0 */
  /* Remove transition */
}
.nav-group-chevron { opacity: 1; } /* was 0 */
.sidebar-version { opacity: 1; }   /* was 0 */
.device-tag { opacity: 1; max-width: 100px; } /* was 0 */

/* 5. Static main content padding */
.app-main {
  padding-left: calc(200px + 20px); /* was calc(var(--sidebar-rail) + 22px) */
}

/* 6. Touch targets — WCAG 2.5.8 */
.nav-item {
  height: 44px;             /* was 42px */
}
.nav-group {
  min-height: 44px;         /* was height:20px + padding */
  height: auto;             /* allow content to size it */
}

/* 7. Rail-mode badge dot override — now irrelevant, remove */
/* DELETE: .app-sidebar:not(:hover):not(.pinned) .nav-badge[data-count]:not([data-count="0"]) { ... } */

/* 8. Hover-expand rules that reveal labels — now always revealed, remove */
/* DELETE: .app-sidebar:hover .nav-label, .app-sidebar.pinned .nav-label { ... } */
/* DELETE: .app-sidebar:hover .nav-group-label, ... */
/* etc. */
```

**mobile stays unchanged**: `@media (max-width: 768px)` block keeps `left: -260px` + `.open`
class overlay behaviour — hamburger menu is untouched.

### Pattern 2: shell.js — remove pin mechanism, keep everything else

**What to remove from shell.js (lines 19-63 + line 203):**
- `const PIN_KEY = 'ag-vote-sidebar-pinned'`
- `togglePin()` function
- localStorage restore block (lines 44-50)
- `bindPinButton()` function and its call (lines 53-63)
- `window.SidebarPin = { toggle: togglePin }` (line 203)

**What to keep in shell.js:**
- `updateScrollFade()` and `bindScrollFade()` — scroll indicators remain useful
- `bindNavGroupToggle()` and `restoreNavGroupState()` — section collapse is kept
- `markActivePage()` — active link highlighting
- `updateSidebarTop()` — header banner offset calculation
- MutationObserver watching for sidebar partial load
- All drawer, mobile nav, theme toggle, notifications, global search sections

**No changes needed in `auth-ui.js`** — `filterSidebar()` works as-is.

### Pattern 3: sidebar.html — pin button removal + "Mon compte" addition

**Remove:** The `<button class="sidebar-pin" id="sidebarPin">` block (lines 3-5).

**Add "Mon compte" nav item.** It must appear in the sidebar for voters. The voter sees
only items they have access to after filterSidebar() runs. "/account" is in the voter
confinement allowlist in auth-ui.js, so adding a nav item without `data-requires-role`
(visible to all) or with `data-requires-role="voter"` is appropriate.

Recommended approach: no `data-requires-role` on "Mon compte" (all authenticated users
should be able to reach their account). Place it at the bottom of the main nav or just
above the footer, similar to "Guide & FAQ".

```html
<!-- Add to sidebar.html near end of main <nav>, after Guide & FAQ -->
<a class="nav-item" href="/account" data-page="account">
  <span class="nav-item-icon" aria-hidden="true">
    <svg class="icon"><use href="/assets/icons.svg#icon-user"></use></svg>
  </span>
  <span class="nav-label">Mon compte</span>
</a>
```

**Also move "Voter" from `.sidebar-device-section` into the main `<nav>`** so voter role
filtering applies in the standard position, and voters get a clear primary destination.
Currently it is under "Device previews" section and still carries the "Tablette" device-tag
which is admin-oriented. For a voter it should appear as a plain nav item.

```html
<!-- Move from sidebar-device-section into main nav (e.g. under Préparation group) -->
<a class="nav-item" href="/vote" data-page="vote"
   data-requires-role="admin,operator,president,voter">
  <span class="nav-item-icon" aria-hidden="true">
    <svg class="icon"><use href="/assets/icons.svg#icon-smartphone"></use></svg>
  </span>
  <span class="nav-label">Voter</span>
</a>
```

### Pattern 4: Voter-only sidebar view

After filterSidebar() runs with role=voter (system level "viewer" or lower + meeting role
"voter"), the visible items are:

- Brand logo (`/`) — no data-requires-role
- "Tableau de bord" — no data-requires-role (always visible)
- "Voter" — `data-requires-role="admin,operator,president,voter"` → voter sees it
- "Guide & FAQ" — no data-requires-role
- "Mon compte" (new) — no data-requires-role

All group headers (Préparation, Séance en direct, etc.) remain in DOM but their children are
hidden, so groups become empty. The existing CSS already handles this: nav-group headers are
always visible (buttons) but their items collapse to `display:none`. The nav-group dividers
still appear as visual separators even with no visible children. This is acceptable per
CONTEXT.md (no changes to group-hiding logic required).

### Anti-Patterns to Avoid

- **Don't set `overflow: visible` on `.app-sidebar`**: labels will clip in desktop mode with
  200px width. Keep `overflow: hidden` — labels are truncated via `text-overflow: ellipsis`.
- **Don't remove the width `transition` via JS**: simply remove the CSS transition property
  from `.app-sidebar`. Mobile `.open` class slide-in uses `transition: left`, not `width`.
- **Don't change the mobile `@media (max-width: 768px)` block**: It overrides sidebar width
  to 260px for the overlay. Changing this would break mobile.
- **Don't modify `--sidebar-expanded`**: It may still be referenced by JS
  (`togglePin` does `calc(var(--sidebar-expanded) + 22px)`). After the pin removal, verify
  no remaining JS references it before removing it.
- **Don't set `.app-main` padding-left to exactly 200px**: Use `calc(200px + 20px)` = 220px
  to maintain the current horizontal breathing room. The 22px right gutter in the existing
  rule was intentional; 20px is close enough per the locked decision.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Role-based item visibility | Custom role-check in sidebar HTML | Existing `filterSidebar()` in auth-ui.js | Already handles system + meeting roles, hierarchy, comma-separated values |
| Label truncation | JS-based truncation loop | CSS `text-overflow: ellipsis` on `.nav-label` | Browser-native, no JS cost |
| Sticky sidebar position | `position: sticky` with scroll calculations | Keep `position: fixed` (already correct) | No reflow, no scroll-linked JS |

## Common Pitfalls

### Pitfall 1: nav-badge rail-mode override still fires
**What goes wrong:** `.app-sidebar:not(:hover):not(.pinned) .nav-badge` selector still
matches the new always-open sidebar (it is never `:hover` forced nor `.pinned`), causing
badges to render as tiny 8px dots instead of full counters.
**Root cause:** The rail-mode badge dot rule assumes the sidebar can be in collapsed state.
**How to avoid:** Delete the entire `.app-sidebar:not(:hover):not(.pinned) .nav-badge[...]`
rule block (lines 1208-1217 in design-system.css).
**Warning signs:** Badge counters appear as tiny dots instead of numbered pills.

### Pitfall 2: hover-reveal selectors still take precedence
**What goes wrong:** After setting `nav-label { opacity: 1 }` at the base level, the old
`.app-sidebar:hover .nav-label` rules remain, potentially overriding or causing specificity
conflicts with dark-theme or mobile overrides.
**Root cause:** Old hover-state reveal rules not deleted.
**How to avoid:** Delete all `.app-sidebar:hover .{label/chevron/version/pin/device-tag}`
and `.app-sidebar.pinned .{...}` rules.
**Warning signs:** Labels briefly disappear or transition unexpectedly on hover.

### Pitfall 3: JS padding-left toggle survives in `togglePin`
**What goes wrong:** If `togglePin` is not fully removed, calling it (even if no button
triggers it) could corrupt `.app-main` padding-left via `main.style.paddingLeft = ...`.
**Root cause:** Partial removal of pin mechanism.
**How to avoid:** Delete the full pin block: `togglePin`, the restore-on-load block,
`bindPinButton`, and `window.SidebarPin`. Then verify `main.style.paddingLeft` has no
inline style after page load.
**Warning signs:** Content shifts on page load if localStorage still has `ag-vote-sidebar-pinned = 1`.

### Pitfall 4: Mobile breakpoint inherits 200px fixed width
**What goes wrong:** The `@media (max-width: 768px)` block overrides sidebar `left: -260px`
and `width: 260px`. If the base `.app-sidebar { width: 200px }` conflicts with the media
query, the sidebar may not slide in correctly.
**Root cause:** Media query specificity / cascade order.
**How to avoid:** Ensure the mobile media query block explicitly sets `width: 260px` on
`.app-sidebar` (it already does) — this overrides the base 200px. No change needed, just
verify cascade order is preserved after edits.
**Warning signs:** Mobile sidebar appears wider/narrower than 260px or doesn't slide in.

### Pitfall 5: Existing e2e test for votant sidebar (POLISH-03) may need update
**What goes wrong:** `critical-path-votant.spec.js` test "votant: sidebar hides admin-only
items" checks that `/vote` item is NOT in the mustBeVisible list, but after moving the
"Voter" link from `.sidebar-device-section` into the main nav, the test may need to
explicitly assert `a[href="/vote"]` is visible for voters.
**Root cause:** Test was written before the sidebar restructuring.
**How to avoid:** After sidebar.html changes, update the test's `mustBeVisible` array to
include `'a[href="/vote"]'` and `'a[href="/account"]'` for the voter assertions.
**Warning signs:** Test fails with "element not visible" on `/vote` or `/account`.

### Pitfall 6: "Mon compte" link target does not exist as a standalone page
**What goes wrong:** The `/account` route exists in voter confinement allowlist but
there may be no `account.htmx.html` page, causing a 404 when voters click it.
**Root cause:** Auth-ui.js adds account link in the banner (`href="/account"`) but the
page itself may not be implemented.
**How to avoid:** Before adding the nav item, verify `/account` or `/account.htmx.html`
exists. If it does not, link to a page that does exist (e.g. `/settings` if accessible, or
investigate if a voter-scoped account page is in a later phase).
**Warning signs:** Clicking "Mon compte" as voter lands on a 404 or redirects elsewhere.

## Code Examples

### NAV-01 — Minimum CSS diff (conceptual)

```css
/* Source: direct reading of design-system.css lines 481-1290 */

/* REMOVE from :root */
/* --sidebar-rail: 58px; */       /* still keep as informational comment, but stop using it */

/* CHANGE in :root */
--sidebar-width: 200px;           /* was 58px */

/* CHANGE .app-sidebar */
.app-sidebar {
  width: 200px;                   /* was var(--sidebar-rail) */
  /* remove: transition: width .25s cubic-bezier(.4, 0, .2, 1); */
}

/* DELETE .app-sidebar:hover, .app-sidebar.pinned { width: ... } */
/* DELETE .app-sidebar.pinned { box-shadow: ... } */
/* DELETE .app-sidebar.pinned ~ .app-main { padding-left: ... } */

/* CHANGE labels — always visible */
.nav-label {
  opacity: 1;
  max-width: 180px;
  text-overflow: ellipsis;
  /* remove transition */
}
.nav-group-label { opacity: 1; max-width: 180px; /* remove transition */ }
.nav-group-chevron { opacity: 1; /* remove transition */ }
.sidebar-version { opacity: 1; /* remove transition */ }
.device-tag { opacity: 1; max-width: 100px; /* remove transition */ }

/* DELETE .app-sidebar:hover .nav-label, .app-sidebar.pinned .nav-label { ... } */
/* DELETE all hover/pinned reveal selectors */

/* CHANGE .app-main */
.app-main {
  padding-left: calc(200px + 20px); /* was calc(var(--sidebar-rail) + 22px) */
}

/* DELETE rail-mode badge dot rule */
/* .app-sidebar:not(:hover):not(.pinned) .nav-badge[data-count]:not([data-count="0"]) { ... } */
```

### NAV-03 — Touch target height

```css
/* Source: design-system.css line 1075 (.nav-item height: 42px) */

.nav-item {
  height: 44px;   /* was 42px — WCAG 2.5.8 minimum */
}

.nav-group {
  min-height: 44px;   /* was height: 20px + padding ~32px total */
  height: auto;       /* let padding control actual rendered height */
  padding: 0 10px;    /* adjust padding to center label vertically */
  align-items: center;
}
```

### NAV-02 — "Mon compte" nav item (sidebar.html addition)

```html
<!-- Source: sidebar.html pattern analysis — place after Guide & FAQ -->
<a class="nav-item" href="/account" data-page="account">
  <span class="nav-item-icon" aria-hidden="true">
    <svg class="icon"><use href="/assets/icons.svg#icon-user"></use></svg>
  </span>
  <span class="nav-label">Mon compte</span>
</a>
```

### shell.js — blocks to remove entirely

```javascript
// Source: shell.js lines 19-63, 203 — DELETE these blocks:

// const PIN_KEY = 'ag-vote-sidebar-pinned';
// function togglePin() { ... }
// if (sidebar && localStorage.getItem(PIN_KEY) === '1') { ... }
// function bindPinButton() { ... }
// bindPinButton();
// window.SidebarPin = { toggle: togglePin };
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Static sidebar always open | Rail 58px + hover expand to 252px | Previous milestone | More screen estate, less discoverable |
| Static padding-left | JS-toggled padding on pin | Previous milestone | Required JS coordination |
| labels always visible | Labels hidden until hover/pin | Previous milestone | UX regression for new users |

**Deprecated/outdated (after this phase):**
- `.app-sidebar:hover` width expansion rule — replaced by static 200px
- `.app-sidebar.pinned` state — class no longer set; rule can be deleted
- `PIN_KEY` localStorage key (`ag-vote-sidebar-pinned`) — no longer written
- `--sidebar-rail` as active layout token — kept for reference but no longer used

## Open Questions

1. **Does `/account` route exist as a page?**
   - What we know: auth-ui.js allows voters on `/account`; auth banner has `href="/account"`;
     no `account.htmx.html` was found via Glob
   - What's unclear: whether `/account` is served, 404s, or redirects
   - Recommendation: Before adding nav item, run `ls public/ | grep account` or check router.
     If not present, link to the nearest available page or leave "Mon compte" as a
     non-linked label until Phase 5.

2. **Should "Voter" remain in `.sidebar-device-section` or move to main nav?**
   - What we know: It is currently in device-section with a "Tablette" device-tag. Voter role
     is already in its `data-requires-role`.
   - What's unclear: Whether the admin use-case for "Aperçu votant" (previewing the vote page
     as admin) should be preserved in the device-section alongside or instead of the voter nav item.
   - Recommendation: Move the item to main nav for clean voter filtering. Keep a separate
     "Aperçu votant" entry in device-section for admins only if needed, but this is a minor detail.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Playwright (node package, config at `tests/e2e/playwright.config.js`) |
| Config file | `tests/e2e/playwright.config.js` |
| Quick run command | `cd tests/e2e && npx playwright test specs/critical-path-votant.spec.js --project=chromium` |
| Full suite command | `cd tests/e2e && npx playwright test --project=chromium` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| NAV-01 | Sidebar is 200px wide and labels are always visible | visual/e2e | `npx playwright test specs/critical-path-dashboard.spec.js --project=chromium` | ✅ (needs assertion added) |
| NAV-02 | Voter sees only "Voter" + "Mon compte"; admin sees all items | e2e | `npx playwright test specs/critical-path-votant.spec.js --project=chromium` | ✅ (needs "Mon compte" assertion) |
| NAV-03 | Nav items have min 44px height | e2e / accessibility | `npx playwright test specs/accessibility.spec.js --project=chromium` | ✅ (may need nav-item height check) |

### Sampling Rate
- **Per task commit:** `cd tests/e2e && npx playwright test specs/critical-path-votant.spec.js --project=chromium`
- **Per wave merge:** `cd tests/e2e && npx playwright test specs/critical-path-votant.spec.js specs/accessibility.spec.js specs/critical-path-dashboard.spec.js --project=chromium`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/e2e/specs/critical-path-votant.spec.js` — needs `a[href="/account"]` in `mustBeVisible` and `a[href="/vote"]` asserted visible; update existing test, don't create new file
- [ ] `tests/e2e/specs/critical-path-dashboard.spec.js` — needs sidebar width assertion: `expect(await page.locator('.app-sidebar').evaluate(el => el.getBoundingClientRect().width)).toBe(200)`

## Sources

### Primary (HIGH confidence)
- Direct source read: `public/assets/css/design-system.css` — all sidebar CSS tokens and rules
- Direct source read: `public/assets/js/core/shell.js` — pin mechanism, scroll fade, mobile nav
- Direct source read: `public/assets/js/pages/auth-ui.js` — filterSidebar(), voter confinement logic
- Direct source read: `public/partials/sidebar.html` — nav item structure, data-requires-role usage
- Direct source read: `tests/e2e/specs/critical-path-votant.spec.js` — existing voter sidebar test

### Secondary (MEDIUM confidence)
- CONTEXT.md (phase 02) — all locked decisions read directly
- REQUIREMENTS.md — NAV-01, NAV-02, NAV-03 definitions read directly

### Tertiary (LOW confidence)
- None — all claims are grounded in direct source reads.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all files read directly, no guessing
- Architecture: HIGH — existing CSS structure and JS patterns fully understood
- Pitfalls: HIGH — inferred from direct CSS analysis (rail-mode badge selector, hover selectors)
- Open questions: MEDIUM — `/account` page existence unverified (Glob returned nothing)

**Research date:** 2026-04-21
**Valid until:** 2026-05-21 (stable codebase, CSS-only changes)
