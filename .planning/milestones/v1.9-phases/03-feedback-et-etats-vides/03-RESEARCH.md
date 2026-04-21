# Phase 03: Feedback et Etats Vides - Research

**Researched:** 2026-04-21
**Domain:** Frontend UI — feedback states, empty states, loading indicators, vote confirmation
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

#### Empty State Strategy (FEED-01)
- Etendre le composant web ag-empty-state existant (supporte deja icon + titre + description + bouton CTA)
- Toutes les pages avec listes/grilles: meetings, members, archives, users, email-templates, audit
- Messages actionnables avec boutons CTA (ex: "Creez votre premiere seance" avec lien vers la creation)
- Meme composant ag-empty-state pour les etats vides imbriques (onglets operateur) — le pattern tab-empty-guide existant fonctionne deja

#### Vote Confirmation & Loading (FEED-02, FEED-04)
- Confirmation de vote: garder le div vote-confirmed-state visible en permanence jusqu'a l'ouverture d'un nouveau vote (supprimer le timeout/reset de 3s) et ajouter horodatage
- Format horodatage: "Vote enregistre le 21/04/2026 a 14:32" — format date francais
- Indicateur de chargement: ajouter un label "Chargement..." a cote des skeleton loaders existants — ne pas supprimer les patterns visuels skeleton
- Afficher le texte de chargement sur les zones de contenu HTMX via .htmx-indicator — visible pendant les requetes HTMX

#### No-Results & Filter Reset (FEED-03)
- Utiliser ag-empty-state avec une icone recherche/filtre, message, et lien "Reinitialiser les filtres"
- Pages avec filtres: meetings (filter pills), members (filter chips), archives (search), audit (filtres date/type)
- Le lien "Reinitialiser" efface l'etat du filtre et declenche un rechargement HTMX de la liste (hx-get sans params de filtre)
- Les dropdowns ag-searchable-select gardent leur attribut empty-text existant (deja "Aucun votant trouve")

### Claude's Discretion
- Aucun — toutes les decisions ont ete prises explicitement

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| FEED-01 | Chaque liste/grille affiche un message clair quand vide ("Creez votre premiere seance") au lieu de skeletons suspendus | ag-empty-state web component is already built; meetings.js already uses it — pattern is established, apply to remaining pages |
| FEED-02 | Apres un vote, confirmation persistante visible (pas un flash 3s) avec horodatage | `showConfirmationState()` in vote.js has the 3s setTimeout — remove it and add timestamp rendering to `#confirmedText` |
| FEED-03 | Filtres et recherches affichent "Aucun resultat" avec suggestion de reinitialiser les filtres | meetings.js and members.js have partial empty state; FEED-03 specifically requires a reset CTA + action that clears filters |
| FEED-04 | Indicateur de chargement explicite en francais ("Chargement...") au lieu de skeletons silencieux | .htmx-indicator divs exist in meetings, members, users, audit — add text label alongside the existing skeleton markup |
</phase_requirements>

---

## Summary

Phase 3 is a **pure frontend HTML/CSS/JS phase** — no PHP backend changes, no API modifications, no new dependencies. All work targets the public HTML pages (`public/*.htmx.html`) and their companion JavaScript files (`public/assets/js/pages/`).

The project already has a fully-functional `ag-empty-state` web component (`public/assets/js/components/ag-empty-state.js`) that renders icon + title + description + optional CTA button. It is already used on the meetings page via `renderEmptyState()` in `meetings.js` and on the members page. The task for FEED-01 is to apply this established pattern to the remaining pages (archives, users, email-templates, audit) which currently show either spinners, raw skeleton rows, or no empty states at all.

The vote confirmation bug (FEED-02) is localized in a single function `showConfirmationState()` in `vote.js` line 1406-1409 — the `setTimeout(..., 3000)` must be removed and a French timestamp must be written to the existing `#vote-confirmed-text` element. FEED-04 (loading text) is a matter of adding a `<span class="htmx-indicator">Chargement...</span>` text node adjacent to each skeleton loader block. The `.htmx-indicator` CSS infrastructure in `design-system.css` already handles show/hide via `.htmx-request` parent class.

**Primary recommendation:** Work page by page — fix vote.js first (FEED-02, isolated), then add loading labels to each list page (FEED-04), then standardize empty states with filter reset CTAs (FEED-01, FEED-03).

---

## Standard Stack

### Core
| Asset | Location | Purpose |
|-------|----------|---------|
| `ag-empty-state.js` | `public/assets/js/components/ag-empty-state.js` | Web component for all empty states. Attribute-driven. Already defined and loaded on meetings + dashboard |
| `design-system.css` | `public/assets/css/design-system.css` | Source of `.empty-state`, `.skeleton`, `.skeleton-row`, `.skeleton-cell`, `.spinner`, `.htmx-indicator` classes |
| `vote.js` | `public/assets/js/pages/vote.js` | Contains `showConfirmationState()` — the 3s timeout to remove |
| `meetings.js` | `public/assets/js/pages/meetings.js` | Already uses `ag-empty-state` + `renderEmptyState()` — reference implementation for FEED-01/FEED-03 |
| `members.js` | `public/assets/js/pages/members.js` | Partially uses `ag-empty-state` for filtered no-results; needs filter reset CTA (FEED-03) |

### Existing Empty State Icons (in ag-empty-state.js)
| Value | Visual | Best Use |
|-------|--------|----------|
| `meetings` | Document with plus sign | Meetings page empty list |
| `members` | Silhouettes | Members page empty list |
| `archives` | Archive box | Archives page |
| `votes` | Ballot | Vote-related empties |
| `generic` | Circle with info | Filter no-results, audit, users |

**Note:** There is no `search` or `filter` icon variant in the component. For FEED-03 (no-results with filter reset), use `icon="generic"` — this is consistent with the existing fallback in `meetings.js` line 316.

---

## Architecture Patterns

### Pattern 1: ag-empty-state in JS-rendered lists (FEED-01 standard approach)
**What:** JS page scripts render empty state HTML by injecting `<ag-empty-state>` tag into the list container after data fetch returns empty.
**When to use:** All pages where JS renders the list (meetings, members, users, email-templates, archives, audit).
**Example (from meetings.js, HIGH confidence — verified in codebase):**
```javascript
// renderEmptyState() in meetings.js — reference pattern
function renderEmptyState(filter) {
  if (filter === 'all' && allMeetings.length === 0) {
    return '<ag-empty-state icon="meetings" title="Aucune séance" ' +
           'description="Créez votre première séance pour commencer." ' +
           'action-label="Nouvelle séance" action-href="/wizard"></ag-empty-state>';
  }
  // no-results with search
  return '<ag-empty-state icon="generic" title="Aucun résultat" ' +
         'description="Essayez un autre terme de recherche."></ag-empty-state>';
}
```

### Pattern 2: Filter reset CTA for FEED-03 (new pattern — not yet in codebase)
**What:** When filters produce zero results, ag-empty-state includes an actionable link/button that clears all filters and re-triggers the list render.
**When to use:** meetings (filter pills + search), members (filter chips + search), archives (filter tabs + search), audit (filter tabs + search).
**Pattern (to implement):**
```javascript
// For JS-managed filter state (meetings, members):
// Render ag-empty-state with slotted button that calls resetFilters()
container.innerHTML = '<ag-empty-state icon="generic" ' +
  'title="Aucun résultat" ' +
  'description="Aucune séance ne correspond aux filtres actifs.">' +
  '<button slot="action" class="btn btn-secondary btn-sm" id="btnResetFilters">' +
  'Réinitialiser les filtres</button>' +
  '</ag-empty-state>';
// Then attach event listener to the injected button
document.getElementById('btnResetFilters').addEventListener('click', resetFilters);
```
**Important:** The slotted button pattern is already supported in ag-empty-state.js (see `connectedCallback` + `slottedAction` preservation logic).

### Pattern 3: htmx-indicator text label for FEED-04
**What:** Add a text-only `<span class="htmx-indicator">` adjacent to skeleton rows. The CSS already hides it by default and shows it during `.htmx-request`.
**When to use:** Any container that uses HTMX requests and shows skeleton rows (meetingsList, membersList, usersTableBody, auditTableBody).
**Example (HTML change only):**
```html
<!-- Before (meetings.htmx.html #meetingsList) -->
<div class="sessions-list" id="meetingsList">
  <div class="htmx-indicator" aria-hidden="true">
    <div class="skeleton-row">...</div>
  </div>
  ...
</div>

<!-- After: add text label inside or beside the htmx-indicator -->
<div class="sessions-list" id="meetingsList">
  <div class="htmx-indicator">
    <span class="loading-text-label">Chargement...</span>
    <div class="skeleton-row">...</div>
  </div>
  ...
</div>
```
**CSS needed:** A `.loading-text-label` class (or inline style) to position the text above/below the skeleton rows. The `.htmx-indicator` parent already controls visibility.

**Alternative for non-HTMX pages:** For pages where JS renders the list directly (not via hx-get), add a visible loading state during the async fetch, then replace with content or empty state. The `aria-busy="true"` pattern already exists on `#usersTableBody`.

### Pattern 4: Vote confirmation persistent state (FEED-02)
**What:** Remove the 3-second auto-reset in `showConfirmationState()` and add a French-formatted timestamp.
**Current code (vote.js line 1406-1409):**
```javascript
function showConfirmationState() {
  setVoteAppState('confirmed');
  setTimeout(function() { setVoteAppState('waiting'); }, 3000); // REMOVE THIS LINE
}
```
**New logic:**
```javascript
function showConfirmationState() {
  setVoteAppState('confirmed');
  // Write French timestamp to the confirmed text element
  var now = new Date();
  var day = String(now.getDate()).padStart(2, '0');
  var month = String(now.getMonth() + 1).padStart(2, '0');
  var year = now.getFullYear();
  var hours = String(now.getHours()).padStart(2, '0');
  var minutes = String(now.getMinutes()).padStart(2, '0');
  var timestamp = 'Vote enregistré le ' + day + '/' + month + '/' + year + ' à ' + hours + ':' + minutes;
  var textEl = document.getElementById('voteConfirmedText'); // or create new element
  if (textEl) textEl.textContent = timestamp;
}
```
**HTML side:** The existing `vote-confirmed-state` div has `<p class="vote-confirmed-text">Vote enregistré</p>` — this element should receive the timestamp text, or a new `<p id="voteConfirmedTimestamp">` should be added after it.

**Reset trigger:** When a new vote opens, `setVoteAppState('voting')` is called — this naturally hides the confirmed state. No explicit reset needed since confirmed state is hidden by CSS when `data-vote-state != 'confirmed'`.

### Pattern 5: HTML static empty states (email-templates)
**What:** Some pages already have a raw `.empty-state` div in HTML (not web component). email-templates.htmx.html line 81 uses a manual div `#emptyState`. This should be migrated to `<ag-empty-state>` for consistency.
**When to use:** email-templates.js controls visibility of `#emptyState` via `hidden` attribute.

### Anti-Patterns to Avoid
- **Don't add new JavaScript files:** All changes go into existing page JS files. No new `*-empty-state.js` helper modules.
- **Don't remove skeleton markup:** CONTEXT.md is explicit — keep visual skeleton rows, add text label alongside them.
- **Don't use inline setTimeout for confirmation reset:** The new model is that confirmed state stays until the next vote opens (state machine handles it).
- **Don't use the `icon-search` SVG attribute in ag-empty-state:** The component only supports 5 icon keys (meetings, members, votes, archives, generic). Use `generic` for filter/search empties.
- **Don't trigger hx-get in filter reset on pages where the list is JS-rendered:** On meetings and members, the list is fully JS-managed (client-side filter on in-memory data). "Reinitialiser" should reset JS state variables and call `renderSessionList()` / `renderPage()`, NOT fire an HTMX request.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead |
|---------|-------------|-------------|
| Empty state UI | Custom div with inline SVG | `<ag-empty-state>` web component — already has icon, title, description, CTA |
| Loading spinner | New CSS keyframe or SVG spinner | Existing `.spinner` class in design-system.css |
| French date formatting | Intl.DateTimeFormat or moment.js | Simple string interpolation with `Date` methods (no locale API needed for fixed format `DD/MM/YYYY à HH:MM`) |
| Filter reset coordination | Event bus or global state | Direct function call to existing `resetFilters()` / `currentFilter = 'all'; renderPage()` pattern |

---

## Common Pitfalls

### Pitfall 1: ag-empty-state not loaded on all pages
**What goes wrong:** Pages like `archives.htmx.html` and `audit.htmx.html` do NOT include `ag-empty-state.js` in their script tags. Injecting `<ag-empty-state>` into the DOM will result in an unknown element that renders nothing.
**Why it happens:** The component is loaded via `type="module"` in `components/index.js` on some pages, and directly via script tag on others (dashboard.htmx.html). Not all pages include either.
**How to avoid:** Before using `ag-empty-state` on a page, verify the page's script list includes either:
- `<script type="module" src="/assets/js/components/index.js">` (operator.htmx.html, meetings.htmx.html, members.htmx.html pattern), OR
- `<script src="/assets/js/components/ag-empty-state.js">` (dashboard.htmx.html pattern)
**Warning signs:** Empty container where the ag-empty-state tag was injected.

### Pitfall 2: slotted action button loses event listener after innerHTML replace
**What goes wrong:** If the container is re-rendered via `innerHTML = ...`, any event listeners attached to the filter-reset button inside the empty state are lost.
**Why it happens:** The button is created then destroyed when the list re-renders.
**How to avoid:** Use event delegation on the stable container element (e.g., `membersList` or `meetingsList`) to catch clicks from the reset button via `data-action="reset-filters"` attribute.

### Pitfall 3: vote confirmation state lingering across multiple votes
**What goes wrong:** Removing the 3s setTimeout means confirmed state persists indefinitely. If `setVoteAppState('voting')` is never called (e.g., next vote has a delay), the confirmed state could linger.
**Why it happens:** The state machine relies on `setVoteAppState()` calls from SSE events or JS logic.
**How to avoid:** Verify that when a new ballot opens via SSE/polling, `setVoteAppState('voting')` is always called before the vote buttons become active. The existing SSE handler in vote.js should already handle this — confirm it calls `setVoteAppState('voting')` when `data.state === 'open'`.

### Pitfall 4: htmx-indicator visible during initial page load without HTMX request
**What goes wrong:** The skeleton rows in `#meetingsList` are not inside the `.htmx-indicator` div — they are siblings. Adding a "Chargement..." label inside `.htmx-indicator` only shows it during HTMX requests, not during the initial JS data fetch.
**Why it happens:** HTMX indicator CSS (`display: none` → `display: inline-flex` on `.htmx-request`) only fires during actual HTMX swaps. Initial page load where JS fetches data via `api()` is not an HTMX request.
**How to avoid:** For FEED-04, distinguish two loading scenarios:
1. **HTMX-triggered reload** (triggered by filter buttons with `hx-get`): `.htmx-indicator` handles this automatically.
2. **Initial JS `api()` fetch** (meetings.js `loadMeetings()`, members.js `loadMembers()`): Add a loading label programmatically before the fetch, remove it in the success/error handler.
**Inspection:** In `meetings.htmx.html`, the skeleton rows are NOT inside `.htmx-indicator` — they are direct children of `#meetingsList`. The `.htmx-indicator` div with skeleton rows is a separate block. This confirms initial load skeletons are controlled by JS (replaced when data arrives), not HTMX.

### Pitfall 5: archives and audit lists use custom render, not ag-empty-state
**What goes wrong:** `archives.js` renders `#archivesList` with custom HTML including a `.archives-loading` spinner div. `audit.js` populates `#auditTableBody` directly. Neither currently calls `ag-empty-state`.
**How to avoid:** After the data fetch in each JS file, check `data.length === 0` and inject `<ag-empty-state>` into the container instead of leaving the spinner or injecting nothing.

---

## Code Examples

### Current skeleton loader pattern (meetings.htmx.html — verified)
```html
<div class="sessions-list" id="meetingsList">
  <div class="htmx-indicator" aria-hidden="true">
    <div class="skeleton-row"><div class="skeleton skeleton-cell"></div></div>
    <div class="skeleton-row"><div class="skeleton skeleton-cell"></div></div>
    <div class="skeleton-row"><div class="skeleton skeleton-cell"></div></div>
  </div>
  <div class="skeleton skeleton-session-item"></div>
  <div class="skeleton skeleton-session-item"></div>
  <div class="skeleton skeleton-session-item"></div>
</div>
```
**Note:** The `.htmx-indicator` block and the standalone `.skeleton-session-item` blocks are both present. FEED-04 should add a text label to the `.htmx-indicator` block. The standalone skeleton items are replaced by JS when data loads.

### Current showConfirmationState (vote.js line 1403-1409 — verified)
```javascript
/**
 * Show confirmed state for 3 seconds then return to waiting.
 */
function showConfirmationState() {
  setVoteAppState('confirmed');
  setTimeout(function() { setVoteAppState('waiting'); }, 3000); // REMOVE
}
```

### Current vote-confirmed-state HTML (vote.htmx.html line 120-127 — verified)
```html
<div class="vote-confirmed-state" id="voteConfirmedState">
  <div class="vote-confirmed-icon" aria-hidden="true">
    <svg class="icon icon-xl"><use href="/assets/icons.svg#icon-check"></use></svg>
  </div>
  <p class="vote-confirmed-choice" id="confirmedChoice"></p>
  <p class="vote-confirmed-text">Vote enregistré</p>
  <p class="vote-confirmed-irreversible">Ce vote est définitif et irréversible.</p>
</div>
```
**For FEED-02:** Add `<p id="voteConfirmedTimestamp" class="vote-confirmed-timestamp"></p>` after `vote-confirmed-text`, then write the French timestamp to it in `showConfirmationState()`.

### existing ag-empty-state usage in members.js (verified)
```javascript
// No results (filtered state)
membersList.innerHTML = '<ag-empty-state icon="members" title="Aucun résultat" ' +
  'description="Essayez un autre terme de recherche."></ag-empty-state>';

// No members at all (unfiltered state)
// Uses custom empty-state-guided div (not ag-empty-state web component)
membersList.innerHTML = '<div class="empty-state-guided">...</div>';
```
**For FEED-01:** The "no members at all" case should be migrated to `ag-empty-state` with CTA (action-label + action-href or slotted button) for consistency.

---

## Page-by-Page Inventory

### Pages requiring FEED-01 (empty state when list is truly empty)

| Page | List Container | Current Empty Handling | Required Change |
|------|----------------|----------------------|-----------------|
| `meetings.htmx.html` | `#meetingsList` | `ag-empty-state` via `renderEmptyState()` | Already done for filter=all; FEED-01 confirms it |
| `members.htmx.html` | `#membersList` | Mixed: custom div for unfiltered, `ag-empty-state` for filtered | Migrate custom div to `ag-empty-state` with dual CTA (add manually / import CSV) |
| `archives.htmx.html` | `#archivesList` | `.archives-loading` spinner div, no empty state | Add `ag-empty-state` with icon=archives when data.length===0 |
| `users.htmx.html` | `#usersTableBody` | 3 skeleton rows, no empty state | Add `ag-empty-state` with icon=generic and "Ajoutez votre premier utilisateur" CTA |
| `email-templates.htmx.html` | `#templatesGrid` / `#emptyState` | Manual div `#emptyState` (not web component) | Replace `#emptyState` with `<ag-empty-state>` for consistency |
| `audit.htmx.html` | `#auditTableBody` / `#auditTimeline` | Spinner only | Add `ag-empty-state` when audit returns empty |

### Pages requiring FEED-03 (no-results with filter reset CTA)

| Page | Filter Controls | Current No-Results | Required Change |
|------|----------------|-------------------|-----------------|
| `meetings.htmx.html` | Filter pills + search | `ag-empty-state` without reset button | Add filter reset button (slotted or action-label pointing to reset) |
| `members.htmx.html` | Filter chips + search | `ag-empty-state` without reset button | Add filter reset button |
| `archives.htmx.html` | Filter tabs + search | No empty state | Add `ag-empty-state` with reset button |
| `audit.htmx.html` | Filter tabs + search | No empty state | Add `ag-empty-state` with reset button |

### Pages requiring FEED-04 (loading text)

| Page | Skeleton Location | Current Loading Feedback | Required Change |
|------|-------------------|------------------------|-----------------|
| `meetings.htmx.html` | `#meetingsList .htmx-indicator` | Silent skeleton shimmer | Add "Chargement..." text in `.htmx-indicator` |
| `members.htmx.html` | `#membersList .htmx-indicator` | Silent skeleton shimmer | Add "Chargement..." text |
| `users.htmx.html` | `#usersTableBody .skeleton-row`s | Silent skeleton shimmer | Add "Chargement..." text before/above skeletons |
| `audit.htmx.html` | Inline spinner in `<td>` | Has `aria-label="Chargement..."` but no visible text | Add visible text label |
| `archives.htmx.html` | `.archives-loading` with spinner | Has text "Chargement des archives…" | Already satisfies FEED-04 — verify only |
| `vote.htmx.html` | `#voteLoadingState` skeletons | Silent skeleton shimmer | Add "Chargement..." text |

---

## State of the Art

| Old Pattern | Current Pattern | Impact for This Phase |
|-------------|-----------------|----------------------|
| Manual div with inline SVG for empty state | `<ag-empty-state>` web component | Use the component everywhere; legacy divs in email-templates need migration |
| `setTimeout` to auto-dismiss confirmation | Permanent state until next vote opens | Remove the setTimeout in `showConfirmationState()` |
| Silent shimmer skeleton = "loading" | Skeleton + explicit French text label | Add text label alongside existing skeletons |

---

## Open Questions

1. **archives.htmx.html already has loading text**
   - What we know: `#archivesList` has `.archives-loading` div with a spinner AND "Chargement des archives…" text (line 148-153)
   - What's unclear: Does this satisfy FEED-04 for archives, or does it need to match the exact wording "Chargement..."?
   - Recommendation: The archives loading text "Chargement des archives…" satisfies FEED-04 in spirit. Only verify it is visible and in French — no change needed unless the planner wants exact wording consistency.

2. **email-templates.htmx.html empty state uses manual div, not web component**
   - What we know: `#emptyState` is a manually-coded `.empty-state` div with a mail SVG icon and a CTA button (line 81-89).
   - What's unclear: Should this be migrated to `<ag-empty-state>` or left as-is since it already has the right visual?
   - Recommendation: Migrate to `<ag-empty-state>` for consistency (FEED-01 standard), but it is already functionally correct.

3. **vote.js SSE path resets state to 'waiting' when vote closes**
   - What we know: `showConfirmationState()` currently resets to `waiting` after 3s.
   - What's unclear: What happens to the confirmed state when the next vote opens via SSE?
   - Recommendation: Verify in vote.js that the SSE handler for `ballot.opened` / `vote.open` calls `setVoteAppState('voting')` — this is the natural replacement for the setTimeout reset.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Playwright (e2e, in `tests/e2e/`) |
| Config file | `tests/e2e/playwright.config.js` |
| Quick run command | `npx playwright test tests/e2e/specs/critical-path-vote.spec.js` |
| Full suite command | `npx playwright test tests/e2e/specs/` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| FEED-01 | Each list page shows ag-empty-state when data is empty | smoke | `npx playwright test tests/e2e/specs/critical-path-meetings.spec.js` | ✅ (verify no-data empty state path) |
| FEED-02 | Vote confirmation persists with French timestamp | e2e | `npx playwright test tests/e2e/specs/critical-path-vote.spec.js` | ✅ (needs assertion added) |
| FEED-03 | Filtered pages show "Aucun résultat" with reset button | smoke | `npx playwright test tests/e2e/specs/critical-path-members.spec.js` | ✅ (needs assertion added) |
| FEED-04 | "Chargement..." text visible during loading | smoke | `npx playwright test tests/e2e/specs/critical-path-meetings.spec.js` | ✅ (structure test, needs new assertion) |

### Sampling Rate
- **Per task commit:** `npx playwright test tests/e2e/specs/critical-path-vote.spec.js` (FEED-02 isolation)
- **Per wave merge:** `npx playwright test tests/e2e/specs/critical-path-meetings.spec.js tests/e2e/specs/critical-path-members.spec.js tests/e2e/specs/critical-path-vote.spec.js`
- **Phase gate:** Full critical-path suite before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] New assertions in `critical-path-vote.spec.js` — assert `#voteConfirmedTimestamp` exists and contains date format `DD/MM/YYYY`
- [ ] New assertions in `critical-path-meetings.spec.js` — assert `ag-empty-state` present when meetings list empty
- [ ] New assertions in `critical-path-members.spec.js` — assert filter reset button exists after filtering to empty

*(Existing test files cover page load and basic interactions; feedback-state assertions need to be added within existing spec files — no new spec files required)*

---

## Sources

### Primary (HIGH confidence)
- Codebase direct inspection — `public/assets/js/components/ag-empty-state.js` (full source read)
- Codebase direct inspection — `public/assets/js/pages/vote.js` (line 1403-1409, `showConfirmationState`)
- Codebase direct inspection — `public/assets/js/pages/meetings.js` (lines 300-317, `renderEmptyState`)
- Codebase direct inspection — `public/assets/js/pages/members.js` (lines 544-563, empty state rendering)
- Codebase direct inspection — `public/assets/css/design-system.css` (lines 3215-3236, `.htmx-indicator` CSS)
- Codebase direct inspection — `public/assets/css/design-system.css` (lines 2479-2510, `.empty-state` CSS)
- Codebase direct inspection — all `.htmx.html` pages for current empty state and loading state inventory

### Secondary (MEDIUM confidence)
- HTMX documentation (well-known): `.htmx-indicator` shown during `.htmx-request` via CSS class toggling — confirmed by design-system.css implementation matching documented behavior

---

## Metadata

**Confidence breakdown:**
- Empty state component (FEED-01): HIGH — component fully built, already in use on 2 pages
- Vote confirmation fix (FEED-02): HIGH — single function, single setTimeout to remove, element IDs confirmed
- Filter reset pattern (FEED-03): HIGH — slotted action already supported by ag-empty-state, event delegation pattern is standard
- Loading text (FEED-04): HIGH — .htmx-indicator CSS confirmed, no new infrastructure needed

**Research date:** 2026-04-21
**Valid until:** 2026-06-01 (stable codebase, no external dependencies)
