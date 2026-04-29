# Roadmap: AgVote

## Milestones

- ✅ **v1.0 Dette Technique** - Phases 1-4 (shipped 2026-04-07) — see `.planning/milestones/v1.0-ROADMAP.md`
- ✅ **v1.1 Coherence UI/UX et Wiring** - Phases 5-7 (shipped 2026-04-08) — see `.planning/milestones/v1.1-ROADMAP.md`
- ✅ **v1.2 Bouclage et Validation Bout-en-Bout** - Phases 8-13 (shipped 2026-04-09) — see `.planning/milestones/v1.2-ROADMAP.md`
- ✅ **v1.3 Polish Post-MVP** - Phases 14-17 (shipped 2026-04-09) — see `.planning/milestones/v1.3-ROADMAP.md`
- ✅ **v1.4 Regler Deferred et Dette Technique** - Phases 1-6 (shipped 2026-04-10) — see `.planning/milestones/v1.4-ROADMAP.md`
- ✅ **v1.5 Nettoyage et Refactoring Services** - Phases 1-7 (shipped 2026-04-20) — see `.planning/milestones/v1.5-ROADMAP.md`
- ✅ **v1.6 Reparation UI et Polish Fonctionnel** - Phases 1-4 (shipped 2026-04-20) — see `.planning/milestones/v1.6-ROADMAP.md`
- ✅ **v1.7 Audit Idempotence** - Phases 1-3 (shipped 2026-04-20) — see `.planning/milestones/v1.7-ROADMAP.md`
- ✅ **v1.8 Refonte UI et Coherence Visuelle** - Phases 1-5 (shipped 2026-04-20) — see `.planning/milestones/v1.8-ROADMAP.md`
- ✅ **v1.9 UX Standards & Retention** - Phases 1-5 (shipped 2026-04-21) — see `.planning/milestones/v1.9-ROADMAP.md`
- ✅ **v2.0 Operateur Live UX** - Phases 1-4 (shipped 2026-04-29) — see `.planning/milestones/v2.0-ROADMAP.md`
- 🚧 **v2.1 Hardening Sécurité** - Phases 1-6 (in progress) — 21 contremesures (F02-F22)

## Phases

<details>
<summary>✅ v1.0 Dette Technique (Phases 1-4) - SHIPPED 2026-04-07</summary>

See `.planning/milestones/v1.0-ROADMAP.md` for full details.

</details>

<details>
<summary>✅ v1.1 Coherence UI/UX et Wiring (Phases 5-7) - SHIPPED 2026-04-08</summary>

See `.planning/milestones/v1.1-ROADMAP.md` for full details.

**Phases:** 3 (5, 6, 7)
**Plans:** 11
**Hotfixes delivered:** 3 (RateLimiter boot, nginx routing, login redesign)

</details>

<details>
<summary>✅ v1.2 Bouclage et Validation Bout-en-Bout (Phases 8-13) - SHIPPED 2026-04-09</summary>

See `.planning/milestones/v1.2-ROADMAP.md` for full details.

**Phases:** 6 (8-13)
**Plans:** 36
**Critical-path Playwright specs:** 23 GREEN x 3 runs zero flake
**Hotfixes delivered:** 5 (RateLimiter boot, nginx routing, login polish, cookie domain, HSTS preload)

</details>

<details>
<summary>✅ v1.3 Polish Post-MVP (Phases 14-17) - SHIPPED 2026-04-09</summary>

See `.planning/milestones/v1.3-ROADMAP.md` for full details.

**Phases:** 4 (14, 15, 16, 17)
**Plans:** 12 (4 + 1 + 5 + 3) — phase 15 executed inline without PLAN files
**Shipped:**
- Visual polish: toast system unifie, dark mode parity, role-specific sidebar, micro-interactions
- Cross-browser: chromium + firefox + webkit + mobile-chrome matrix (25/25 chromium, 25/25 firefox, 23/25 webkit, 21/25 mobile-chrome)
- Accessibility deep audit: 47 structural violations fixed, keyboard-nav spec (6/6), contrast audit produced (316 nodes DEFERRED to token remediation), WCAG 2.1 AA partial conformance
- Loose ends: settings loadSettings race fixed, eIDAS chip delegation fixed, Phase 12 SUMMARY audit ledger (6 findings, 3 deferred to v2)

**Deferred to v2:**
- CONTRAST-REMEDIATION (316 color-contrast nodes, design-token work)
- V2-OVERLAY-HITTEST (systematic `[hidden]`+flex overlay sweep)
- V2-TRUST-DEPLOY (trust.htmx.html auditor/assessor fixtures)
- V2-CSP-INLINE-THEME (strict CSP compatibility)

</details>

<details>
<summary>✅ v1.4 Regler Deferred et Dette Technique (Phases 1-6) — SHIPPED 2026-04-10</summary>

See `.planning/milestones/v1.4-ROADMAP.md` for full details.

**Phases:** 6 (1-6)
**Plans:** 14
**Requirements:** 24/24 satisfied
**Shipped:**
- Contrast AA remediation: 316 violations -> 0, WCAG 2.1 AA CONFORME
- Global [hidden] rule + codebase audit
- Auditor/assessor Playwright fixtures with seed endpoint
- htmx 2.0.6 upgrade with zero regressions (4 browsers)
- CSP nonce enforcement in report-only mode
- 4 controllers refactored from >500 to <300 LOC

</details>

<details>
<summary>✅ v1.5 Nettoyage et Refactoring Services (Phases 1-7) - SHIPPED 2026-04-20</summary>

See `.planning/milestones/v1.5-ROADMAP.md` for full details.

**Phases:** 7 (1 cleanup + 5 refactoring + 1 validation gate)
**Plans:** 9
**Requirements:** 18/18 satisfied
**Shipped:**
- Codebase cleanup: 50+ console.log removed, dead code purged, superglobals migrated
- 5 services refactored from >600 LOC to <300 LOC each
- 7 new extracted classes (SessionManager, RbacEngine, CsvImporter, XlsxImporter, ValueTranslator, ReportGenerator, RetryPolicy)
- Zero regressions confirmed (routes unchanged, unit tests green, E2E specs intact)

</details>

<details>
<summary>✅ v1.6 Reparation UI et Polish Fonctionnel (Phases 1-4) - SHIPPED 2026-04-20</summary>

See `.planning/milestones/v1.6-ROADMAP.md` for full details.

**Phases:** 4 (1 JS audit + 1 form modernization + 1 wizard + 1 validation gate)
**Plans:** 8
**Requirements:** 9/9 satisfied
**Shipped:**
- JS interaction audit: 8 broken selectors fixed across 21 pages
- Form layout modernization: multi-column grids on 16 pages, field classes normalized
- Wizard compaction: CSS spacing reduced for 1080p viewport fit
- Zero regressions confirmed

</details>

<details>
<summary>✅ v1.7 Audit Idempotence (Phases 1-3) - SHIPPED 2026-04-20</summary>

See `.planning/milestones/v1.7-ROADMAP.md` for full details.

**Phases:** 3 (1 audit + 1 backend guards + 1 frontend/tests)
**Plans:** 4
**Requirements:** 7/7 satisfied
**Shipped:**
- 73 routes audited, 13 Critique targets identified and protected
- IdempotencyGuard on all critical creation/import/email routes
- Workflow transitions idempotent (launch/close)
- HTMX X-Idempotency-Key header on all POST/PATCH
- IdempotencyGuard JSON deserialize bug fixed

</details>

<details>
<summary>✅ v1.8 Refonte UI et Coherence Visuelle (Phases 1-5) - SHIPPED 2026-04-20</summary>

See `.planning/milestones/v1.8-ROADMAP.md` for full details.

**Phases:** 5
**Plans:** 9
**Requirements:** 13/13 satisfied
**Shipped:**
- Palette stone->slate (beige->gris neutre moderne)
- Wizard field-input->form-input, 33 inline styles elimines, drawer CSS classes
- Version v2.0 unifiee, footer accent fixe, modales standardisees
- Hero compact, radio->select, KPI dead code supprime

</details>

<details>
<summary>✅ v1.9 UX Standards & Retention (Phases 1-5) - SHIPPED 2026-04-21</summary>

See `.planning/milestones/v1.9-ROADMAP.md` for full details.

**Phases:** 5 (typography + sidebar + feedback + jargon + validation gate)
**Plans:** 9
**Requirements:** 16/16 satisfied
**Shipped:**
- Typography: base 16px, labels normal case, header 64px, spacing 20-24px
- Sidebar: always-open 200px, pin removed, voter sees only Voter + Mon compte, 44px touch targets
- Feedback: persistent vote confirmation with timestamp, "Chargement..." labels, ag-empty-state on all pages, filter reset
- Clarity: voter jargon eliminated, admin tooltips, checkbox confirmation, export descriptions
- Validation: NAV-04 verified, PHP syntax clean, visual coherence approved

</details>

<details>
<summary>✅ v2.0 Operateur Live UX (Phases 1-4) - SHIPPED 2026-04-29</summary>

See `.planning/milestones/v2.0-ROADMAP.md` for full details.

**Phases:** 4 (Checklist + Focus Mode + Animations + Validation Gate)
**Plans:** 6
**Requirements:** 11/11 satisfied
**Audit:** PASS (cross-phase integration verified — `.planning/milestones/v2.0-MILESTONE-AUDIT.md`)
**Shipped:**
- Checklist temps reel: quorum/votes/SSE/connected voters avec alertes visuelles
- Mode Focus: vue 5-zones epuree avec toggle + persistance sessionStorage
- Animations Vote: compteurs RAF + bar transitions, respect prefers-reduced-motion
- Hotfix securite (PR #247): /setup 404 + CSRF strict (finding F1)
- CI repair (PR #248): lint-js + migrate-check verts, validate massivement ameliore

</details>

### 🚧 v2.1 Hardening Sécurité (In Progress)

**Milestone Goal:** Eliminer les 21 contremesures de sécurité restantes (F02 à F22) identifiées par l'audit du 2026-04-29 — defense en profondeur sur authentification, integrite du vote, isolation tenant, perimetre, uploads et headers HTTP. Aucune fuite cross-tenant tolérée à la sortie du milestone.

**Scope:** Backend uniquement (controllers, repositories, middlewares, infra de tests CI). UI/UX différé à v2.2+.

**Strategy:** 1 PR par phase, < 600 LOC chacune. Phases 2/3/4 peuvent partiellement paralléliser après Phase 1 ; Phase 5 indépendante ; Phase 6 = gate finale.

## Phase Details

### Phase 1: Sprint 0 finition
**Goal**: Boucler les 4 hotfixes Sprint 0 résiduels (F1 déjà shipped en v2.0). Pose l'infrastructure (TRUSTED_PROXIES, audit per-member, idempotence) sur laquelle s'appuient les phases suivantes.
**Depends on**: Nothing (first phase)
**Requirements**: HARDEN-F02, HARDEN-F03, HARDEN-F04, HARDEN-F05
**Success Criteria** (what must be TRUE):
  1. Une requête avec header `X-Forwarded-For` depuis une IP non listée dans `TRUSTED_PROXIES` ne contourne pas le rate-limit du login (le compteur s'incrémente sur l'IP réelle, pas l'IP usurpée).
  2. Un 2ᵉ appel à `degraded_tally` sur la même motion sans annulation préalable retourne HTTP 409 ; l'audit log liste before/after avec le champ `reason` (>= 20 chars).
  3. Modifier `voting_power` de 5 membres via `members_bulk` produit 5 événements `member_voting_power_changed` distincts dans `audit_events` (un par member_id).
  4. Une session du tenant A connectée au flux SSE `/api/v1/events.php` ne reçoit aucun événement émis par un meeting du tenant B, même avec un `meeting_id` valide en query.

### Phase 2: Vote intégrité & cross-tenant
**Goal**: Garantir l'intégrité du vote face aux attaques TOCTOU, race conditions, fuite de tokens en BD, IDOR cross-tenant et CSRF replay. Le cœur de cible de l'auditeur offensif.
**Depends on**: Phase 1
**Requirements**: HARDEN-F06, HARDEN-F07, HARDEN-F08, HARDEN-F09, HARDEN-F10
**Success Criteria** (what must be TRUE):
  1. Deux requêtes concurrentes `POST /vote?token=X` avec le même token retournent : la première HTTP 200, la seconde HTTP 401 ; jamais 200 deux fois (vérifié par stress test).
  2. `SELECT token FROM invitations` en BD ne contient aucun token utilisable directement (uniquement des hashes HMAC-SHA256). Le flux invitation par email continue de fonctionner.
  3. Une session du tenant A tentant un GET/POST/PATCH sur n'importe quelle ressource (motion, ballot, member, attachment, proxy) du tenant B retourne 404 systématiquement.
  4. En `APP_ENV=production`, un opérateur (rôle non-admin) tentant `meeting_reset_demo` sur un meeting `live` reçoit HTTP 409 ; en draft + admin + token typé `RESET-<code>`, ça passe.
  5. Un token CSRF valide pour `POST /meetings` est rejeté lors d'une requête `POST /admin_settings` (token scopé par couple METHOD+PATH).

### Phase 3: Périmètre & SSRF
**Goal**: Fermer les vecteurs d'exfiltration / pivot via webhooks, redirects email, et brute-force d'authentification. Defense périmétrique.
**Depends on**: Phase 1
**Requirements**: HARDEN-F11, HARDEN-F12, HARDEN-F13
**Success Criteria** (what must be TRUE):
  1. Configurer `MONITOR_WEBHOOK_URL=http://169.254.169.254/...` (cloud metadata) ou `http://10.0.0.1/...` (RFC1918) lève une exception au boot du `MonitoringService` ; seules des URLs HTTPS d'hôtes whitelistés sont acceptées.
  2. La 11ᵉ requête `password_reset_request` depuis la même IP en 10 minutes retourne HTTP 429 avec header `Retry-After`. La 6ᵉ requête sur le même email (tous IPs confondus) idem.
  3. 10 échecs login consécutifs sur le même email verrouillent le compte avec un message FR explicite et un header `Retry-After` qui croît exponentiellement (2, 4, 8... minutes, plafond 24h).

### Phase 4: Uploads & contenu
**Goal**: Sécuriser le pipeline upload PDF, prévenir l'injection de formules dans les exports XLSX/CSV, durcir la génération PDF (dompdf).
**Depends on**: Phase 1
**Requirements**: HARDEN-F14, HARDEN-F15, HARDEN-F16
**Success Criteria** (what must be TRUE):
  1. Un fichier non-PDF renommé en `.pdf` (magic bytes ≠ `%PDF-`) est rejeté à l'upload avec HTTP 400 ; un PDF valide est servi en download avec `Content-Disposition: attachment` + `X-Content-Type-Options: nosniff`.
  2. Un membre nommé `=cmd|...` est exporté dans le CSV/XLSX comme `'=cmd|...` (string littéral, jamais évalué comme formule par Excel ou LibreOffice).
  3. Un PDF de procuration généré pour un nom de membre `<script>alert(1)</script>` rend le texte échappé (`&lt;script&gt;...`) ; aucun fetch HTTP distant ni interprétation PHP par dompdf (`isRemoteEnabled=false`, `isPhpEnabled=false`).

### Phase 5: Headers, cookies & defense-in-depth
**Goal**: Migrer la CSP vers nonce-strict, durcir les flags cookies de session, supprimer les fallbacks dev permissifs en production.
**Depends on**: Nothing (peut paralléliser avec Phases 2-4 — n'utilise aucune infra de Phase 1)
**Requirements**: HARDEN-F17, HARDEN-F18, HARDEN-F19
**Success Criteria** (what must be TRUE):
  1. La CSP est passée en mode strict avec `script-src 'self' 'nonce-$nonce'` (sans `'unsafe-inline'`). Une page sans nonce voit ses scripts rejetés en console, observable via les rapports CSP collectés.
  2. Après login, `Set-Cookie` contient `SameSite=Strict; Secure; HttpOnly`. Le cookie ID change entre pré-login et post-login (régénération). Tester aussi sur logout et changement de rôle.
  3. Lancer l'application avec `APP_SECRET` < 32 chars retourne une exception au boot dans tous les environnements (dev ET prod). Lancer avec `APP_ENV=production` ET `APP_DEBUG=1` est refusé.

### Phase 6: Tests & monitoring (validation gate)
**Goal**: Cristalliser les acquis dans la suite de tests, instrumenter le signal sécurité en prod, mettre à jour la documentation. Empêche les régressions futures.
**Depends on**: Phases 1, 2, 3, 4, 5 (tous fixes en place avant tests/monitoring)
**Requirements**: HARDEN-F20, HARDEN-F21, HARDEN-F22
**Success Criteria** (what must be TRUE):
  1. Le dossier `tests/Security/` contient au moins 1 test par finding F02-F22 (21 tests minimum). Le job CI exécute cette testsuite à chaque PR ; toute régression future déclenche un rouge.
  2. 10 tentatives 401/403 en 60 secondes sur la même IP déclenchent une alerte webhook via `MonitoringService`. Toute opération sur `motions.manual_tally`, `members.voting_power`, ou tentative de `DELETE FROM audit_events` produit un log critical visible.
  3. `SECURITY_AUDIT.md` est à jour avec les 22 findings (F01-F22) marqués CORRIGÉ avec lien vers le commit/PR. `SECURITY.md` à la racine décrit le processus de signalement (responsible disclosure).

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Sprint 0 finition | v2.1 | 0/? | Not started | - |
| 2. Vote intégrité & cross-tenant | v2.1 | 0/? | Not started | - |
| 3. Périmètre & SSRF | v2.1 | 0/? | Not started | - |
| 4. Uploads & contenu | v2.1 | 0/? | Not started | - |
| 5. Headers, cookies & defense-in-depth | v2.1 | 0/? | Not started | - |
| 6. Tests & monitoring | v2.1 | 0/? | Not started | - |
