---
phase: 10-live-session-views
plan: "02"
subsystem: voter-interface
tags: [css, javascript, design-tokens, presence-toggle, accessibility, aria]
requirements_completed: [VOTE-01, VOTE-02, VOTE-03]
dependency_graph:
  requires: [10-01]
  provides: [presence-toggle-js, tokenized-vote-css, aria-audit]
  affects: [public/vote.htmx.html, public/assets/css/vote.css, public/assets/js/pages/vote.js, public/public.htmx.html]
tech_stack:
  added: []
  patterns: [aria-pressed toggle, hidden attribute visibility, attendances_upsert API integration]
key_files:
  created: []
  modified:
    - public/vote.htmx.html
    - public/assets/css/vote.css
    - public/assets/js/pages/vote.js
    - public/public.htmx.html
decisions:
  - "Task 1 (HTML/CSS) was already committed as a93e5a6 before this plan execution — only JS wiring and ARIA remained"
  - "apiPost returns response.json() directly (not {body, status}), so result.ok is the correct check (not result.body.ok)"
  - "_isAbsent guard added to all setVoteButtonsEnabled enable-paths: refresh() line 793 and setBlocked() line 108"
  - "Presence toggle shown/hidden in member select change handler and in invitation-mode applyInvitationLock()"
  - "ARIA labels added to chart-container and decision-section on public.htmx.html — all other landmarks were already correct"
metrics:
  duration: "5min"
  completed_date: "2026-03-13"
  tasks_completed: 2
  files_modified: 4
---

# Phase 10 Plan 02: Voter View Restyle — Presence Toggle + ARIA Audit Summary

**One-liner:** Self-service presence toggle wired to attendances_upsert API with vote button disable guard, ARIA labels added to public display page chart and decision sections.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Inline styles cleanup + presence toggle HTML + vote.css tokenization | a93e5a6 | public/vote.htmx.html, public/assets/css/vote.css |
| 2 | Presence toggle JS logic + ARIA audit on both pages | 7a5f5eb | public/assets/js/pages/vote.js, public/public.htmx.html |

## What Was Built

### Task 1: HTML/CSS (a93e5a6)

The `vote.htmx.html` was cleaned up and presence toggle added:
- `app-footer` uses `hidden` attribute instead of `style="display:none"`
- Footer links use semantic CSS classes (`footer-logo`, `footer-logo-mark`, `flex-spacer`, `footer-link`) instead of inline styles
- Presence toggle button `#btnPresence` added in `.vote-footer` between `.member-info` and `.footer-status`, starts `hidden`
- Only 1 JS-managed inline style remains (`voteParticipationFill` width)

**CSS (`vote.css`):**
- `.presence-toggle` styles: design-token colors, `var(--color-success)` border/background when present, `var(--color-border)` when absent
- `.presence-toggle[aria-pressed="false"]` for absent state styling
- `.vote-absent-hint` italic text for disabled vote hint
- `.app-footer[hidden]` hidden support and footer utility classes

### Task 2: JS Wiring + ARIA Audit (7a5f5eb)

**`vote.js` additions:**
- `_isAbsent` state variable already present; `togglePresence()` and `updatePresenceUI()` functions already present
- Wired `btnPresence` click -> `togglePresence()` in the `wire()` setup function
- Member select change handler now shows/hides presence toggle: `presenceToggle.hidden = !selectedMemberId()`
- `applyInvitationLock()` shows presence toggle when invitation mode auto-selects a member
- `setVoteButtonsEnabled(!!memberId)` in `refresh()` updated to `setVoteButtonsEnabled(!!memberId && !_isAbsent)` to block voting when absent
- `setBlocked()` updated: `setVoteButtonsEnabled(!on && !!selectedMemberId() && !_isAbsent)` for consistent absent guard

**ARIA audit (`public.htmx.html`):**
- Added `aria-label="Résultats du vote"` to `.chart-container#chart_container`
- Added `aria-label="Décision et quorum"` to `.decision-section#decision_section`
- All other landmarks were already correct: `role="banner"` on header, `role="main"` on main, `aria-live="assertive"` on `#sr_alert`, `aria-label` on resolution tracker, `aria-label` on all interactive buttons

**ARIA audit (`vote.htmx.html`):**
- `role="dialog"`, `aria-modal="true"`, `aria-labelledby="confirmTitle"` already present on confirmation overlay
- `aria-hidden` toggled correctly in `vote-ui.js` (false when open, true when closed)
- Focus set to `btnConfirm` on overlay open; focus trap via keydown handler already implemented
- All form controls have associated `<label>` elements
- `role="status"` and `aria-live="polite"` already on `#voteHint`

## Verification Results

| Check | Result |
|-------|--------|
| `grep -c 'style=' vote.htmx.html` returns ≤ 1 | PASS (1) |
| `grep -c 'togglePresence\|_isAbsent\|btnPresence'` returns ≥ 3 | PASS (17) |
| `btnPresence` click handler wired in `wire()` | PASS |
| Presence toggle hidden on load, shown after member selection | PASS |
| `_isAbsent` guard in `setVoteButtonsEnabled` calls | PASS |
| `aria-label` on chart-container | PASS |
| `aria-label` on decision-section | PASS |
| Confirmation overlay focus management | PASS (pre-existing) |

## Deviations from Plan

### Pre-existing Task 1 completion

Task 1 (HTML/CSS changes: inline styles cleanup, presence toggle HTML, vote.css tokenization) was already committed as `a93e5a6` before this plan execution. This plan only needed to execute Task 2 (JS wiring + ARIA audit). This is not a deviation from the plan's outcome, just execution timing.

### apiPost response shape correction

The plan example checked `result?.body?.ok` but `Utils.apiPost` returns `response.json()` directly (not `{body, status}` wrapper). The existing code correctly uses `result && result.ok`. No change needed.

## Self-Check

- [x] `public/vote.htmx.html` — exists, has `#btnPresence` with `hidden` attribute, 1 inline style only
- [x] `public/assets/css/vote.css` — has `.presence-toggle`, `.vote-absent-hint`, `.app-footer[hidden]`
- [x] `public/assets/js/pages/vote.js` — has `togglePresence`, `updatePresenceUI`, `_isAbsent`, click handler wired, member change handler shows toggle
- [x] `public/public.htmx.html` — has `aria-label` on chart-container and decision-section
- [x] Commits a93e5a6 and 7a5f5eb exist in git log

## Self-Check: PASSED
