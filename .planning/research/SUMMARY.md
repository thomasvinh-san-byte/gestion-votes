# Research Summary: AgVote v1.1 — Coherence UI/UX et Wiring

**Synthesized:** 2026-04-07
**Sources:** STACK.md, FEATURES.md, ARCHITECTURE.md, PITFALLS.md

## Executive Summary

AgVote v1.1 is a correctness and coherence milestone on a shipped product, not greenfield work. The application already has a complete custom CSS design system (`design-system.css`, 5,278 lines, OKLCH palette, `@layer` structure), an 18-spec Playwright E2E suite with multi-browser and role-based auth injection, and a stable PHP 8.4 backend. The v1.0 milestone delivered Redis infrastructure and test hardening; v1.1's job is to make the front-end look and behave like the professional tool the back-end already is. The problem is not missing infrastructure — it is broken wiring and inconsistent application of what already exists.

The recommended approach is dependency-ordered: audit and repair the JS/HTML contract that v4.2 broke first (DOM ID contracts, sidebar async timing, custom element placement), then apply design tokens page-by-page with a named visible deliverable per phase, then expand Playwright interaction coverage to catch future regressions. The only genuine stack addition needed is upgrading Playwright to 1.59.1 and adding `@axe-core/playwright` ^4.10. No CSS framework change is warranted.

The dominant risk is repeating v4.2: restructuring HTML for visual reasons and silently breaking the 1,269 `querySelector`/`getElementById` calls across all page JS files. A JS audit phase producing an explicit ID contract inventory must precede any HTML restructuring. A secondary risk is delivering CSS token infrastructure with no visible page results — every design phase must ship at least one named, visually verifiable page.

## Stack Additions

| Addition | Version | Rationale |
|----------|---------|-----------|
| Playwright upgrade | 1.58.2 → 1.59.1 | Bug fixes, improved selectors |
| @axe-core/playwright | ^4.10 | Accessibility auditing in E2E tests |

**No new CSS frameworks.** The existing design-system.css is complete. Tailwind, Bootstrap, etc. would conflict with the existing OKLCH token architecture and HTMX server-driven model.

**HTMX 2.0 deferred.** Breaking `hx-on` case changes require full audit. The broken wiring is application-layer bugs (DOM selectors, event handlers), not an HTMX version problem.

## Feature Table Stakes (P1 — Required)

- JS/fetch wiring repair across all pages
- Design token consistency on all CSS files
- Login 2-panel layout (branding left, form right, `<768px` responsive)
- Playwright baseline green with `waitForHtmxSettled()` helper
- Loading state CSS for `.htmx-request`

## Feature Polish (P2 — When P1 Stable)

- Toast notification system
- Role-specific sidebar nav items
- Dark mode parity audit
- Operator workflow E2E coverage

## Feature Deferred (v1.2+)

- Horizontal-first layout refactor
- Onboarding tip propagation
- HTMX 2.0 upgrade

## Top 5 Pitfalls

1. **HTML restructuring silently breaks JS** — v4.2 confirmed: `vote.js:852` targets `getElementById('voteButtons')` but only `class="vote-buttons"` exists. **Mitigation:** ID contract inventory before any HTML changes.
2. **CSS token infrastructure with no visible page output.** **Mitigation:** every phase must name specific pages as deliverables.
3. **`ag-modal`/`ag-pdf-viewer` overlays clipped** when layout adds `overflow:hidden` ancestors. **Mitigation:** verify custom elements append to `document.body`.
4. **Playwright happy-path tests pass while all buttons are broken.** **Mitigation:** every page spec requires one click + DOM state change assertion.
5. **Per-page CSS specificity wars block design system rollout.** **Mitigation:** audit hardcoded values before applying tokens; use `@layer pages` for page overrides.

## Architecture Key Insight

"HTMX app" is misleading — it's actually vanilla JS + `fetch()`. Despite `.htmx.html` naming, only 2 pages (`postsession.htmx.html`, `vote.htmx.html`) include `htmx.min.js`. All other pages use imperative `fetch()` calls in `public/assets/js/pages/{page}.js`. "Fix HTMX wiring" means fixing broken fetch handlers and DOM selectors in those JS files.

## Suggested Phase Order

1. **JS Audit and Wiring Repair** — ID contract inventory, repair fetch/event handler breakage, sidebar partial contract, `waitForHtmxSettled()` helper
2. **Login Page + Design Token Rollout** — 2-panel login as first fully redesigned page, validates token approach
3. **Dashboard + Core Pages Design Token Application** — token rollout to dashboard, hub, meetings; loading states, status badges
4. **Playwright Interaction Coverage Expansion** — interaction tests for all P1 pages; operator workflow E2E
5. **Polish** — dark mode parity, role nav, toasts

**Phase ordering rationale:** Phase 1 (JS Audit) is mandatory before Phase 2+ because any HTML change before the contract is known risks repeating v4.2. Login before dashboard because it has the least JS complexity and validates the rollout approach.

---
*Synthesized: 2026-04-07*
