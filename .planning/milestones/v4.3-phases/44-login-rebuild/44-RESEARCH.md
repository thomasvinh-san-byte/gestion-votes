# Phase 44: Login Rebuild - Research

**Researched:** 2026-03-20
**Domain:** CSS animations, floating labels, login UX patterns, vanilla JS auth wiring
**Confidence:** HIGH

## Summary

Phase 44 is a ground-up visual and UX rebuild of `public/login.html`, `public/assets/css/login.css`, and `public/assets/js/pages/login.js`. The structural skeleton and backend wiring already work correctly — the API endpoint (`/api/v1/auth_login.php`), the `api()` helper in `utils.js`, the redirect-by-role logic, and the demo-hint autofill are all verified functional. The rebuild scope is cosmetic-and-interaction: new HTML structure for floating labels, expanded card (400px → 420px), animated gradient orb, stronger shadows, per-field error states, and micro-interactions on the submit button.

The project uses a rich vanilla CSS design system (`design-system.css`) with a full token vocabulary covering shadows, transitions, easings, colors, and spacing. All new CSS must consume these tokens — no raw values. The project has no test framework (nyquist_validation key absent from config; treat as enabled, but manual browser test is the validation gate for a login page).

**Primary recommendation:** Write new HTML with floating-label field-wrappers first, then rewrite login.css targeting those new class names, then update login.js to match the new DOM selectors. Preserve the existing JS logic verbatim — only selector updates are needed.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Centered card on gradient background — refined with better gradient and subtle patterns (Clerk-style)
- 420px card with elevated depth — stronger shadow, subtle border glow on focus-within
- Animated gradient orb — subtle moving radial gradient behind card (Clerk/Vercel style) for visual polish
- Logo mark (48px) + wordmark + tagline — current structure refined with larger logo mark and tighter spacing
- Floating labels — label starts as placeholder, floats above on focus/filled (Stripe style), modern and space-efficient
- Password visibility toggle only — keep current eye icon toggle, clean and sufficient
- Full-width gradient button with micro-interaction — subtle scale on hover, loading spinner replaces text, success checkmark animation on login
- Inline per-field errors + banner — field-level red borders AND error banner above submit, polished styling
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

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| REB-02 | Login — complete HTML+CSS rewrite, auth flow wired, field validation, top 1% entry point | All existing JS logic is sound; floating label HTML pattern, orb animation, micro-interactions documented below |
| WIRE-01 | Every rebuilt page has verified API connections — no dead endpoints, no mock data, no broken HTMX targets | Backend endpoints and `api()` call patterns audited and documented; redirect-by-role logic preserved |
</phase_requirements>

---

## Standard Stack

### Core
| Library/Token | Version | Purpose | Why Standard |
|---------------|---------|---------|--------------|
| `design-system.css` | 2.0 (project) | All CSS tokens — colors, spacing, shadows, transitions | Project rule: no raw values in component CSS |
| `app.css` | project | Entrypoint that imports design-system and pages.css | All pages link only app.css + page-specific CSS |
| `login.css` | rewrite | Page-specific overrides on top of design system | Already established pattern |
| `utils.js` → `api()` | project | Authenticated fetch with CSRF + timeout | Used by all pages; login.js already calls it correctly |
| `login-theme-toggle.js` | project | Listens on `#btnTheme`, writes `data-theme` attribute | 10 lines; keep as-is |
| `theme-init.js` | project | Must be first `<script>` in `<head>` — prevents FOUC | Already in HTML; do not move |

### Floating Label Pattern
Floating labels require a specific HTML wrapper structure. CSS-only approach using `:placeholder-shown` + sibling combinator is preferred (no JS needed for the float state):

```html
<div class="field-group">
  <input type="email" id="email" name="email"
         class="field-input" placeholder=" " required autocomplete="email">
  <label for="email" class="field-label">Adresse email</label>
  <!-- field-error-msg placed here -->
  <span class="field-error-msg" id="email-error"></span>
</div>
```

**Key:** `placeholder=" "` (single space) is required — `:placeholder-shown` is false when a space placeholder is not shown (i.e., user has typed), enabling the CSS float. The label is placed AFTER the input in DOM order so the `~` sibling combinator works.

### Animation Approach — Gradient Orb
CSS `@keyframes` with `animation` on a pseudo-element or dedicated `<div>`. No JS needed. Use `pointer-events: none` and `position: fixed` / `position: absolute` with `z-index` below card.

```css
@keyframes orb-drift {
  0%   { transform: translate(-50%, -50%) scale(1); opacity: 0.45; }
  50%  { transform: translate(-50%, -48%) scale(1.08); opacity: 0.55; }
  100% { transform: translate(-50%, -50%) scale(1); opacity: 0.45; }
}
.login-orb {
  position: fixed;
  top: 40%;
  left: 50%;
  width: 600px;
  height: 600px;
  border-radius: 50%;
  background: radial-gradient(circle, var(--color-primary-glow) 0%, transparent 70%);
  animation: orb-drift 8s var(--ease-emphasized) infinite;
  pointer-events: none;
  z-index: 0; /* card must be z-index: 1+ */
}
```

Dark mode: `--color-primary-glow` is `rgba(61, 126, 248, 0.16)` in dark theme — cooler blue automatically via token.

### Submit Button Micro-Interactions
The success checkmark animation uses an SVG injected/swapped via JS after successful login. Spinner is already implemented with `border-top-color: currentColor` spin. New additions:
- Scale on hover: `transform: scale(1.01)` or `translateY(-1px)` (already in current CSS)
- Loading state: disable button, swap text → spinner (already works in current JS)
- Success state: swap text → "Connecte" + inject checkmark SVG, then redirect after 600ms delay (already in current JS logic; visually enhance with CSS animation)

---

## Architecture Patterns

### Recommended File Structure (no changes to file locations)
```
public/
├── login.html                        # Rewrite: new floating-label structure
├── assets/css/login.css              # Rewrite: all new selectors, orb, floating labels
└── assets/js/pages/
    ├── login.js                      # Update: selector names only; preserve all logic
    └── login-theme-toggle.js         # Keep as-is (10 lines, already correct)
```

### HTML Structure Pattern

```html
<body>
  <div class="login-orb" aria-hidden="true"></div>  <!-- animated gradient orb -->

  <main class="login-page" role="main">
    <div class="login-card">
      <!-- Brand -->
      <div class="login-brand">
        <div class="login-brand-mark"><!-- 48px SVG icon --></div>
        <h1>AG-VOTE</h1>
        <p class="login-tagline">Gestion des assemblées délibératives</p>
      </div>

      <!-- Form -->
      <form class="login-form" id="loginForm" ...>
        <div class="field-group" id="emailGroup">
          <input type="email" id="email" class="field-input" placeholder=" " ...>
          <label for="email" class="field-label">Adresse email</label>
          <span class="field-error-msg" id="emailError"></span>
        </div>

        <div class="field-group" id="passwordGroup">
          <div class="field-input-wrap">
            <input type="password" id="password" class="field-input" placeholder=" " ...>
            <button type="button" id="togglePassword" class="field-eye" ...>
              <!-- eye SVGs -->
            </button>
          </div>
          <label for="password" class="field-label">Mot de passe</label>
          <span class="field-error-msg" id="passwordError"></span>
        </div>

        <div id="errorBox" class="login-error" role="alert" aria-live="assertive" tabindex="-1"></div>
        <div id="successBox" class="login-success" role="status" aria-live="polite"></div>

        <button type="submit" class="login-btn" id="submitBtn">
          <span class="login-btn-text">Se connecter</span>
          <span class="login-spinner" id="loginSpinner" aria-hidden="true"></span>
        </button>
      </form>

      <!-- Trust -->
      <div class="login-trust"><!-- lock SVG + text --></div>

      <!-- Footer -->
      <div class="login-footer">
        <a href="/index.html">Retour à l'accueil</a>
        <button type="button" id="btnTheme" class="login-theme-toggle" ...><!-- sun/moon SVGs --></button>
        <span class="login-version">v4.3</span>
      </div>
    </div><!-- .login-card -->

    <!-- Demo credentials panel (outside card, below) -->
    <div id="demoPanel" class="demo-panel" hidden><!-- injected by JS --></div>
  </main>
</body>
```

### Floating Label CSS Pattern

```css
/* Field group wrapper */
.field-group {
  position: relative;
  margin-bottom: var(--space-1); /* error msg needs space below */
}

/* Input — padding-top creates space for floated label */
.field-input {
  width: 100%;
  height: 52px; /* taller than current 44px — breathing room for floating label */
  padding: 18px var(--space-3) 6px;
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: var(--text-md); /* 16px — prevents iOS zoom */
  background: var(--color-bg);
  color: var(--color-text);
  transition: border-color var(--duration-fast) var(--ease-standard),
              box-shadow var(--duration-moderate) var(--ease-standard);
}

/* Label — initially centered (acts as placeholder) */
.field-label {
  position: absolute;
  left: var(--space-3);
  top: 50%;
  transform: translateY(-50%);
  font-size: var(--text-md); /* 16px when large */
  color: var(--color-text-muted);
  pointer-events: none;
  transition: transform var(--duration-deliberate) var(--ease-emphasized),
              font-size var(--duration-deliberate) var(--ease-emphasized),
              color var(--duration-deliberate) var(--ease-emphasized);
}

/* Float: triggered when focused OR when value exists (placeholder not shown) */
.field-input:focus ~ .field-label,
.field-input:not(:placeholder-shown) ~ .field-label {
  transform: translateY(-130%);
  font-size: var(--text-xs); /* 12px when floated */
  color: var(--color-text-secondary);
}
```

**IMPORTANT for password field:** The label is inside `.field-group` but the input is inside `.field-input-wrap`. Use the general sibling combinator carefully, or place the label inside `.field-input-wrap` as well — see Anti-Patterns section.

### Focus-within Card Border Glow

```css
.login-card:focus-within {
  border-color: var(--color-border-focus);
  box-shadow: var(--shadow-xl), 0 0 0 4px var(--color-primary-glow);
}
```

### Anti-Patterns to Avoid
- **Label before input in DOM:** The `~` CSS sibling combinator only works forward (input must precede label in DOM). Label must come AFTER input.
- **placeholder vs placeholder=" ":** Empty placeholder hides `:placeholder-shown` detection — the placeholder must be a single space character `" "`.
- **Password field label placement:** With `.field-input-wrap` nested inside `.field-group`, placing `<label>` as a sibling of `.field-input-wrap` means `~` doesn't reach the input. Solution: put the label inside `.field-input-wrap` or use JavaScript to toggle a `.has-value` class on `.field-group`. The simpler approach is JS-assisted: add `has-value` class to the group on input events and check `password.value.length > 0`.
- **Removing `@starting-style`:** The current card entrance animation uses `@starting-style` (Chrome 117+, Safari 17.4+). It is a good progressive enhancement — keep it.
- **Changing JS IDs without updating login.js:** login.js uses getElementById for: `loginForm`, `email`, `password`, `errorBox`, `successBox`, `submitBtn`, `loginSpinner`, `togglePassword`. If any of these IDs change, login.js MUST be updated in the same plan.
- **Removing `api()` call signature:** login.js calls `api('/api/v1/auth_login.php', { email, password })` — this global function is in utils.js. Do not change the endpoint path or payload shape.
- **Demo hint via `card.appendChild`:** Current JS dynamically appends demo hint to `.login-card`. New design places it outside the card as `#demoPanel`. The JS `showDemoHint()` function must be updated to target `#demoPanel` and use `removeAttribute('hidden')` instead of `appendChild`.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| CSS tokens | Raw hex/px values | Design system tokens (`--color-*`, `--space-*`, `--shadow-*`) | Dark mode, consistency, maintainability |
| Spinner | Custom spinner CSS | Existing `.login-spinner` pattern (border-top-color spin) | Already tested, accessible with aria-hidden |
| Password toggle | JS show/hide logic | Extend current toggle logic from login.js | Already works with eye-open/eye-closed SVG swap |
| CSRF handling | Manual header injection | `api()` from utils.js handles CSRF automatically | crypto.randomUUID idempotency key auto-added |
| Theme detection | `localStorage.getItem('ag-vote-theme')` | `theme-init.js` script in `<head>` | Already handles FOUC prevention |

**Key insight:** The login page's complexity is entirely in CSS and fine visual polish. The JS logic is solid — the rebuild is primarily HTML restructuring and CSS rewriting.

---

## Common Pitfalls

### Pitfall 1: Floating label occlusion with password toggle
**What goes wrong:** The eye button inside `.field-input-wrap` at absolute-right intersects the input's padding area. If the label is also inside the wrap, the absolute positioning calculations collide.
**Why it happens:** Multiple absolutely-positioned children in the same relative container without z-index or right-padding compensation.
**How to avoid:** Give the password input `padding-right: 48px` so typed text doesn't slide under the eye button. Position toggle button at `right: var(--space-3)`.
**Warning signs:** Text typed in password field disappears under the eye icon at longer password lengths.

### Pitfall 2: `:placeholder-shown` unreliable with autofill
**What goes wrong:** Browser autofill fills the input but the label doesn't float because `:placeholder-shown` may still be true under some browser autofill implementations.
**Why it happens:** Autofill bypasses normal input events; CSS sees the placeholder as still-shown.
**How to avoid:** Add JS input event listeners that add/remove a `.has-value` class — use this class as the float trigger alongside `:not(:placeholder-shown)`. For the password field, JS-assisted approach is required anyway (see anti-patterns above).
**Warning signs:** Autofilled email shows label overlapping value on page load.

### Pitfall 3: `border-glow on focus-within` fighting input focus ring
**What goes wrong:** `.login-card:focus-within` adds a box-shadow that visually competes with the input focus ring (`--shadow-focus`).
**Why it happens:** Two box-shadows on different elements both trying to be the most prominent visual indicator.
**How to avoid:** Make the card focus-within glow subtle (`0 0 0 3px var(--color-primary-glow)`) and keep the input's own focus ring stronger and closer. Use `outline: none` on input + custom `box-shadow` on input focus so only one ring is visible at a time.

### Pitfall 4: Animated orb causing layout shift or jank
**What goes wrong:** Animating `transform` on a large radial-gradient `<div>` causes repaints on some GPUs.
**Why it happens:** `transform` on its own should be GPU-composited, but `opacity` changes can still cause repaint if `will-change` is not set.
**How to avoid:** Add `will-change: transform, opacity` to `.login-orb`. Use `position: fixed` (not `absolute`) so the orb doesn't participate in document flow. Keep the animation duration slow (8–12s) to minimize CPU budget.

### Pitfall 5: Dark mode gradient orb too prominent
**What goes wrong:** On dark background, `var(--color-primary-glow)` is `rgba(61, 126, 248, 0.16)` — can look like an aggressive blue smear.
**Why it happens:** The dark bg (#0B0F1A) provides lower luminance contrast, making subtle gradients more visible.
**How to avoid:** Use `--color-primary-subtle` in dark mode (`rgba(61, 126, 248, 0.12)`) as the orb center color, or override with `[data-theme="dark"] .login-orb { opacity: 0.7; }`.

### Pitfall 6: JS getElementById mismatches after HTML rewrite
**What goes wrong:** login.js queries `getElementById('loginForm')`, `getElementById('email')`, etc. — if these IDs are renamed in the new HTML, JS silently fails (null reference).
**Why it happens:** Ground-up HTML rewrite touches every element; easy to overlook an ID.
**How to avoid:** Run a post-rewrite audit — grep login.js for all `getElementById` and `querySelector` calls, verify each ID/class exists in new HTML before marking the plan done.

---

## Code Examples

### Verified: `api()` call pattern (from utils.js line 627)
```javascript
// POST to auth endpoint
var { status, body } = await api('/api/v1/auth_login.php', { email, password });
// GET (no body) to whoami
api('/api/v1/whoami.php').then(function(res) { ... });
```

### Verified: Design system shadow tokens available
```css
/* Use these — all defined in design-system.css */
box-shadow: var(--shadow-xl);   /* 0 16px 40px ... */
box-shadow: var(--shadow-2xl);  /* 0 24px 64px ... — for max elevation */
```

### Verified: Card focus-within glow pattern
```css
.login-card {
  /* base shadow */
  box-shadow: var(--shadow-lg);
  transition: box-shadow var(--duration-deliberate) var(--ease-standard),
              border-color var(--duration-deliberate) var(--ease-standard);
}
.login-card:focus-within {
  border-color: var(--color-border-focus); /* rgba(22, 80, 224, 0.50) light / rgba(61, 126, 248, 0.50) dark */
  box-shadow: var(--shadow-xl), 0 0 0 3px var(--color-primary-glow);
}
```

### Verified: Field-level error state pattern (from existing login.css)
```css
/* Current pattern — can rename .field-error to .field-group--error */
.field-group--error .field-input {
  border-color: var(--color-danger);
  box-shadow: 0 0 0 3px var(--color-danger-subtle);
}
.field-error-msg {
  font-size: var(--text-xs);
  color: var(--color-danger);
  margin-top: var(--space-1);
  display: none;
}
.field-group--error .field-error-msg {
  display: block;
}
```

### Verified: Demo credentials panel — new approach
```javascript
// Replace showDemoHint() which appended to card:
function showDemoHint() {
  var panel = document.getElementById('demoPanel');
  if (!panel) return;
  panel.removeAttribute('hidden');
  // populate innerHTML with role buttons
}
```

### Verified: Dark mode token values (from design-system.css line 588)
- Dark bg: `#0B0F1A`
- Dark surface raised (card bg): `#1E2438`
- Dark border: `#252C3C`
- Dark primary: `#3D7EF8`
- Dark primary glow: `rgba(61, 126, 248, 0.16)`

---

## State of the Art

| Old Approach | Current Approach | Notes |
|--------------|------------------|-------|
| Static label above input | Floating label (`:placeholder-shown` + CSS transform) | Stripe checkout style; saves vertical space |
| `box-shadow` as focus ring | `outline` + `box-shadow` combined (or `var(--shadow-focus)`) | Design system has `--shadow-focus` token |
| Dynamically appended demo hint | Static `<div id="demoPanel" hidden>` outside card | Cleaner; JS just removes `hidden` attr |
| 400px card width | 420px card with `max-width` | More breathing room, still fits 480px+ |
| Static gradient background | Animated radial orb + static gradient | Clerk/Vercel style polish |

**Deprecated/outdated in this codebase:**
- `display: none` / `display: block` toggling for error messages: the current code uses `.visible` class + `display: none` default. For the rebuild, keep this pattern but rename classes to match new BEM-ish structure.

---

## Open Questions

1. **Version number in footer**
   - What we know: CONTEXT.md mentions "subtle version number" in footer
   - What's unclear: Is the version hardcoded `v4.3` or pulled from an API/config?
   - Recommendation: Hardcode `v4.3` as a static string — no API call for this.

2. **`forgotLink` / `forgotMsg` — keep or remove?**
   - What we know: Current login.html has a "Mot de passe oublié?" link that shows a static admin-contact message
   - What's unclear: CONTEXT.md does not mention it explicitly
   - Recommendation: Keep the forgot-password hint in the new design (simple text expansion) — it's a trust element and the JS already handles it cleanly.

3. **`@starting-style` browser support**
   - What we know: Chrome 117+, Safari 17.4+ support it; Firefox partial
   - What's unclear: Target browser floor for this project
   - Recommendation: Keep `@starting-style` for card entrance — it's a progressive enhancement; browsers without support just see the card without the fade-in. Already used in current login.css.

---

## Validation Architecture

No automated test framework detected in the project (`nyquist_validation` key absent from config.json — treating as enabled but noting the gap).

### Test Framework
| Property | Value |
|----------|-------|
| Framework | None detected |
| Config file | n/a |
| Quick run command | Manual: open browser at `/login.html` |
| Full suite command | Manual browser checklist (see below) |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REB-02 | Login page renders at 1024px+ without breakage | manual-smoke | open browser → inspect layout | n/a |
| REB-02 | Floating labels float on focus and when filled | manual-interaction | type in email/password fields | n/a |
| REB-02 | Gradient orb animates without layout shift | manual-visual | observe animation for 5s | n/a |
| REB-02 | Dark mode has full parity | manual-visual | toggle theme, inspect card/orb/inputs | n/a |
| WIRE-01 | Submit form → API → redirect to dashboard | manual-smoke | use demo admin credentials, verify redirect | n/a |
| WIRE-01 | Empty fields show inline error, no page reload | manual-interaction | click submit with empty fields | n/a |
| WIRE-01 | Wrong credentials show error banner + field highlights | manual-interaction | submit wrong password | n/a |
| WIRE-01 | Already-logged-in user auto-redirected | manual-smoke | visit /login.html while session active | n/a |

### Sampling Rate
- **Per task commit:** Open `/login.html` in browser — visual pass + submit one test credential
- **Per wave merge:** Full manual checklist above
- **Phase gate:** All 8 behaviors verified before `/gsd:verify-work`

### Wave 0 Gaps
- No test infrastructure exists — all validation is manual browser testing. No files to create.

---

## Sources

### Primary (HIGH confidence)
- Direct file read: `public/login.html` — existing HTML structure and IDs
- Direct file read: `public/assets/css/login.css` — existing CSS patterns
- Direct file read: `public/assets/js/pages/login.js` — all JS selectors, API calls, logic
- Direct file read: `public/assets/css/design-system.css` — verified all token names and values
- Direct file read: `public/assets/js/core/utils.js` — verified `api()` signature (line 627)
- Direct file read: `44-CONTEXT.md` — locked decisions

### Secondary (MEDIUM confidence)
- CSS floating label `:placeholder-shown` pattern — well-established CSS technique, verified against MDN-documented behavior
- `@starting-style` browser support — CSS WG specification, Chrome 117+

### Tertiary (LOW confidence)
- Gradient orb timing (8–12s) — based on Clerk/Vercel observation; exact values left to Claude's discretion per CONTEXT.md

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — read directly from project files
- Architecture: HIGH — based on existing verified code and locked decisions
- Pitfalls: HIGH — identified from actual code analysis (getElementById deps, floating label edge cases)

**Research date:** 2026-03-20
**Valid until:** 2026-04-20 (stable vanilla stack)
