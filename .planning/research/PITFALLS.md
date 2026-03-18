# Domain Pitfalls — AG-VOTE v4.0 "Clarity & Flow"

**Domain:** UX overhaul + PDF upload/viewer + copropriété transformation + design system migration on PHP + vanilla JS voting platform
**Researched:** 2026-03-18
**Overall confidence:** HIGH (direct codebase inspection combined with verified external sources for PDF security, performance, UX patterns)

---

## Risk Matrix Overview

| Risk | Severity | Likelihood | Priority |
|------|----------|------------|----------|
| PDF.js CVE-2024-4367 (arbitrary JS execution) | CRITICAL | HIGH (if unpatched) | P0 |
| No file serving endpoint — uploaded PDFs inaccessible | CRITICAL | HIGH (confirmed gap) | P0 |
| "Top 1% UI" scope creep — no objective done criteria | HIGH | HIGH | P1 |
| Copropriété transformation over-deletes voting_power logic | HIGH | MEDIUM | P1 |
| Tour triggers exist in HTML but have zero JS implementation | HIGH | HIGH (confirmed stub) | P1 |
| UX overhaul feature parity gaps — actions removed in redesign | HIGH | MEDIUM | P1 |
| CSS token dark mode regression — silent, no type system | HIGH | MEDIUM | P1 |
| STORAGE_PATH env var defined but controller hardcodes /tmp path | MEDIUM | HIGH (confirmed) | P2 |
| PDF.js bundle weight — loaded globally instead of lazily | MEDIUM | HIGH | P2 |
| PC-first design shift breaking voter screen via shared components | MEDIUM | LOW-MEDIUM | P2 |
| CSS animation performance on lower-end hardware | MEDIUM | MEDIUM | P2 |
| Guided tour library license trap (Shepherd.js commercial) | MEDIUM | MEDIUM | P2 |
| WCAG regression during redesign — focus rings / ARIA lost | MEDIUM | MEDIUM | P2 |

---

## Critical Pitfalls

### Pitfall 1: PDF.js CVE-2024-4367 — Arbitrary JavaScript Execution via Malicious PDF

**Severity:** CRITICAL
**Likelihood:** HIGH if unpatched
**Phase to address:** First phase introducing the PDF viewer. Cannot be deferred.

**What goes wrong:**
CVE-2024-4367 allows an attacker to execute arbitrary JavaScript in the browser the moment a malicious PDF is opened in PDF.js. The bug is a missing type check in the font rendering code — a crafted PDF can inject a script string that PDF.js evaluates as JavaScript. A proof-of-concept is publicly available and exploits are indexed on Exploit-DB.

In AG-VOTE's context: if an operator uploads a malicious PDF attachment (via `MeetingAttachmentController::upload()`), and a voter or another operator views it inline using the PDF.js viewer, the attacker's JS executes in the victim's authenticated browser session. This enables session token theft, vote manipulation, or full account takeover. It is a stored XSS attack path delivered through a PDF.

**Why it happens:**
PDF.js < 4.2.67 does not sanitize font type values in the PDF spec's font rendering pipeline. The missing check was a simple `typeof` guard that was overlooked.

**Mitigation:**
1. Pin `pdfjs-dist` to version >= 4.2.67. The current version as of March 2026 is 5.5.207 (HIGH confidence: confirmed from npm registry). Load from CDN with a pinned version in the URL — never `@latest`.
2. Set `isEvalSupported: false` in the PDF.js `getDocument()` options. This disables font compilation via `eval()`, eliminating the attack surface for CVE-2024-4367 and related font-injection bugs.
3. Add a `sandbox` attribute to any `<iframe>` used to host the PDF viewer: `sandbox="allow-scripts allow-same-origin"` limits what injected JS can do.
4. Enforce `Content-Security-Policy: script-src 'self'` on the viewer page — verify the existing nginx CSP applies to the viewer route.

**Detection (warning signs):**
- PDF viewer loads from CDN without a pinned version number in the URL
- `isEvalSupported` is not set to `false` in the `getDocument()` call
- Any `<iframe>` displaying PDFs is missing the `sandbox` attribute

**Sources:**
- [CVE-2024-4367 — Arbitrary JavaScript execution in PDF.js (Codean Labs)](https://codeanlabs.com/blog/research/cve-2024-4367-arbitrary-js-execution-in-pdf-js/) — HIGH confidence
- [GitHub Advisory GHSA-wgrm-67xf-hhpq](https://github.com/advisories/GHSA-wgrm-67xf-hhpq) — HIGH confidence
- [Snyk — pdfjs-dist CVE-2024-4367](https://security.snyk.io/vuln/SNYK-JS-PDFJSDIST-6810403) — HIGH confidence

---

### Pitfall 2: No File Serving Endpoint — Uploaded PDFs Are Inaccessible to Voters

**Severity:** CRITICAL
**Likelihood:** HIGH (confirmed gap in codebase)
**Phase to address:** PDF infrastructure phase, before any viewer UI work.

**What goes wrong:**
`MeetingAttachmentController` stores uploaded PDFs at `/tmp/ag-vote/uploads/meetings/{meeting_id}/{uuid}.pdf`. There is currently no PHP endpoint that reads these files and serves them to authenticated clients. There is no nginx `location` block serving `/tmp/ag-vote/uploads/` (nor should there be). The `meeting_attachments` API only supports `GET` (list metadata), `POST` (upload), and `DELETE` — confirmed by direct inspection of `public/api/v1/meeting_attachments.php`.

If v4.0 adds a PDF inline viewer (PDF.js), the viewer needs an authenticated URL to load the file from. Without a dedicated secure serving endpoint, the only workaround would be storing PDFs inside `public/` — which is a serious security risk (unauthenticated direct URL access to any uploaded file).

**Why it happens:**
The `meeting_attachments` migration and controller were built in a prior milestone as a data layer. The serving layer was explicitly deferred. v4.0 is the first milestone that actually displays PDFs to users, so this gap becomes a blocker.

**Consequences if ignored:**
- All PDF attachment metadata exists in the DB but the files are never viewable
- A developer might store files in `public/uploads/` to unblock themselves — creating an unauthenticated access and path traversal risk

**Mitigation:**
1. Build a dedicated `GET /api/v1/meeting_attachment_serve.php?id={uuid}` endpoint that:
   - Validates the attachment UUID
   - Verifies the requesting user's tenant matches the attachment's tenant
   - Verifies the meeting's access rules (operator or voter role for the specific meeting)
   - Reads the file from the storage path using `readfile()`
   - Outputs `Content-Type: application/pdf`, `Content-Disposition: inline`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`
2. Apply the existing rate limit zone (`limit_req zone=api`) to this endpoint.
3. PDF.js `getDocument()` then points to this authenticated URL — not a public static path.

**Detection:**
- `grep -r "readfile\|fpassthru" public/api/v1/` returns no results → serving endpoint does not exist

---

## High Severity Pitfalls

### Pitfall 3: "Top 1% UI" Scope Creep — No Objective Done Criteria

**Severity:** HIGH
**Likelihood:** HIGH
**Phase to address:** Before the first v4.0 design phase starts. This is a process risk, not a technical one.

**What goes wrong:**
"Top 1% UI" is aspirational language, not a specification. Without concrete, measurable done criteria, v4.0 phases never close. Every component can always be polished further. Animation can always be tweaked. Typography can always be refined. The milestone drags on indefinitely because "good enough" has no definition.

This is the highest-likelihood risk in v4.0. It has also been the source of scope inflation in past design milestones: v2.0 and v3.0 both generated sub-phases (20.1 through 20.4 in v3.0) because visual polish work kept expanding.

**Why it happens:**
"Top 1% UI" is a quality statement, not a functional requirement. The disconnect between "this looks great" (subjective) and "this is done" (binary) means every phase is at risk of definition inflation.

**Consequences:**
- Milestone timeline extends indefinitely
- Individual phases split into sub-phases (the v3.0 pattern)
- Demoralization: the milestone feels like it never ships

**Mitigation:**
1. Define "top 1% UI" with concrete, measurable criteria before the first phase starts. Suggested objective criteria:
   - All interactive states have transitions <= 200ms (measured with DevTools Performance panel)
   - Focus rings visible at 3:1 contrast ratio minimum on all interactive elements (`:focus-visible` always has a `box-shadow` ring)
   - No layout shift on page load (CLS = 0, verified with Lighthouse)
   - All new components have a documented loading, empty, and error state
   - Zero inline `style=""` attributes in production HTML (all styling via design tokens)
   - Dark mode parity: all new components pass visual dark mode review in same commit
2. For each phase, define done as: "the phase plan's checklist is complete AND the criteria above are met" — not "it looks perfect."
3. Cap visual polish work per phase: if a phase is functionally complete but has polish items, log them as a separate future phase rather than blocking the current one from closing.
4. Add a design reference screenshot per screen that serves as the primary acceptance criterion — "matches the design reference" is more objective than "looks great."

---

### Pitfall 4: Guided Tour Triggers Are HTML Stubs With Zero JS Implementation

**Severity:** HIGH
**Likelihood:** HIGH (confirmed by codebase inspection)
**Phase to address:** First phase that commits to delivering guided UX — must decide: implement or remove.

**What goes wrong:**
Three pages already have guided tour infrastructure in HTML that is completely unwired:
- `wizard.htmx.html`: `<button id="btnTour" class="tour-trigger-btn">` exists, `data-tour="wizard-step1"` attributes on steps
- `postsession.htmx.html`: `<button id="btnTour" class="tour-trigger-btn">` exists, `data-tour="postsession-stepper"` on elements
- `members.htmx.html`: `<div class="members-onboarding">` with onboarding steps markup

None of these trigger any JS logic. `grep -rn "btnTour\|tour-trigger-btn" public/assets/js/pages/` returns no results. The buttons are visible in the UI but do nothing when clicked.

**Why it happens:**
The tour infrastructure was scaffolded as placeholders during v2.0/v3.0 design work, with the intention of implementing tours in a future milestone. v4.0 is that milestone.

**Consequences:**
- v4.0's "guided UX overhaul" requires a decision on every stubbed tour: implement with a real tour library, or replace with a different guided UX pattern (inline hints, empty states, contextual popovers)
- If stubbed buttons remain unimplemented and ship in v4.0, they are visible UX defects (buttons that do nothing)
- If a tour library is chosen without license/size research, it may introduce a commercial licensing requirement or unacceptable bundle weight

**Mitigation:**
1. Decide tour strategy before building: full step-by-step library tour, contextual hints using the existing `ag-popover` Web Component, or inline empty-state guidance. Not all three simultaneously.
2. If using a library: use Driver.js (`driver.js` on npm). It is MIT licensed, actively maintained, framework-agnostic, smallest bundle among serious contenders, works with vanilla JS. Do NOT use Shepherd.js v12+ (commercial license required for non-open-source use; verify carefully for AG-VOTE's case).
3. Load Driver.js only on pages that use it — not in the shell/layout.
4. The existing `ag-popover` Web Component is already in the codebase and zero additional weight — use it for single-element contextual hints instead of a full tour library.
5. Any stubbed tour button that will NOT be implemented in v4.0 must be removed from the HTML before shipping.

**Detection:**
- Click `btnTour` on wizard, postsession, or members — nothing happens → stub is unimplemented

---

### Pitfall 5: Copropriété Transformation — "tantièmes" Is voting_power, Not a Separate Feature

**Severity:** HIGH
**Likelihood:** MEDIUM
**Phase to address:** Copropriété transformation phase, before any deletion begins.

**What goes wrong:**
The "copropriété transformation" task sounds like deleting isolated dead code, but the actual codebase footprint reveals it is terminology layered over generic voting weight logic:

- `ImportService.php` line 237: `'voting_power' => ['voting_power', 'ponderation', 'tantièmes', ...]` — `tantièmes` is a CSV column alias, not a separate feature
- `settings.js` line 419: a "distribution key" modal uses `tantiemes` as a selector option — this is the only true copropriété-specific UI
- `AggregateReportRepository.php` line 99: a comment mentioning "evolution des tantiemes" — a documentation label only
- `shell.js` line 683: navigation label "Annuaire des copropriétaires" — a string in the members menu item

The schema: `members.voting_power` is a generic numeric column used for ALL weighted voting scenarios. It is referenced in 14+ locations across `BallotsService`, `AttendancesService`, `ExportService`, `MeetingReportService`, `BallotRepository`, `MemberRepository`, `MemberGroupRepository`, `ProxyRepository`, `ExportTemplateRepository`. The `attendances.effective_power` column overrides it per-meeting for proxy scenarios.

The risk: developers conflate "removing copropriété" with "removing voting_power weighting," which would break all weighted vote sessions.

**Why it happens:**
The term "tantièmes" appears alongside `voting_power`, suggesting the feature was originally built for copropriétés. Without an explicit definition of "what counts as copropriété code," developers may over-delete.

**Consequences:**
- Removing `voting_power` calculation from ballot tallying breaks all weighted vote sessions silently — tallies become 1:1 instead of proportional
- Removing `tantièmes` from `ImportService.php` breaks CSV import for any installation using weighted votes — import succeeds but all weights default to 1.0
- The settings distribution key modal (`openKeyModal`) is a stub (its `onConfirm` only calls `AgToast.show(...)`, no API call) — but deletion requires confirming no backend endpoint exists for it

**Mitigation:**
1. Before any deletion, define scope explicitly: "copropriété transformation = rename/remove terminology, NOT removal of voting_power mechanics."
2. The complete deletion list is small and safe:
   - `shell.js` line 683: rename "Annuaire des copropriétaires" → "Annuaire des membres"
   - `settings.js`: remove the `openKeyModal` / distribution key UI (confirm no API endpoint matches first — confirmed: no `distribution_keys.php` exists in `public/api/v1/`)
   - `AggregateReportRepository.php` line 99: update comment to remove tantièmes reference
3. Keep `'tantiemes'` and `'tantièmes'` as accepted CSV import aliases in `ImportService.php` indefinitely — backward compatibility, no user impact, no downside.
4. Write a PHPUnit test asserting that weighted votes (voting_power != 1.0) still tally correctly after the transformation phase. This test should cover the `BallotsService::weight` path.

**Detection:**
- `grep -r "voting_power" app/` returning zero results after the phase = over-deletion
- E2E test: create two members with voting_power 2.0 and 1.0, cast votes, verify tally weights correctly

---

### Pitfall 6: UX Overhaul — Feature Parity Gaps During "Guided Experience" Redesign

**Severity:** HIGH
**Likelihood:** MEDIUM
**Phase to address:** Must be addressed before redesigning each screen — inventory first, design second.

**What goes wrong:**
v4.0 redesigns all screens for guided experience, starting from scratch with no wireframe constraint. The risk is that redesigned screens omit functionality that existed in v3.0 but was not prominent enough to be noticed during redesign. Common examples:
- A bulk action button accessible via a toolbar in v3.0 hidden in the new guided flow
- An advanced filter visible in the dense table layout removed in the simplified card layout
- Keyboard shortcuts or power-user paths interrupted by the guided overlay

This is distinct from bugs — the new design genuinely removes the feature because it was not noticed.

**Why it happens:**
When designing from scratch, you design what you plan to build, not what already exists. Features that are secondary flows, admin edge cases, or advanced operator options are easy to omit because they are not prominent in the current UI.

**Consequences:**
- Operators who relied on specific v3.0 interface actions find them gone after upgrade
- Features have to be re-added mid-milestone, disrupting design consistency and causing scope creep

**Mitigation:**
1. Before redesigning each screen, generate a "feature inventory" of every user action available on that screen in v3.0. Include: all buttons and their API calls, all form fields and their persistence, all modal paths, keyboard navigation.
2. The feature inventory becomes the feature parity checklist for the v4.0 design. No item from the inventory can be removed without an explicit product decision logged in the phase plan.
3. Regression test: for each redesigned page, the existing Playwright E2E specs from v3.0 should still pass — they encode the existing feature contracts. If they fail after redesign, something was removed.
4. The guided overlay must be dismissible and must not block access to existing advanced functionality.

**Detection:**
- v3.0 E2E test passes before redesign, fails after → feature was removed
- Manual audit: compare interactive elements (buttons, form fields, modals) between v3.0 HTML and v4.0 HTML for each redesigned page

---

### Pitfall 7: CSS Token Migration — Dark Mode Contrast Regression Is Silent

**Severity:** HIGH
**Likelihood:** MEDIUM
**Phase to address:** Any phase that introduces new CSS tokens. Enforce as a commit discipline.

**What goes wrong:**
AG-VOTE has 64+ CSS tokens split across a light (`:root {}`) block and a single dark (`[data-theme="dark"] {}`) block in `design-system.css` (4,576 lines). When v4.0 introduces new tokens for guided disclosure layouts, new surface elevations, or new interactive states, the dark theme override block must be updated in parallel.

The common failure mode: a new `--color-surface-card` token is added to `:root`, used in 3 new components, but never added to `[data-theme="dark"]`. Dark mode falls back to the light value — often invisible text on a dark background. No error is thrown; the browser silently uses the fallback.

Existing pattern: `outline: none` on `.btn:focus-visible` is correctly replaced with a `box-shadow` focus ring using `--ring-color` and `--ring-offset` tokens. New components must follow this exact pattern — never suppress `outline` without providing an equivalent `box-shadow` ring.

**Why it happens:**
CSS custom properties have no type system. A missing dark override fails silently. Manual visual inspection is the only detection method unless a contrast checker is wired into CI.

**Consequences:**
- Components built during v4.0 look broken or unreadable in dark mode on first user report
- WCAG AA failures in dark mode are invisible to automated HTML validators (they parse structure, not rendered color)

**Mitigation:**
1. Two-token rule: every new CSS token added to `:root` MUST have a companion entry in `[data-theme="dark"]` in the same commit. No exceptions.
2. Use the browser's Accessibility DevTools (Chrome Accessibility tab, Firefox Accessibility panel) to spot-check contrast in dark mode for every new component before marking a task done.
3. Add `axe-core` or `Pa11y` to the Playwright E2E suite to catch contrast failures in CI — run on both light and dark themes.
4. Treat dark mode parity as a per-phase deliverable, not a final audit concern.
5. Follow the existing persona token pattern (`--persona-admin-*`, `--persona-operator-*`) for new token families — each already has explicit dark overrides, making the pattern clear.

**Detection:**
- New CSS token appears in `:root` but not in `[data-theme="dark"]` block
- Visual: text invisible or low-contrast when toggling dark mode on any new component
- Playwright E2E: `page.evaluate(() => document.body.setAttribute('data-theme', 'dark'))` before visual assertions

---

## Medium Severity Pitfalls

### Pitfall 8: STORAGE_PATH Env Var Defined but Controller Hardcodes /tmp Path

**Severity:** MEDIUM
**Likelihood:** HIGH (confirmed by codebase inspection)
**Phase to address:** PDF infrastructure phase.

**What goes wrong:**
`.env` defines `STORAGE_PATH=/tmp/ag-vote` and `docker-compose.yml` mounts a named volume `app-storage` at `/tmp/ag-vote` — so the data loss risk from container restarts is already mitigated in the Docker deployment. However, `MeetingAttachmentController.php` hardcodes `/tmp/ag-vote/uploads/meetings/` directly (lines 63 and 118) instead of reading `STORAGE_PATH` from the environment.

This means:
1. Changing the storage path for a non-Docker deployment (Render, bare metal, different hosting) requires modifying PHP source code rather than an env var
2. The delete endpoint (`line 118`) and upload endpoint (`line 63`) are not in sync with the env-configured path if someone overrides `STORAGE_PATH`
3. The `meeting_generate_report_pdf.php` endpoint and other services use the same pattern — the storage path is scattered across multiple PHP files rather than centralized

**Why it matters:**
Self-hosted deployment is a core value proposition of AG-VOTE. Operators on non-Docker hosting who set `STORAGE_PATH` to their actual persistent disk path get no benefit — the PHP code ignores it.

**Mitigation:**
1. Add a PHP constant or config function that reads `STORAGE_PATH` from the environment with a default fallback:
   ```php
   define('AG_STORAGE_PATH', rtrim(getenv('STORAGE_PATH') ?: '/tmp/ag-vote', '/'));
   ```
2. Replace all hardcoded `/tmp/ag-vote/` strings in PHP controllers with `AG_STORAGE_PATH . '/'`.
3. Do this in the PDF infrastructure phase, before adding the file serving endpoint (which would otherwise also hardcode the path).

**Detection:**
- `grep -rn "tmp/ag-vote" app/` returns results → paths are hardcoded, not env-driven

---

### Pitfall 9: PDF.js Bundle Weight — Loaded on Every Page vs. Lazy Loading

**Severity:** MEDIUM
**Likelihood:** HIGH

**What goes wrong:**
`pdfjs-dist` at version 5.5.207 is a significant bundle (exact minified+gzipped size unconfirmed but substantial — v3 was ~262 kB minified). On a vanilla JS project with no bundler (AG-VOTE uses per-page JS files, no webpack/vite), including PDF.js in a `<script>` tag at the shell or layout level loads the entire library even on pages where no PDF will be shown.

The PDF viewer is only needed on: the voter view (to show resolution documents during voting), the wizard/hub (to preview uploaded attachments before sessions). Loading it on the dashboard, settings, users, admin, and archives pages is pure waste.

**Mitigation:**
1. Do NOT add PDF.js to the shell or any shared layout. Load it only in the `<head>` of specific pages that use it (`vote.htmx.html`, `hub.htmx.html`, wizard).
2. Use `defer` attribute on the script tag to avoid blocking page render: `<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@5.5.207/build/pdf.min.mjs" type="module" defer></script>`.
3. Set `workerSrc` to a matching CDN-pinned version for the worker to avoid a second uncached network request.
4. Initialize PDF.js only when the "view PDF" action is triggered — not on page load. The CDN script loads in the background while the user reads the page.
5. Measure: run Lighthouse on the voter view before and after adding PDF.js. Target: no regression in Total Blocking Time or Largest Contentful Paint.

**Confidence:** MEDIUM — exact v5.x gzipped size not confirmed; pattern recommendation is well-established.

**Detection:**
- PDF.js `<script>` appears in shell.js, layout template, or any page that does not show PDFs
- Lighthouse TBT regression vs. v3.0 baseline on non-PDF pages

---

### Pitfall 10: Malicious PDF Serving — Missing Security Headers for the Serve Endpoint

**Severity:** MEDIUM
**Likelihood:** MEDIUM
**Phase to address:** PDF infrastructure phase, alongside building the serve endpoint.

**What goes wrong:**
When building `meeting_attachment_serve.php` (see Pitfall 2), the response headers matter as much as the content. If the serve endpoint does not explicitly set the correct headers, browsers may attempt to re-sniff the content type or execute embedded scripts. Additionally:
- If the Content Security Policy on the viewer page allows `object-src: 'self'`, a `<object>` or `<embed>` tag showing the PDF bypasses PDF.js entirely and uses the browser's built-in renderer — which may not honour `isEvalSupported: false`
- The nginx `add_header` inheritance rule may cause PHP-set headers to be overridden or vice versa — must be verified with `curl -I`

**Mitigation:**
The serve endpoint must set these headers in PHP before calling `readfile()`:
```php
header('Content-Type: application/pdf');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . $safeFilename . '"');
header('Cache-Control: private, no-store, no-cache');
header('X-Frame-Options: SAMEORIGIN');
```
The filename in `Content-Disposition` must be sanitized: strip path separators, non-printable chars. Never use the raw `original_name` from the database directly in a header value.

Do not use `<object>` or `<embed>` to display PDFs — use PDF.js rendering into a `<canvas>`. This ensures all PDF rendering goes through the patched library.

Verify final headers with `curl -I https://app/api/v1/meeting_attachment_serve.php?id=test` before closing the phase.

---

### Pitfall 11: Guided Tour Library — Shepherd.js Requires Commercial License for v12+

**Severity:** MEDIUM
**Likelihood:** MEDIUM
**Phase to address:** When implementing the guided tour feature.

**What goes wrong:**
If v4.0's guided tour is implemented using Shepherd.js (the most common vanilla JS tour library in search results), Shepherd.js v12+ changed its license model. Commercial use requires a paid license. AG-VOTE is open-source, but the license terms must be verified for the specific usage type before committing to it.

**Recommendation:**
Use Driver.js (`driver.js` on npm). It is:
- MIT licensed (confirmed from GitHub repository)
- Zero external dependencies
- Smallest bundle among serious contenders (confirmed from 2026 comparison research)
- Written in TypeScript with vanilla JS output
- Actively maintained with regular 2026 updates
- Supports spotlight highlighting, step-based tours, contextual overlay

For single-element contextual hints (tooltips that appear without a full tour), use the existing `ag-popover` Web Component already in the codebase — zero additional bundle weight.

Load Driver.js only on pages that use guided tours (same lazy-loading discipline as PDF.js).

**Confidence:** MEDIUM — Shepherd.js license change confirmed for v12+, Driver.js MIT status confirmed from GitHub.

**Sources:**
- [driver.js — driverjs.com](https://driverjs.com) — MIT, actively maintained
- [Shepherd.js comparison](https://userorbit.com/blog/best-open-source-product-tour-libraries) — MEDIUM confidence

---

### Pitfall 12: CSS Animation Performance on Lower-End Devices

**Severity:** MEDIUM
**Likelihood:** MEDIUM
**Phase to address:** Any phase introducing new animations, transitions, or guided tour spotlights.

**What goes wrong:**
v4.0 targets "top 1% UI," which typically means more animation: entrance transitions, staggered list reveals, micro-interactions, guided tour spotlight animations. On developer machines, these run at 60fps. On lower-end devices — and the voter screen, which must work on phones — excessive CSS animations cause jank, battery drain, and GPU memory issues.

**Current state:** `design-system.css` already has a global `prefers-reduced-motion: reduce` media query at line 2491 that sets `animation-duration: 0.01ms` and `transition-duration: 0.01ms` on `*`, `*::before`, `*::after`. This is the correct foundation. The risk is new animations added in v4.0 that bypass this or create GPU layer bloat via `will-change`.

**Why it happens:**
CSS `will-change: transform` used indiscriminately — every animated element gets its own GPU compositor layer. Overuse floods GPU memory. Each `transform` or `opacity` animation creates a stacking context.

**Mitigation:**
1. The existing `prefers-reduced-motion` block is correct — do not modify it. New animations added in v4.0 will automatically be suppressed for users with that preference.
2. Restrict `will-change` to elements that are actively transitioning. Apply via JavaScript immediately before animation starts and remove it immediately after. Never apply it in CSS to elements that are always in the DOM.
3. For guided tour spotlights, use CSS `clip-path` or `box-shadow` expansion — these properties are cheaper on the compositor than scaling elements.
4. Test the voter screen (mobile-priority per PROJECT.md) on Chrome DevTools CPU throttling (6x slowdown) before each phase ships.
5. Performance budget: no animation should cause >3 dropped frames on 6x CPU throttle in DevTools Performance panel.

**Sources:**
- [GPU Animation — Smashing Magazine](https://www.smashingmagazine.com/2016/12/gpu-animation-doing-it-right/) — well-established rendering behavior, HIGH confidence

---

### Pitfall 13: PC-First Design Shift Breaking the Voter Screen

**Severity:** MEDIUM
**Likelihood:** LOW-MEDIUM
**Phase to address:** Any phase that modifies shared Web Components or `design-system.css`.

**What goes wrong:**
v4.0 shifts to PC-first design (1024px+). The voter screen (`vote.htmx.html`) is explicitly "mobile only for voter screen (in-room voting on phone)" per PROJECT.md. This creates a bifurcation risk: developers working in PC-first mode add fixed-width containers, large padding, multi-column layouts, and desktop-only interactive patterns — and one of these changes lands on a shared Web Component (`ag-modal`, `ag-toast`, `ag-confirm`, `ag-popover`) that breaks at 375px without anyone noticing during PC-first development.

**Mitigation:**
1. Explicitly document the voter screen as a mobile-only exception in every phase plan that touches shared components.
2. The existing `mobile-viewport.spec.js` E2E test covers a 375x812 viewport vote page test — verify it runs on every phase that modifies shared components.
3. In CSS, comment any shared component that gets PC-first changes: `/* VOTER SCREEN: verify at 375px */`.
4. The voter screen's CSS (`vote.css`) should maintain a `max-width: 480px` layout boundary that is explicitly tested.

**Detection:**
- Voter screen visually breaks at 375px viewport after any phase that touched shared components

---

## Minor Pitfalls

### Pitfall 14: WCAG Regression During Redesign — Focus Rings and ARIA Landmark Loss

**What goes wrong:**
v3.0 achieved WCAG AA compliance (per PROJECT.md: skip links, ARIA landmarks, focus indicators). When pages are fully redesigned from scratch, skip links can be accidentally omitted, ARIA roles on new layouts may be wrong or missing, and focus rings can be lost when new component CSS conflicts with the existing pattern.

**Current state confirmed:** The existing `.btn:focus-visible` pattern correctly sets `outline: none` and replaces it with a `box-shadow` focus ring using `--ring-color` and `--ring-offset` tokens. New components in v4.0 must follow this exact pattern.

**Prevention:**
- Treat WCAG compliance as a per-phase deliverable. Every redesigned page must have: `<a href="#main-content">` skip link, `<main>`, `<nav>`, `<header>`, `<footer>` landmarks, `:focus-visible` ring on all interactive elements.
- Run `axe-core` via Playwright on each completed page before marking the phase done.
- Never suppress `outline` on focusable elements without replacing it with an equivalent `box-shadow` focus indicator.

---

### Pitfall 15: Import CSV Backward Compatibility for voting_power Column Aliases

**What goes wrong:**
If copropriété transformation removes `'tantiemes'` from the column alias list in `ImportService.php` line 237, any tenant importing member CSVs with a "Tantièmes" column header will silently lose their voting weight data after the upgrade. The import succeeds (no error), but `voting_power` defaults to 1.0 for all members.

**Prevention:**
Keep `'tantiemes'` and `'tantièmes'` as accepted aliases in `ImportService.php` indefinitely. The alias is internal to import parsing, completely invisible to users. Only the UI label needs to change.

---

### Pitfall 16: The Settings "Distribution Key" Modal Is a Stub — Safe to Delete But Requires Verification First

**What goes wrong:**
`settings.js` line 407 defines `openKeyModal()`, which renders a "Clé de répartition" modal with a tantièmes/lots selector. The `onConfirm` callback only calls `AgToast.show(...)` — it does not call any API. This is UI scaffolding that was never backed by a real endpoint.

Before deleting it, the discovery must be confirmed. Direct inspection of `public/api/v1/` shows no `distribution_keys.php`, `admin_distribution_keys.php`, or similar endpoint. The modal is confirmed as a complete stub — safe to remove as part of copropriété transformation.

**Prevention:**
Run `ls public/api/v1/ | grep -i "distribution\|key\|copro"` before deleting the modal. If the result is empty (as confirmed today), the modal is safe to remove.

---

## Security Checklist

| Item | Risk If Missed | Current Status |
|------|---------------|----------------|
| pdfjs-dist >= 4.2.67 | Arbitrary JS execution in any browser viewing a malicious PDF | Not yet installed (PDF feature not built) |
| `isEvalSupported: false` in PDF.js getDocument() | Exploit path for CVE-2024-4367 and related font bugs | Not yet configured |
| Authenticated file serve endpoint | Direct URL access to uploaded PDFs by unauthenticated users | Endpoint does not exist yet |
| `Content-Type: application/pdf` on serve endpoint | MIME sniffing attack — browser tries to execute embedded content | Not yet built |
| `Content-Disposition` filename sanitized in response header | Path traversal or header injection via crafted filename | Not yet built |
| STORAGE_PATH env var read by PHP controller | Hardcoded path ignores env var; breaks non-Docker deployment | Not done — controller hardcodes `/tmp/ag-vote` |
| Rate limiting on serve endpoint | Bulk PDF download by unauthorized parties via enumeration | Not yet done |
| Do not use `<object>` or `<embed>` for PDF display | Bypasses PDF.js, uses browser native renderer (may not honour isEvalSupported) | Not yet built |
| PDF filename in DB `original_name` is sanitized on upload | SQL injection or filename injection | Already done: `basename($file['name'])` in controller |
| MIME type checked via `finfo` not file extension | Content type forgery via `.pdf` extension on non-PDF file | Already done: `finfo(FILEINFO_MIME_TYPE)` check in controller |
| File extension double-checked | `.php.pdf` or similar polyglot bypass | Already done: extension check + MIME check combined |
| File size limit enforced | Server disk exhaustion via large PDF upload | Already done: 10 MB limit in controller |
| Tenant isolation on serve endpoint | Tenant A viewing Tenant B's meeting attachments | Not yet built — must be enforced in the serve endpoint |

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| PDF.js in shell/layout | 500+ kB loaded on dashboard, settings, admin pages | Load only on pages that use it, with `defer` | First deploy with PDF.js in a shared script |
| `will-change: transform` on many elements simultaneously | GPU layer explosion, browser memory spike, jank on mobile | Apply only immediately before animation, remove after | When guided tour highlights >5 elements with animation at once |
| PDF canvas rendering + CSS animations simultaneously | Voter screen drops frames while PDF loads | Use `prefers-reduced-motion`, stagger animations, load PDF async | When voter views a resolution PDF while a vote opens |
| Guided tour library bundled globally | Extra weight on first load for users who never see the tour | Lazy-import on first tour trigger, or use ag-popover for simple hints | If Driver.js is in the page `<head>` unconditionally |
| No phase performance baseline | Cannot detect regressions | Run Lighthouse on voter view and wizard before any v4.0 phase begins | Entire milestone |

---

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|---------------|------------|
| PDF infrastructure | No serve endpoint — viewer has no authenticated source URL | Build `meeting_attachment_serve.php` before building any viewer UI |
| PDF infrastructure | pdfjs-dist version not pinned | Pin to >= 4.2.67 (latest: 5.5.207) in initial install |
| PDF infrastructure | STORAGE_PATH env var not read by PHP | Replace hardcoded `/tmp/ag-vote` with env-driven constant in same phase |
| PDF infrastructure | Missing tenant isolation on serve endpoint | Verify tenant_id match before serving file |
| PDF infrastructure | `<object>` or `<embed>` used for PDF display | Use PDF.js canvas rendering exclusively |
| Guided UX implementation | Tour buttons are HTML stubs — zero JS wiring | Decide: implement with Driver.js or replace with ag-popover hints |
| Guided UX implementation | Tour library license (Shepherd.js v12+) | Use Driver.js (MIT) instead |
| Guided UX implementation | Tour library loaded on all pages | Lazy-import only on pages with guided tours |
| Copropriété transformation | Over-deletion of voting_power logic | Feature inventory before deletion, PHPUnit test for weighted vote tally |
| Copropriété transformation | Remove tantiemes CSV alias breaks import backward compat | Keep alias in ImportService.php permanently |
| Design system tokens | Dark mode token missing for new components | Two-token rule: every `:root` token gets `[data-theme="dark"]` companion |
| PC-first redesign | Voter screen mobile breakage via shared components | Run mobile-viewport.spec.js after every phase touching shared components |
| "Top 1% UI" | No objective done criteria | Define measurable criteria (contrast, CLS, transition timing, token purity) before first phase |
| Any full page redesign | Feature parity gaps (actions removed in new design) | Feature inventory from v3.0 as parity checklist before designing |
| Any CSS rewrite | WCAG regression (focus rings, ARIA landmarks) | axe-core Playwright check per page before phase closes |
| Any CSS rewrite | outline:none without box-shadow replacement | Follow existing .btn:focus-visible pattern — outline:none + box-shadow ring |

---

## Sources

- Direct codebase inspection: `app/Controller/MeetingAttachmentController.php` — upload handler with finfo MIME check, 10 MB limit, hardcoded /tmp/ag-vote storage path (HIGH confidence)
- Direct codebase inspection: `public/api/v1/meeting_attachments.php` — GET/POST/DELETE only, no serve endpoint (HIGH confidence)
- Direct codebase inspection: `public/assets/css/design-system.css` lines 2491-2500 — prefers-reduced-motion already globally implemented (HIGH confidence)
- Direct codebase inspection: `public/assets/css/design-system.css` lines 1102-1106 — outline:none correctly replaced with box-shadow focus ring (HIGH confidence)
- Direct codebase inspection: `public/assets/css/design-system.css` lines 309-364 — dark theme token block (HIGH confidence)
- Direct codebase inspection: `wizard.htmx.html`, `postsession.htmx.html`, `members.htmx.html` — btnTour/data-tour HTML stubs with zero JS wiring (HIGH confidence)
- Direct codebase inspection: `app/Services/ImportService.php` line 237 — tantièmes is CSV alias, not separate feature (HIGH confidence)
- Direct codebase inspection: `public/assets/js/pages/settings.js` line 407-419 — openKeyModal is a stub, no API call (HIGH confidence)
- Direct codebase inspection: `public/assets/js/core/shell.js` line 683 — only UI string using "copropriétaires" (HIGH confidence)
- Direct codebase inspection: `.env` line 61, `docker-compose.yml` line 48 — STORAGE_PATH defined, named volume mounted, but PHP hardcodes path (HIGH confidence)
- Direct codebase inspection: `database/migrations/20260219_meeting_attachments.sql` — schema has tenant_id, meeting_id, stored_name columns (HIGH confidence)
- [CVE-2024-4367 — Arbitrary JavaScript execution in PDF.js (Codean Labs)](https://codeanlabs.com/blog/research/cve-2024-4367-arbitrary-js-execution-in-pdf-js/) — HIGH confidence
- [GitHub Advisory GHSA-wgrm-67xf-hhpq](https://github.com/advisories/GHSA-wgrm-67xf-hhpq) — HIGH confidence
- [Snyk — pdfjs-dist vulnerabilities](https://security.snyk.io/package/npm/pdfjs-dist) — pdfjs-dist 5.5.207 current as of March 2026, HIGH confidence
- [pdfjs-dist npm page](https://www.npmjs.com/package/pdfjs-dist) — version 5.5.207 confirmed, MEDIUM confidence (exact gzipped size not confirmed)
- [driver.js — driverjs.com](https://driverjs.com) — MIT licensed, actively maintained, zero dependencies, MEDIUM confidence
- [Best Open-Source Product Tour Libraries — Userorbit](https://userorbit.com/blog/best-open-source-product-tour-libraries) — Driver.js vs Shepherd.js comparison, MEDIUM confidence
- [Progressive Disclosure — Nielsen Norman Group](https://www.nngroup.com/articles/progressive-disclosure/) — canonical UX reference, HIGH confidence
- [GPU Animation — Smashing Magazine](https://www.smashingmagazine.com/2016/12/gpu-animation-doing-it-right/) — will-change overuse causes GPU layer explosion, HIGH confidence
- [WCAG color contrast guidance](https://www.allaccessible.org/blog/color-contrast-accessibility-wcag-guide-2025/) — 4.5:1 for normal text, 3:1 for large text, HIGH confidence
- [OWASP Unrestricted File Upload](https://owasp.org/www-community/vulnerabilities/Unrestricted_File_Upload) — MIME + extension check pattern confirmed as already implemented correctly, HIGH confidence

---

*Pitfalls research for: AG-VOTE v4.0 "Clarity & Flow" — UX overhaul + PDF upload/viewer + copropriété transformation + design system migration*
*Researched: 2026-03-18*
*Previous PITFALLS.md content was v4.0-focused but has been updated with codebase verification corrections and new findings from direct inspection.*
