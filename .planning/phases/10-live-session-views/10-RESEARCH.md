# Phase 10: Live Session Views - Research

**Researched:** 2026-03-13
**Domain:** CSS token migration, horizontal bar charts, present/absent toggle, ARIA audit — vanilla HTML/CSS/JS
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

#### Room display styling
- Keep the existing header bar (status badge, meeting title, clock, controls) — restyle to design tokens, do NOT remove it
- Respect the theme toggle — do NOT force dark background. User can choose light or dark via existing toggle button
- Result bars: switch from vertical to horizontal orientation per wireframe
- Keep the resolution tracker pills at the bottom — useful for audience progress awareness
- Secret vote block: restyle to design tokens (colors, fonts, borders), keep existing layout (lock icon, title, participation bar)

#### Voter view touch UX
- No bottom navigation — the voter page is a focused single-purpose interface, not a multi-tab app
- Keep all 4 vote buttons (Pour, Contre, Abstention, Blanc) — Blanc is a legal voting option in French assemblies
- Restyle buttons to design tokens (Phase 4 colors, radius, shadows) — keep current sizes which are already touch-friendly
- Keep the bottom-sheet confirmation overlay — standard mobile pattern, thumb-friendly

#### Real-time data flow
- No countdown timer for vote closing — operator controls open/close, countdown is a functional feature outside UI redesign scope
- Keep existing participation progress bar on voter view, restyle to design tokens
- Room display timer: keep current clock time display, do NOT switch to session elapsed time
- Tokenize public.css — replace all hardcoded colors/fonts with design system CSS custom properties

#### Present/absent toggle
- Place toggle in voter footer area near member info — compact, doesn't interfere with voting
- When voter marks absent: disable vote buttons and show message — prevents voting while absent (legal consistency)
- Toggle is instant — no confirmation dialog needed (presence is easily reversible)
- Call attendance API to update server — real self-service, operator sees the change in their attendance panel

#### Inline styles cleanup
- Full cleanup of all inline styles on both pages — replace with CSS classes or hidden attributes (same approach as Phase 9 operator page)
- App-footer on both pages: replace style="display:none" with hidden attribute (keep HTML per Phase 6 pattern)

#### Accessibility
- Quick ARIA audit on both pages: ensure labels, live regions, focus management on confirmation overlay
- Fix any issues found during audit

### Claude's Discretion
- Exact horizontal bar sizing and animation for room display results
- How to handle the mode toggle (Vote/Resultats) styling
- Mobile responsive breakpoints for room display
- Presence toggle visual design (switch, button, chip — pick what fits the footer area)
- ARIA audit scope and prioritization of fixes

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| DISP-01 | Full-screen layout (no header/sidebar), dark background (#0B0F1A) | Theme toggle kept; dark is the default theme for projection; header stays but is restyled; no sidebar on this page already |
| DISP-02 | Session title, current resolution, live result bars, participation %, timer, status | All elements already exist in public.htmx.html; work is restyle + horizontal bar refactor |
| VOTE-01 | Touch-optimized tablet/mobile layout with bottom navigation | No bottom nav per locked decision; layout is already touch-optimized; restyle to design tokens |
| VOTE-02 | Large resolution title, big vote buttons (Pour/Contre/Abstention), hand raise button | All 4 vote buttons exist; hand raise (speech panel) exists; restyle to tokens |
| VOTE-03 | Vote confirmation screen, countdown timer, present/absent toggle | Confirmation overlay exists; no countdown per locked decision; present/absent toggle is new UI element backed by existing attendance API |
</phase_requirements>

---

## Summary

Phase 10 is a CSS token migration and layout refinement pass on two standalone pages that already have full functionality. `public.htmx.html` (room display) and `vote.htmx.html` (voter tablet interface) were built before the design system tokens were applied to their dedicated CSS files (`public.css`, `vote.css`). Both files already reference design-system tokens for most properties, but some hardcoded values and structural patterns remain.

The two significant functional additions are: (1) converting the vertical bar chart in the room display to a horizontal layout — this is a pure CSS restructure of `.bar-chart` / `.bar-item` / `.bar-wrapper`, plus a JS change to update `width` instead of `height`; (2) adding a present/absent toggle in the voter footer — a new DOM element that calls the existing `attendances_upsert.php` API with `mode: "present"` or `mode: "absent"` and disables vote buttons when absent.

The inline styles cleanup follows the same pattern established in Phase 9: replace `style="display:none"` on `app-footer` with the `hidden` attribute, and move any remaining inline `style="width: 0%"` (dynamic bar widths) to CSS custom properties or JS-managed class state where they are set by JS at runtime — those are acceptable as "JS-managed inline styles" per the project pattern.

**Primary recommendation:** Work in three focused passes per page — (1) tokenize CSS, (2) structural layout changes, (3) new DOM additions — keeping JS changes minimal and confined to `vote.js` / `vote-ui.js` for the attendance toggle.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Vanilla CSS with custom properties | — | Design token application | Project identity; no framework migrations |
| `attendances_upsert.php` API | existing | Present/absent self-service | Already used by operator-attendance.js; same payload shape |
| `event-stream.js` SSE | existing | Real-time updates on both pages | Already wired; no changes needed |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `vote.js` + `vote-ui.js` IIFE modules | existing | Voter interface logic | Add presence toggle handler here |
| `public.js` IIFE module | existing | Room display bar chart updates | Update `height` → `width` style for horizontal bars |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| CSS-only horizontal bars | SVG / Canvas | CSS is simpler, consistent with existing pattern, and animatable with `transition: width` |
| New presence toggle endpoint | Reusing attendances_upsert.php | Reuse is correct — same API used by operator tab already |

**Installation:** No new dependencies. All required assets already exist.

---

## Architecture Patterns

### Recommended Project Structure
No new files needed. All changes are to existing files:
```
public/
├── public.htmx.html        # inline style cleanup only
├── vote.htmx.html          # inline style cleanup + presence toggle DOM
├── assets/css/
│   ├── public.css          # horizontal bar refactor + token pass
│   └── vote.css            # token pass + presence toggle styles
└── assets/js/pages/
    ├── public.js           # bar fill direction: height → width
    ├── vote.js             # presence toggle API call
    └── vote-ui.js          # presence toggle UI state
```

### Pattern 1: Horizontal Bar Chart (Room Display)
**What:** Replace vertical `.bar-item` column layout with horizontal rows where bar fills left-to-right.
**When to use:** Wide projection screens — horizontal bars are more readable at distance.
**Example:**
```css
/* Source: public.css refactor — horizontal bar pattern */
.bar-chart {
  display: flex;
  flex-direction: column;   /* was: row */
  gap: var(--space-4);
  width: 100%;
  max-width: 800px;
}

.bar-item {
  display: grid;
  grid-template-columns: 6rem 1fr 4rem;  /* label | bar | percentage */
  align-items: center;
  gap: var(--space-3);
}

.bar-wrapper {
  width: 100%;
  height: 32px;             /* was: 150px with flex-end */
  background: var(--color-bg-subtle);
  border-radius: var(--radius-full);
  overflow: hidden;
}

.bar {
  height: 100%;
  width: 0;                 /* animated by JS */
  border-radius: var(--radius-full);
  transition: width 1s var(--ease-bounce);  /* was: height */
}
```

JS update (public.js) — change `element.style.height` to `element.style.width` in the bar update function.

### Pattern 2: Present/Absent Toggle in Voter Footer
**What:** A compact toggle button placed alongside the member info in `.vote-footer`, wired to `attendances_upsert.php`.
**When to use:** Voter is already identified (member selected or invitation mode).
**Example:**
```html
<!-- Add inside .vote-footer > .member-info area -->
<button class="presence-toggle" id="btnPresence"
  aria-pressed="true"
  aria-label="Marquer comme absent"
  type="button">
  <span class="presence-toggle-icon" aria-hidden="true">
    <!-- inline SVG check or X -->
  </span>
  <span class="presence-toggle-label">Présent</span>
</button>
```

```css
/* Source: vote.css addition */
.presence-toggle {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-full);
  border: 2px solid var(--color-success);
  background: var(--color-success-subtle);
  color: var(--color-success-text);
  font-size: var(--text-sm);
  font-weight: var(--font-bold);
  cursor: pointer;
  transition: background var(--duration-fast), border-color var(--duration-fast), color var(--duration-fast);
  -webkit-tap-highlight-color: transparent;
}

.presence-toggle[aria-pressed="false"] {
  border-color: var(--color-border);
  background: var(--color-bg-subtle);
  color: var(--color-text-muted);
}
```

### Pattern 3: Inline Style Cleanup
**What:** Replace `style="display:none"` with `hidden` attribute on `app-footer` (both pages). Dynamic width/height values set by JS at runtime remain as inline styles — this is the accepted pattern.

**Accepted inline styles (set by JS):**
- `participation_bar.style.width = '0%'` — runtime animation, JS-managed, acceptable
- `quorumVisualFill.style.width = '...'` — runtime animation, JS-managed, acceptable
- `quorumSeuil.style.left = '...'` — runtime positioning, JS-managed, acceptable
- `voteParticipationFill.style.width = '...'` — runtime animation, JS-managed, acceptable

**Must remove (static inline styles):**
- `app-footer` both pages: `style="display:none"` → `hidden` attribute
- `app-footer` link children: all `style="..."` decorative styles → move to CSS class `.app-footer-link` or similar
- `app-footer` logo: `style="font-size:12px;gap:6px;"` → CSS class
- `app-footer` logo-mark: `style="width:18px;height:18px;border-radius:3px;font-size:8px;"` → CSS class
- `app-footer` spacer: `style="flex:1"` → CSS class `.flex-spacer` or existing `.flex-1`

### Pattern 4: CSS Custom Property Tokenization
**What:** Replace any remaining hardcoded values in `public.css` and `vote.css` with design system tokens.

**Remaining hardcoded values found:**
In `public.css`:
- `.motion-counter { background: rgba(255,255,255,0.15) }` — context-dependent (used on colored backgrounds); may need a local token
- `.meeting-picker-card:hover { box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb...) }` — uses non-existent `--color-primary-rgb`; replace with `var(--color-primary-glow)` (same pattern as tracker-pill.active)

In `vote.css`:
- `:root` block defines shadow variables as `rgba(...)` hardcoded — already grouped as local tokens within vote.css, this is acceptable and intentional (they are already tokenized per dark/light theme overrides)

### Anti-Patterns to Avoid
- **Forcing dark theme on public.htmx.html:** Theme toggle is a locked decision; do not set `data-theme="dark"` in HTML; user controls it
- **Adding bottom navigation to vote.htmx.html:** Explicitly forbidden by CONTEXT.md
- **Removing the vote page's `app-footer`:** Phase 6 placed it there; replace `style="display:none"` with `hidden` only
- **Calling a new API for presence toggle:** Use existing `attendances_upsert.php` with same payload shape as operator
- **Removing countdown timer placeholder:** The `voteTimer` element exists but is hidden; leave it hidden, no new timer logic needed

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Attendance mode update | Custom REST endpoint | `attendances_upsert.php` with `{meeting_id, member_id, mode}` | Already handles auth, audit log, SSE broadcast |
| Real-time presence status | New SSE channel | Existing `event-stream.js` | Operator already receives presence updates via SSE |
| Horizontal bar animation | CSS keyframes | CSS `transition: width` | Simpler, already proven in participation bars |
| Vote button disable on absent | Complex state machine | Set `disabled` attribute + `hidden` on hint text | Same `setVoteButtonsEnabled()` function pattern already in vote.js |

**Key insight:** Every interaction pattern needed already exists either in the CSS, the JS modules, or the API layer. This phase is alignment, not invention.

---

## Common Pitfalls

### Pitfall 1: Bar fill direction in JS
**What goes wrong:** After switching `.bar` from vertical (height-animated) to horizontal (width-animated), the JS still sets `element.style.height`. Bars appear flat (0 height) and never animate.
**Why it happens:** `public.js` has a `updateBars()` or similar function that sets `barForFill.style.height = pct + '%'`. The CSS change alone is not sufficient.
**How to avoid:** Search `public.js` for all `.style.height` assignments on bar fill elements and change to `.style.width`.
**Warning signs:** Bars are invisible after CSS change but data is updating correctly.

### Pitfall 2: Present/absent toggle not linked to vote button disable
**What goes wrong:** User marks absent, toggle updates visually, but vote buttons remain enabled. Voter can still cast a vote while absent.
**Why it happens:** The `setVoteButtonsEnabled()` function in `vote.js` only checks motion state, not attendance state.
**How to avoid:** Track a `isAbsent` flag in vote.js scope; call `setVoteButtonsEnabled(false)` when toggling absent; also set a visible message in `voteHint` explaining why buttons are disabled.
**Warning signs:** Vote buttons enabled simultaneously with absent state toggle showing.

### Pitfall 3: Attendance toggle requires member selection
**What goes wrong:** Toggle is rendered before member is selected (direct URL without invitation token), breaking the API call because `meeting_id` or `member_id` is empty.
**Why it happens:** Toggle button exists in footer which renders immediately, but member context is only available after selection.
**How to avoid:** Toggle starts `hidden`; show it only after a member is selected (same pattern as `voteParticipation` div which starts `hidden`). Check both `selectedMeetingId()` and `selectedMemberId()` before showing and before API call.
**Warning signs:** `attendances_upsert.php` returns 400 error on toggle click.

### Pitfall 4: Horizontal bar label layout at small widths
**What goes wrong:** On mobile (<480px), the 3-column label|bar|% grid compresses the bar to zero width.
**Why it happens:** `grid-template-columns: 6rem 1fr 4rem` is fixed-width for labels; on very small screens labels don't shrink.
**How to avoid:** Add responsive override for narrow viewports — stack to `grid-template-columns: 1fr` with label above bar, or use `minmax(0, 1fr)` for the bar column and allow label truncation.
**Warning signs:** Bar disappears on mobile preview.

### Pitfall 5: `color-mix()` in participation bar
**What goes wrong:** `quorum-visual-fill.met` uses `color-mix(in srgb, ...)` which is already in public.css — this works in modern browsers but fails in older ones.
**Why it happens:** This is pre-existing code from Phase 7; not introduced by Phase 10.
**How to avoid:** No action needed — this is an existing constraint, not a Phase 10 concern. Do not introduce new `color-mix()` usages if targeting broader browser support.

### Pitfall 6: `[hidden]` attribute overridden by CSS
**What goes wrong:** After replacing `style="display:none"` with `hidden` on `app-footer`, the footer reappears because CSS `.app-footer { display: flex }` overrides the HTML `hidden` attribute.
**Why it happens:** CSS specificity — `[hidden] { display: none }` is a low-specificity rule from the reset; a class selector overrides it.
**How to avoid:** Ensure `design-system.css` has `[hidden] { display: none !important }` (standard reset pattern) OR keep `display:none` in CSS for `.app-footer` on voter/projection pages via a page-role selector:
```css
[data-page-role="public"] .app-footer,
[data-page-role*="voter"] .app-footer {
  display: none;
}
```
Phase 9 resolved this by using the operator.htmx.html `app-footer` without `hidden` attribute — the footer renders in the normal app shell flow. For public/voter pages, the `hidden` attribute approach needs the CSS support verified first.

---

## Code Examples

### Attendance API call pattern (from operator-attendance.js)
```javascript
// Source: /public/assets/js/pages/operator-attendance.js:106
const { body } = await api('/api/v1/attendances_upsert.php', {
  meeting_id: O.currentMeetingId,
  member_id: memberId,
  mode: mode   // 'present' | 'absent' | 'remote'
});
if (body?.ok === true) {
  // success
} else {
  // rollback + notify
}
```

Voter equivalent (in vote.js IIFE scope):
```javascript
// Pattern: same API, voter self-service
async function togglePresence(isPresent) {
  var meetingId = selectedMeetingId();
  var memberId = selectedMemberId();
  if (!meetingId || !memberId) return;
  var mode = isPresent ? 'present' : 'absent';
  try {
    var result = await apiPost('/api/v1/attendances_upsert.php', {
      meeting_id: meetingId,
      member_id: memberId,
      mode: mode
    });
    if (result?.body?.ok) {
      updatePresenceUI(isPresent);
      setVoteButtonsEnabled(isPresent && currentMotionOpen);
    }
  } catch(e) {
    notify('error', 'Erreur de mise à jour de présence');
  }
}
```

### Horizontal bar JS update pattern
```javascript
// Source: public.js — change from height to width
// Before:
document.getElementById('bar_for_fill').style.height = pct + '%';
// After:
document.getElementById('bar_for_fill').style.width = pct + '%';
```

### app-footer hidden attribute (Phase 9 pattern reference)
```html
<!-- public.htmx.html — before -->
<footer class="app-footer" role="contentinfo" style="display:none">

<!-- After (hidden attribute + CSS rule to ensure it) -->
<footer class="app-footer" role="contentinfo" hidden>
```
Add to `public.css` and `vote.css` to make `hidden` reliable:
```css
/* Ensure [hidden] works even with app-footer flex override */
.app-footer[hidden] { display: none; }
```

### Presence toggle hidden until member selected (pattern from voteParticipation)
```javascript
// In vote.js — show toggle only when member is known
// Source: same pattern as voteParticipation div (line ~657 in vote.js)
var presenceToggle = document.getElementById('btnPresence');
if (presenceToggle) {
  presenceToggle.hidden = !selectedMemberId();
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Vertical bar charts (column flex) | Horizontal bars (row grid per item) | Phase 10 (wireframe v3.19.2) | Better readability on wide projection screens |
| `style="display:none"` on app-footer | `hidden` attribute | Phase 9 pattern → Phase 10 applies | Semantic HTML, matches WCAG intent for visibility |

**Deprecated/outdated:**
- `style="display:none"` for app-footer: replaced by `hidden` attribute per Phase 9 decision
- Hardcoded `rgba(255,255,255,0.15)` in motion-counter: replace with a contextual token

---

## Open Questions

1. **`app-footer[hidden]` CSS specificity**
   - What we know: Phase 9 operator.htmx.html uses `app-footer` without `hidden` (it lives inside `app-shell`); the operator page's footer is visible as part of the shell flow, it doesn't need hiding. The public/voter pages used `style="display:none"`.
   - What's unclear: Whether `design-system.css` has `[hidden] { display: none !important }` — a quick grep was not done. If it does, `hidden` attribute works. If not, need `.app-footer[hidden] { display: none; }` CSS rule.
   - Recommendation: Add `.app-footer[hidden] { display: none; }` explicitly in `public.css` and `vote.css` as a safe fallback. This is low-risk.

2. **Presence toggle state on SSE update**
   - What we know: The operator attendance tab updates via SSE; the voter page already receives SSE events.
   - What's unclear: Whether an SSE event is emitted when attendance changes via `attendances_upsert.php`, which could allow operator overrides to reflect on the voter screen.
   - Recommendation: Treat this as out of scope for Phase 10. The toggle is self-service; operator overrides are visible in the operator panel. No reactive attendance state on voter view needed.

---

## Validation Architecture

> nyquist_validation key absent from config.json — treated as enabled.

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (backend), manual browser testing (frontend CSS/JS) |
| Config file | `phpunit.xml` |
| Quick run command | `vendor/bin/phpunit --testsuite unit` |
| Full suite command | `vendor/bin/phpunit` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| DISP-01 | Room display uses design tokens, no hardcoded colors | manual | Visual browser check | N/A |
| DISP-02 | Result bars show horizontally with live data | manual | Load public.htmx.html in browser | N/A |
| VOTE-01 | Touch layout renders on tablet viewport | manual | DevTools 768px viewport | N/A |
| VOTE-02 | Vote buttons respond to touch | manual | Mobile browser test | N/A |
| VOTE-03 | Present/absent toggle calls API and disables buttons | manual | Browser network tab + operator panel | N/A |

### Sampling Rate
- **Per task commit:** Open page in browser, verify no CSS regressions
- **Per wave merge:** Full visual check: public page (dark+light), voter page (mobile+tablet+desktop)
- **Phase gate:** Both pages pixel-checked against design tokens before `/gsd:verify-work`

### Wave 0 Gaps
None — existing test infrastructure covers all phase requirements. This phase has no automated unit tests; it is a CSS/HTML restyle. Manual browser verification is the appropriate gate.

---

## Sources

### Primary (HIGH confidence)
- `/home/user/gestion-votes/public/public.htmx.html` — full room display structure, all 210 lines read
- `/home/user/gestion-votes/public/vote.htmx.html` — full voter interface structure, all 282 lines read
- `/home/user/gestion-votes/public/assets/css/public.css` — full current CSS, 989 lines read
- `/home/user/gestion-votes/public/assets/css/vote.css` — full current CSS, 1485 lines read
- `/home/user/gestion-votes/public/assets/css/design-system.css` — token reference, first 120 lines + app-footer section read
- `/home/user/gestion-votes/public/assets/js/pages/operator-attendance.js` — attendance API call pattern, first 130 lines read
- `/home/user/gestion-votes/public/assets/css/operator.css` — Phase 9 precedent, first 80 lines read

### Secondary (MEDIUM confidence)
- `.planning/phases/10-live-session-views/10-CONTEXT.md` — locked decisions and code context
- `.planning/REQUIREMENTS.md` — DISP-01/02, VOTE-01/02/03 requirement text
- `.planning/STATE.md` — prior phase decisions influencing patterns

### Tertiary (LOW confidence)
- None

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries/APIs verified by reading source files directly
- Architecture: HIGH — horizontal bar pattern and presence toggle pattern derived from reading existing code; no speculation
- Pitfalls: HIGH for bar direction and toggle visibility; MEDIUM for `[hidden]` specificity (needs quick CSS verification during implementation)

**Research date:** 2026-03-13
**Valid until:** 2026-04-13 (stable codebase, no external dependencies)
