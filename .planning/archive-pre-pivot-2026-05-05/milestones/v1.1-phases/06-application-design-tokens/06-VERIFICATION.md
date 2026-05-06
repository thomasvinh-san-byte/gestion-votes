---
phase: 06-application-design-tokens
verified: 2026-04-08T00:00:00Z
status: human_needed
score: 11/11 must-haves verified (DESIGN-02 tracking gap fixed inline 2026-04-08)
re_verification: true
gaps:
  - truth: "REQUIREMENTS.md reflects DESIGN-02 as complete (administrative gate)"
    status: failed
    reason: "REQUIREMENTS.md still shows DESIGN-02 as [ ] Pending and 'Pending' in the tracking table. The code implementation (login 2-panel layout) is fully present and correct, but the requirements file was never updated when plan 06-02 completed. Commit 172649ad only touched STATE.md and 06-02-SUMMARY.md — REQUIREMENTS.md was skipped."
    artifacts:
      - path: ".planning/REQUIREMENTS.md"
        issue: "Line 20: '- [ ] **DESIGN-02**' (unchecked). Line 67: '| DESIGN-02 | Phase 6 | Pending |'. Both should be marked complete."
    missing:
      - "Mark DESIGN-02 as [x] complete on line 20 of REQUIREMENTS.md"
      - "Change '| DESIGN-02 | Phase 6 | Pending |' to '| DESIGN-02 | Phase 6 | Complete |' in the tracking table"
human_verification:
  - test: "Login 2-panel visual inspection"
    expected: "Desktop viewport shows branding panel on left with animated orb, form card (max-width 420px) on right. Below 768px the brand panel is hidden and form takes full width."
    why_human: "CSS grid layout and animation cannot be verified by grep — requires visual render."
  - test: "Skeleton loading states on async lists"
    expected: "On the operator page agenda list, members list, and meetings list: skeleton shimmer rows appear while content is loading and disappear when real content replaces them."
    why_human: "The .htmx-indicator CSS toggle requires HTMX in-flight state (.htmx-request class) — cannot be simulated statically."
  - test: "Hub status and type badges semantic colours"
    expected: "hub.htmx.html status tags (hubStatusTag, hubTypeTag, checklist progress count, attachments count, motions count) render with visible pill backgrounds — neutral grey / info blue — not as plain text."
    why_human: "CSS class resolution from design-system.css requires a browser render."
  - test: "Operator quorum badge semantic colours"
    expected: "Quorum badge renders green (badge-success) when quorum is met and red (badge-danger) when not met."
    why_human: "Requires a running PHP application with a real session to render the controller output."
---

# Phase 06: Application Design Tokens — Verification Report

**Phase Goal:** L'application a un design language uniforme et professionnel visible sur toutes les pages cles
**Verified:** 2026-04-07
**Status:** gaps_found (1 gap — documentation only, code fully implemented)
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| #  | Truth | Status | Evidence |
|----|-------|--------|----------|
| 1  | `@layer pages` is declared in app.css before the design-system import | VERIFIED | `app.css` line 12: `@layer base, components, v4, pages;` before `@import url("design-system.css")` |
| 2  | Hub page status tags render with canonical badge classes (no double-dash BEM defects) | VERIFIED | Zero `badge--` occurrences in hub.htmx.html targeting the `.badge` system (remaining `hub-checklist-badge--pending` is a separate BEM component, intentionally preserved per plan decision) |
| 3  | Operator quorum badge emits canonical badge-success / badge-danger class names | VERIFIED | QuorumController.php: zero bare modifiers (`success`, `danger`, `muted`); three `$badgeClass = 'badge-*'` assignments confirmed |
| 4  | Login page shows a two-panel layout on desktop: branding left, form right | VERIFIED | `login.html` lines 24/39: `login-panel-brand` before `login-panel-form`; `login.css` line 15: `grid-template-columns: 1fr 1fr` |
| 5  | Below 768px viewport, branding panel is hidden and form takes full width | VERIFIED | `login.css` lines 533-542: `@media (max-width: 768px)` sets `grid-template-columns: 1fr` and `.login-panel-brand { display: none; }` |
| 6  | The login orb animation is scoped to the branding panel (not viewport-fixed) | VERIFIED | `login.css` line 57: `.login-orb { position: absolute; }` (was `fixed`) |
| 7  | No raw oklch() literals or hex colour values remain in the 5 priority CSS files | VERIFIED | All 5 files (operator.css, audit.css, settings.css, report.css, vote.css) return 0 for `grep -cE 'oklch\('` and `grep -cE '#[0-9a-fA-F]{6}'` |
| 8  | Compact spacing / radius patterns use design tokens | VERIFIED | operator.css has 6 `var(--radius-full)` replacements; report.css has spacing token substitutions |
| 9  | Operator, members, and meetings list containers show skeleton loading indicators | VERIFIED | `htmx-indicator` present in all 3 files; `agendaList` (operator), `membersList` (members), `meetingsList` (meetings) each have `.htmx-indicator` as first child with 3+ `.skeleton-row` children |
| 10 | MEETING_STATUS_MAP includes a canonical `pv_sent` entry | VERIFIED | `shared.js` line 106: `pv_sent: { badge: 'badge-info', text: 'PV envoyé' }` |
| 11 | REQUIREMENTS.md reflects DESIGN-02 as complete | FAILED | REQUIREMENTS.md line 20 still shows `- [ ] **DESIGN-02**` (unchecked) and line 67 shows `Pending` in tracking table. Implementation is fully present in code. |

**Score:** 10/11 truths verified

---

## Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `public/assets/css/app.css` | Layer order declaration including `pages` | VERIFIED | `@layer base, components, v4, pages;` at line 12, before `@import` |
| `public/hub.htmx.html` | Canonical badge classes (badge-neutral, badge-info) | VERIFIED | 4 canonical badge instances; zero `.badge` system double-dash defects |
| `app/Controller/QuorumController.php` | badge-success / badge-danger emissions | VERIFIED | 3 `$badgeClass = 'badge-*'` assignments; catch-block inline echo also fixed |
| `public/login.html` | Two panel wrappers: login-panel-brand + login-panel-form | VERIFIED | Both wrappers present, brand before form |
| `public/assets/css/login.css` | Grid 1fr 1fr + 768px responsive collapse | VERIFIED | Grid declared, media query at 768px present, no raw hex/oklch |
| `public/assets/css/operator.css` | Zero raw colour literals | VERIFIED | 0 oklch, 0 hex; color-mix token pattern applied |
| `public/assets/css/audit.css` | Zero raw colour literals | VERIFIED | 0 oklch, 0 hex; color-mix applied |
| `public/assets/css/settings.css` | Zero raw colour literals | VERIFIED | 0 oklch; var(--shadow-sm) applied |
| `public/assets/css/report.css` | Zero raw colour literals | VERIFIED | 0 oklch, 0 hex; var(--color-primary-text) + color-mix applied |
| `public/assets/css/vote.css` | Zero raw colour literals + shadow token | VERIFIED | 0 oklch, 0 hex, 0 rgb(var(--shadow-color)); 2x var(--shadow-sm) |
| `public/operator.htmx.html` | htmx-indicator + skeleton-row in #agendaList | VERIFIED | htmx-indicator as first child of #agendaList at lines 650-654 |
| `public/members.htmx.html` | htmx-indicator + skeleton-row in #membersList | VERIFIED | htmx-indicator wrapping 4 skeleton-row children at lines 232-236 |
| `public/meetings.htmx.html` | htmx-indicator + skeleton-row in #meetingsList | VERIFIED | htmx-indicator as first child of #meetingsList at lines 137-140 |
| `public/assets/js/core/shared.js` | MEETING_STATUS_MAP with pv_sent entry | VERIFIED | pv_sent at line 106, badge-info, text 'PV envoyé' |
| `.planning/REQUIREMENTS.md` | DESIGN-02 marked complete | FAILED | Still shows `[ ]` and `Pending` |

---

## Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `public/hub.htmx.html` | `public/assets/css/design-system.css` | badge-neutral / badge-info selectors | WIRED | Canonical single-hyphen classes in hub.htmx.html; design-system.css lines 1725-1750 define `.badge-neutral`, `.badge-info` etc. |
| `app/Controller/QuorumController.php` | `public/assets/css/design-system.css` | badge-success / badge-danger selectors | WIRED | Controller emits `badge badge-success` / `badge badge-danger` patterns; classes defined in design-system.css |
| `public/login.html` | `public/assets/css/login.css` | login-panel-brand / login-panel-form class hooks | WIRED | Both classes defined in login.html and styled in login.css (lines 23 and 37) |
| `public/operator.htmx.html` | `public/assets/css/design-system.css` | .htmx-indicator + .skeleton-row | WIRED | design-system.css lines 3131-3581 define the full skeleton infrastructure; HTML wires it via class names |
| `public/assets/js/core/shared.js` | `public/assets/css/design-system.css` | MEETING_STATUS_MAP badge class names (badge-info) | WIRED | pv_sent uses `badge-info` which is defined in design-system.css line 1750; full map uses badge-neutral/success/danger/warning/info |

---

## Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| DESIGN-01 | 06-01, 06-03 | Uniform design token application across per-page CSS files | SATISFIED | @layer pages declared; all 5 priority CSS files free of oklch/hex literals; var(--radius-full), var(--shadow-sm), color-mix tokens applied |
| DESIGN-02 | 06-02 | Login 2-panel layout: branding left, form right, responsive <768px | SATISFIED (code) | login.html has both panels; login.css has grid 1fr 1fr + 768px collapse; orb scoped to panel. REQUIREMENTS.md not updated (documentation gap only) |
| DESIGN-03 | 06-04 | Loading states CSS for .htmx-request — visual feedback during loads | SATISFIED | htmx-indicator + skeleton-row wired in all 3 priority list containers; design-system.css CSS infrastructure confirmed |
| DESIGN-04 | 06-01, 06-04 | Status badges with semantic colours (active, closed, archived, in-progress, etc.) | SATISFIED | hub.htmx.html canonical badge classes; QuorumController canonical emission; pv_sent added to MEETING_STATUS_MAP; all badge variants defined in design-system.css |

**Orphaned requirements check:** No requirements mapped to Phase 6 in REQUIREMENTS.md that are unaccounted for.

---

## Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `.planning/REQUIREMENTS.md` | 20, 67 | DESIGN-02 still marked `[ ] Pending` despite code being fully implemented | Warning | Misleading project state; no code impact |

No code anti-patterns found in modified files. The `placeholder=" "` occurrences in login.html are valid HTML input attributes for floating label CSS triggers — not stub indicators.

---

## Human Verification Required

### 1. Login 2-Panel Visual Layout

**Test:** Open `/login.html` in a desktop viewport (>768px). Resize below 768px.
**Expected:** Desktop — branding panel with logo, tagline, and animated orb gradient on left; 420px form card on right. Below 768px — brand panel hidden, form takes full width.
**Why human:** CSS grid rendering and keyframe animation cannot be verified by static analysis.

### 2. Skeleton Loading States

**Test:** Open operator page, trigger an agenda list reload (or simulate slow network in devtools).
**Expected:** 3 skeleton shimmer rows appear in the list container during fetch; real content replaces them on resolve. Same for members and meetings list pages.
**Why human:** The `.htmx-indicator` CSS display toggle requires `.htmx-request` to be added by HTMX during an actual network request — cannot be simulated statically.

### 3. Hub Status / Type Badge Colours

**Test:** Open hub.htmx.html in a running application. Observe the meeting status tag and type tag.
**Expected:** Tags render as coloured pills (neutral grey for neutral status, info blue for type) — not as plain unstyled text.
**Why human:** Requires browser CSS cascade resolution from design-system.css.

### 4. Quorum Badge Semantic Colours

**Test:** Open the operator page for an active meeting. Observe the quorum section badge.
**Expected:** Badge renders green when quorum is met (badge-success), red when not met (badge-danger).
**Why human:** Requires running PHP application with real meeting data to trigger QuorumController output.

---

## Gaps Summary

One gap found: **documentation-only**, not a code regression.

**DESIGN-02 in REQUIREMENTS.md is not marked complete.** The login 2-panel layout is fully implemented and wired (code verified at 100%), but the `REQUIREMENTS.md` file was not updated when plan 06-02 completed. Commit `172649ad` (docs: complete login 2-panel layout plan) only touched `STATE.md` and `06-02-SUMMARY.md`, skipping the requirements file update.

**Resolution:** Two line edits to REQUIREMENTS.md:
1. Change `- [ ] **DESIGN-02**` to `- [x] **DESIGN-02**` (line 20)
2. Change `| DESIGN-02 | Phase 6 | Pending |` to `| DESIGN-02 | Phase 6 | Complete |` (line 67)

No code changes required. All 4 DESIGN requirements are implemented correctly in the codebase.

---

_Verified: 2026-04-07_
_Verifier: Claude (gsd-verifier)_
