---
phase: 2
slug: mode-focus
status: locked
created: 2026-04-29
---

# Phase 2 — Mode Focus: Decisions Locked

## Phase Goal

L'operateur peut basculer vers une vue epuree a 5 zones qui masque les informations secondaires et conserve uniquement les controles essentiels pour conduire le scrutin.

## Locked Decisions

### D-1 — CSS scope: `.op-focus-mode` on `#viewExec` container

Researchers disagreed: phase-researcher chose `.op-focus-mode` on `#viewExec`, ui-researcher chose `body.focus-mode`.

**Locked:** `.op-focus-mode` on `#viewExec`.

**Why:**
- Coherent with Phase 1's `.op-checklist-panel--collapsed` modifier pattern (modifier on container, not body)
- Survives `setMode('setup')` -> `setMode('exec')` cycles
- Limits CSS scope to the operator exec view (no risk of affecting login/admin/voter)
- Matches existing operator.css convention (every rule namespaced `.op-*`)

### D-2 — Phase 1 checklist panel HIDDEN in focus mode

Researchers disagreed: phase-researcher kept it visible, ui-researcher hid it.

**Locked:** Hidden in focus mode.

**Why:**
- Focus mode goal = "5 zones EXACTLEMENT" — checklist panel has 4 rows (SSE, quorum, votes, online), incompatible with single-zone-quorum requirement
- Quorum data is the ONLY indicator from the panel needed in focus mode (per FOCUS-01); SSE/votes/online are monitoring noise during active vote
- A dedicated, larger quorum zone (zone 3) better serves "conduire le scrutin" — readable from far during seance
- Does NOT waste Phase 1 work: panel remains the default in normal exec mode, checklist remains the operator's full status board

### D-3 — Quorum zone (Zone 3) data source

**Locked:** Reuse `computeQuorumStats()` from `operator-exec.js` (already used by Phase 1 checklist).

The DOM is new (dedicated focus-mode quorum block), the data source is shared. A new function `refreshFocusQuorum()` reads stats, writes to focus-mode-only DOM. Hidden when not in focus mode (display:none via `.op-focus-mode .X` rule).

### D-4 — Toggle button placement and copy

**Locked:**
- DOM: inside `.op-exec-header-right`, BEFORE `#opBtnCloseSession`
- ID: `opBtnFocusMode`
- ARIA: `aria-pressed="false"` initially
- Icon: `icon-maximize` (focus on) / `icon-minimize` (return to full)
- Label: "Focus" (default) / "Vue complete" (when active)
- Tooltip: "Mode focus: 5 zones essentielles" / "Revenir a la vue complete"

### D-5 — Persistence

**Locked:** `sessionStorage.opFocusMode` (boolean string). Restored in `setMode('exec')` AFTER the existing `opChecklistCollapsed` block. Cleared on `setMode('setup')` (focus mode is exec-only).

### D-6 — Action bar behavior in focus mode

**Locked:** `.op-action-bar` becomes `position: sticky; bottom: 0;` in focus mode (FOCUS-02 — visible without scroll). No JavaScript needed for this — pure CSS.

### D-7 — Hidden elements list (final)

In focus mode, hide:
- `.op-sidebar` (left agenda)
- `.op-exec-status-bar` (status bar)
- `.op-kpi-strip` (7 KPIs)
- `.op-tags` (correspondance, procurations, quorum tags — duplicated by zone 3)
- `.op-resolution-progress` (segmented progress bar)
- `.op-guidance` / `.exec-close-banner` (guidance bar)
- `.op-tabs` (sub-tabs inside resolution card)
- `.op-checklist-panel` (Phase 1 panel — replaced by dedicated zones)
- `#opBtnRoomDisplay` (Projection — secondary)

Show in focus mode:
- Zone 1: motion title (`.op-resolution-title`)
- Zone 2: vote result (`.op-vote-card`)
- Zone 3: quorum status (NEW dedicated block)
- Zone 4: chronometer (`.op-live-chrono`, enlarged)
- Zone 5: action bar (`.op-action-bar`)
- `#opBtnFocusMode` toggle in header

## Plan Structure

Two plans, two waves (matches Phase 1 cadence):

- **02-01-PLAN.md** — HTML + CSS (toggle button, focus-mode rules, dedicated quorum block, sticky action bar)
- **02-02-PLAN.md** — JS wiring (toggle handler in operator-tabs.js, refreshFocusQuorum in operator-exec.js, sessionStorage persistence)
