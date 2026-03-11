# AG-VOTE Roadmap — v1.4

## Milestone: Test Coverage & Final Polish

**Goal**: Achieve 100% controller test coverage, add missing security header, and clean up remaining dead code/TODOs.

---

### Phase 1 — NotificationsController Unit Test
**Status**: done
**Goal**: Close the last controller test gap (37/38 tested → 38/38).
- Created NotificationsControllerTest.php: 9 tests, 28 assertions
- Tests: structure, methods, NOTIF_ACTIONS constant, limit clamping, markRead response, auth requirement

### Phase 2 — Permissions-Policy Header + E2E
**Status**: done
**Goal**: Add Permissions-Policy header for browser feature restriction.
- Added `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()` to SecurityProvider
- Added E2E tests for all security headers (X-Content-Type-Options, X-Frame-Options, CSP, Permissions-Policy)

### Phase 3 — Dead Code & TODO Audit
**Status**: done
**Goal**: Audit remaining TODOs and dead code.
- wizard.js TODO (line 159): intentional — wizard UI exists but API call not wired yet
- operator-tabs.js forwarding wrappers: intentional — called from HTML onclick handlers
- No PHP TODOs found across entire app/ directory
- No dead code to remove

---

## Previous: v1.3 (Code Quality & Frontend Cleanup) — COMPLETE

3 phases: unused vars fixed (142→0), innerHTML triaged safe, CI lint ratchet.

## Previous: v1.2 (Security & Resilience Hardening) — COMPLETE

4 phases: tenant isolation, rate limiting, PWA hardening, audit verification.

## Previous: v1.1 (Post-Audit Hardening) — COMPLETE

6 phases: E2E suite, CI pipeline, CDN hardening, app shell audit, error handling, accessibility.
