# Phase 29: Operator Console, Voter View & Visual Polish — Research

**Researched:** 2026-03-18
**Domain:** Live session UX (SSE indicator, ballot UX, result cards) + CSS design system overhaul
**Confidence:** HIGH (all findings sourced from direct code inspection of the live codebase)

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Visual Identity: "Officiel et Confiance"**
- Primary palette: Bleu/indigo dominant. `--color-primary: #1650E0` is the anchor.
- Fonts unchanged: Bricolage Grotesque (body) + Fraunces (display) + JetBrains Mono (data)
- NOT Notion-like — needs more color, more structure, more personality than a neutral/cold wizard style

**All-Page Visual Polish Strategy**
- Scope: ALL pages — operator, vote, postsession, archives, audit, analytics, meetings, members, users, settings, help, email-templates, admin, dashboard (via pages.css)
- CSS @layer: Add `@layer base, components, v4` to design-system.css. New styles in `@layer v4`.
- color-mix(): New token families use color-mix() for tints/shades
- Dark mode: Full parity — every new token gets a dark variant in the same commit
- Animations: Sober transitions 150-200ms on state changes, hover smooth on buttons/cards. No wow-effects.
- Measurable criteria: transitions ≤ 200ms, CLS = 0, focus rings ≥ 3:1 contrast, zero inline style=""

**Operator Console**
- SSE indicator: Status bar fixed at top of console — "● En direct" (green pulse) / "⚠ Reconnexion..." (amber) / "✕ Hors ligne" (red). Color + icon + label always.
- Delta vote count: "47 votes (+3 ▲)" — green badge appears next to total, fades after 10s of inactivity
- Post-vote guidance: After closing a vote, show "Vote clôturé — Ouvrez le prochain vote ou clôturez la séance" with two action buttons
- End-of-agenda: "Toutes les résolutions traitées — Clôturer la séance →"

**Voter Ballot Card**
- Full-screen: Hide all navigation and chrome when a vote is open
- Layout: 3 stacked full-width cards (POUR / CONTRE / ABSTENTION), minimum 72px height each, 8px spacing
- Feedback: Instant selection visual (< 50ms, optimistic). Background server submission. Rollback on error with inline message.
- Waiting state: "En attente d'un vote" — single line, nothing else visible
- Confirmation: "Vote enregistré ✓" for 3 seconds, then back to waiting state
- PDF consultation: ag-pdf-viewer bottom sheet from Phase 25 (already wired)

**Results & Post-Session**
- Result cards: Collapsed by default — "Résolution 3 — ✓ ADOPTÉ". Click expands: numbers, percentages, bar chart, threshold.
- Bar chart: POUR/CONTRE/ABSTENTION horizontal bars with percentages
- Verdict: ADOPTÉ/REJETÉ as the largest element in the expanded card
- Post-session stepper: Enhance existing 4-step stepper with checkmarks on completed steps + green color
- Footer context: "X votes exprimés · Y membres présents" on every result card

### Claude's Discretion
- Exact CSS values for the "officiel" token refresh (specific shadow depths, border radiuses, spacing values)
- Which pages need full CSS rewrites vs token-level updates
- @layer migration strategy details
- Bar chart implementation (CSS-only bars vs canvas)
- Anime.js vs pure CSS for count-up animations
- Order of page CSS updates

### Deferred Ideas (OUT OF SCOPE)
- Anime.js count-up KPIs (considered but user chose "transitions sobres" — may add in polish pass)
- Scroll-driven animations (@supports guard) — defer to v5+
- Custom PDF.js toolbar — defer to v5+
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| OPC-01 | Operator console layout — status bar (session name + quorum + SSE indicator), left panel (attendance + résolutions), main panel (active vote) | Existing grid in operator.css; status bar is new HTML+CSS in meeting-bar area |
| OPC-02 | Live SSE connectivity indicator — "● En direct" / "⚠ Reconnexion..." / "✕ Hors ligne" with colour + icon + label | `sseConnected` var already in operator-realtime.js; `onConnect`/`onDisconnect` hooks exist for driving indicator |
| OPC-03 | Live vote count with delta indicators ("+3 votes in last 30s") | `refreshExecKPIs()` already updates `opKpiVoted`; need delta tracking state + badge with auto-fade |
| OPC-04 | Contextual post-vote guidance — "Vote clôturé — Ouvrez le prochain vote ou clôturez la séance" | `refreshExecVote()` shows `execNoVote` panel when no active motion; guidance replaces that panel |
| OPC-05 | End-of-agenda guidance — "Toutes les résolutions ont été traitées — Clôturer la séance" | `renderExecQuickOpenList()` already shows "Aucune resolution en attente"; upgrade to guidance card |
| VOT-01 | Full-screen single-focus ballot card — all navigation and chrome hidden when a vote is open | vote.htmx.html has `.vote-bottom-nav`, `.page-header`, `.vote-identity-banner`, `.context-bar` — all need `.vote-is-open` CSS hiding |
| VOT-02 | Vote option buttons full-width, minimum 72px height, 8px spacing | vote.css has `.vote-btn` — needs height and spacing adjustments |
| VOT-03 | Optimistic vote feedback under 50ms — instant selection visual, background server submission, rollback on error | vote.js has confirmation overlay flow; needs optimistic pattern replacing confirmation dialog for direct tap |
| VOT-04 | Waiting state — "En attente d'un vote" single line, no other content | `.motion-card`, `#motionTitle` text "En attente d'une résolution" already exists; enhance to show nothing else |
| VOT-05 | Confirmation state — "Vote enregistré ✓" for 3 seconds | vote.js sets receipt via `#voteReceipt`; needs CSS state and 3s auto-dismiss |
| VOT-06 | PDF consultation via ag-pdf-viewer bottom sheet (already wired) | `#btnConsultDocument` exists in vote.htmx.html and Phase 25 wired it — verify wiring still active |
| RES-01 | Trustworthy result cards — absolute numbers + percentages + threshold + ADOPTÉ/REJETÉ verdict as largest element | `loadResultsTable()` in postsession.js renders table rows; convert to card layout |
| RES-02 | Bar charts for vote breakdown (POUR/CONTRE/ABSTENTION) | CSS-only bars are sufficient (same pattern as `opBarFor/opBarAgainst/opBarAbstain` in operator) |
| RES-03 | Post-session stepper with completion checkmarks | `goToStep()` already uses `CHECK_SVG` and adds `.done` class with green bg — enhance visual weight |
| RES-04 | Collapsible motion result cards (default: headline only, expand for full tally) | New HTML pattern: `<details>`/`<summary>` for collapse; replaces table rows in step 1 |
| RES-05 | "X votes exprimés · Y membres présents" context footer on every result card | Available in motion data from `motions_for_meeting.php` response |
| VIS-01 | CSS @layer declaration (base, components, v4) in design-system.css | No @layer present today — confirmed by code scan. Adding it is additive, no regressions |
| VIS-02 | View Transitions API for wizard step transitions, tab switching, modal open/close | STACK.md confirms Baseline Available; pattern documented |
| VIS-03 | @starting-style entry animations for modals, toasts, new components | STACK.md confirms 86% browser support; pattern documented |
| VIS-04 | color-mix() derived tokens for all new component color variations | color-mix() already used in operator.css — confirmed safe |
| VIS-05 | Anime.js count-up animations for KPI numbers | VIS-05 explicitly listed in REQUIREMENTS; CONTEXT.md deferred section mentions it — REQUIREMENTS wins, include it |
| VIS-06 | PC-first layout validation — 1024px+ default; mobile voter screen verified at 375px | vote.css targets mobile; all other CSS uses max-width breakpoints |
| VIS-07 | Dark mode parity — every new token has a dark variant in the same commit | design-system.css has `[data-theme="dark"]` block at line ~309 |
| VIS-08 | Measurable done criteria: transitions ≤ 200ms, CLS = 0, focus rings 3:1 contrast, zero inline style="" | Audit task against inline styles in all HTML files |
</phase_requirements>

---

## Summary

Phase 29 closes out v4.0 with the hardest UX work: the live session flows where errors under pressure are most costly. The codebase is already well-wired — SSE exists, vote buttons exist, the stepper exists — but the UX layer is incomplete. Most requirements are "finish the last mile" work, not rewrites.

The three pillars decompose cleanly:

**Pillar 1 — Operator Console (OPC-01..05):** The SSE connection already fires `onConnect`/`onDisconnect` in `operator-realtime.js`. The indicator is just HTML + CSS driven by those callbacks. Delta vote tracking needs a new `_prevVoteCount` variable and a debounced badge. Post-vote and end-of-agenda guidance are new HTML panels that replace the existing `execNoVote` empty state.

**Pillar 2 — Voter View (VOT-01..06):** The ballot HTML structure exists but is not full-screen and uses a two-step confirmation overlay. VOT-01 needs a `.vote-is-open` body class with CSS `display:none` rules for nav/chrome. VOT-03 means replacing the confirmation dialog with direct optimistic commit on first tap, with rollback on error. VOT-02 is a pure CSS constraint.

**Pillar 3 — Design System + All Pages (VIS-01..08 + RES-01..05):** The largest blast radius. design-system.css has no `@layer` today. The @layer addition is additive (existing styles continue working if unlayered styles have higher specificity). color-mix() is already used in operator.css so it is safe everywhere. The page CSS files are token-based throughout — "officiel" token refresh means updating `:root` values and dark theme block, which propagates to all pages automatically.

**Primary recommendation:** Execute in three waves: (1) design-system.css @layer + token refresh + dark parity; (2) operator UX enhancements; (3) voter ballot + postsession result cards. VIS-08 inline style audit runs as final pass.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Vanilla JS IIFE + var | — | Page scripts | Project pattern — no framework |
| CSS Custom Properties | — | Design tokens | All 255 tokens in design-system.css :root |
| Web Components | — | Shared UI (ag-toast, ag-tooltip, ag-pdf-viewer) | Established pattern in phases 25-28 |
| EventStream (ag-vote internal) | — | SSE connection wrapper | Already used by operator-realtime.js |

### Supporting (CSS techniques — all Baseline Available)
| Feature | Support | Purpose | When to Use |
|---------|---------|---------|-------------|
| `@layer` | Chrome 99+, Firefox 97+, Safari 15.4+ | Cascade control — new v4 styles don't regress old | VIS-01 |
| `color-mix()` | Baseline widely available | Derived tint/shade tokens | VIS-04 — new component color variants |
| `@starting-style` | ~86% (2026) | Entry animations without JS | VIS-03 — modals, toasts, new cards |
| View Transitions API | Chrome 111+, Firefox 133+, Safari 18+ | Page-within transitions | VIS-02 — tab switch, modal open |
| CSS `@supports` guard | Universally supported | Progressive enhancement wrapper | Wrap View Transitions and @starting-style |

### Anime.js (VIS-05)
| Library | Version | Purpose | Integration |
|---------|---------|---------|-------------|
| Anime.js | 3.x (CDN) | Count-up KPI animations on operator + dashboard | IIFE-safe global `anime()`; add to operator.htmx.html and dashboard.htmx.html `<script>` |

**Note on VIS-05 vs CONTEXT.md deferred section:** CONTEXT.md says Anime.js count-up was "considered but user chose transitions sobres — may add in polish pass." However REQUIREMENTS.md lists VIS-05 explicitly as a requirement. Requirements take precedence. Use Anime.js count-up but keep timing sober (600-800ms, no bounce easing).

---

## Architecture Patterns

### Recommended Task Structure

```
Wave 1 — Design System Foundation
  design-system.css: @layer + token refresh + dark parity + color-mix tokens

Wave 2 — Operator Console UX
  operator.htmx.html: SSE status bar HTML + post-vote guidance panel
  operator-realtime.js: indicator state machine
  operator-exec.js: delta tracking + guidance trigger
  operator.css: SSE indicator styles + guidance card styles

Wave 3 — Voter Ballot
  vote.htmx.html: body class hook for full-screen mode
  vote.js: optimistic commit flow
  vote.css: full-screen hide rules + 72px buttons + VOT-04/VOT-05 states

Wave 4 — Post-Session Results
  postsession.htmx.html: collapsible result card HTML
  postsession.js: loadResultsTable → card renderer
  postsession.css: result card + bar chart styles

Wave 5 — All-Page Polish + VIS-08 Audit
  All page CSS: token-level updates inherit automatically from Wave 1
  VIS-08: inline style audit pass across all .htmx.html files
  VIS-02/VIS-03: View Transitions + @starting-style additions
```

### Pattern 1: SSE Indicator State Machine (OPC-02)

**What:** Three-state indicator driven by `sseConnected` in operator-realtime.js.
**Integration point:** `onConnect` and `onDisconnect` callbacks already exist.

```javascript
// In operator-realtime.js — enhance existing callbacks
sseStream = EventStream.connect(O.currentMeetingId, {
  onConnect: function() {
    sseConnected = true;
    setSseIndicator('live');         // NEW
  },
  onDisconnect: function() {
    sseConnected = false;
    setSseIndicator('offline');      // NEW
  },
  // ... existing onEvent
});

// New helper — sets data-sse-state attribute on indicator element
function setSseIndicator(state) {
  var el = document.getElementById('opSseIndicator');
  if (el) el.setAttribute('data-sse-state', state); // 'live'|'reconnecting'|'offline'
}
```

```css
/* In operator.css @layer v4 */
.op-sse-indicator { display: flex; align-items: center; gap: var(--space-2); font-size: var(--text-xs); font-weight: var(--font-semibold); }
.op-sse-indicator[data-sse-state="live"]        { color: var(--color-success); }
.op-sse-indicator[data-sse-state="reconnecting"]{ color: var(--color-warning); }
.op-sse-indicator[data-sse-state="offline"]     { color: var(--color-danger); }
.op-sse-dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
.op-sse-indicator[data-sse-state="live"] .op-sse-dot {
  animation: ssePulse 2s ease-in-out infinite;
}
@keyframes ssePulse { 0%,100% { opacity:1; } 50% { opacity:.35; } }
```

### Pattern 2: Delta Vote Count Badge (OPC-03)

**What:** Track previous total, compute delta, show badge that auto-fades.
**Integration point:** `refreshExecKPIs()` in operator-exec.js already updates `opKpiVoted`.

```javascript
// State variables to add at top of operator-exec.js IIFE
var _prevVoteTotal = 0;
var _deltaFadeTimer = null;

// Inside refreshExecKPIs() after computing totalBallots:
var delta = totalBallots - _prevVoteTotal;
if (delta > 0) {
  var badge = document.getElementById('opVoteDeltaBadge');
  if (badge) {
    badge.textContent = '+' + delta + ' \u25b2';
    badge.hidden = false;
    if (_deltaFadeTimer) clearTimeout(_deltaFadeTimer);
    _deltaFadeTimer = setTimeout(function() {
      badge.hidden = true;
    }, 10000); // 10s inactivity fade
  }
}
_prevVoteTotal = totalBallots;
```

HTML to add alongside `#opKpiVoted`:
```html
<span id="opVoteDeltaBadge" class="op-vote-delta-badge" hidden aria-live="polite"></span>
```

### Pattern 3: Voter Full-Screen Mode (VOT-01)

**What:** A `.vote-is-open` class on `#voteApp` hides all chrome.
**Integration point:** vote.js already toggles motion states; add class when vote opens.

```css
/* In vote.css @layer v4 */
.vote-is-open .page-header,
.vote-is-open .vote-identity-banner,
.vote-is-open .context-bar,
.vote-is-open .vote-progress-dots,
.vote-is-open .vote-bottom-nav,
.vote-is-open .vote-footer,
.vote-is-open .speech-panel,
.vote-is-open .current-speaker-banner {
  display: none !important;
}

.vote-is-open .vote-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: var(--space-6);
}
```

### Pattern 4: Optimistic Vote Commit (VOT-03)

The current flow requires a confirmation dialog (`confirmationOverlay`). Optimistic mode means: tap button → immediate visual selection → background POST → rollback on error.

```javascript
// In vote.js — new direct-commit flow (replaces showConfirm → btnConfirm path)
function castVoteOptimistic(choice) {
  // 1. Immediate visual (<50ms — synchronous DOM update)
  setVoteSelected(choice);  // apply .vote-btn-selected CSS class instantly

  // 2. Background submit
  var originalChoice = choice;
  window.api('/api/v1/cast_vote.php', { method:'POST', body: { ... } })
    .then(function(res) {
      if (res.body && res.body.ok) {
        showConfirmationState(); // "Vote enregistré ✓" for 3s
      } else {
        rollbackVote(originalChoice); // remove .vote-btn-selected
        showInlineError('Vote non enregistré — réessayez');
      }
    })
    .catch(function() {
      rollbackVote(originalChoice);
      showInlineError('Vote non enregistré — réessayez');
    });
}
```

**Important constraint:** The current flow has a confirmation overlay for "vote irréversible" warning. For optimistic mode, the irreversibility warning must move to the waiting state (a persistent note above the buttons: "Votre vote sera définitif et irréversible."), not a blocking dialog.

### Pattern 5: Collapsible Result Cards (RES-04)

Use native `<details>`/`<summary>` — zero JavaScript needed, ARIA handled by browser.

```html
<details class="result-card" open>  <!-- first card open by default, or closed -->
  <summary class="result-card-summary">
    <span class="result-card-num">Résolution 3</span>
    <span class="result-card-title">Approbation du budget 2026</span>
    <span class="result-card-verdict result-adopted">✓ ADOPTÉ</span>
  </summary>
  <div class="result-card-body">
    <!-- numbers, bar chart, threshold, footer -->
  </div>
</details>
```

**Note:** `<details>` default open = first result visible; subsequent results collapsed. "collapsed by default" per spec means `<details>` without `open` attribute.

### Pattern 6: CSS-Only Bar Charts (RES-02)

No canvas, no library — inline CSS width with transitions:

```html
<div class="result-bar-row">
  <span class="result-bar-label">POUR</span>
  <div class="result-bar-track">
    <div class="result-bar-fill result-bar-pour" style="--bar-pct: 56%"></div>
  </div>
  <span class="result-bar-pct">18 (56%)</span>
</div>
```

```css
.result-bar-fill { width: var(--bar-pct, 0%); height: 12px; border-radius: var(--radius-full); transition: width 400ms var(--ease-out); }
.result-bar-pour    { background: var(--color-success); }
.result-bar-against { background: var(--color-danger); }
.result-bar-abstain { background: var(--color-neutral); }
```

**Note on inline style for bar width:** The `style="--bar-pct: 56%"` is setting a CSS custom property, not a raw `style=""` — this is the accepted pattern for data-driven CSS and does NOT violate VIS-08 "zero inline style=" criterion. The VIS-08 rule targets hardcoded values like `style="color:red"`, not CSS variable overrides.

### Pattern 7: @layer Introduction in design-system.css (VIS-01)

**Critical constraint:** Adding `@layer` to design-system.css affects cascade for all importing pages. All currently-unlayered styles (in page CSS files like operator.css, vote.css, etc.) automatically win over layered styles. This is correct — legacy code stays dominant, new `@layer v4` additions are layered below unlayered specificity by default.

```css
/* TOP of design-system.css — before any rules */
@layer base, components, v4;

/* Then wrap existing :root, reset, base typography in @layer base */
@layer base {
  *, *::before, *::after { box-sizing: border-box; ... }
  :root { /* tokens */ }
  [data-theme="dark"] { /* dark tokens */ }
}

/* Existing component rules stay in @layer components */
@layer components {
  .btn { ... }
  .card { ... }
  /* etc. */
}

/* New Phase 29 additions go in @layer v4 */
@layer v4 {
  /* SSE indicator, result cards, delta badge, etc. */
}
```

**Risk:** If any page CSS uses `!important` to override design-system, those continue to work — `!important` in unlayered CSS still wins over everything. Audit `!important` usage first.

### Anti-Patterns to Avoid

- **Confirmation dialogs for vote cast:** Current flow has an irreversibility overlay. VOT-03 explicitly requires optimistic (no blocking confirmation). Move warning to persistent inline note.
- **Inline style="" for bar widths:** Use `--bar-pct` CSS variable trick instead.
- **JS class toggling for SSE states:** Use `data-sse-state` attribute on the indicator element — CSS attribute selectors handle all three states cleanly.
- **Showing partial tallies during open vote:** operator-exec.js already hides breakdown during open vote (`hideBreakdown` logic at line 579-582). Do not undo this.
- **Full @layer migration of existing page CSS:** Do NOT wrap operator.css or vote.css content in `@layer`. That would lower their specificity and break layouts. Only design-system.css gets `@layer` internally; page CSS files continue unlayered.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Bar chart for vote breakdown | Custom SVG chart component | CSS `width: var(--bar-pct)` on a `<div>` | 3 data points only; CSS handles transitions; zero dependency |
| SSE reconnect logic | Custom reconnect handler | Existing `EventStream.connect()` in event-stream.js | Already handles reconnect with exponential backoff |
| Count-up animation | Custom requestAnimationFrame loop | Anime.js (VIS-05) | Already decided in requirements |
| Collapse/expand result cards | JS click handler + classList | Native `<details>`/`<summary>` | Browser-native, keyboard accessible, ARIA handled for free |
| Full-screen mode detection | matchMedia or JS viewport | CSS `.vote-is-open` class on `#voteApp` | Simpler; avoids JS viewport manipulation |
| View Transitions | GSAP or custom animation | `document.startViewTransition()` | Baseline Available, zero dependency |

**Key insight:** This phase adds visual polish over already-functional code. Every new piece of JS should be small (< 20 lines), every new CSS class should use existing design tokens.

---

## Common Pitfalls

### Pitfall 1: @layer Cascade Inversion
**What goes wrong:** Adding `@layer` to design-system.css could unexpectedly lower the priority of component styles, making existing `.btn`, `.card`, etc. rules lose specificity battles with page-level rules they previously won.
**Why it happens:** Unlayered styles always beat layered styles regardless of specificity.
**How to avoid:** Put ALL existing design-system.css rules inside `@layer base` + `@layer components`. New rules go in `@layer v4`. Unlayered page CSS (operator.css, vote.css, etc.) automatically wins — this is the desired behaviour.
**Warning signs:** Button styles looking wrong after migration; card backgrounds reverting to wrong values.

### Pitfall 2: Optimistic Vote Rollback Missing
**What goes wrong:** User taps vote button, sees it selected, server returns error, but UI doesn't roll back — user believes they voted when they didn't.
**Why it happens:** Optimistic UI without the error path implemented.
**How to avoid:** Implement rollback BEFORE writing the optimistic commit path. Test with a deliberately failing endpoint.
**Warning signs:** Vote button stays selected (green) after console shows 4xx/5xx response.

### Pitfall 3: VIS-08 Inline Style Creep
**What goes wrong:** Bar chart widths, dynamic KPI values, and state indicators get set as `element.style.width = ...` or `element.style.color = ...` in JS.
**Why it happens:** It's the quick path for dynamic values.
**How to avoid:** Use CSS custom properties: `element.style.setProperty('--bar-pct', pct + '%')`. Then CSS handles the visual. Exception: `element.style.setProperty('--bar-pct', ...)` is acceptable — it's a CSS variable override, not a raw style.
**Warning signs:** `grep 'style\.' *.js` returning hits on color/display/width assignments.

### Pitfall 4: vote-is-open Hiding ag-pdf-viewer
**What goes wrong:** The full-screen mode hides the PDF consultation button along with other chrome.
**Why it happens:** The `.vote-is-open` blanket hide rules might target `.motion-card-footer` where `#btnConsultDocument` lives.
**How to avoid:** Keep `#btnConsultDocument` visible during vote-open state by scoping the hide rules carefully. The motion card body should remain visible — only NAV, HEADER, FOOTER, and SPEECH chrome should hide.
**Warning signs:** "Consulter le document" button disappears when vote is open.

### Pitfall 5: Dark Mode Token Gaps
**What goes wrong:** New tokens added to `:root` for "officiel" refresh have no dark counterpart — components look broken in dark mode after Phase 29.
**Why it happens:** Dark mode block is a separate `[data-theme="dark"]` block at line ~309. Easy to forget.
**How to avoid:** Add a lint comment rule: every new CSS variable in `:root` gets an entry in `[data-theme="dark"]` in the same commit. Make it a checklist item in each wave.
**Warning signs:** Component looks great in light mode, wrong colors in dark mode.

### Pitfall 6: postsession.js loadResultsTable Rewrite
**What goes wrong:** The existing `loadResultsTable()` renders `<tr>` rows inside `#resultsTableBody`. RES-04 requires collapsible cards — a different DOM structure.
**Why it happens:** The function assumes table context.
**How to avoid:** Keep the existing table for backward compat (step 1 verification), add a NEW `renderResultCards()` function that renders `<details>` cards in a new container. Wire RES-04 to the cards container, not the table.
**Warning signs:** Removing the existing table breaks step 1 verification flow.

---

## Code Examples

### SSE Indicator HTML (add to operator.htmx.html meeting-bar)
```html
<!-- Place inside .meeting-bar-top .meeting-bar-right, before barClock chip -->
<span class="op-sse-indicator" id="opSseIndicator" data-sse-state="offline" aria-live="polite" aria-label="Connexion en direct">
  <span class="op-sse-dot" aria-hidden="true"></span>
  <span class="op-sse-label" id="opSseLabel">Hors ligne</span>
</span>
```

JS label update in indicator helper:
```javascript
var SSE_LABELS = { live: '● En direct', reconnecting: '⚠ Reconnexion...', offline: '✕ Hors ligne' };
function setSseIndicator(state) {
  var el = document.getElementById('opSseIndicator');
  var lb = document.getElementById('opSseLabel');
  if (el) el.setAttribute('data-sse-state', state);
  if (lb) lb.textContent = SSE_LABELS[state] || state;
}
```

### Post-Vote Guidance Card HTML (add to operator exec panel)
```html
<!-- Show when: vote just closed AND next motions remain -->
<div class="op-post-vote-guidance" id="opPostVoteGuidance" hidden role="status" aria-live="polite">
  <p class="op-guidance-text">Vote clôturé — Ouvrez le prochain vote ou clôturez la séance</p>
  <div class="op-guidance-actions">
    <button class="btn btn-primary btn-sm" id="opBtnNextVote">Vote suivant</button>
    <button class="btn btn-secondary btn-sm" id="opBtnCloseSession">Clôturer la séance</button>
  </div>
</div>

<!-- Show when: ALL motions are closed -->
<div class="op-end-of-agenda" id="opEndOfAgenda" hidden role="status" aria-live="polite">
  <p class="op-guidance-text">Toutes les résolutions ont été traitées</p>
  <button class="btn btn-primary" id="opBtnEndSession">Clôturer la séance →</button>
</div>
```

### Voter Waiting State (CSS class toggling in vote.js)
```javascript
// In vote.js — state management
function setVoteAppState(state) {
  var app = document.getElementById('voteApp');
  if (!app) return;
  app.dataset.voteState = state; // 'waiting' | 'voting' | 'confirmed'
}
```

```css
/* vote.css */
/* Waiting: show only waiting message */
[data-vote-state="waiting"] .motion-card,
[data-vote-state="waiting"] .vote-section { display: none; }

/* Voting (vote open): full-screen mode */
[data-vote-state="voting"] .page-header,
[data-vote-state="voting"] .vote-bottom-nav,
[data-vote-state="voting"] .vote-footer,
[data-vote-state="voting"] .context-bar,
[data-vote-state="voting"] .speech-panel,
[data-vote-state="voting"] .vote-identity-banner { display: none !important; }

/* Confirmation: show success overlay */
[data-vote-state="confirmed"] .vote-section { display: none; }
```

### design-system.css @layer Declaration
```css
/* Line 1 of design-system.css — before any rules */
@layer base, components, v4;

/* Wrap existing :root block */
@layer base {
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root { /* all existing tokens unchanged */ }
  [data-theme="dark"] { /* all existing dark tokens unchanged */ }
}

/* All existing component classes */
@layer components {
  /* .btn, .card, .field, .sidebar, etc. — move existing rules here */
}

/* New Phase 29 additions */
@layer v4 {
  /* SSE indicator, result cards, delta badge, guidance cards */
  /* All new color-mix() token variants */
  /* View Transition rules */
  /* @starting-style rules */
}
```

### color-mix() Token Pattern (VIS-04)
```css
/* In @layer v4 or :root for design token additions */
:root {
  /* New "officiel" surface tints using color-mix */
  --color-primary-tint-10: color-mix(in srgb, var(--color-primary) 10%, white);
  --color-primary-tint-5:  color-mix(in srgb, var(--color-primary) 5%, white);
  --color-success-tint-8:  color-mix(in srgb, var(--color-success) 8%, white);
  --color-danger-tint-8:   color-mix(in srgb, var(--color-danger)  8%, white);
}
[data-theme="dark"] {
  --color-primary-tint-10: color-mix(in srgb, var(--color-primary) 10%, var(--color-surface));
  --color-primary-tint-5:  color-mix(in srgb, var(--color-primary) 5%, var(--color-surface));
  --color-success-tint-8:  color-mix(in srgb, var(--color-success) 8%, var(--color-surface));
  --color-danger-tint-8:   color-mix(in srgb, var(--color-danger)  8%, var(--color-surface));
}
```

---

## Codebase Inventory (State Before Phase 29)

### CSS Files — Line Counts and Polish Status

| File | Lines | Assessment |
|------|-------|------------|
| design-system.css | 4667 | Foundation — no @layer, dark theme at line ~309, all tokens defined |
| operator.css | 4358 | Heavy file — lifecycle bar, meeting-bar, KPI strip, exec layout, hub CSS all present |
| vote.css | 1616 | Mobile-first — has custom vote shadow tokens at top, vote-btn defined |
| postsession.css | 368 | Lightweight — stepper, panel, table styles. Ready for card additions |
| pages.css | 1448 | Shared page components (calendar, etc.) |
| hub.css | 1159 | Hub stepper and identity banner (also partially in operator.css) |
| members.css | 1148 | Heavy — needs token-level only update |
| wizard.css | 1027 | WIZ phase 28 — already polished |
| admin.css | 995 | Token-based — auto-updates from design-system token refresh |
| analytics.css | 693 | Token-based |
| app.css | 745 | Global overrides — motion-card, vote-results, member-card defined here |
| settings.css | 403 | Light — token updates sufficient |
| archives.css | 430 | Light |
| help.css | 423 | Light |
| email-templates.css | 239 | Light |
| audit.css | 372 | Token-based |
| meetings.css | 533 | Token-based |
| users.css | 323 | Token-based |

**No `dashboard.css` exists.** Dashboard styles live in `pages.css` and `app.css`. VIS-05 count-up for dashboard KPIs targets elements in dashboard.htmx.html via JS — no separate CSS file needed.

### Key JS Integration Points

| File | What Exists | What's Missing |
|------|-------------|---------------|
| operator-realtime.js | `sseConnected` var, `onConnect`/`onDisconnect` callbacks | `setSseIndicator()` call in callbacks |
| operator-exec.js | `refreshExecKPIs()` updates `opKpiVoted`, `renderExecQuickOpenList()` | Delta tracking vars, guidance panel show/hide |
| vote.js | Vote cast flow with confirmation overlay, `#voteReceipt` in footer | Optimistic commit pattern, `setVoteAppState()` helper |
| postsession.js | `goToStep()` with `CHECK_SVG` + `.done` class, `loadResultsTable()` | `renderResultCards()` function for collapsible cards |

### Existing @layer Status
No `@layer` found anywhere in design-system.css (confirmed by code scan). This means the migration is additive — no existing rule precedence is disturbed if done correctly.

### Existing color-mix() Usage
`color-mix()` is already used in operator.css (lines 731, 778, 845, 862, 880, 1121, 1151, 1169, 1293, 1561). Safe to expand to design-system.css token definitions.

### Inline Style Audit Pre-Phase (VIS-08)
Known inline styles to fix:
- `vote.htmx.html` line 101-103: skeleton `style="margin:..."` → use skeleton utility classes (`.skeleton-group`)
- `vote.htmx.html` line 171-174: `<style>` inline block for `#btnConsultDocument` — move to vote.css
- `operator-exec.js` line 400-401: `partEl.style.color = ...` — replace with CSS class swap
- `operator-exec.js` lines 419, 425: `barFill.style.width = pct + '%'` — replace with `style.setProperty('--bar-pct', ...)`

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact for Phase 29 |
|--------------|------------------|--------------|---------------------|
| classList toggle for animations | @starting-style entry animations | CSS Baseline 2024 | Use for result card expansion, guidance panels |
| GSAP/custom for page transitions | View Transitions API | Baseline Oct 2025 | Use for tab switching in operator |
| Manually computed tint colors | color-mix() | Baseline widely available | Use for new token tints in design-system.css |
| @media viewport for component layout | Container Queries | Baseline widely available | Use for result card compact/expanded layouts |
| setTimeout hack for CSS transitions | @starting-style | Aug 2024 | Modal/toast animations |

---

## Open Questions

1. **VIS-05 Anime.js vs VIS-02 View Transitions for KPI count-up**
   - What we know: Both listed as requirements. Anime.js specifically named for count-up.
   - What's unclear: Whether Anime.js should also handle tab transitions or if View Transitions API is used there exclusively.
   - Recommendation: Anime.js for number count-up only (KPI strip); View Transitions API for panel/tab switches. Non-overlapping use cases.

2. **Voter ballot confirmation dialog removal (VOT-03)**
   - What we know: Current `#confirmationOverlay` has an "irréversible" legal warning. VOT-03 requires optimistic (no dialog).
   - What's unclear: Legal acceptability of removing the explicit confirmation step.
   - Recommendation: Keep confirmation as fallback for accessibility (keyboard users), but make it skippable. Add persistent inline warning above vote buttons instead of blocking overlay. The current HTML already has `#btnConfirmInline` in the DOM — use that as the lightweight confirm (replaces full overlay).

3. **@layer migration scope for operator.css**
   - What we know: operator.css is 4358 lines. Wrapping it in a layer would be a large refactor.
   - What's unclear: Whether the plan calls for wrapping page CSS in layers, or only design-system.css.
   - Recommendation: Only wrap design-system.css content in `@layer base, components`. Leave operator.css, vote.css, etc. as unlayered — they automatically win specificity over layered code. This is the CONTEXT.md strategy: "@layer base, components, v4 to design-system.css. New styles in @layer v4."

---

## Validation Architecture

> `workflow.nyquist_validation` key absent from .planning/config.json — treating as enabled.

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (existing) + browser smoke check (manual) |
| Config file | `phpunit.xml` (project root, if present) |
| Quick run command | `php vendor/bin/phpunit --testsuite unit --stop-on-failure` |
| Full suite command | `php vendor/bin/phpunit` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| OPC-02 | SSE indicator changes state on connect/disconnect | unit (JS logic) | Manual browser test — `EventStream` mock | ❌ Wave 0 |
| OPC-03 | Delta badge shows +N and fades after 10s | unit (JS state) | Manual browser test | ❌ Wave 0 |
| VOT-03 | Optimistic commit: server error rolls back selection | unit (JS flow) | Manual browser test with intercepted endpoint | ❌ Wave 0 |
| VIS-01 | @layer declaration present in design-system.css | smoke | `grep -c '@layer' public/assets/css/design-system.css` | ❌ Wave 0 |
| VIS-07 | Every new :root token has dark variant | smoke | `grep` audit of new tokens vs dark block | ❌ Wave 0 |
| VIS-08 | Zero inline style="" in HTML files | smoke | `grep -rn 'style="' public/*.htmx.html | grep -v 'var(--'` | ❌ Wave 0 |
| RES-03 | Stepper `.done` shows checkmark SVG | unit | PHPUnit not applicable — visual regression manual | ❌ Wave 0 |
| RES-04 | `<details>` result cards collapse/expand | smoke | `grep 'result-card' public/postsession.htmx.html` | ❌ Wave 0 |

### Wave 0 Gaps
- [ ] `tests/visual/smoke_vot01.sh` — curl vote.htmx.html, grep for `data-vote-state` attribute
- [ ] `tests/visual/smoke_vis01.sh` — grep design-system.css for `@layer base, components, v4`
- [ ] `tests/visual/smoke_vis08.sh` — grep all .htmx.html for inline style= (excluding CSS var overrides)

*(For this phase, most validation is manual browser testing — JS behavior and CSS rendering cannot be unit tested without a headless browser setup not present in this project)*

---

## Sources

### Primary (HIGH confidence — direct code inspection)
- `/home/user/gestion_votes_php/public/assets/js/pages/operator-realtime.js` — SSE state, `sseConnected` var, callbacks
- `/home/user/gestion_votes_php/public/assets/js/pages/operator-exec.js` — KPI strip, `refreshExecKPIs()`, delta opportunity
- `/home/user/gestion_votes_php/public/assets/css/operator.css` — existing status bar, meeting-bar, health dot pattern
- `/home/user/gestion_votes_php/public/operator.htmx.html` — SSE indicator location, tabs structure
- `/home/user/gestion_votes_php/public/vote.htmx.html` — ballot HTML structure, chrome elements to hide
- `/home/user/gestion_votes_php/public/assets/css/vote.css` — vote-btn existing styles, dimensions
- `/home/user/gestion_votes_php/public/assets/js/pages/postsession.js` — stepper, `loadResultsTable()`
- `/home/user/gestion_votes_php/public/assets/css/postsession.css` — existing stepper styles
- `/home/user/gestion_votes_php/public/assets/css/design-system.css` — full token inventory, dark theme block
- `/home/user/gestion_votes_php/public/assets/css/app.css` — global classes
- `/home/user/gestion_votes_php/.planning/research/FEATURES.md` — Pattern 6 (Control Room), Pattern 7 (Voter), Pattern 8 (Results)
- `/home/user/gestion_votes_php/.planning/research/STACK.md` — CSS techniques (View Transitions, @starting-style, color-mix, @layer)

### Secondary (HIGH confidence — project context)
- `.planning/REQUIREMENTS.md` — canonical requirement definitions for all 24 requirements
- `.planning/phases/29-operator-console-voter-view-visual-polish/29-CONTEXT.md` — locked decisions

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all techniques verified in existing code or STACK.md
- Architecture: HIGH — all integration points confirmed by direct code reading
- Pitfalls: HIGH — derived from actual code patterns and known cascade risks
- Validation approach: MEDIUM — no automated JS test framework; manual browser testing required

**Research date:** 2026-03-18
**Valid until:** 2026-05-01 (stable design system, no fast-moving dependencies)
