---
phase: 81-fix-ux-interactivity-blocking-popups-broken-layouts-fragile-frontend-wiring
verified: 2026-04-03T06:35:05Z
status: gaps_found
score: 13/15 must-haves verified
gaps:
  - truth: "AgToast.show() is called with correct argument order (type, message, duration) everywhere"
    status: failed
    reason: "settings.js still has 7 reversed AgToast.show calls using (message, type) order at lines 339, 342, 345, 399, 635, 646, 649. Plan 03 SUMMARY claimed all 26 were fixed, but these were missed."
    artifacts:
      - path: "public/assets/js/pages/settings.js"
        issue: "7 calls use reversed order: AgToast.show(message, type) instead of AgToast.show(type, message). Lines 339 (ternary message), 342, 345, 399, 635, 646, 649."
    missing:
      - "Fix lines 339, 342, 345, 399, 635, 646, 649 in settings.js to use AgToast.show(type, message) order"
      - "Line 339: AgToast.show(isEdit ? 'Politique...' : 'Politique...', 'success') -> AgToast.show('success', isEdit ? '...' : '...')"
      - "Lines 342, 345, 399, 646, 649: Move string message to second arg, type ('error') to first arg"
      - "Line 635: AgToast.show('Envoi d\\'un email de test...', 'info') -> AgToast.show('info', 'Envoi d\\'un email de test...')"

  - truth: "Double-submit is prevented on all form submit buttons via Shared.btnLoading()"
    status: partial
    reason: "settings.js saveSection() function has no btnLoading call. Plan 03 SUMMARY claimed 'saveSection() now accepts triggerBtn param for proper btnLoading lifecycle' but the actual code at line 657 has no triggerBtn parameter, no btnLoading call, and silently catches errors in the Promise.all inner catch."
    artifacts:
      - path: "public/assets/js/pages/settings.js"
        issue: "saveSection() at line 657 has no triggerBtn parameter, no Shared.btnLoading usage. Also line 673: .catch(function() {}) silently swallows per-key save errors."
    missing:
      - "Add triggerBtn parameter to saveSection() signature"
      - "Add Shared.btnLoading(triggerBtn, true) before Promise.all"
      - "Add finally block: Shared.btnLoading(triggerBtn, false)"
      - "Pass btn from initSectionSave() click handler to saveSection(card, section, btn)"
      - "Fix silent inner catch at line 673 — replace with error accumulation or AgToast on failure"
---

# Phase 81: Fix UX Interactivity Verification Report

**Phase Goal:** Fix UX interactivity — blocking popups, broken layouts, fragile frontend wiring. Make all pages feel like one cohesive product with professional-grade interactivity.
**Verified:** 2026-04-03T06:35:05Z
**Status:** gaps_found
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Form sections with 4+ fields display in 2-column grid on screens wider than 768px | VERIFIED | `.form-grid` utility in design-system.css line 1950: `repeat(auto-fit, minmax(240px, 1fr))` with 768px breakpoint |
| 2 | Full-width pages have no max-width constraint on main content | VERIFIED | hub-body: `width: 100%`, analytics-content: `width: 100%`, meetings-main .page-content: `width: 100%`, audit-page: `width: 100%`, users-page: `width: 100%`, postsession-main: `width: 100%` |
| 3 | Narrow pages (settings, admin, vote) use max-width centered layout | VERIFIED | settings.css line 14: `max-width: var(--content-narrow, 720px)`, admin.css line 11: same, vote.css line 166: `max-width: 720px` |
| 4 | Every destructive action shows AgConfirm.ask() dialog before executing | VERIFIED | operator-tabs.js confirmModal wrapper at line 234 delegates to AgConfirm.ask(); members.js 3 calls, admin.js 1 call, settings.js 2 calls, users.js 2 calls, postsession.js 1 call, email-templates-editor.js 2 calls |
| 5 | No Shared.openModal() used for simple confirmation dialogs | VERIFIED | All simple confirmations migrated. Shared.openModal preserved only for form-containing modals: operator-tabs.js lines 1249, 2645, 2751; admin.js lines 445, 491, 534; members.js lines 323, 771; archives.js line 485; email-templates-editor.js line 205 |
| 6 | All existing modal behaviors (Escape, backdrop-click, focus trap) continue to work | VERIFIED | ag-modal.js line 176: backdrop click handler calls `this.close()` when `e.target === backdrop && closable`; Escape handler confirmed present |
| 7 | No window.confirm() or window.alert() in codebase | VERIFIED | `grep -r "window.confirm\|window.alert" public/assets/js/pages/` returns 0 results |
| 8 | Every API call has visible feedback: loading state, success toast, error toast | PARTIAL | members.js catch blocks use setNotif('error', ...) — all covered. BUT settings.js saveSection() silently swallows per-key errors in the inner .catch(function(){}) at line 673 |
| 9 | No fetch .catch() silently discards errors — all show AgToast.show('error', ...) | PARTIAL | settings.js line 673: `.catch(function() {})` silently swallows individual setting save errors inside saveSection() |
| 10 | AgToast.show() is called with correct argument order (type, message) everywhere | FAILED | settings.js has 7 reversed calls: lines 339 (ternary), 342, 345, 399, 635, 646, 649 use (message, type) instead of (type, message) |
| 11 | Double-submit is prevented on all form submit buttons via Shared.btnLoading() | PARTIAL | wizard.js: Shared.btnLoading on btnCreate confirmed. settings.js: btnLoading on delBtn (line 389) and btnTest (line 496) confirmed. But saveSection() at line 657 has no btnLoading — section save buttons can double-submit |
| 12 | Wizard steps display form fields in 2-column grid layout on desktop | VERIFIED | wizard.css line 178: `.wiz-step-body .step-content, .step-fields, .form-body { grid-template-columns: 1fr 1fr }` with full-width exceptions (textarea, .field-full, .data-table-wrapper) and 768px breakpoint. Wizard page max-width: 960px |
| 13 | SSE disconnect shows a persistent warning banner at top of page | VERIFIED | event-stream.js lines 64-78: showSseWarning()/hideSseWarning() functions; line 140: `source.onerror` calls showSseWarning(); line 92: `connected` listener calls hideSseWarning(); exported on window.EventStream |
| 14 | Navigating away from wizard or settings with unsaved changes shows a warning dialog | VERIFIED | wizard.js: beforeunload at line 994, Shell.beforeNavigate at line 1001, isWizardDirty() tracks both FormData snapshot and in-memory members/resolutions arrays. settings.js: beforeunload at line 748, Shell.beforeNavigate at line 756, scoped to templateEditor only |
| 15 | Modal, toast, wizard transitions use correct animation timing per UI-SPEC | VERIFIED | ag-modal.js: `duration-fast (150ms)` spring enter; ag-toast.js: `duration-normal (200ms)` ease-out slide-in, `duration-deliberate (300ms)` exit; ag-confirm.js: `duration-fast (150ms)` spring; wizard.css: `duration-deliberate (300ms)` emphasized. All 3 components have `prefers-reduced-motion` handling |

**Score:** 13/15 truths verified (2 failed/partial)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `public/assets/css/design-system.css` | `.form-grid` utility class with auto-fit | VERIFIED | Line 1950: `repeat(auto-fit, minmax(240px, 1fr))` + full-width exceptions + 768px breakpoint |
| `public/assets/css/design-system.css` | `.sse-warning-banner` class | VERIFIED | Line 4483: warning token colors, slideDown animation, prefers-reduced-motion override |
| `public/assets/css/app.css` | Per-page width strategy | VERIFIED | All pages have correct width strategy applied in per-page CSS files |
| `public/assets/js/pages/operator-tabs.js` | `confirmModal()` delegating to AgConfirm.ask() | VERIFIED | Line 234: `return AgConfirm.ask({...})` |
| `public/assets/js/pages/members.js` | AgConfirm.ask() for destructive actions | VERIFIED | 3 calls: delete group (line 378), delete member (line 725), generate seed (line 980) |
| `public/assets/js/pages/admin.js` | AgConfirm.ask() for toggle user | VERIFIED | 1 call at line 420 |
| `public/assets/js/components/ag-modal.js` | Backdrop-click close + animation timing | VERIFIED | Line 176: backdrop click handler; line 119: `duration-fast 150ms ease-spring`; prefers-reduced-motion at line 125 |
| `public/assets/js/pages/users.js` | Global setNotif used (local override removed) | VERIFIED | No `function setNotif` in users.js; 2 AgConfirm.ask() calls at lines 325, 350 |
| `public/assets/js/pages/operator-tabs.js` | Corrected AgToast.show argument order | VERIFIED | All AgToast.show calls start with type string ('success', 'error', etc.) |
| `public/assets/js/pages/settings.js` | Corrected AgToast.show argument order | FAILED | 7 calls still reversed: lines 339, 342, 345, 399, 635, 646, 649 |
| `public/assets/css/wizard.css` | 2-column grid layout for wizard step content | VERIFIED | Line 178: `grid-template-columns: 1fr 1fr` on `.wiz-step-body .step-content, .step-fields, .form-body` |
| `public/assets/js/core/event-stream.js` | SSE disconnect banner injection/removal | VERIFIED | showSseWarning()/hideSseWarning() at lines 64/76; integrated into onerror (line 140) and connected handler (line 92) |
| `public/assets/js/pages/settings.js` | beforeunload unsaved changes warning | VERIFIED | Line 748: beforeunload + Shell.beforeNavigate at line 756 with AgConfirm.ask for templateEditor |
| `public/assets/js/pages/wizard.js` | beforeunload unsaved changes warning | VERIFIED | Line 994: beforeunload + Shell.beforeNavigate at line 1001 with AgConfirm.ask |
| `public/assets/js/components/ag-toast.js` | Animation timing matching UI-SPEC | VERIFIED | Line 80: `duration-normal (200ms) ease-out` enter; line 83: `duration-deliberate (300ms)` exit; prefers-reduced-motion at line 85 |
| `public/assets/js/components/ag-confirm.js` | Animation timing matching UI-SPEC | VERIFIED | Line 73: `duration-fast (150ms) ease-spring`; prefers-reduced-motion at line 79 |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `operator-tabs.js` | `ag-confirm.js` | AgConfirm.ask() in confirmModal() | WIRED | confirmModal() at line 234 calls AgConfirm.ask() |
| `members.js` | `ag-confirm.js` | AgConfirm.ask() for destructive actions | WIRED | 3 confirmed calls |
| `event-stream.js` | `design-system.css` | `.sse-warning-banner` CSS class | WIRED | showSseWarning() creates element with class `sse-warning-banner` at line 68; CSS class exists in design-system.css |
| `wizard.js` | `wizard.css` | 2-column grid CSS applied to step content | WIRED | wizard.css targets `.wiz-step-body .step-content` etc.; wizard.js renders `.wiz-step` + `.step-content` structure |
| `settings.js` | `ag-toast.js` | AgToast.show(type, message) for all feedback | PARTIAL | 20/27 calls correct; 7 calls reversed (lines 339, 342, 345, 399, 635, 646, 649) |
| `users.js` | `utils.js` | Global setNotif() (no local shadow) | WIRED | No `function setNotif` in users.js; global setNotif in utils.js delegates to AgToast |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| D-01 | Plan 02 | ONE confirmation design pattern (AgConfirm.ask()) uniformly applied | SATISFIED | AgConfirm.ask() in operator-tabs, admin, settings, members, users, postsession, email-templates; zero window.confirm/alert |
| D-02 | Plan 02 | All modales close on backdrop click, Escape key, proper focus trap | SATISFIED | ag-modal.js line 176: backdrop click handler; Escape + focus trap confirmed present; no unjustified closable="false" found |
| D-03 | Plan 02 | Only destructive/irreversible actions get confirmation dialogs | SATISFIED | Destructive actions use variant:'danger', state changes variant:'warning'; non-destructive actions execute directly with toast |
| D-04 | Plan 04 | Wizard multi-step with horizontal layout, fluid transitions | SATISFIED | wizard.css 2-column grid at line 178; 960px max-width; 300ms emphasized transition |
| D-05 | Plan 01 | Form fields use 2-3 column CSS grid where space allows | SATISFIED | `.form-grid` utility (auto-fit minmax(240px, 1fr)) in design-system.css |
| D-06 | Plan 01 | Page width is context-dependent: operator/dashboard full-width, forms narrow | SATISFIED | Narrow pages: settings/admin/vote at max-width 720px; full-width pages: hub/analytics/meetings/members/audit/postsession/users/operator |
| D-07 | Plan 01 | ALL pages must exploit horizontal space | SATISFIED | All 8 full-width pages confirmed using width: 100% with no max-width on main content containers |
| D-08 | Plan 03 | Every fetch/API call has visible feedback (loading, success toast, error toast) | PARTIAL | Most covered. settings.js saveSection() inner .catch(function(){}) at line 673 silently swallows per-key save errors; no btnLoading on section save buttons |
| D-09 | Plan 03 | Standardized feedback patterns (Pattern A for create/delete, Pattern B for toggles) | SATISFIED | Pattern A implemented across wizard, members, users, settings delete/reset operations |
| D-10 | Plan 03 | Form validation, no double-submit via Shared.btnLoading() | PARTIAL | wizard.js btnCreate, settings.js delBtn/btnTest covered. settings.js saveSection() (section save buttons) missing btnLoading — can double-submit |
| D-11 | Plan 04 | SSE/real-time connections reconnect reliably and show connection status when disconnected | SATISFIED | event-stream.js showSseWarning/hideSseWarning centrally managed, auto-shows on onerror, auto-hides on connected event |
| D-12 | Plan 04 | Navigation/state changes must not lose data — warn on unsaved changes | SATISFIED | wizard.js and settings.js both have beforeunload + Shell.beforeNavigate intercept with AgConfirm.ask |
| D-13 | Plan 01 | CSS design tokens consolidated, @layer cascade enforced | SATISFIED | Token audit confirmed btn/badge/card/modal use design tokens; no ad-hoc hardcoded values found |
| D-14 | Plan 01 | Components use consistent variants — eliminate ad-hoc styling overrides | SATISFIED | Component audit in Plan 01 confirmed consistent token usage |
| D-15 | Plan 04 | Professional animation timing per UI-SPEC contracts, reduced motion supported | SATISFIED | ag-modal 150ms spring, ag-toast 200ms ease-out/300ms exit, ag-confirm 150ms spring, wizard 300ms emphasized; all have prefers-reduced-motion |

### Anti-Patterns Found

| File | Lines | Pattern | Severity | Impact |
|------|-------|---------|----------|--------|
| `public/assets/js/pages/settings.js` | 339, 342, 345, 399, 635, 646, 649 | `AgToast.show(message, type)` — reversed argument order | Blocker | Toast type shown as visible message text; French message text used as type parameter → broken toast display for these 7 interactions (quorum policy save/error, email test send/error) |
| `public/assets/js/pages/settings.js` | 673 | `.catch(function() {})` — silent error swallow inside saveSection() | Warning | Individual setting key save failures are invisible to the user; saveSection() then shows "Section enregistrée" success toast even if some keys failed |
| `public/assets/js/pages/settings.js` | 657 | `saveSection()` missing `Shared.btnLoading` | Warning | Section save buttons can be clicked multiple times — double-submit not prevented |
| `public/assets/js/pages/operator-realtime.js` | 84 | `typeof AgToast !== 'undefined' && AgToast.show` defensive guard | Info | Plan 03 removed these guards from operator-tabs.js and hub.js but not operator-realtime.js. Does not break behavior but inconsistent with standardized pattern |

### Human Verification Required

None — all failing items are verifiable programmatically.

### Gaps Summary

Two gaps block full goal achievement for requirement D-08/D-10:

**Gap 1 (Blocker): settings.js has 7 remaining reversed AgToast.show calls**

Plan 03 claimed to fix all 26 reversed AgToast calls in settings.js. The bulk of the quorum policy save/error path (lines 339, 342, 345), the quorum policy error catch (line 399), and the email test send (lines 635, 646, 649) were missed. These 7 calls display the French message as the toast "type" and the English type string as the visible message — the toasts show garbled text to users.

**Gap 2 (Warning): saveSection() missing btnLoading and has silent inner catch**

Plan 03 SUMMARY stated "saveSection() now accepts triggerBtn param for proper btnLoading lifecycle via Promise.all finally()" — this change does not exist in the actual code. The function at line 657 has no triggerBtn parameter, and line 673 silently swallows per-key save errors. Section save buttons in settings can be double-clicked. This is a D-10 violation (no double-submit prevention for section saves) and a D-08 violation (silent failure path).

Both gaps are localized to `public/assets/js/pages/settings.js`.

---

_Verified: 2026-04-03T06:35:05Z_
_Verifier: Claude (gsd-verifier)_
