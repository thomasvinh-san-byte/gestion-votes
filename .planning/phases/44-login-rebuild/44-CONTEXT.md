# Phase 44: Login Rebuild - Context

**Gathered:** 2026-03-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Complete ground-up rebuild of the Login page — new HTML structure, new CSS, JS verified and updated, auth flow wired end-to-end, field validation working. Top 1% entry point quality targeting Stripe/Clerk reference level.

</domain>

<decisions>
## Implementation Decisions

### Layout & Visual Impact
- Centered card on gradient background — refined with better gradient and subtle patterns (Clerk-style)
- 420px card with elevated depth — stronger shadow, subtle border glow on focus-within
- Animated gradient orb — subtle moving radial gradient behind card (Clerk/Vercel style) for visual polish
- Logo mark (48px) + wordmark + tagline — current structure refined with larger logo mark and tighter spacing

### Form UX & Interactions
- Floating labels — label starts as placeholder, floats above on focus/filled (Stripe style), modern and space-efficient
- Password visibility toggle only — keep current eye icon toggle, clean and sufficient
- Full-width gradient button with micro-interaction — subtle scale on hover, loading spinner replaces text, success checkmark animation on login
- Inline per-field errors + banner — field-level red borders AND error banner above submit, polished styling

### Polish & Trust Elements
- Demo credentials: collapsible panel below card — auto-shown in demo/dev env, compact role buttons that auto-fill, restyled
- Trust signal: subtle lock icon + text below card — refined typography and spacing
- Footer: "Retour à l'accueil" link + theme toggle + subtle version number
- Dark mode: full parity — dark surface card, darker input bg, cooler gradient orb tones, vivid button stays

### Claude's Discretion
- Exact HTML structure and element hierarchy
- CSS class naming (can rename if cleaner)
- Whether to refactor login.js or just update selectors
- Floating label implementation details (CSS-only vs JS-assisted)
- Gradient orb animation specifics (keyframe timing, colors)
- Exact responsive breakpoint behavior

</decisions>

<canonical_refs>
## Canonical References

### Current files (to be rewritten)
- `public/login.html` — Current login HTML
- `public/assets/css/login.css` — Login page styles
- `public/assets/js/pages/login.js` — Login JS (form submission, validation, auth redirect)
- `public/assets/js/pages/login-theme-toggle.js` — Theme toggle for login page

### Design system
- `public/assets/css/app.css` — Global styles and design tokens
- `public/assets/css/design-system.css` — Tokens, components, utilities

### Backend
- `public/api/v1/auth_login.php` — Login API endpoint
- `public/api/v1/whoami.php` — Session check endpoint

</canonical_refs>

<code_context>
## Existing Code Insights

### JS Dependencies (MUST preserve)
- login.js uses getElementById for form, email, password, errorBox, successBox, submitBtn, togglePassword, loginSpinner
- Form submission via `api()` helper from utils.js — POST to `/api/v1/auth_login.php`
- Auto-redirect on already-logged-in via `/api/v1/whoami.php`
- Role-based redirect logic (admin→admin, operator→meetings, president→operator, voter→vote)
- Demo hint auto-shown when `app_env === 'demo' || 'development'`
- Safe redirect validation for `?redirect=` param
- Field-level error states via `.field-error` class

### What Changes
- ALL HTML structure — new layout with floating labels, refined card
- ALL CSS rules — new styles for animated gradient, floating labels, micro-interactions
- JS selectors updated to match new HTML — verify all DOM queries work
- Demo hint restyled as collapsible panel below card

### What Must NOT Break
- Auth flow: email/password → API → redirect by role
- Auto-redirect when already logged in
- Demo mode detection and auto-fill
- Password visibility toggle
- Theme toggle (light/dark)
- Error/success message display
- Safe redirect parameter handling

</code_context>

<specifics>
## Specific Ideas

- The login page is the first impression — must feel like a premium product
- Floating labels give a modern, space-efficient feel (Stripe checkout reference)
- Animated gradient orb adds subtle life without being distracting
- Dark mode should feel intentionally designed, not just inverted

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 44-login-rebuild*
*Context gathered: 2026-03-20*
