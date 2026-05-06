# Milestones

## v2.6 Clôture dette technique (Shipped: 2026-05-05)

**Phases:** 1-5 (5 phases v2.6, indépendantes et parallélisables)
**Plans:** 10 plans, 17/17 reqs covered (16 satisfied + 1 unsatisfied runtime fix pushé pending re-test)
**Tests:** 32 PHPUnit GREEN (102 assertions) + 1 Playwright spec live PASS (sse-heartbeat 16.8s)
**Stats:** 156 files changed, +8719 / -391 LOC, 57 commits, 1 day timeline (05:55 → 10:46 UTC)

**Key accomplishments:**

- Stop-tests v2.5 directive levée : HEARTBEAT-V25-03/04 livrés (PHPUnit 8/29 + Playwright spec opérationnelle live)
- `business_error` direct-emit count : 1 → 0 via `AbstractController::extractBusinessErrorCode()` (8 lignes catch enhancement) — bénéficie automatiquement à ~80 sites snake_case existants (`meeting_not_found`, `motion_not_found`, etc.)
- `ErrorEventsRepository::capture()` guard d'idempotence intra-request (md5 rid|code|route) — empêche double-row sur rafale SSE
- 10 tokens 1-site retirés (-2 width / -4 emphasis / -4 ring variants) sur `design-system.css` + 9 callers migrés ; ratios borders 97.7% + shadows 100% (au-dessus cible 95%)
- `EXPLORE-PATTERNS.md` placé à `.planning/intel/` canonique avec stub redirect + refs actives mises à jour
- PDF qualité éditoriale verrouillée par 4 PHPUnit smoke tests (parsing PDF binaire via `smalot/pdfparser` ^2 nouveau dev dep) — production code intact, vérifie header répété + UTF-8 accents + pagination Page X sur Y

**Bug fixes en cours de milestone (quick tasks + emergency commits) :**
- Docker autoload bug (`composer dump-autoload --classmap-authoritative` post `COPY . .` + `bin/rebuild.sh --quick` flag autoload-only)
- Alpine repo drift fix (`apk upgrade --no-cache` avant `apk add --virtual *-dev`)
- CI lint-js fixes (`_prevQuorumMet` declaration + `module` global UMD)
- CI PHPUnit OOM (`-d memory_limit=2G` sur validate + coverage step)
- enum `meeting_status` `'running'` → `'live'` dans `seedRunningMeeting` JS helper

**Tech debt deferred (v2.7 candidates) :**
- INFRA-V26-01 runtime gate : commit `80854f0` enum fix pushé, re-test live pending
- INFRA-V26-03 fresh-clone walkthrough chronométré (≤30 min cible)
- INFRA-V26-05 real `gsd-code-reviewer` v2.6 review ≥30 fichiers
- TOKENS visual regression 5 pages + 6 orphans + 3 emphasis residuels (~15 min v2.7 mini-cleanup pour ≤25 1-site)
- PDF visual inspection PV ≥10 pages réel
- 2 résiduels French throws (AttendancesService, BallotsService) → snake_case
- 4 pre-existing ErrorDictionaryTest failures (pré-v2.6, hors-scope)

**Audit:** `gaps_found` (1 unsatisfied req fix-pushed-pending-retest) — accepted as tech debt per audit recommendation Option B (pragmatic close).

**Archived to:** `.planning/milestones/v2.6-ROADMAP.md` + `.planning/milestones/v2.6-REQUIREMENTS.md` + `.planning/milestones/v2.6-MILESTONE-AUDIT.md` + `.planning/milestones/v2.6-TOKENS-AUDIT-FINAL.md`

---

## v2.5 Real-time Live Cockpit + Logger Migration (Shipped: 2026-05-04)

**PRs delivered:** #261 (v2.5 implementation) + #262 (v2.4 dead artifacts cleanup)
**Phases:** 5-7 (continuing numbering from v2.4) + bonus SEC-V2-01 closeout
**Plans:** 12 reqs (10 v2.5 + 2 SEC bonus) · 8 done · 4 deferred

**Key accomplishments:**

- `meeting.heartbeat` 10s SSE tick — payload {status, validated_at, quorum: {applied, met, present_members, eligible_members, present_weight, eligible_weight}, operator_count}, sub-queries try/catch isolated
- 47 sites `error_log()` legacy migrated to `Logger::error/warning/critical/alert/info` with severity differentiation (CSRF/auth/rate-limit → warning, DB → critical, security signal → alert)
- `error_events` table + `ErrorEventsRepository` + `api_fail()` capture hook (try/catch isolated, never breaks user response)
- `/admin/error-stats` rewired on `error_events` (supersede v2.4 transitional version that filtered audit_events) — 4 KPI cards + timeline SVG inline + top codes table with proportion bars + window selector + cross-tenant toggle (admin) + drill-down by tenant
- `next_step_clicks` table + `MetricsController` POST endpoint + `window.AgErrorMetrics` JS utility (sendBeacon preferred) — dashboard extended with CTR column (vert ≥20%, muted sinon)
- **SEC-V2-01 closeout extended** : 27 method signatures hardened across 11 repos (Motion + Policy + Agenda + ManualAction + Meeting + MeetingReport + Notification + ReminderSchedule + Invitation + EmailQueue + VoteToken) — eliminated `tenantId = ''` default footgun. Audit-ready : zero default in `app/Repository/`.

**Tech Debt carried to v2.6+:**

- HEARTBEAT-V25-03/04 (PHPUnit + Playwright tests deferred per stop-tests directive)
- COCKPIT-V25-01 (sub-tab Avancé ≤25 strict — débloqué post-v2.4 merge mais non exécuté)
- TOKENS-V25-01 (49 tokens 1-site audit `design-system.css`)
- Gates dev-machine v2.4 (Playwright runs, screenshots, PDF visuels, CSS smoke) inheritance

**Known deferred items at close:** 4 (see v2.5-ROADMAP.md `### Known Gaps`)

---

## v2.4 Polish & Robustness (Shipped: 2026-05-04 via PR #260)

**Phases completed:** 4 phases, 9 plans, 12 reqs (12/12 done with caveats)
**Verdict:** `tech_debt` (no critical blockers, dev-machine gates pending)

**Key accomplishments:**

- COCKPIT-V24-01/02 — Cockpit ≤25 cliquables visibles + rouge danger confiné (operator.css `--color-danger*` 21→9, sidebar 0 décoratif)
- ERR-V24-01 — `business_error` → 3 codes spécifiques (`meeting_transition_failed` / `operation_failed` / `state_read_failed`)
- ERR-V24-02 — `<ag-integrity-modal>` debounced via `attributeChangedCallback`, utility `window.AgSseDebounce.create()`
- ERR-V24-03 — `Logger::errorContext/criticalContext/alertContext` helpers + `/admin/error-stats` v2.4 (audit_events filtered with banner — superseded by v2.5)
- TEST-V24-01..05 — `seedMeeting()` helper triple-guarded + dual-install Playwright resolved + tests/e2e/README + EXPLORE-PATTERNS.md
- TECH-V24-01 — dompdf `@page` header `[Titre] — JJ/MM/YYYY` + footer `Page X sur Y` via `counter(page|pages)`
- TECH-V24-02 — Borders/shadows tokens 100.00% (218/218 borders + 104/104 shadows, 49 nouveaux tokens 1-site dans design-system.css, D-08 amendé)

**Tech Debt carried to v2.5:** 12 items deferred — closed in v2.5 (see v2.5 entry above)

---

## v2.1 Hardening Sécurité (Shipped: 2026-04-29)

**Phases completed:** 6 phases, 22 plans, 21 contremesures (F02-F22)

**Key accomplishments:**

- F02 ClientIp helper avec whitelist TRUSTED_PROXIES — bypass X-Forwarded-For impossible
- F03 Idempotence degraded_tally + audit before/after + reason ≥ 20 chars
- F04 Audit per-member sur members_bulk voting_power (1 événement par ID modifié)
- F05 SSE auth-first + isolation tenant via SseAuthGate (404 cross-tenant, pas 403)
- F06 Vote token consume atomique cristallisé en regression test
- F07 Migration NULL legacy invitation tokens + CHECK constraint
- F08 IDOR cross-tenant : 4 méthodes MotionRepository auditées et durcies
- F09 resetDemo lockdown : 4 gardes (meeting_id required, prod-admin, typed RESET-{prefix}, status whitelist)
- F10 CSRF scopé par action (HMAC METHOD+PATH) opt-in via tokenFor()
- F11 UrlValidator (SSRF outbound + email redirect) — refus RFC1918, link-local, userinfo
- F12 Rate limits sur tracking + reset constant-time
- F13 AccountLockout par compte avec backoff exponentiel (cap 24h)
- F14-F16 Uploads PDF magic bytes + formula injection + dompdf hardening
- F17-F19 CSP_STRICT_MODE opt-in + SameSite=Strict default + prod-debug refusé au boot
- F20 Tests Security testsuite (11 tripwires) + F21 SecuritySignal escalator + F22 SECURITY.md responsible disclosure

**PRs delivered:** #247 (F1 setup hardening), #249 + #250 + #254 (consolidées), #255

**Tech debt carried to v2.2:**
- 8 méthodes MotionRepository à `tenantId = ''` optionnel (audit des callers)
- Migration progressive templates `field()` → `fieldFor(method, path)` (F10 sur tous les forms)
- Switch hash invitation token SHA-256 → HMAC-SHA256 (forcer re-issue)

---

## v2.0 Operateur Live UX (Shipped: 2026-04-29)

**Phases completed:** 4 phases, 6 plans, 4 tasks

**Key accomplishments:**
- (none recorded)

---

## v1.4 Régler Deferred et Dette Technique (Shipped: 2026-04-10)

**Phases completed:** 6 phases, 14 plans, 33 tasks

**Key accomplishments:**

- 1. [Rule 1 — Bug] Plan premise relied on hex literals that don't exist in source
- Created:
- 316 contrast violations iteratively reduced to 0 across 22 pages via 5 axe-core runs, 26 micro-adjustments to 10 CSS files + 9 new on-subtle companion tokens, A11Y-REPORT declared CONFORME
- Global :where([hidden]) { display: none !important } rule added to @layer base; 16 redundant per-selector overrides removed across 10 CSS files; codebase-wide audit documenting 25 conflict sites
- Playwright spec with 4 tests proving [hidden] -> display:none on operator/settings/vote pages plus dynamic element guard, all green on chromium
- seedUser endpoint with route-level production gate plus Playwright loginAsAuditor/loginAsAssessor helpers and dedicated assessor E2E user
- All trust E2E specs migrated from loginAsAdmin/loginAsOperator to loginAsAuditor -- zero admin fallback patterns remain
- htmx 1.9.12 replaced with 2.0.6, htmx-1-compat safety net activated, 3 DELETE endpoints migrated from body to query params
- Full Playwright suite (212 specs x 4 browsers) confirms zero htmx 2.0.6 regressions -- all failures pre-existing and documented
- SecurityProvider::nonce() accessor + PageController PHP serving for 21 .htmx.html files + nonce placeholders on all 168 script/style tags across 33 files
- Report-only CSP with nonce + strict-dynamic alongside existing enforcing header, nginx CSP deduplication, and Playwright zero-violation spec across 21 pages
- Pre-split structural test contracts for 4 service classes using @group pending-service annotation across all 4 controller test files
- MeetingsController (687->295 LOC) and MeetingWorkflowController (559->184 LOC) extracted into MeetingLifecycleService and MeetingTransitionService with nullable RepositoryFactory DI
- OperatorController (516->130 LOC) and AdminController (510->203 LOC) slimmed to thin HTTP adapters with OperatorWorkflowService (297 LOC) and AdminService (295 LOC) handling all business logic

---

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
