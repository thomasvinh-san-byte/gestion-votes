# Phase 46: Operator Console Rebuild - Research

**Researched:** 2026-03-22
**Domain:** Vanilla JS SPA — two-panel operator console, SSE real-time, vote lifecycle management
**Confidence:** HIGH (code read directly from existing files, no stale inference)

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Two-panel split layout — agenda/motions sidebar (280px) + main content area. Meeting bar full-width at top
- Horizontal tab bar — keep multi-tab approach (Résolutions, Présences, Paramètres, Dashboard, Speech) with refined styling
- Compact meeting bar — meeting selector, status badge, SSE indicator, clock, refresh. Streamlined
- Full available width — no max-width constraint, use all space for data-dense console
- Vote panel: card with live counters — large vote counts (Pour/Contre/Abstention) with animated increments, progress bar, open/close buttons prominently placed
- SSE indicator: dot + label in meeting bar — green "Connecté" / red "Hors ligne", compact and always visible
- Delta badges: animated increment — shows +N when new votes arrive, auto-clears after 3s
- Action button tooltips: ag-tooltip on disabled buttons — explain WHY disabled (e.g. "Ouvrez d'abord la séance")
- Agenda sidebar: motion list with status badges — each motion shows title, majority type, vote status (pending/open/closed/adopted), clickable to load in main panel
- Execution mode: prominent execution card — when vote is open, vote panel takes visual priority with larger counters and colored border accent
- Full dark mode parity via tokens — all components use CSS tokens, dark mode automatic. SSE indicator stays vivid green/red
- Responsive: sidebar collapses to top tabs at 1024px — on narrower screens, sidebar becomes horizontal scrollable tab strip

### Claude's Discretion
- Exact HTML structure and element hierarchy
- CSS class naming
- How to handle the 6 JS files (refactor vs update selectors)
- Exact animation timing for vote count increments and delta badges
- Tab content rendering approach
- Responsive breakpoint details

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| REB-04 | Operator console — complete HTML+CSS+JS rewrite, SSE wired, live vote panel functional, agenda sidebar, tooltips on all actions | All JS DOM IDs documented; SSE lifecycle fully understood; ag-tooltip component analyzed; vote open/close flow mapped |
| WIRE-01 | Every rebuilt page has verified API connections — no dead endpoints, no mock data, no broken HTMX targets | 4 backend API files are thin dispatch stubs; all route through OperatorController; testing pattern established from phases 43–45 |
| WIRE-02 | SSE connections verified on operator and voter pages — live updates flow correctly | SSE connection flow fully documented; EventStream.connect() + MeetingContext event lifecycle understood |
</phase_requirements>

---

## Summary

The operator console is the most complex page in AG-VOTE — it coordinates 6 JS modules through a shared `window.OpS` bridge, manages SSE real-time updates with polling fallback, and drives the entire vote lifecycle. The rebuild must replace all HTML and CSS while preserving every JS DOM ID that these modules depend on.

The critical insight from reading the code: the existing operator page is already structured with a two-panel approach in `operator.css` (280px sidebar + 1fr main). The decision is to **simplify and clarify** this structure — remove the bimodal setup/exec toggle, merge into a single always-available layout, and make the vote panel the visual centerpiece. The JS modules already implement most of the required behavior; the rebuild mainly updates HTML structure and CSS, then adjusts JS selectors to match.

The SSE infrastructure is clean: `operator-realtime.js` uses `EventStream.connect()` (the project's custom SSE wrapper), driven by `MeetingContext.EVENT_NAME` events. This lifecycle is well-defined and must be preserved exactly.

**Primary recommendation:** New HTML + new CSS first. Then update JS selector IDs across all 6 modules. Preserve `window.OpS` bridge and all `O.fn.*` registrations — these are the integration contracts. Never touch the API layer.

---

## Standard Stack

### Core
| Component | Version/Pattern | Purpose | Why Standard |
|-----------|----------------|---------|--------------|
| Vanilla JS (IIFE modules) | ES5/ES6 mix | All operator logic | Project convention — no framework |
| `window.OpS` bridge | Custom (line 11 operator-tabs.js) | Cross-module state and function sharing | Established integration contract for all 6 JS files |
| `EventStream.connect()` | Custom SSE wrapper | SSE connection management | Existing implementation in operator-realtime.js |
| `MeetingContext` | Custom service | Meeting ID change events | Drives SSE connect/disconnect lifecycle |
| `ag-tooltip` Web Component | Custom (ag-tooltip.js) | Tooltip on disabled buttons | CSS-only shadow DOM, `text` + `position` attributes |
| `ag-popover` Web Component | Custom | Help popovers | Used in current meeting bar |
| CSS design tokens | `design-system.css` | All colors, spacing, radius | Required for dark mode parity |
| `anime.js` | CDN loaded, deferred | KPI count-up animations | Already used in operator-exec.js with graceful fallback |

### Supporting
| Component | Version/Pattern | Purpose | When to Use |
|-----------|----------------|---------|-------------|
| `Shared.hide()` / `Shared.show()` | utils.js | Element visibility | For show/hide operations instead of direct style |
| `escapeHtml()` | utils.js global | XSS-safe HTML insertion | Any innerHTML with user data |
| `icon(name, classes)` | utils.js global | SVG icon inline helper | Dynamic icon insertion in innerHTML |
| `api(url, opts)` | utils.js global | Fetch wrapper | All API calls |
| `setNotif(type, msg)` | utils.js global | Toast notifications | User feedback on actions |

### Installation
No new packages — this is a pure HTML/CSS/JS rebuild using existing infrastructure.

---

## Architecture Patterns

### Recommended Project Structure (files to create/rewrite)

```
public/
├── operator.htmx.html          ← REWRITE (new two-panel layout, new DOM IDs)
└── assets/
    ├── css/
    │   └── operator.css        ← REWRITE (new layout classes, refined components)
    └── js/pages/
        ├── operator-tabs.js    ← UPDATE selectors + DOM IDs for new HTML
        ├── operator-exec.js    ← UPDATE selectors + DOM IDs for new HTML
        ├── operator-motions.js ← UPDATE selectors + DOM IDs for new HTML
        ├── operator-realtime.js← MINIMAL CHANGE — only opSseIndicator/opSseLabel IDs
        ├── operator-attendance.js ← UPDATE selectors
        └── operator-speech.js  ← UPDATE selectors
```

### Pattern 1: New Two-Panel HTML Layout

**What:** Replace the current grid-area-based layout (statusbar / tabnav / main) with a cleaner structure. Meeting bar full-width at top. Below: 280px sidebar + flex-1 main panel. No mode-switch toggle needed.

**When to use:** Only one layout needed — always show sidebar + main.

```html
<!-- Source: derived from existing operator.css two-panel approach -->
<div class="app-shell" data-page-role="operator">
  <!-- Meeting bar — full width, always visible -->
  <header class="op-meeting-bar" id="opMeetingBar">
    <div class="op-meeting-bar-left">
      <select id="meetingSelect">...</select>
      <span id="meetingStatusBadge">—</span>
    </div>
    <div class="op-meeting-bar-center">
      <!-- Tab navigation lives here at top -->
    </div>
    <div class="op-meeting-bar-right">
      <span class="op-sse-indicator" id="opSseIndicator" data-sse-state="offline">
        <span class="op-sse-dot"></span>
        <span id="opSseLabel">Hors ligne</span>
      </span>
      <span id="barClock">--:--</span>
      <button id="btnBarRefresh">Actualiser</button>
    </div>
  </header>

  <!-- Two-panel body -->
  <div class="op-body">
    <!-- Agenda sidebar (280px) -->
    <aside class="op-sidebar" id="opSidebar">
      <div id="opAgendaList"></div>
    </aside>

    <!-- Main content (flex-1) -->
    <main class="op-main" id="opMain">
      <!-- Tab content panels here -->
      <!-- Vote panel card = visual centerpiece when vote open -->
      <div id="execActiveVote" hidden>
        <div class="op-vote-card op-vote-card--active">
          <div id="execVoteTitle">—</div>
          <!-- Pour / Contre / Abstention large counters -->
          <span id="execVoteFor">0</span>
          <span id="execVoteAgainst">0</span>
          <span id="execVoteAbstain">0</span>
          <!-- Progress bars -->
          <div id="opBarFor"></div>
          <!-- Delta badge -->
          <span id="opVoteDeltaBadge" hidden></span>
        </div>
      </div>
    </main>
  </div>
</div>
```

**Critical ID preservation:** The following IDs are referenced in JS and MUST exist in the new HTML:
- `meetingSelect`, `meetingStatusBadge`, `opSseIndicator`, `opSseLabel`, `opSseLabel`, `barClock`
- `opAgendaList` — renderAgendaList() in operator-exec.js
- `execActiveVote`, `execNoVote`, `execVoteTitle`, `execVoteFor`, `execVoteAgainst`, `execVoteAbstain`
- `execVoteParticipationBar`, `execVoteParticipationPct`
- `opBarFor`, `opBarAgainst`, `opBarAbstain`, `opPctFor`, `opPctAgainst`, `opPctAbstain`
- `opVoteDeltaBadge` — delta badge in exec-KPIs
- `opKpiPresent`, `opKpiQuorum`, `opKpiQuorumCheck`, `opKpiVoted`, `opKpiResolution`
- `opResTitle`, `opResTags`, `opResLiveDot`
- `opBtnProclaim`, `opBtnToggleVote`
- `opBtnCloseSession`, `opBtnEndSession`, `execBtnCloseSession`
- `opBtnNextVote` — post-vote guidance
- `opTransitionCard`, `opTransitionText`
- `opPostVoteGuidance`, `opEndOfAgenda`
- `opQuorumOverlay`, `opQuorumStats`, `opQuorumRiskNote`, `opQuorumContinuer`, `opQuorumReporter`, `opQuorumSuspendre`
- `opResolutionProgress` — progress track click handler
- `opExecTitle`, `opExecTimer`
- `opActionBar`
- `opSubTabs` — sub-tab click delegation in bindExecSubTabs()
- `opPanelResultat` — sub-tab panel
- `execLiveBadge`, `execBtnCloseVote`
- `execParticipation`, `execMotionsDone`, `execMotionsTotal`
- `execManualVoteList`, `execManualSearch`
- `execSpeakerInfo`, `execSpeechActions`, `execSpeechQueue`, `execSpeakerTimer`
- `devOnline`, `devStale`, `execDevOnline`, `execDevStale`
- `execQuickOpenList`
- `noActiveVote`, `activeVotePanel`, `activeVoteTitle` (vote tab in setup mode)
- `tabsNav`, `noMeetingState`, `srAnnounce`
- `tabCountResolutions`, `tabCountPresences`, `tabCountAlerts`
- `tabSeparator` — separator between prep and live tabs
- `healthChip`, `healthScore`, `healthHint`
- `contextHint`, `btnModeSetup`, `btnModeExec`, `btnPrimary`
- `meetingBarActions`, `modeSwitch`, `modeIndicator`
- `lifecycleBar`, `btnProjector`

### Pattern 2: window.OpS Bridge — Must Not Change

**What:** All 6 JS modules communicate through `window.OpS` (set in operator-tabs.js line 11). Functions are registered via `O.fn.functionName = fn` and called via `OpS.fn.*`.

**When to use:** Every new function exposed cross-module.

```javascript
// Source: operator-tabs.js line 11 — this MUST remain in new version
window.OpS = { fn: {} };

// Each module registers its functions on OpS:
O.fn.renderAgendaList = renderAgendaList;  // operator-exec.js
O.fn.connectSSE       = connectSSE;       // operator-realtime.js
O.fn.loadResolutions  = loadResolutions;  // operator-motions.js
```

**Critical:** OpS state properties must also be preserved:
`O.currentMeetingId`, `O.currentMeeting`, `O.currentMeetingStatus`, `O.currentMode`,
`O.currentOpenMotion`, `O.previousOpenMotionId`, `O.motionsCache`, `O.ballotsCache`,
`O.attendanceCache`, `O.membersCache`, `O.proxiesCache`, `O.policiesCache`,
`O.speechQueueCache`, `O.currentSpeakerCache`, `O.quorumWarningShown`,
`O.execSpeechTimerInterval`

### Pattern 3: SSE Lifecycle

**What:** SSE connects via `EventStream.connect()` on `MeetingContext.EVENT_NAME`. Driven by meeting selection change.

```javascript
// Source: operator-realtime.js — preserve this pattern exactly
window.addEventListener(MeetingContext.EVENT_NAME, function(e) {
  var newId = e.detail ? e.detail.newId : null;
  if (!newId) {
    if (sseStream) { sseStream.close(); sseStream = null; sseConnected = false; }
    setSseIndicator('offline');
    return;
  }
  // Debounced reconnect (300ms)
  _sseDebounceTimer = setTimeout(function() { connectSSE(); }, 300);
});
```

`setSseIndicator(state)` updates `#opSseIndicator[data-sse-state]` + `#opSseLabel` text.
States: `'live'` | `'reconnecting'` | `'offline'`
Labels: `'● En direct'` | `'⚠ Reconnexion...'` | `'✕ Hors ligne'`

### Pattern 4: Delta Badge (3s auto-clear)

The CONTEXT.md requires auto-clear after 3s, but the current code clears after 10s. Use 3s in the rebuild.

```javascript
// Current pattern in operator-exec.js — update fade timer to 3000ms
var delta = totalBallots - _prevVoteTotal;
if (delta > 0 && _prevVoteTotal > 0) {
  var badge = document.getElementById('opVoteDeltaBadge');
  if (badge) {
    badge.textContent = '+' + delta + ' ▲';
    badge.hidden = false;
    if (_deltaFadeTimer) clearTimeout(_deltaFadeTimer);
    _deltaFadeTimer = setTimeout(function() { badge.hidden = true; }, 3000); // 3s per CONTEXT
  }
}
```

### Pattern 5: ag-tooltip on Disabled Buttons

**What:** Wrap disabled buttons with `<ag-tooltip text="..." position="bottom">`. The component is a Web Component with shadow DOM. Text attribute is the tooltip copy.

```html
<!-- Source: ag-tooltip.js + existing usage in operator.htmx.html -->
<ag-tooltip text="Ouvrez d'abord la séance pour activer ce bouton" position="bottom">
  <button id="btnPrimary" disabled>Ouvrir la séance</button>
</ag-tooltip>
```

Important: `ag-tooltip` shows on hover AND focus-within. Works correctly on disabled buttons because the wrapper element receives the hover event.

### Pattern 6: Agenda Sidebar Item Structure

```html
<!-- Source: renderAgendaList() in operator-exec.js -->
<!-- JS renders: <div class="op-agenda-item {status}" data-motion-id="{id}" role="button" tabindex="0"> -->
<!-- status: 'pending' | 'current' | 'voted' -->
<!-- Must contain: .op-agenda-num, .op-agenda-title, .op-agenda-status-dot -->
```

The sidebar `#opAgendaList` is a container; its content is fully rendered by `renderAgendaList()` in JS.

### Pattern 7: KPI Strip Animation

Uses `anime.js` for count-up. Graceful fallback (direct textContent set) when anime not loaded.
Elements must have structure: leading text node + `.op-kpi-total` child span.

```html
<!-- IDs referenced in refreshExecKPIs() -->
<span id="opKpiPresent">0<span class="op-kpi-total">/0</span></span>
<span id="opKpiQuorum">0%</span>
<span id="opKpiQuorumCheck" hidden>✓</span>
<span id="opKpiVoted">0<span class="op-kpi-total">/0</span></span>
<span id="opKpiResolution">0<span class="op-kpi-total">/0</span></span>
```

### Anti-Patterns to Avoid

- **Removing window.OpS state properties:** Any missing state property (e.g. `O.membersCache`) causes silent failures downstream across modules
- **Renaming DOM IDs that JS depends on:** The 6 JS modules have ~80 DOM IDs hardcoded — any rename breaks silently at runtime
- **Setting display:none on tab panels inline in HTML:** Use `hidden` attr or `.active` class toggling; inline styles override JS class management
- **Removing data-partial or data-loaded attributes:** operator-tabs.js uses these for lazy partial loading (`ensureExecViewLoaded()`)
- **Touching the API layer:** The 4 API PHP files are thin dispatch stubs calling `OperatorController` — do not modify them
- **Forgetting to call bindProgressSegmentClicks():** This is called at module init in operator-exec.js; the `#opResolutionProgress` element must exist
- **Missing execSpeechTimerInterval on OpS:** operator-exec.js reads `O.execSpeechTimerInterval` — it must exist on the OpS object

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Tooltip on disabled button | Custom CSS tooltip | `ag-tooltip` Web Component | Already implemented, shadow DOM, accessible, text attr |
| Popover/help overlay | Custom modal | `ag-popover` Web Component | Already in meeting bar |
| SSE reconnection with backoff | Custom retry logic | `EventStream.connect()` wrapper | Already handles reconnect, debounce in realtime.js |
| Count-up animations | CSS-only counter | `anime.js` with graceful fallback | Pattern established in operator-exec.js |
| Dark mode token application | Hardcoded dark colors | `var(--color-*)` design tokens | Auto-applies via `[data-theme="dark"]` on html element |
| Meeting context events | Direct meetingId tracking | `MeetingContext.EVENT_NAME` listener | SSE reconnect lifecycle depends on this event |

---

## Common Pitfalls

### Pitfall 1: Breaking the Mode Switch Logic
**What goes wrong:** The current code has a bimodal setup/exec toggle (`btnModeSetup`, `btnModeExec`, `currentMode`). The rebuild removes visible mode toggle UI, but `currentMode` state and `setMode()` must still work internally — several JS branches check `O.currentMode === 'exec'`.
**Why it happens:** Assuming removal of UI = removal of the underlying state machine.
**How to avoid:** Keep `btnModeSetup`, `btnModeExec` in DOM even if visually hidden; OR audit every `O.currentMode` reference in all 6 files before removing the toggle.
**Warning signs:** Vote panel never switches to exec display; KPI strip doesn't update on vote open.

### Pitfall 2: Missing Lazy-Load Partial Attributes
**What goes wrong:** `operator-tabs.js` uses `container.dataset.partial` and `container.dataset.loaded` for lazy partial loading (`ensureLiveTabsLoaded`, `ensureExecViewLoaded`). If these attributes are removed, partials never load.
**Why it happens:** Not noticing the data attributes on the exec view container.
**How to avoid:** Preserve `data-partial="/partials/operator-exec.html"` and `data-loaded` attribute pattern, OR inline the exec content directly (simpler for rebuild).
**Warning signs:** Execution mode shows blank content; `viewExec` is null after setMode('exec').

**Recommendation for rebuild:** Inline exec content directly in the main HTML (no lazy partial loading). The rebuild HTML will be full-page anyway, removing the need for partials. Remove `ensureExecViewLoaded()` lazy-load calls and just reference `#viewExec` directly.

### Pitfall 3: CSS Grid vs Flex Collision
**What goes wrong:** The current `operator.css` forces `.app-shell { display: grid }` to override design-system's flex. The new rebuild must do the same or use a different approach.
**Why it happens:** `grid-template-*` properties are silently ignored on a flex container.
**How to avoid:** Use `[data-page-role="operator"] .app-shell { display: grid; }` override, or structure the layout with a new wrapper div that uses flex/grid without the app-shell conflict.
**Warning signs:** Two-panel layout collapses; sidebar appears full-width.

### Pitfall 4: Delta Badge Clear Timer Discrepancy
**What goes wrong:** Current code clears delta badge after 10s (`setTimeout(fn, 10000)`). CONTEXT.md specifies 3s.
**Why it happens:** Existing code predates the CONTEXT decision.
**How to avoid:** Update `_deltaFadeTimer` timeout to `3000` in `refreshExecKPIs()`.

### Pitfall 5: ag-tooltip on Programmatically Disabled Buttons
**What goes wrong:** When JS sets `button.disabled = true` at runtime (e.g., `toggleVoteBtn.disabled`), the tooltip still needs to work. CSS `:hover` doesn't trigger on pointer-events:none disabled buttons, but the ag-tooltip wrapper element is NOT disabled, so hover works.
**Why it happens:** Assuming disabled attr blocks all pointer events on the wrapper.
**How to avoid:** Always wrap the `<button>` inside `<ag-tooltip>`, never the reverse. Test hover on disabled state explicitly.

### Pitfall 6: Forgetting opSubTabs Delegation
**What goes wrong:** `bindExecSubTabs()` in operator-tabs.js uses delegation on `#opSubTabs`. If this element is renamed or moved, sub-tab switching breaks silently.
**Why it happens:** Sub-tabs are inside exec view, which was previously lazy-loaded.
**How to avoid:** Preserve `id="opSubTabs"` on the sub-tab container. The `data-op-tab` attribute pattern on each tab and `opPanel{TabName}` ID pattern on panels must be preserved.

---

## Code Examples

Verified patterns from source files:

### SSE Indicator DOM IDs (operator-realtime.js:29-33)
```javascript
function setSseIndicator(state) {
  var el = document.getElementById('opSseIndicator');  // needs data-sse-state attr
  var lb = document.getElementById('opSseLabel');
  if (el) el.setAttribute('data-sse-state', state);
  if (lb) lb.textContent = SSE_LABELS[state] || state;
}
```

### Vote Count Rendering (operator-exec.js:664-692)
```javascript
// Pour/Contre/Abstention counts read from ballotsCache
var fc = 0, ac = 0, ab = 0;
Object.values(O.ballotsCache).forEach(function(v) {
  if (v === 'for') fc++;
  else if (v === 'against') ac++;
  else if (v === 'abstain') ab++;
});
// Progress bars use CSS custom property --bar-pct
if (barFor) barFor.style.setProperty('--bar-pct', pctFor + '%');
```

### Agenda Item Click Navigation (operator-exec.js:322-336)
```javascript
// Rendered items: class="op-agenda-item {status}" data-motion-id="{id}"
// Click calls selectMotion(motionId)
// Keyboard: Enter/Space also calls selectMotion
list.querySelectorAll('.op-agenda-item[data-motion-id]').forEach(function(item) {
  item.addEventListener('click', function() {
    selectMotion(item.dataset.motionId);
  });
});
```

### ag-tooltip Usage (from HTML and operator-tabs.js)
```html
<!-- Always-visible tooltip explaining disabled state -->
<ag-tooltip text="Disponible après ajout des membres, enregistrement des présences et configuration du vote" position="bottom">
  <button class="btn btn-sm btn-primary" id="btnPrimary" disabled>Ouvrir la séance</button>
</ag-tooltip>
```

### op-agenda-item Status Classes (operator-exec.js:314)
```javascript
var status = m.closed_at ? 'voted'
  : (O.currentOpenMotion && O.currentOpenMotion.id === m.id ? 'current' : 'pending');
// CSS must define: .op-agenda-item.pending, .op-agenda-item.current, .op-agenda-item.voted
```

### CSS Layout Pattern for Two-Panel (operator.css:16-64 — existing, to be refined)
```css
/* Override app-shell flex with grid for operator page */
[data-page-role="operator"] .app-shell {
  display: grid;
  grid-template-rows: auto 1fr;  /* meeting-bar + body */
  height: 100vh;
  overflow: hidden;
}
/* Two-panel body */
[data-page-role="operator"] .op-body {
  display: grid;
  grid-template-columns: 280px 1fr;
  overflow: hidden;
}
/* Responsive: collapse sidebar at 1024px */
@media (max-width: 1024px) {
  [data-page-role="operator"] .op-body {
    grid-template-columns: 1fr;
  }
  [data-page-role="operator"] .op-sidebar {
    /* becomes horizontal scrollable strip */
  }
}
```

---

## State of the Art

| Old Approach | Current (to rebuild) | Target After Phase 46 | Impact |
|--------------|---------------------|----------------------|--------|
| Bimodal setup/exec toggle | Mode switch UI in meeting-bar-actions | Single always-available layout, no mode toggle needed | Simpler UX, less state machine complexity |
| Lazy-loaded exec partial | `data-partial="/partials/operator-exec.html"` | Inline in main HTML | Eliminates partial loading race conditions |
| Delta badge clears after 10s | `setTimeout(fn, 10000)` | 3s per CONTEXT.md decision | Matches user expectation |
| Large CSS file (4679 lines) | Multiple concerns mixed | Clean new operator.css for new layout | Faster to maintain |
| Tabs + mode switch (complex) | `setMode('exec')` triggered by live meeting | Tab bar always visible, vote panel contextually shown | Mission-control feel |

**Deprecated/outdated patterns:**
- `viewSetup` / `viewExec` separate DOM nodes: In the rebuild these should be replaced with a unified panel system
- `loadPartial()` / `ensureExecViewLoaded()` lazy partial loading: Inline content in rebuild HTML eliminates this complexity
- `lastSetupTab` / mode switch save/restore: Remove if mode toggle UI is gone

---

## Open Questions

1. **Keep or remove setMode() internal state machine?**
   - What we know: `O.currentMode` is checked in 3+ places across operator-exec.js and operator-realtime.js for branching behavior
   - What's unclear: If mode toggle UI is hidden, does `currentMode` still need to change? Or should exec mode be always-on?
   - Recommendation: Keep `currentMode` state but make exec mode auto-activate when meeting goes live. Remove manual toggle buttons from HTML but keep the internal state logic intact to avoid breaking exec-branch code.

2. **Inline exec content vs keep partials?**
   - What we know: `operator-exec.html` and `operator-live-tabs.html` are currently lazy-loaded partials
   - What's unclear: How many total lines inlining adds vs. complexity of keeping partial system
   - Recommendation: Inline everything in `operator.htmx.html` for the rebuild. This eliminates the `data-partial` race condition and simplifies the codebase. The original reason for partials (90KB page weight concern) is outdated.

3. **Tab structure: sidebar motions vs. top tab for motions?**
   - What we know: The sidebar shows agenda motions; the existing tabs include "Ordre du jour" tab with full CRUD
   - What's unclear: In the new design, does sidebar replace "Ordre du jour" tab or complement it?
   - Recommendation: Sidebar is a quick-navigation list (click to load motion in main panel). The "Ordre du jour" tab remains for full CRUD operations. These are complementary.

---

## Validation Architecture

> workflow.nyquist_validation key is absent from config.json — treating as enabled.

### Test Framework
| Property | Value |
|----------|-------|
| Framework | None detected — PHP/JS vanilla project, no automated test suite |
| Config file | none |
| Quick run command | Open browser: `http://localhost/operator.htmx.html` |
| Full suite command | Manual browser walkthrough per success criteria |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REB-04 | Operator console fully rebuilt | manual | n/a — browser verification | ❌ Wave 0 N/A |
| REB-04 | SSE live indicator shows "connected" | manual | Load page, select meeting, observe indicator | n/a |
| REB-04 | Vote open/close/tally flow | manual | Open vote, observe counts, close | n/a |
| REB-04 | Agenda sidebar lists all motions | manual | Select meeting, observe sidebar | n/a |
| REB-04 | Disabled buttons show tooltips | manual | Hover disabled buttons in meeting bar | n/a |
| WIRE-01 | No dead API endpoints | manual | Open browser console, check for 4xx errors | n/a |
| WIRE-02 | SSE updates live vote display | manual | Cast vote on voter page, observe operator counter | n/a |

### Sampling Rate
- **Per task commit:** Browser load check — no JS console errors, layout renders correctly
- **Per wave merge:** Full walkthrough of vote flow (open → cast votes → SSE update → close)
- **Phase gate:** All 5 success criteria from CONTEXT.md verified before marking complete

### Wave 0 Gaps
None — no automated test infrastructure exists or is expected for this PHP/vanilla JS project. All verification is browser-based per established pattern from phases 43–45.

---

## Sources

### Primary (HIGH confidence)
- `/home/user/gestion_votes_php/public/assets/js/pages/operator-realtime.js` — SSE lifecycle, indicator IDs, event types
- `/home/user/gestion_votes_php/public/assets/js/pages/operator-exec.js` — KPI strip, vote rendering, agenda list, delta badge, action bar
- `/home/user/gestion_votes_php/public/assets/js/pages/operator-tabs.js` — OpS bridge, tab navigation, mode switch, lazy loading, window.OpS state properties
- `/home/user/gestion_votes_php/public/assets/js/pages/operator-motions.js` — loadResolutions, vote CRUD, DOM IDs in resolutions tab
- `/home/user/gestion_votes_php/public/assets/css/operator.css` — existing layout patterns, SSE indicator CSS, delta badge CSS
- `/home/user/gestion_votes_php/public/assets/js/components/ag-tooltip.js` — tooltip API (text, position attrs), shadow DOM structure
- `/home/user/gestion_votes_php/public/operator.htmx.html` — current HTML structure, all DOM IDs in use

### Secondary (MEDIUM confidence)
- `.planning/phases/46-operator-console-rebuild/46-CONTEXT.md` — User decisions and constraints
- `.planning/REQUIREMENTS.md` — REB-04, WIRE-01, WIRE-02 definitions

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries/components read directly from source
- Architecture: HIGH — DOM ID inventory compiled from all 6 JS files
- Pitfalls: HIGH — identified from actual code patterns, not speculation

**Research date:** 2026-03-22
**Valid until:** 2026-04-22 (stable vanilla JS codebase, no external dependencies changing)
