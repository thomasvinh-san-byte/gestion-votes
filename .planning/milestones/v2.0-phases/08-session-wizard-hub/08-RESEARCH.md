# Phase 8: Session Wizard & Hub - Research

**Researched:** 2026-03-13
**Domain:** Vanilla JS / HTML / CSS — multi-step wizard, session hub lifecycle UI
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Strict validation: required fields must be filled before 'Suivant' enables — prevents incomplete sessions
- Step 2 (Membres): Edit button per row (not inline cell editing) for member table
- Step 3 (Ordre du jour): Drag-and-drop reorder for resolutions (promoted from nice-to-have to required)
- Step 3 (Ordre du jour): Global default voting rule set in Step 1, each resolution inherits but can override
- Hub main action card: Fully dynamic — CTA text, icon, and color all change based on current session stage; always shows ONE most important next action
- Hub KPI cards: 4 KPI cards — participants, resolutions, quorum needed, convocations
- Hub checklist: Auto-check items when data confirms completion; manual only for human-judgment steps; full list: Titre défini, Date fixée, Membres ajoutés, Résolutions créées, Convocations envoyées, Documents attachés
- Hub documents panel: Display only — list documents with download links, no upload from hub
- After clicking 'Créer': redirect to hub.htmx.html with success toast ('Séance créée avec succès')
- Auto-save drafts to localStorage — wizard progress saved client-side, resumable if user leaves
- Full refactor of ALL inline styles to CSS classes on BOTH wizard.htmx.html and hub.htmx.html
- Full JS refactor of wizard.js and hub.js alongside HTML/CSS
- One CSS per page: wizard gets wizard.css (separate from meetings.css)

### Claude's Discretion
- Accordion behavior: true accordion vs progressive reveal with collapsible completed steps
- Stepper completed step visual: checkmark vs color change
- CSV import UX pattern: file picker + preview recommended
- Secret ballot toggle placement and UX
- Whether Step 4 shows 'Télécharger PDF' alongside 'Créer'
- Hub status bar: exact number of stages (current 6 is acceptable), whether segments are clickable or visual-only, color scheme
- Hub KPI cards: whether to reuse .kpi-card from Phase 7 or create hub-specific variant
- Hub layout organization (status bar → action → KPIs → checklist + docs, or alternative)
- Whether to keep the identity banner (session title, date, place) or simplify

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| WIZ-01 | 4-step accordion with visual stepper (done/active/pending circles) | Wireframe defines `.wiz-progress-wrap`, `.wiz-step-item`, `.wiz-snum` CSS; JS `updateStepper()` already handles state; needs inline-to-CSS extraction |
| WIZ-02 | Step 1 — Infos générales: title, type, date, time, location, address | HTML structure complete; inline styles need extraction to `wizard.css`; voting rule default must feed Step 3 resolution inherit |
| WIZ-03 | Step 2 — Membres: CSV import, manual entry table, lot assignment, vote weight | Drop zone HTML exists; CSV parsing via `FileReader` API (native, no library); members.js has file drag pattern to copy |
| WIZ-04 | Step 3 — Ordre du jour: resolution entries, voting rule per resolution, secret ballot toggle | Resolution add form complete; drag-and-drop reorder via HTML5 Drag API (native, no library); chip toggle for secret ballot already exists |
| WIZ-05 | Step 4 — Récapitulatif: review all info, create button, PDF option | Recap builder exists; API wire-up needed; localStorage draft save/restore needed; redirect to hub after create |
| HUB-01 | Status bar with colorful segments representing session stages | Wireframe tour text confirms horizontal colored bar ("barre colorée, chaque segment = une étape"); current hub has vertical stepper only — new horizontal bar needed; operator.css has `op-track` segment pattern to reference |
| HUB-02 | Main action card (highlighted, large CTA) for next step | hub.js `renderAction()` fully working; inline styles need extraction to `hub.css` |
| HUB-03 | 4 KPI cards (participants, resolutions, quorum needed, convocations) | hub.js `loadData()` sets all 4 KPI values; HTML uses inline styles — extract to hub.css; `.kpi-card` in design-system.css is available |
| HUB-04 | Preparation checklist with completion tracking | hub.js `renderAction()` renders checklist per step; needs to be separated into standalone checklist section per CONTEXT.md decisions |
| HUB-05 | Associated documents panel with download links | Documents HTML and JS rendering complete; display-only per decision; needs CSS extraction and download link pattern |
</phase_requirements>

---

## Summary

Both `wizard.htmx.html` and `hub.htmx.html` are substantially built — all major HTML structure, JS logic, and data rendering exist. The primary work of this phase is **CSS extraction** (every inline `style=""` attribute becomes a CSS class in a new `wizard.css` / `hub.css`), **JS cleanup** (refactoring side effects like proper `const`/`let` usage is out of scope since project uses `var`; clean up relies on IIFE pattern which already exists), and **feature alignment** with wireframe v3.19.2.

The key new features are: (1) localStorage draft auto-save for the wizard, (2) drag-and-drop resolution reorder in Step 3, (3) wiring the API call in `btnCreate`, (4) the horizontal colorful status bar in the hub (HUB-01 — not yet present, current hub only has a left-column vertical stepper), and (5) the standalone preparation checklist on the hub.

The wireframe (`ag_vote_wireframe.html`) is the canonical pixel reference — all CSS values, token names, and class definitions are defined there. The hub uses `var(--accent)`, `var(--success)`, `var(--warn)`, `var(--danger)`, `var(--purple)` for per-stage colors.

**Primary recommendation:** Extract inline styles to `wizard.css` / `hub.css` using wireframe class definitions as the source of truth, wire the missing features (localStorage draft, drag-and-drop, API call, horizontal status bar), and redirect flow from wizard Créer → hub.

---

## Standard Stack

### Core
| Library/API | Version | Purpose | Why Standard |
|------------|---------|---------|--------------|
| HTML5 Drag and Drop API | Native | Resolution reorder in Step 3 | Zero dependencies; project avoids external libraries; `members.js` already uses dragover/dragleave patterns |
| FileReader API | Native | CSV file reading in Step 2 | Native; consistent with existing `members.js` CSV import pattern |
| localStorage | Native | Wizard draft auto-save | Project already uses for sidebar pin (`PIN_KEY`), theme, sidebar group state |
| `var` keyword + IIFE | Codebase convention | Page JS modules | All pages follow this pattern — no ES6 modules, no `const`/`let` globally |

### Supporting
| Asset | Source | Purpose | When to Use |
|-------|--------|---------|-------------|
| `wizard.css` (new) | Create fresh | All wizard-specific styles | Follows one-CSS-per-page pattern; wizard loads `meetings.css` currently — that needs updating to `wizard.css` |
| `hub.css` (new) | Create fresh | All hub-specific styles | hub.htmx.html currently loads `pages.css` — change to `hub.css` |
| `design-system.css` | Existing | Global tokens, `.card`, `.chip`, `.btn`, `.alert`, `.kpi-card` | Already imported via `app.css`; do NOT duplicate tokens |
| `operator.css` | Reference only | Hub/stepper CSS patterns already defined for operator page | Read for pattern reference; hub gets its own hub.css |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| HTML5 Drag API | SortableJS | SortableJS is simpler but adds an external dependency the project avoids; HTML5 Drag API is sufficient for a short resolution list |
| localStorage draft save | IndexedDB | IndexedDB is overkill for a simple form draft; localStorage is already used for app state |
| wizard.css | Extending meetings.css | meetings.css is for the sessions list page; mixing concerns violates the one-CSS-per-page pattern |

**Installation:** No new packages — all native APIs.

---

## Architecture Patterns

### Recommended File Changes
```
public/
├── wizard.htmx.html       # Remove all style="" attributes; update CSS link to wizard.css
├── hub.htmx.html          # Remove all style="" attributes; update CSS link to hub.css
├── assets/css/
│   ├── wizard.css         # NEW — all wizard-specific CSS classes
│   └── hub.css            # NEW — all hub-specific CSS classes
└── assets/js/pages/
    ├── wizard.js          # Add: localStorage draft, drag-drop, API wire, validation gating
    └── hub.js             # Add: horizontal status bar render, standalone checklist
```

### Pattern 1: Inline-to-CSS Extraction (established by Phase 7)
**What:** Every `style="..."` attribute in HTML becomes a named CSS class. JS-applied inline styles for dynamic values (colors, widths) are the ONLY acceptable inline styles remaining.
**When to use:** All HTML elements — the wireframe CSS at `ag_vote_wireframe.html` provides the canonical class definitions for `wiz-progress-wrap`, `wiz-step-item`, `wiz-snum`, `wf-step`, `step-nav`, `ctx-panel`, `field-label`, `field-input`, `grid-4`, `flex-between`, `hub-step-row`, `hub-step-num`, `hub-step-line`, `hub-step-text`, `hub-step-title`, `hub-action-btn`, `hub-details-toggle`, `hub-identity-date`, `hub-identity-meta`.

```css
/* Source: ag_vote_wireframe.html lines 912-920 */
.wiz-progress-wrap {
  display: flex; align-items: stretch; background: var(--color-surface);
  border: 1px solid var(--color-border); border-radius: var(--radius-lg);
  overflow: hidden; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
.wiz-step-item {
  flex: 1; display: flex; align-items: center; gap: 10px;
  padding: 11px 14px; border-right: 1px solid var(--color-border);
  font-size: 12px; font-weight: 600; color: var(--color-text-muted);
  transition: background .2s; position: relative;
}
.wiz-step-item.done  { color: var(--color-success); background: color-mix(in srgb, var(--color-success) 5%, transparent); }
.wiz-step-item.active { color: var(--color-primary); background: var(--color-primary-subtle); font-weight: 700; }
.wiz-step-item.active::after {
  content: ''; position: absolute; bottom: 0; left: 0; right: 0;
  height: 3px; background: var(--color-primary);
}
```

### Pattern 2: localStorage Draft Auto-Save
**What:** Wizard form state is serialized to localStorage on every field change (or on 'Suivant') and restored on page load.
**When to use:** Only in wizard.js — not in hub.js.
**Example:**
```javascript
// Source: existing shell.js localStorage pattern adapted for wizard
var DRAFT_KEY = 'ag-vote-wizard-draft';

function saveDraft() {
  var draft = {
    step: currentStep,
    title: (document.getElementById('wizTitle') || {}).value || '',
    type:  (document.getElementById('wizType') || {}).value || '',
    date:  (document.getElementById('wizDate') || {}).value || '',
    hh:    (document.getElementById('wizTimeHH') || {}).value || '',
    mm:    (document.getElementById('wizTimeMM') || {}).value || '',
    place: (document.getElementById('wizPlace') || {}).value || '',
    addr:  (document.getElementById('wizAddr') || {}).value || '',
    quorum:(document.getElementById('wizQuorum') || {}).value || '',
    members: members,      // array from Step 2
    resolutions: resolutions // array from Step 3
  };
  try { localStorage.setItem(DRAFT_KEY, JSON.stringify(draft)); } catch(e) {}
}

function restoreDraft() {
  try {
    var raw = localStorage.getItem(DRAFT_KEY);
    if (!raw) return;
    var draft = JSON.parse(raw);
    // Restore each field value...
    if (draft.title) document.getElementById('wizTitle').value = draft.title;
    // ... etc
    members = draft.members || [];
    resolutions = draft.resolutions || [];
    showStep(draft.step || 0);
  } catch(e) {}
}

function clearDraft() {
  try { localStorage.removeItem(DRAFT_KEY); } catch(e) {}
}
```

### Pattern 3: HTML5 Drag-and-Drop Resolution Reorder
**What:** Resolution list items are draggable; drop reorders the `resolutions` array and re-renders.
**When to use:** Step 3 resolution list only.
**Example:**
```javascript
// Source: HTML5 Drag and Drop API — adapted from members.js dragover pattern
function renderResoList() {
  var list = document.getElementById('wizResoList');
  if (!list) return;
  list.innerHTML = '';
  resolutions.forEach(function(r, i) {
    var row = document.createElement('div');
    row.className = 'reso-row';
    row.draggable = true;
    row.dataset.index = i;
    row.innerHTML = buildResoRowHTML(r, i); // escapeHtml all values
    row.addEventListener('dragstart', onDragStart);
    row.addEventListener('dragover', onDragOver);
    row.addEventListener('drop', onDrop);
    row.addEventListener('dragend', onDragEnd);
    list.appendChild(row);
  });
}

var dragSrcIdx = null;

function onDragStart(e) {
  dragSrcIdx = parseInt(this.dataset.index, 10);
  this.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
}
function onDragOver(e) {
  e.preventDefault();
  e.dataTransfer.dropEffect = 'move';
  this.classList.add('drag-over');
}
function onDrop(e) {
  e.stopPropagation();
  var targetIdx = parseInt(this.dataset.index, 10);
  if (dragSrcIdx !== null && dragSrcIdx !== targetIdx) {
    var moved = resolutions.splice(dragSrcIdx, 1)[0];
    resolutions.splice(targetIdx, 0, moved);
    saveDraft();
    renderResoList();
  }
}
function onDragEnd() { renderResoList(); } // re-render clears drag-over states
```

### Pattern 4: Hub Horizontal Colorful Status Bar (HUB-01)
**What:** A horizontal bar of colored equal-width segments, one per lifecycle stage, placed above the hub layout. The wireframe guided tour says: "La barre colorée montre l'avancement. Chaque segment = une étape."
**When to use:** At the top of hub.htmx.html main content, replacing or supplementing the current left-column vertical stepper.
**Note:** The current `hub.htmx.html` uses a left-column vertical stepper. The wireframe also uses this vertical stepper as primary navigation. HUB-01 requirement for a "colorful status bar" is best implemented as a new **horizontal bar above** the existing layout — the vertical stepper stays as navigation, the horizontal bar serves as quick visual status indicator.
**Example:**
```javascript
// Source: wireframe op-track pattern (line 1683) adapted for hub stages
function renderStatusBar() {
  var bar = document.getElementById('hubStatusBar');
  if (!bar) return;
  var html = '';
  HUB_STEPS.forEach(function(s, i) {
    var isDone = i < currentStep;
    var isActive = i === currentStep;
    var color = isDone ? 'var(--color-success)' : isActive ? s.color : 'var(--color-border)';
    html += '<div class="hub-bar-segment' + (isActive ? ' active' : isDone ? ' done' : '') + '"' +
      ' style="background:' + color + '"' +
      ' title="' + escapeHtml(s.titre) + '">' +
      '<span class="hub-bar-label">' + escapeHtml(s.titre) + '</span>' +
    '</div>';
  });
  bar.innerHTML = html;
}
```

```css
/* wizard.css / hub.css new classes */
.hub-status-bar {
  display: flex; gap: 3px; margin-bottom: 14px; border-radius: var(--radius-sm);
  overflow: hidden;
}
.hub-bar-segment {
  flex: 1; height: 6px; border-radius: 0; transition: all .3s ease;
  position: relative; cursor: default;
}
.hub-bar-segment.active { height: 10px; border-radius: var(--radius-sm); }
```

### Pattern 5: Step Validation Gating
**What:** 'Suivant' button is disabled until required fields pass validation. Lock is enforced per step.
**When to use:** wizard.js — each 'Suivant' button click first calls `validateStep(n)`.
**Example:**
```javascript
function validateStep(n) {
  if (n === 0) {
    var title = (document.getElementById('wizTitle') || {}).value || '';
    var date  = (document.getElementById('wizDate') || {}).value || '';
    var hh    = (document.getElementById('wizTimeHH') || {}).value || '';
    var mm    = (document.getElementById('wizTimeMM') || {}).value || '';
    return title.trim().length > 0 && date.length > 0 && hh.length === 2 && mm.length === 2;
  }
  if (n === 1) { return members.length > 0; }
  if (n === 2) { return resolutions.length > 0; }
  return true;
}

// In button binding:
['btnNext0', function() {
  if (!validateStep(0)) {
    showFieldErrors(0); // highlight missing required fields
    return;
  }
  saveDraft();
  showStep(1);
}],
```

### Anti-Patterns to Avoid
- **Leaving ANY inline `style=""` on static elements:** Every static visual property must be a CSS class. Only JS-driven dynamic values (colors from `HUB_STEPS[i].color`, widths like `progress-fill` percentages) may use inline styles in JS.
- **Adding external drag libraries (SortableJS):** The resolution list is short; HTML5 Drag API suffices without a dependency.
- **Putting wizard CSS into meetings.css:** One CSS file per page — wizard gets `wizard.css`, hub gets `hub.css`.
- **Using `const`/`let` in page JS:** The codebase convention is `var` for all page scripts. Do not introduce ES6 module syntax.
- **Duplicating design token values:** Never hardcode colors or spacing. Use `var(--color-primary)`, `var(--color-success)`, etc.
- **Keeping the `display:none` show/hide pattern with inline styles in JS:** Acceptable only for JS-driven show/hide; the pattern already exists in `showStep()` and `renderAction()`.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| CSV file parsing | Custom CSV tokenizer | `FileReader.readAsText()` + `String.split('\n').map(r => r.split(','))` | CSV in this domain is simple (no quoted commas needed for member lists); members.js shows the drag-to-upload zone pattern |
| Drag-and-drop reorder | Position-tracking mouse events | HTML5 Drag and Drop API | Native, cross-browser, already used in members.js for upload zone |
| Form dirty-state tracking | Complex event subscriptions | Save on each 'Suivant' + restore from localStorage | Simple enough for wizard; no real-time sync needed |
| Toast notification | Custom popup | `Shared.showToast('Séance créée avec succès', 'success')` | Already implemented as `ag-toast` web component from Phase 5 |
| Modal for member edit | Custom dialog | `ag-modal` component from Phase 5 | Standard component already in use across the app |
| Empty state for members/resolutions | Custom HTML | `Shared.emptyState(icon, title, subtitle)` | Project utility — use consistently |

**Key insight:** This phase is alignment and refactoring work. Every custom solution already exists in the codebase. The value is in extracting and connecting, not building net new.

---

## Common Pitfalls

### Pitfall 1: CSS Token Naming Mismatch
**What goes wrong:** The wireframe uses legacy token names (`var(--accent)`, `var(--success)`, `var(--surface-alt)`, `var(--border-dash)`, `var(--accent-light)`) while the design system (installed in Phase 4) uses `var(--color-primary)`, `var(--color-success)`, `var(--color-bg-subtle)`, etc.
**Why it happens:** The wireframe was built with its own token layer; the project redesign aligned to a new naming convention in Phase 4.
**How to avoid:** When copying CSS from wireframe, translate ALL token names using this map:

| Wireframe token | Design system token |
|----------------|---------------------|
| `var(--accent)` | `var(--color-primary)` |
| `var(--accent-light)` | `var(--color-primary-subtle)` |
| `var(--accent-dark)` | `var(--color-primary-hover)` |
| `var(--accent-glow)` | `var(--color-primary-glow)` |
| `var(--success)` | `var(--color-success)` |
| `var(--success-bg)` | `var(--color-success-subtle)` |
| `var(--danger)` | `var(--color-danger)` |
| `var(--warn)` | `var(--color-warning)` |
| `var(--warn-bg)` | `var(--color-warning-subtle)` |
| `var(--purple)` | `var(--color-purple)` |
| `var(--surface)` | `var(--color-surface)` |
| `var(--surface-alt)` | `var(--color-bg-subtle)` |
| `var(--bg)` | `var(--color-bg)` |
| `var(--border)` | `var(--color-border)` |
| `var(--border-soft)` | `var(--color-border-subtle)` |
| `var(--border-dash)` | `var(--color-border)` with `border-style: dashed` |
| `var(--text-dark)` | `var(--color-text)` |
| `var(--text-muted)` | `var(--color-text-muted)` |
| `var(--text-light)` | `var(--color-text-muted)` (lighter) |
| `var(--radius)` | `var(--radius-md)` |
| `var(--radius-sm)` | `var(--radius-sm)` |
| `var(--radius-lg)` | `var(--radius-lg)` |
| `var(--shadow-xs)` | `var(--shadow-sm)` |
| `var(--font-mono)` | `var(--font-mono)` |
| `var(--font-display)` | `var(--font-display)` |

**Warning signs:** A CSS property renders correctly in light theme but breaks in dark theme — indicates a hard-coded color or wrong token.

### Pitfall 2: Existing CSS Classes in operator.css vs New hub.css
**What goes wrong:** `operator.css` already defines `.hub-identity`, `.hub-stepper`, `.hub-step`, `.hub-action`, `.hub-checklist`, and many more hub-specific classes. If `hub.htmx.html` links to `hub.css` but `operator.css` is NOT loaded, these classes won't exist.
**Why it happens:** These hub classes were added to `operator.css` during Phase 6 navigation work (the hub page previously loaded `pages.css`).
**How to avoid:** `hub.css` must contain ALL hub-specific styles. Either copy the hub sections from `operator.css` into `hub.css`, or change hub.htmx.html to load both `operator.css` and `hub.css` (not ideal). Recommended: move hub-specific CSS from `operator.css` into `hub.css` and update `operator.css` to remove duplicates. Verify `operator.htmx.html` does NOT rely on these hub classes.
**Warning signs:** Hub page loads but layout is broken (missing borders, wrong colors) — means CSS classes exist in `operator.css` but `hub.css` is incomplete.

### Pitfall 3: localStorage Draft Key Collision
**What goes wrong:** Multiple browser tabs with different wizard sessions corrupt each other's draft.
**Why it happens:** Single localStorage key without session scoping.
**How to avoid:** If editing an existing meeting (future use), include the meeting ID in the key. For new creation, a single key `ag-vote-wizard-draft` is sufficient since only one new-session wizard can be in progress. Clear the draft immediately after successful API call.

### Pitfall 4: Validation Gating Doesn't Handle Edge Cases
**What goes wrong:** Step 1 validation passes but date is in the past, or time is invalid (`99:99`).
**Why it happens:** Simple `.length > 0` checks don't validate value ranges.
**How to avoid:** In `validateStep(0)`, validate HH is 0-23 and MM is 0-59. The existing `setupTimeInput()` already clamps values on blur — trust that and check `parseInt(hh, 10) <= 23`.

### Pitfall 5: Drag-and-Drop Fails on Mobile
**What goes wrong:** HTML5 Drag API doesn't fire on touch devices.
**Why it happens:** `dragstart`/`dragover`/`drop` events are pointer-device only.
**How to avoid:** For a management UI (operators/admins on desktop), this is acceptable. Add a visual drag handle icon (`≡` or `⠿`) so the intent is clear. If mobile support is needed in future, `touch-action: none` + custom touch handlers — but this is out of scope for this phase.

---

## Code Examples

Verified patterns from official sources and existing codebase:

### CSV File Import (members.js pattern)
```javascript
// Source: members.js lines 918-927 — drag-to-upload zone
uploadZone.addEventListener('dragover', function(e) {
  e.preventDefault();
  uploadZone.classList.add('dragover');
});
uploadZone.addEventListener('dragleave', function() {
  uploadZone.classList.remove('dragover');
});
uploadZone.addEventListener('drop', function(e) {
  e.preventDefault();
  uploadZone.classList.remove('dragover');
  var file = e.dataTransfer.files[0];
  if (file) handleFile(file);
});

// File picker button triggers:
function handleFile(file) {
  var reader = new FileReader();
  reader.onload = function(e) {
    var lines = e.target.result.split('\n');
    var rows = lines.map(function(l) { return l.split(','); });
    // First row = headers; rows[1..] = data
    var imported = rows.slice(1).filter(function(r) { return r.length >= 2; }).map(function(r) {
      return { name: r[0].trim(), lot: r[1].trim(), email: r[2] ? r[2].trim() : '' };
    });
    members = members.concat(imported);
    renderMembersList();
    saveDraft();
  };
  reader.readAsText(file);
}
```

### Wizard Draft Save/Restore
```javascript
// Source: shell.js localStorage pattern + wizard.js state
var DRAFT_KEY = 'ag-vote-wizard-draft';

// Call saveDraft() on every 'Suivant', and on any field blur for Step 1
function saveDraft() {
  try {
    localStorage.setItem(DRAFT_KEY, JSON.stringify({
      step: currentStep,
      s1: {
        title: getId('wizTitle').value,
        type:  getId('wizType').value,
        date:  getId('wizDate').value,
        hh:    getId('wizTimeHH').value,
        mm:    getId('wizTimeMM').value,
        place: getId('wizPlace').value,
        addr:  getId('wizAddr').value,
        quorum:getId('wizQuorum').value
      },
      members: members,
      resolutions: resolutions
    }));
  } catch(e) {}
}

// Call restoreDraft() inside init() before showStep(0)
// Call clearDraft() after successful POST /api/v1/meetings
```

### API Call in btnCreate
```javascript
// Source: wizard.js TODO comment — wire POST /api/v1/meetings
// api() global function is established in codebase (see Phase 7 decision)
['btnCreate', function() {
  var btn = document.getElementById('btnCreate');
  if (btn) { btn.disabled = true; btn.textContent = 'Création…'; }

  var payload = buildPayload(); // title, type, date, participants, resolutions
  api('POST', '/api/v1/meetings', payload)
    .then(function(res) {
      clearDraft();
      // Redirect to hub with success toast queued via sessionStorage
      sessionStorage.setItem('ag-vote-toast', JSON.stringify({ msg: 'Séance créée avec succès', type: 'success' }));
      window.location.href = '/hub.htmx.html?id=' + res.id;
    })
    .catch(function(err) {
      if (btn) { btn.disabled = false; btn.textContent = 'Créer la séance'; }
      Shared.showToast('Erreur lors de la création. Veuillez réessayer.', 'error');
    });
}]
```

### Hub Standalone Checklist (HUB-04)
```javascript
// Per CONTEXT.md: checklist is standalone section, not embedded in action card
// Auto-check items: true when data confirms completion; false for human-judgment items
var CHECKLIST_ITEMS = [
  { key: 'title',   label: 'Titre défini',           autoCheck: function(d) { return !!d.title; } },
  { key: 'date',    label: 'Date fixée',             autoCheck: function(d) { return !!d.date; } },
  { key: 'members', label: 'Membres ajoutés',        autoCheck: function(d) { return d.memberCount > 0; } },
  { key: 'resolutions', label: 'Résolutions créées', autoCheck: function(d) { return d.resolutionCount > 0; } },
  { key: 'convocations', label: 'Convocations envoyées', autoCheck: function(d) { return d.convocationsSent; } },
  { key: 'documents', label: 'Documents attachés',   autoCheck: function(d) { return d.documentCount > 0; } }
];

function renderChecklist(sessionData) {
  var container = document.getElementById('hubChecklist');
  if (!container) return;
  var done = 0;
  var html = '';
  CHECKLIST_ITEMS.forEach(function(item) {
    var checked = item.autoCheck(sessionData);
    if (checked) done++;
    html += '<div class="hub-check-item' + (checked ? ' done' : '') + '">' +
      '<div class="hub-check-icon">' + (checked ? svgIcon('check', 12, '#fff') : '') + '</div>' +
      '<span class="hub-check-label">' + escapeHtml(item.label) + '</span>' +
      (!checked ? '<span class="hub-check-todo">À faire</span>' : '') +
    '</div>';
  });
  // Add progress bar
  var pct = Math.round(done / CHECKLIST_ITEMS.length * 100);
  container.innerHTML =
    '<div class="hub-checklist-header"><span class="hub-checklist-title">Préparation</span>' +
    '<span class="hub-checklist-progress-text">' + done + ' / ' + CHECKLIST_ITEMS.length + '</span></div>' +
    '<div class="hub-checklist-bar"><div class="hub-checklist-bar-fill" style="width:' + pct + '%"></div></div>' +
    html;
}
```

---

## State of the Art

| Old Approach | Current Approach | Phase | Impact |
|--------------|-----------------|-------|--------|
| Inline styles on all wizard/hub elements | CSS class extraction to wizard.css / hub.css | Phase 8 | Maintainability, dark theme support, token consistency |
| Step navigation by show/hide with `style.display` set in JS | Same pattern preserved — acceptable for JS-driven visibility | — | No change needed |
| hub.js demo data hard-coded | Real API data from `/api/v1/meetings/{id}` (partially implemented) | Phase 8 | Connects real session data to hub UI |
| Wizard Step 5 (confirmation screen in wizard.htmx.html) | Redirect to hub.htmx.html after Créer + success toast | Phase 8 | Step 4 becomes final step; confirmation is the hub itself |
| Drag-and-drop: NICE-TO-HAVE comment in wizard.js | Required feature — HTML5 Drag API implementation | Phase 8 | Resolution reorder is now locked requirement |

**Deprecated/outdated:**
- `wizard.htmx.html` loading `meetings.css`: Change to `wizard.css` — meetings.css is for the sessions list page only.
- `hub.htmx.html` loading `pages.css`: Change to `hub.css` — pages.css is for dashboard/general pages.
- Step 5 (confirmation screen) in wizard.htmx.html: Replace with redirect-to-hub flow per CONTEXT.md decision.

---

## Open Questions

1. **Hub CSS ownership: operator.css contains hub classes**
   - What we know: `operator.css` defines `.hub-identity`, `.hub-stepper`, `.hub-step`, `.hub-action`, `.hub-checklist`, `.hub-accordion`, and many more hub-specific classes (lines 2825-3139+). These were added during Phase 6.
   - What's unclear: Does `operator.htmx.html` depend on any of these hub classes for its own layout? If so, moving them out of `operator.css` risks breaking the operator page.
   - Recommendation: Audit `operator.htmx.html` to confirm it does NOT use `.hub-identity`, `.hub-action`, etc. If confirmed, move hub CSS to `hub.css`. If operator uses them, keep in `operator.css` AND duplicate in `hub.css` (or create a shared `hub-components.css`). Most likely safe to move since operator has its own distinct layout classes.

2. **API /api/v1/meetings endpoint — exact payload shape**
   - What we know: The wizard sends `POST /api/v1/meetings`; hub loads from `GET /api/v1/meetings/{id}`.
   - What's unclear: Exact payload keys for members (lot assignment, vote weight field names), resolutions (majority type enum values), and session type values.
   - Recommendation: Inspect existing API route handlers to confirm field names before wiring `btnCreate`. Do not guess — use existing members.js and meetings.htmx.html API calls as reference.

3. **HUB-01 colorful status bar — horizontal or vertical?**
   - What we know: REQUIREMENTS.md says "colorful segments representing session stages." Wireframe tour text says "barre colorée." Current hub has a left-column vertical stepper. The operator page has a horizontal `op-track` bar for resolutions.
   - What's unclear: Whether HUB-01 means adding a horizontal bar (supplementing the vertical stepper) or replacing the vertical stepper entirely.
   - Recommendation: Add a horizontal colorful status bar ABOVE the main two-column layout, keeping the vertical stepper as navigation. The bar is visual-only (6 segments, one per stage, each with the stage's color). This satisfies "colorful segments" while preserving the UX navigation the vertical stepper provides.

---

## Validation Architecture

> `workflow.nyquist_validation` is absent from `.planning/config.json` — treated as enabled.

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Not detected — no test framework configuration found in project |
| Config file | None |
| Quick run command | Manual browser inspection (open wizard.htmx.html, hub.htmx.html) |
| Full suite command | Manual review of all 4 wizard steps + all 6 hub lifecycle stages |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| WIZ-01 | Stepper shows done/active/pending states correctly | manual | Open wizard, advance steps, verify stepper visual | N/A |
| WIZ-02 | Step 1 required fields block 'Suivant' when empty | manual | Leave title/date empty, click Suivant — should not advance | N/A |
| WIZ-03 | CSV file import populates members table | manual | Drag a CSV file onto drop zone, verify table rows appear | N/A |
| WIZ-04 | Resolution drag-and-drop reorders list | manual | Add 3 resolutions, drag second to first position | N/A |
| WIZ-05 | Clicking 'Créer' redirects to hub with toast | manual | Fill all steps, click Créer, verify redirect and toast | N/A |
| WIZ-05 | localStorage draft persists on page reload | manual | Fill Step 1, reload page, verify fields restored | N/A |
| HUB-01 | Horizontal status bar shows 6 colored segments | manual | Open hub.htmx.html, verify bar presence above layout | N/A |
| HUB-02 | Action card updates when lifecycle step changes | manual | Click different stepper steps, verify CTA changes | N/A |
| HUB-03 | 4 KPI cards display correct values | manual | Open hub with known session data, verify 4 KPI cards | N/A |
| HUB-04 | Checklist auto-checks 4 of 6 items for a complete session | manual | Load hub with complete session, verify auto-checked items | N/A |
| HUB-05 | Documents panel shows download links | manual | Open hub, verify documents listed with download buttons | N/A |
| Both | No inline styles remain on static elements | automated | `grep -n 'style="' public/wizard.htmx.html public/hub.htmx.html` should show 0 static results | ❌ command to run after changes |

### Sampling Rate
- **Per task commit:** `grep -n 'style="' public/wizard.htmx.html public/hub.htmx.html` (should find zero static inline styles)
- **Per wave merge:** Manual browser review of wizard flow + hub lifecycle in both light and dark themes
- **Phase gate:** All manual checks green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] No test framework — all validation is manual browser inspection
- [ ] `grep` command for inline style detection is the only automated check available

---

## Sources

### Primary (HIGH confidence)
- `ag_vote_wireframe.html` (local) — Canonical CSS class definitions for all wizard/hub specific classes; wireframe token names; HUB_STEPS data structure; hub layout structure
- `public/wizard.htmx.html` + `public/assets/js/pages/wizard.js` — Existing wizard implementation; what's built vs what's missing
- `public/hub.htmx.html` + `public/assets/js/pages/hub.js` — Existing hub implementation; HUB_STEPS array; renderAction, renderStepper logic
- `public/assets/css/operator.css` lines 2825–3139 — Hub CSS classes already defined in the wrong file
- `public/assets/css/design-system.css` — `.kpi-card`, `.chip`, `.stepper`, token definitions
- `.planning/phases/08-session-wizard-hub/08-CONTEXT.md` — All locked decisions and discretion areas

### Secondary (MEDIUM confidence)
- `public/assets/js/core/shell.js` — localStorage usage pattern (PIN_KEY, sidebar groups, theme)
- `public/assets/js/pages/members.js` — File drag-and-drop zone pattern; CSV FileReader usage
- `public/assets/css/meetings.css` — CSS file structure pattern to follow for wizard.css

### Tertiary (LOW confidence)
- None — all findings are verified from local source files

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all decisions based on direct codebase inspection
- Architecture: HIGH — wireframe provides exact CSS; existing JS patterns are clear
- Pitfalls: HIGH — token naming mismatch and CSS ownership issues verified by direct file inspection
- HUB-01 interpretation: MEDIUM — wireframe tour text confirms "barre colorée" but wireframe page shows vertical stepper as primary; horizontal bar recommendation is an inference

**Research date:** 2026-03-13
**Valid until:** 2026-04-13 (30 days; stable vanilla codebase, no fast-moving dependencies)
