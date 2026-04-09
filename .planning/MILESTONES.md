# Milestones

## v1.3 Polish Post-MVP (Shipped: 2026-04-09)

**Phases completed:** 4 phases, 12 plans, 14 tasks

**Key accomplishments:**

- One-liner:
- One-liner:
- One-liner:
- Pre-existing (no changes needed):
- Status:
- Axe audit matrix extended from 7 hand-written tests to a 22-page parametrized PAGES array with per-page waiver plumbing, unblocking A11Y-01 baseline.
- None.
- File created:
- `tests/e2e/specs/contrast-audit.spec.js`
- File created:
- Switched settings.js loadSettings from POST to GET, extracted snapshot applier with defensive re-apply, exposed window.__settingsLoaded handshake, and locked the UI population path with a Playwright regression assertion that previously had to be bypassed.

---

## v1.2 Bouclage et Validation Bout-en-Bout (Shipped: 2026-04-09)

**Phases completed:** 6 phases (8-13), 36 plans, 23 critical-path Playwright specs

**Key accomplishments:**

- **MVP shipped** : 21 pages auditees + chaque page passe 3 gates (width pleine ecran, design tokens, fonction prouvee Playwright)
- **23 critical-path specs Playwright GREEN** : 4 par role + 19 par page, 3 runs consecutifs zero flake (~1.2m total)
- **Test infrastructure Docker** : Playwright tourne dans un container reproductible (Phase 8), elimination des bugs "le test serait valide si seulement il pouvait s'executer"
- **13 width caps removed** sur les pages applicatives (settings, dashboard, vote, wizard, admin, email-templates) ; 2 ajouts justifies sur docs/help (80ch lecture)
- **5 endpoints fantomes prouves** : procuration_pdf, motions_override_decision, invitations_send_reminder, meeting_attachments_public, meeting_attachment_serve
- **3 vote settings wired** dans VoteEngine + QuorumEngine via fallback policy synthesis (settVoteMode, settQuorumThreshold, settMajority)
- **Settings page reparee** : sauvegarde + reload prouve par Playwright (la page que l'utilisateur appelait "theatre" passe maintenant un test end-to-end)
- **2 controllers refactores** : MeetingReportsController 727→256 lignes, MotionsController 720→299 lignes (DEBT-02/03 v1.0 carry-over closes)
- **getDashboardStats wired** dans DashboardController (DEBT-01 v1.0 carry-over enfin clos)
- **5 hotfixes critiques delivres en cours de milestone** : RateLimiter::configure() boot regression (`ddcf02f2`), nginx clean URL routing multi-week regression (`2f441469`), Login 2-panel polish (`448f38a3`), Cookie domain via Redis TCP direct (Phase 9), Chrome HSTS preload .app collision via network alias agvote (Phase 9)

**Tech debt reportee a v1.3 :** Multi-browser test matrix (firefox/webkit), axe-core deep audit, HTMX 2.0 upgrade, visual regression testing.

---

## v1.1 Coherence UI/UX et Wiring (Shipped: 2026-04-08)

**Phases completed:** 3 phases, 11 plans

**Key accomplishments:**

- JS audit + wiring repair: 1,269 querySelector calls audited, 1 confirmed mismatch fixed (vote.js voteButtons), 5 dead-code blocks removed for v4.2 orphan selectors, sidebar async timing hardened with sidebar:loaded event, waitForHtmxSettled() Playwright helper created
- Login redesigned 2x: first 50/50 then 40/60 brand panel with hero title, 3 feature highlights, trust pills, gradient + animated glow, all coherent with project tokens
- Design tokens uniformly applied: 7 raw color literals replaced across operator/settings/report/vote/audit.css, @layer pages cascade declared, 5 badge defects fixed (hub BEM, QuorumController PHP)
- HTMX skeleton-row loading wired into operator/members/meetings list containers, pv_sent badge entry added
- Playwright upgraded to 1.59.1 with @axe-core/playwright + per-page accessibility audits (7 pages)
- 22 networkidle calls removed from 6 spec files; new page-interactions.spec.js (8 tests, 7 pages) and operator-e2e.spec.js (full workflow with hybrid API+UI strategy)
- 3 critical hotfixes delivered: RateLimiter::configure() boot regression (v1.0 cleanup leftover blocked all API requests), nginx clean URL routing (multi-week regression that served index.html instead of htmx.html for /dashboard, /meetings, /hub), login redesign polish

**Tech debt carried to v1.2:** Browser test execution requires installing libatk system libraries or running in Docker. 11 human verification items deferred. Phase 5 + Phase 6 nyquist_compliant flag never flipped. Backend tech debt from v1.0 still pending (getDashboardStats wiring, MeetingReports/Motions controller split).

---

## v1.0 Dette Technique (Shipped: 2026-04-07)

**Phases completed:** 4 phases, 11 plans, 18 tasks

**Key accomplishments:**

- Application now fails fast with French error when Redis is unreachable; RateLimiter uses atomic Lua EVAL instead of PIPELINE, with all file-based fallback code deleted
- EventBroadcaster stripped of all file-based fallback code; SSE server detection replaced with Redis TTL heartbeat (sse:server:active); events.php writes heartbeat each loop iteration
- PDO::ATTR_TIMEOUT=10 and configurable statement_timeout added to all DB connections; 12-metric getDashboardStats() consolidates 11+ COUNT queries into a single SQL round-trip via scalar subqueries
- AbstractRepository.selectGenerator()
- Replace unbounded member table loads in EmailQueueService with LIMIT/OFFSET paginated batches of 25, preventing OOM for large associations (500+ members)
- 1. [Constraint - Test Infrastructure] api_require_role() stubbed as no-op in bootstrap
- ImportController reduced 67% (921 to 303 lines) by extracting all business logic into ImportService with nullable DI constructor and 4 typed process methods
- ImportController reduced to 149 lines with zero delegation wrappers; ImportService processMemberImport verified with 5 mock-RepositoryFactory integration tests; TEST-01 and TEST-02 marked complete
- EventBroadcasterTest extended with 6 Redis-integration tests covering event ordering, atomic dequeue, consumer fan-out, tenant event isolation, heartbeat TTL expiry, and queue trim limit — closing TEST-03.
- 49-test ImportServiceTest covering all accented aliases (ponderation/pondération, prenom/prénom, tantiemes/tantièmes, etc.) across 4 column maps plus readCsvFile header normalization edge cases
- SC1 gap closed: EventBroadcasterTest extended with structural Redis connection loss proof + client reconnect buffer/drain/re-buffer cycle, TEST-04 documentation lag fixed

---
