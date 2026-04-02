# Phase 47: Hub Rebuild - Research

**Researched:** 2026-03-22
**Domain:** Vanilla JS page rebuild — session lifecycle hub, quorum bar, stepper checklist, API wiring
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Full-width card-based layout — session header card + two-column below (checklist + details), horizontal KPIs
- Visual progress bar quorum — colored bar showing attendance vs threshold, with numbers inline
- Hero card session header — session title, type, date, status badge prominently displayed
- 1200px max-width centered — appropriate for a detail page
- Vertical stepper checklist — each prerequisite (convocation sent, quorum reached, agenda locked) shows checkmark/cross/circle with description
- Primary CTA at top + contextual actions in checklist — main "Ouvrir la séance" button prominent, smaller actions next to each checklist item
- Inline blocked reasons — clear text below each blocked item explaining why, with ag-tooltip for details
- "Aller à la console" CTA button — navigates to operator with meeting_id
- Compact attendance card with quorum bar — present/total count with visual progress toward quorum threshold
- Motions summary: count badge + list preview — N motions with first 3 titles visible, "Voir tout" link to operator
- Full dark mode parity via tokens — consistent with all rebuilt pages
- Responsive: stack to single column at 768px

### Claude's Discretion
- Exact HTML structure and element hierarchy
- CSS class naming
- Whether to refactor hub.js or just update selectors
- Exact quorum bar colors and thresholds
- Checklist item order and grouping
- Responsive breakpoint details

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| REB-05 | Hub — complete HTML+CSS+JS rewrite, session lifecycle wired, quorum bar functional, checklist with real data | All three files are confirmed present and understood; rebuild pattern from Phase 46 (operator console) is directly applicable |
| WIRE-01 | Every rebuilt page has verified API connections — no dead endpoints, no mock data, no broken HTMX targets | API audit below identifies one dead endpoint (`/api/v1/meetings/{id}/convocations`) that must be fixed; all other endpoints are verified |
</phase_requirements>

---

## Summary

Phase 47 rebuilds three files: `hub.htmx.html` (299 lines), `hub.css` (1311 lines), and `hub.js` (658 lines). The current implementation already has solid JS logic — lifecycle steps, quorum bar calculation, checklist rendering, and API wiring — but the HTML/CSS layout is the old stepper+action-card pattern from before the v4.3 ground-up rebuild mandate. The new layout follows the same card-based approach established in the operator console rebuild (Phase 46): hero header card, two-column body, token-only dark mode.

The most critical wiring issue: `hub.js` calls `/api/v1/meetings/{id}/convocations` (POST) for the "send convocations" action, but no such route exists in `routes.php`. The actual working endpoint used by the operator page is `/api/v1/invitations_send_bulk` (POST with `meeting_id` and `only_unsent`). This dead endpoint must be fixed during this phase to satisfy WIRE-01.

The checklist items the user wants (convocation sent, quorum reached, agenda locked) map to real data from two backend endpoints: `wizard_status` (for quorum and session state) and `invitations_stats` (for convocation sent status). The `meeting_workflow_check` endpoint (GET `/api/v1/meeting_workflow_check?meeting_id=...`) returns `transitions` with `can_proceed`, `issues`, and `warnings` per next-state — this is exactly what drives the blocked-reason display.

**Primary recommendation:** Rewrite HTML and CSS fully, update hub.js selectors to match new HTML (do not rewrite logic), and fix the dead convocations endpoint to use `invitations_send_bulk`.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Vanilla JS (IIFE) | ES5+ | Hub page controller | Project identity — no framework |
| `window.api()` | project util | XHR wrapper for all API calls | Standard across all pages |
| `ag-quorum-bar` | custom web component | Visual quorum progress bar | Already loaded on page, `<ag-quorum-bar current="" required="" total="">` |
| `ag-popover` | custom web component | Tooltip/popover for blocked reasons | Already used in hub, available on page |
| `ag-toast` | custom web component | Success/error feedback | `window.AgToast.show(msg, type)` |
| `ag-confirm` | custom web component | Confirmation dialogs | `window.AgConfirm.ask({...}).then(ok => ...)` |
| `Shared.showToast()` | core/shared.js | Alternative toast path | Available but AgToast preferred |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `window.api(url, body, method)` | project | All API calls | Every fetch to `/api/v1/...` |
| `meeting-context.js` | project | Meeting ID propagation | Already loaded, provides context service |
| `theme-init.js` | project | Dark mode token init | Loaded in `<head>` before CSS |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Inline SVG icons | `<use href="/assets/icons.svg#...">` | SVG sprite would be cleaner, but hub.js builds SVG inline from SVG_ICONS dict — keep as-is for JS consistency |
| `display:none` show/hide | `hidden` attribute | Use `hidden` attr pattern (consistent with other rebuilt pages) rather than `style.display` |

**No new package installs required.** All dependencies already on page.

---

## Architecture Patterns

### Recommended Project Structure
```
public/
├── hub.htmx.html          # Full rewrite — new card layout
├── assets/css/hub.css     # Full rewrite — new card/stepper/quorum CSS
└── assets/js/pages/hub.js # Selective update — selectors only, logic preserved
```

### Pattern 1: Hero Header Card (from Context decisions)
**What:** Full-width card at top with session title, type badge, status badge, date, place, participant count
**When to use:** Every rebuilt page — establishes session identity at a glance
**Example structure:**
```html
<div class="hub-hero" id="hubHero">
  <div class="hub-hero-icon"><!-- clipboard SVG --></div>
  <div class="hub-hero-body">
    <div class="hub-hero-title-row">
      <h2 class="hub-hero-title" id="hubTitle">Chargement…</h2>
      <div class="hub-hero-badges">
        <span class="badge badge--neutral" id="hubTypeTag">-</span>
        <span class="badge badge--info" id="hubStatusTag">En préparation</span>
      </div>
    </div>
    <div class="hub-hero-meta" id="hubMeta">
      <!-- date · place · participants — populated by JS -->
    </div>
  </div>
  <div class="hub-hero-actions">
    <a class="btn btn-primary" id="hubMainCta" href="#">Ouvrir la séance</a>
    <a class="btn btn-secondary" id="hubOperatorBtn" href="#">Aller à la console</a>
  </div>
</div>
```

### Pattern 2: Two-Column Body (checklist left + quorum/motions right)
**What:** CSS Grid `220px 1fr` (matching operator layout proportions), sticky left column
**When to use:** The main body below the hero — checklist on left, quorum bar + motions on right
```css
.hub-body {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: var(--space-card);
  align-items: start;
  max-width: 1200px;
  margin: 0 auto;
}
```

### Pattern 3: Vertical Stepper Checklist (new design)
**What:** Each checklist item is a card row: icon (check/cross/circle) + label + status badge + optional blocked-reason text
**When to use:** Left column — shows convocation sent, quorum reached, agenda locked
**Key difference from old design:** Items are now the 3 lifecycle prerequisites, not the 6 prep tasks
```html
<div class="hub-checklist-item hub-checklist-item--done" data-check="convocation">
  <div class="hub-checklist-dot hub-checklist-dot--done"><!-- check icon --></div>
  <div class="hub-checklist-content">
    <span class="hub-checklist-label">Convocations envoyées</span>
    <span class="hub-checklist-badge hub-checklist-badge--done">Fait</span>
  </div>
</div>
<div class="hub-checklist-item hub-checklist-item--blocked" data-check="quorum">
  <div class="hub-checklist-dot hub-checklist-dot--pending"><!-- circle icon --></div>
  <div class="hub-checklist-content">
    <span class="hub-checklist-label">Quorum atteint</span>
    <span class="hub-checklist-badge hub-checklist-badge--blocked">En attente</span>
    <span class="hub-checklist-reason">12/24 présents — seuil requis : 13</span>
  </div>
</div>
```

### Pattern 4: Quorum Bar (compact card, right column)
**What:** Existing `<ag-quorum-bar>` web component inside a card with numeric display
**When to use:** Right column top — the visual centerpiece
**API data:** `present_count` and `members_count` from `wizard_status`, `quorum_met` boolean for color state

### Pattern 5: JS-First Reading (critical project pattern)
**What:** Read existing JS before touching HTML to understand ALL DOM selectors
**When to use:** Always — before writing a single line of HTML
**IDs to preserve:** `hubTitle`, `hubDate`, `hubPlace`, `hubParticipants`, `hubTypeTag`, `hubStatusTag`, `hubQuorumBar`, `hubQuorumPct`, `hubQuorumSection`, `hubMotionsList`, `hubMotionsSection`, `hubChecklist`, `hubMainBtn` — preserve or consciously update each in hub.js

### Anti-Patterns to Avoid
- **`display:none` in HTML:** Use `hidden` attribute, not inline style — JS removes `hidden` attr to reveal
- **`style.display = ''` in JS:** Replace with `el.removeAttribute('hidden')` / `el.setAttribute('hidden', '')`
- **Inline style blocks for colors that are not JS-driven:** All colors via tokens, never hard-coded hex except SSE indicator (which is explicitly intentional per Phase 46 decision)
- **Old 6-step stepper model:** The new hub has 3 checklist items (convocation, quorum, agenda), not 6 steps
- **`/api/v1/meetings/{id}/convocations` (POST):** This route does NOT exist — use `/api/v1/invitations_send_bulk` instead

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Quorum progress visualization | Custom CSS bar | `<ag-quorum-bar>` web component | Already on page, handles all thresholds and accessibility |
| Confirmation dialogs | Custom modal | `window.AgConfirm.ask({...})` | Consistent UX, already imported |
| Toast notifications | Custom banner | `window.AgToast.show(msg, type)` | Consistent, already imported |
| Tooltip for blocked reasons | Custom dropdown | `<ag-popover trigger="hover">` | Already on page |
| API fetch | `fetch()` directly | `window.api(url, body, method)` | Handles auth, error normalization, JSON |
| Status transition check | Duplicate logic | `GET /api/v1/meeting_workflow_check?meeting_id=...` | Returns `can_proceed` + `issues` per next-state |

**Key insight:** The project has custom web components (`ag-quorum-bar`, `ag-popover`, `ag-toast`, `ag-confirm`) that handle the exact UX needed. Use them.

---

## API Audit (WIRE-01)

### Working endpoints hub.js uses:
| Endpoint | Method | Purpose | Status |
|----------|--------|---------|--------|
| `/api/v1/wizard_status?meeting_id=...` | GET | Load session data (members_count, present_count, motions_total, quorum_met, meeting_status) | VERIFIED — route 139 in routes.php |
| `/api/v1/meeting_workflow_check?meeting_id=...` | GET | Get transition readiness and blocked reasons per next-state | VERIFIED — route 249 |
| `/api/v1/meeting_transition` | POST `{meeting_id, to_status}` | Trigger lifecycle transition (draft→scheduled→frozen→live) | VERIFIED — route 248 |
| `/api/v1/resolution_documents?motion_id=...` | GET | Doc badges per motion | VERIFIED (existing) |
| `/api/v1/invitations_stats?meeting_id=...` | GET | Convocation sent/pending counts | VERIFIED — route 207 |

### Dead endpoint (MUST FIX):
| Old Call | Problem | Fix |
|----------|---------|-----|
| `POST /api/v1/meetings/{id}/convocations` | Route does NOT exist in routes.php | Replace with `POST /api/v1/invitations_send_bulk` with body `{meeting_id, only_unsent: true}` |

### wizard_status response shape (confirmed from DashboardController.php line 173):
```json
{
  "ok": true,
  "data": {
    "meeting_id": "uuid",
    "meeting_title": "string",
    "meeting_status": "draft|scheduled|frozen|live|paused|closed|validated|archived",
    "current_motion_id": "uuid|null",
    "members_count": 42,
    "present_count": 18,
    "motions_total": 5,
    "motions_closed": 3,
    "has_president": true,
    "quorum_met": false,
    "policies_assigned": true
  }
}
```

### meeting_workflow_check response (no to_status param):
```json
{
  "current_status": "scheduled",
  "transitions": {
    "frozen": { "can_proceed": false, "issues": [{"code": "no_attendance", "msg": "..."}], "warnings": [] },
    "draft":  { "can_proceed": true,  "issues": [], "warnings": [] }
  }
}
```

### invitations_stats response (InvitationsController line 155):
```json
{
  "stats": { "pending": 5, "sent": 37, ... },
  ...
}
```

**Checklist item mapping:**
- `convocation sent` = `invitations_stats.stats.sent > 0` (or `stats.pending === 0`)
- `quorum reached` = `wizard_status.data.quorum_met === true`
- `agenda locked` = `meeting_status === 'frozen' || meeting_status === 'live'`

---

## Common Pitfalls

### Pitfall 1: Stale DOM Selector Mismatch
**What goes wrong:** New HTML has different IDs than what hub.js targets — quorum bar doesn't update, checklist stays as skeleton
**Why it happens:** HTML is rewritten without auditing every `getElementById` in hub.js
**How to avoid:** Run a full grep of all `getElementById`/`querySelector` calls in hub.js BEFORE writing new HTML, map each to the new structure
**Warning signs:** Console errors like `Cannot set properties of null` on page load

### Pitfall 2: Dead Convocations Endpoint
**What goes wrong:** "Envoyer les convocations" button silently fails or shows 404 error
**Why it happens:** `hub.js` line 373 calls `/api/v1/meetings/{id}/convocations` which is not in routes.php
**How to avoid:** Replace with `POST /api/v1/invitations_send_bulk` + body `{meeting_id: sessionId, only_unsent: true}`
**Warning signs:** Network tab shows 404 on button click

### Pitfall 3: `display:none` vs `hidden` Inconsistency
**What goes wrong:** Quorum section and motions section still use `style="display:none"` in HTML — JS sets `section.style.display = ''` to show them
**Why it happens:** Old pattern copied forward
**How to avoid:** New HTML should use `hidden` attribute on sections that start hidden; update JS to use `removeAttribute('hidden')` / `setAttribute('hidden', '')`

### Pitfall 4: Missing `meeting_id` in Operator Nav
**What goes wrong:** "Aller à la console" button navigates to operator without meeting_id — operator shows empty state
**Why it happens:** Button href is static `/operator.htmx.html`
**How to avoid:** JS must append `?meeting_id=...` to the operator link after loading session data (same pattern already in hub.js lines 598-605 for HUB_STEPS)

### Pitfall 5: wizard_status is Not the Checklist Source of Truth
**What goes wrong:** Checklist shows wrong "convocations sent" state because wizard_status doesn't return invitation counts
**Why it happens:** `wizard_status` returns `quorum_met` and `meeting_status` but NOT convocation sent status
**How to avoid:** Make a second API call to `invitations_stats?meeting_id=...` for convocation data, OR check if `meeting_status` is past `draft` as a proxy

### Pitfall 6: Old 6-Step Stepper Logic Leftover
**What goes wrong:** `HUB_STEPS` array and `renderStatusBar()` / `renderStepper()` with 6 steps remain but new HTML expects 3-item checklist
**Why it happens:** JS is partially updated, old status bar rendering code still runs
**How to avoid:** Remove or disable `HUB_STEPS`/`renderStatusBar`/`renderStepper` if not keeping the 6-step progress bar; new design focuses on 3-prerequisite checklist + primary CTA

---

## Code Examples

### Calling workflow check for blocked reasons
```javascript
// Source: MeetingWorkflowController.php workflowCheck() — routes.php line 249
async function loadWorkflowReadiness(meetingId) {
  var res = await window.api('/api/v1/meeting_workflow_check?meeting_id=' + encodeURIComponent(meetingId));
  if (res && res.body && res.body.ok) {
    return res.body.data; // { current_status, transitions: { frozen: {can_proceed, issues, warnings}, ... } }
  }
  return null;
}
```

### Sending convocations (CORRECT endpoint)
```javascript
// Use invitations_send_bulk, NOT /api/v1/meetings/{id}/convocations
window.api('/api/v1/invitations_send_bulk', {
  meeting_id: sessionId,
  only_unsent: true
}, 'POST').then(function(res) {
  if (res && res.body && res.body.ok) {
    var sent = res.body.data && res.body.data.sent || 0;
    window.AgToast.show('Convocations envoyées (' + sent + ')', 'success');
  }
});
```

### Triggering a lifecycle transition
```javascript
// Source: MeetingWorkflowController.php transition() — routes.php line 248
window.api('/api/v1/meeting_transition', {
  meeting_id: meetingId,
  to_status: 'frozen'
}, 'POST').then(function(res) {
  if (res && res.body && res.body.ok) {
    // reload hub data
  } else if (res && res.body && res.body.error === 'workflow_issues') {
    var issues = res.body.data && res.body.data.issues || [];
    // display issues inline
  }
});
```

### Valid status transitions (from MeetingWorkflowService.php)
```
draft → scheduled (requires: motions exist)
scheduled → frozen (requires: attendance recorded; warns if no president)
frozen → live (warns if quorum not met — can still proceed)
live → paused (blocked if vote open)
live/paused → closed (blocked if vote open)
closed → validated
validated → archived
```

### ag-quorum-bar usage
```html
<!-- Source: existing hub.htmx.html line 177 — component works as-is -->
<ag-quorum-bar id="hubQuorumBar" current="0" required="0" total="0"></ag-quorum-bar>
```
```javascript
// Update via attributes
bar.setAttribute('current', String(presentCount));
bar.setAttribute('required', String(quorumRequired));
bar.setAttribute('total', String(memberCount));
```

### Dark mode token pattern (no hardcoded colors)
```css
/* Use design tokens only — see app.css / design-system.css */
.hub-hero { background: var(--color-surface); border: 1px solid var(--color-border); }
.hub-hero::before { background: linear-gradient(90deg, var(--color-primary), var(--color-primary-hover)); }
/* Exception: ag-quorum-bar internal colors may use fixed vivid values (Phase 46 precedent) */
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| 6-step stepper as main nav | 3-item prerequisite checklist + primary CTA | Phase 47 redesign | Simpler — hub answers "is this ready?" not "what step am I on?" |
| `display:none` / `style.display=''` | `hidden` attribute | Phase 44 (login rebuild) | Consistent with HTML spec, cleaner JS |
| Separate action card + checklist + quorum sections | Hero card + two-column (checklist + quorum) | Phase 47 redesign | Better use of horizontal space |
| `hub.css` as 1311-line file | Fresh rewrite, token-only | Phase 47 | Eliminates dead CSS from old stepper/action-card patterns |

**Deprecated/outdated:**
- `HUB_STEPS` array with 6 steps: kept only if status progress bar is retained; if removed, also remove `renderStatusBar()` and `renderStepper()`
- `hub-identity`, `hub-layout-body`, `hub-stepper-col`, `hub-main-col`, `hub-action`, `hub-action-header`: all replaced by new hero+two-column layout classes

---

## Open Questions

1. **Keep or drop the 6-step horizontal status bar (`hubStatusBar`)?**
   - What we know: CONTEXT.md says "vertical stepper checklist" with 3 items; the horizontal bar is not mentioned
   - What's unclear: Whether the colorful 6-segment progress bar at top adds value or is redundant with the 3-item checklist
   - Recommendation: Drop it — the new hero card with status badge is sufficient; simplifies JS (no `renderStatusBar` / `renderStepper`)

2. **Where does "Modifier" (edit in wizard) link live in new layout?**
   - What we know: Current header has "Modifier" and "Séances" buttons in the `app-header` right area
   - What's unclear: Whether those stay in the `app-header` or move to the hero card
   - Recommendation: Keep in `app-header` right area — consistent with operator rebuild pattern

3. **Second convocation (`btn2eConvoc`) — include or drop?**
   - What we know: Current HTML has a "2e convocation" warn card in the details section; no backend endpoint for it was found
   - What's unclear: Is this a real feature or aspirational UI?
   - Recommendation: Drop from rebuild — not wired to any backend, out of scope for v4.3

---

## Validation Architecture

> `workflow.nyquist_validation` key is absent from config.json — treating as enabled.

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (detected in vendor/) |
| Config file | phpunit.xml (if exists) |
| Quick run command | `php vendor/bin/phpunit --stop-on-failure` |
| Full suite command | `php vendor/bin/phpunit` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REB-05 | Hub HTML renders session title, quorum bar, checklist from wizard_status API | manual browser | N/A — visual page verification | N/A |
| REB-05 | Quorum bar updates with live present_count | manual browser | N/A | N/A |
| REB-05 | Checklist items reflect convocation sent / quorum met / agenda locked | manual browser | N/A | N/A |
| WIRE-01 | invitations_send_bulk endpoint returns 200 on POST | manual browser + Network tab | N/A | N/A |
| WIRE-01 | meeting_transition POST triggers status change | manual browser | N/A | N/A |

> The hub is a client-side page — behavioral verification is manual browser testing per project convention (established in Phase 43–46 browser checkpoints). No automated JS test framework is present for page-level integration.

### Sampling Rate
- **Per task commit:** Reload hub page with a valid `?id=...` parameter, check console for errors
- **Per wave merge:** Full browser walkthrough: quorum bar visible, checklist all 3 items populated, convocation button triggers correct endpoint, "Aller à la console" navigates correctly
- **Phase gate:** Browser checkpoint approved before WIRE-01 marked complete

### Wave 0 Gaps
None — existing test infrastructure (manual browser) is consistent with all prior rebuilds in v4.3.

---

## Sources

### Primary (HIGH confidence)
- `app/Controller/DashboardController.php` — wizardStatus() response shape (lines 134–186)
- `app/Controller/MeetingWorkflowController.php` — transition(), workflowCheck() API contracts
- `app/Services/MeetingWorkflowService.php` — issuesBeforeTransition() logic, valid status transitions
- `app/routes.php` — all verified route registrations
- `public/hub.htmx.html` — existing HTML (all 299 lines read)
- `public/assets/js/pages/hub.js` — existing JS (all 658 lines read)
- `public/assets/css/hub.css` — existing CSS (all 1311 lines read)
- `public/assets/js/pages/operator-tabs.js` lines 2879–2889 — correct invitations_send_bulk call

### Secondary (MEDIUM confidence)
- `app/Controller/InvitationsController.php` — invitations_stats response structure (lines 106–155)
- `.planning/STATE.md` — accumulated context from Phase 43–46 decisions

### Tertiary (LOW confidence)
- None

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries confirmed present in HTML, all endpoints verified in routes.php
- Architecture: HIGH — three prior rebuilds (dashboard, login, wizard, operator) establish clear patterns
- Dead endpoint fix: HIGH — confirmed absence of `/meetings/{id}/convocations` in routes.php, confirmed working alternative
- Pitfalls: HIGH — all identified from reading actual code, not inference

**Research date:** 2026-03-22
**Valid until:** 2026-04-22 (stable PHP backend, low churn)
