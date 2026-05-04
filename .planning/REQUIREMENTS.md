# Requirements: AgVote v2.4 Polish & Robustness

**Defined:** 2026-05-04
**Core Value:** Consolider la fiabilité production post-v2.3 — éliminer les frictions de toolchain identifiées, refactorer les codes d'erreur génériques en codes ciblés observables, et finir le polish cockpit pour atteindre une charge cognitive opérateur maîtrisée.

**Source des requirements :** backlog v2.3 trié dans `.planning/v2.4-BACKLOG-PLAN.md` (4 phases validées) — heritage Schoger S-1 + S-6, code-review v2.3 followups, Phase 4 audit ERR-04 followups, A1 gate friction (sandbox install Playwright), Phase 3 Schoger S-8 propagation d'erreur scan.

**Pré-requis** : v2.3 mergée (PR #259) avant démarrage Phase 1 v2.4.

---

## v1 Requirements

### Cockpit Polish & Hygiène (Phase 1)

- [ ] **COCKPIT-V24-01**: Le cockpit opérateur affiche au plus **25 boutons / éléments cliquables visibles** simultanément (vs ~70 actuellement). Le PLAN.md de la phase identifie chaque bouton actuel, propose un regroupement (panel rétractable, persona-scoped, contextual-only), et justifie quels sont conservés en avant. *Schoger S-1 — declutter.*

- [ ] **COCKPIT-V24-02**: Le rouge danger (`--color-danger`, `--color-danger-subtle`) est utilisé **uniquement** pour signaler un état critique nécessitant une action immédiate (quorum perdu, vote raté). Les indicateurs de présence/connexion utilisent une palette neutre ou success ; la sidebar/nav opérateur n'a aucun rouge décoratif. Audit livré dans le PLAN.md. *Schoger S-6 — persona color confinement.*

### Error Observability & Resilience (Phase 2)

- [ ] **ERR-V24-01**: Le code d'erreur générique `business_error` est remplacé par **3 codes spécifiques** dans `app/Services/ErrorDictionary.php` couvrant les 3 cas d'usage actuels documentés en 04.6-AUDIT.md. Chaque caller migré vers le code spécifique. `business_error` reste émis < 5 % des erreurs en prod. *04.6-FOLLOWUP-2.*

- [ ] **ERR-V24-02**: Les empty-states avec rafale d'événements SSE (modal d'intégrité, dashboard hero card live) implémentent un guard d'idempotence (debounce ≥250ms ou state-machine `idle | rendering | rendered`) qui **prévient les double-render** lors de bursts. Test E2E reproductible avec injection synthétique de 5 events en 100ms. *04.6-FOLLOWUP-3.*

- [ ] **ERR-V24-03**: `Logger::error()` enrichi avec contexte standardisé (`request_id`, `user_id`, `tenant_id`, `error_code`, `caller`) sur tous les call-sites identifiés par l'audit. Un dashboard simple (page admin ou commande CLI) affiche le **taux d'utilisation du next-step ErrorDictionary** par code (cliques sur la suggestion vs ignorance) — métrique pour valider Phase 4 v2.3 ERR-02.

### Test Infrastructure (Phase 3)

- [ ] **TEST-V24-01**: `tests/e2e/helpers/seed-meeting.js` est implémenté (signature `seedMeeting({tenantId, status, motionsCount}) → meetingId`) et active le test `@integration` F-4 (modal-focus-trap.spec.js) précédemment skippé. Test passe en CI dev. *01.4-FOLLOWUP-2.*

- [ ] **TEST-V24-02**: `gsd-code-reviewer` accepte un argument `--scope=js|php|tests|all` et un budget timeout configurable (`--timeout-min=N`, défaut 60). Documentation dans `.claude/get-shit-done/agents/gsd-code-reviewer.md`. Vérifié via review v2.4 sur 50+ fichiers sans timeout. *GSD-V2.4-1.*

- [ ] **TEST-V24-03**: Le dual-install Playwright (root `package.json` + `tests/e2e/package.json`) est résolu : un seul `package.json` source de vérité (`tests/e2e/`), root supprimé ou stub minimal vers tests/e2e. README à jour. *GSD-V2.4-2.*

- [ ] **TEST-V24-04**: `tests/e2e/README.md` créé (ou enrichi) avec : install commands (`sudo npx playwright install --with-deps chromium`), browsers téléchargés par défaut, gestion auth-setup rate-limit (5min cooldown assessor), procédures debug. *GSD-V2.4-3.*

- [ ] **TEST-V24-05**: Un guide `.planning/codebase/EXPLORE-PATTERNS.md` documente le pattern de scan évitant les faux-positifs BEM substring (ex: `shortcut-cards` matche `shortcut-cards__title` mais pas `shortcut-cards-grid`). 3 anti-patterns concrets recensés, exemple correct fourni. *03.2-FOLLOWUP-1.*

### Print + Tech Debt residuel (Phase 4)

- [ ] **TECH-V24-01**: L'export PDF (dompdf via `ProcurationPdfService` + `MeetingReportsService` si applicable) génère un **header répété** sur chaque page (titre séance + date) et un footer numéro de page. Vérifié visuellement sur 3 PVs longs (≥10 pages). *EDITORIAL-07-FULL — partial v2.3 = CSS print only.*

- [ ] **TECH-V24-02**: Le ratio borders/shadows utilisant les design tokens vs valeurs hardcodées atteint **≥95 %** (vs ~70 % post-v2.3 quick TECH-01). Audit produit la liste des ~140 borders + ~45 shadows residuels et les migre cas-par-cas. Atomic commits per fichier pour revert ciblé. *TECH-01-BASSE.*

---

## v2 Requirements (déférés milestone v2.5+)

### Sécurité (milestone v2.5 dédié)

- **SEC-V24-01**: `MotionRepository` tenantId check sur tous finders publics
- **SEC-V24-02**: `F10` `fieldFor()` sanitization input
- **SEC-V24-03**: Hash invitation HMAC vs plain token

### UX ambitieux (milestone v2.6+)

- **UX-V24-01**: Système d'animations cohérent (motion design tokens)
- **UX-V24-02**: A/B testing infra KPI
- **UX-V24-03**: Mobile-first opérateur (refonte responsive complète)
- **UX-V24-04**: Empty states illustrés (designer dependency)

---

## Out of Scope

Explicitement exclu de v2.4 :

| Feature | Reason |
|---------|--------|
| Refonte business logic | v2.4 = polish + observability, pas de nouveau métier |
| Nouvelles dépendances framework | Stack PHP 8.4 + HTMX + vanilla JS reste fixe |
| Migration `gh` CLI | MCP server github suffit, dual stack overhead non justifié |
| Sécurité backend (SEC-*) | Bloc cohérent → milestone v2.5 dédié |
| Mobile responsive opérateur | Hors-scope (v2.6+ design lead requis) |
| Animations / motion design | Hors-scope (v2.6+ design system requis) |

---

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| COCKPIT-V24-01 | Phase 1 | Pending |
| COCKPIT-V24-02 | Phase 1 | Pending |
| ERR-V24-01 | Phase 2 | Pending |
| ERR-V24-02 | Phase 2 | Pending |
| ERR-V24-03 | Phase 2 | Pending |
| TEST-V24-01 | Phase 3 | Pending |
| TEST-V24-02 | Phase 3 | Pending |
| TEST-V24-03 | Phase 3 | Pending |
| TEST-V24-04 | Phase 3 | Pending |
| TEST-V24-05 | Phase 3 | Pending |
| TECH-V24-01 | Phase 4 | Pending |
| TECH-V24-02 | Phase 4 | Pending |

**Coverage:**
- v1 requirements: 12 total
- Mapped to phases: 12
- Unmapped: 0 ✓

---

*Requirements defined: 2026-05-04 from `.planning/v2.4-BACKLOG-PLAN.md`*
*Last updated: 2026-05-04 — initial v2.4 definition*

---

## Milestone v2.5 — Real-time Live Cockpit + Logger Migration

**Defined:** 2026-05-04 (post v2.4-MILESTONE-AUDIT.md `tech_debt` verdict)
**Goal:** Boucler les 12 items deferred du milestone v2.4 — observabilité serveur réelle, signal SSE temps réel autonome, finitions cockpit.

### v2.5 Phase 5: SSE Live Pulse

- [x] **HEARTBEAT-V25-01**: `public/api/v1/events.php` émet `meeting.heartbeat` toutes les 10s avec payload `{meeting_id, server_time, status, validated_at, quorum: {applied, met, present_members, eligible_members, present_weight, eligible_weight}, operator_count}`. Sub-queries try/catch isolées. *Livré commit `02179ea`.*
- [x] **HEARTBEAT-V25-02**: Frontend `operator-realtime.js` consomme `meeting.heartbeat` via `applyHeartbeat()` qui rafraîchit `quorumStatusBadge`/`quorumStatusDetail`/badge présence/timestamp SSE. Hash payload pour skip DOM thrash. Détection transition quorum → toast "Quorum atteint !". *Livré commit `02179ea`.*
- [ ] **HEARTBEAT-V25-03**: PHPUnit `HeartbeatPayloadTest` ≥3 tests GREEN sur `buildHeartbeatPayload()` (cas nominal + dégradations). *Différé — directive utilisateur stop-tests.*
- [ ] **HEARTBEAT-V25-04**: Playwright `sse-heartbeat.spec.js` (≥1 event reçu après 12s). *Différé — directive utilisateur stop-tests.*

### v2.5 Phase 6: Logger Migration & Error Tracking

- [x] **LOG-V25-01**: 47 sites `error_log()` legacy migrés vers `Logger::error/warning/critical/alert/info` avec sévérité différenciée. 6 commits atomiques. 46/48 sites migrés ; 2 résiduels documentés (bootstrap pre-Logger gate + Logger sink fallback). *Commits `f26317a`..`c8fa35c`.*
- [x] **LOG-V25-02**: Table `error_events` (`database/migrations/20260504_error_events.sql`) + 3 indexes. `ErrorEventsRepository::capture()` try/catch isolé. `api_fail()` hooké pour capturer chaque return erreur AVANT throw `ApiResponseException`. *Commit `372c36a`.*
- [x] **LOG-V25-03**: `/admin/error-stats` recâblée sur `error_events` (skip de la version v2.4 transitionnelle). `AdminErrorStatsController::stats()` + page HTMX (4 KPI + timeline SVG + top codes table). Window 1-720h, scope tenant + cross-tenant admin + drill-down. *Commit `d3f4765`.*
- [x] **LOG-V25-04**: Endpoint `POST /api/v1/metrics/next-step-clicked` (`MetricsController`) rate-limit 60/min. `next_step_clicks` table + repo + JS utility `window.AgErrorMetrics.trackNextStep()` (sendBeacon préféré). Page `/admin/error-stats` étendue avec colonne CTR. *Commit `f1ae41c`.*

### v2.5 Phase 7: Cockpit Polish résiduel

- [ ] **COCKPIT-V25-01**: Sub-tab Avancé du cockpit operator ne fait plus passer le compte de cliquables visibles à >25 quand activé. Spec Playwright `cockpit-button-count.spec.js` étendu d'1 cas.
- [ ] **TOKENS-V25-01**: Audit des 49 tokens 1-site introduits v2.4 P4.3 dans `design-system.css`. Décision *keep* / *consolidate* documentée dans `.planning/v2.5-TOKENS-AUDIT.md`. Objectif tokens 1-site < 30.

### v2.5 Bonus — SEC-V2-01 closeout

- [x] **SEC-V2-01**: 8 méthodes MotionRepository à `tenantId = ''` optionnel migrées vers paramètre requis avec filtre tenant inconditionnel. *Commit `bef552f`.*
- [x] **SEC-V2-01-extended**: Pattern étendu à 10 repos additionnels (19 méthodes). Audit-ready : zéro `tenantId = ''` default dans `app/Repository/`. *Commit `ec1ee49`.*

---

### v2.5 Traceability

| Requirement | Phase | Status | Evidence |
|-------------|-------|--------|----------|
| HEARTBEAT-V25-01 | 5 | ✓ Done | commit `02179ea` |
| HEARTBEAT-V25-02 | 5 | ✓ Done | commit `02179ea` |
| HEARTBEAT-V25-03 | 5 | ⏸ Deferred (tests) | — |
| HEARTBEAT-V25-04 | 5 | ⏸ Deferred (tests) | — |
| LOG-V25-01 | 6 | ✓ Done | commits `f26317a`..`c8fa35c` |
| LOG-V25-02 | 6 | ✓ Done | commit `372c36a` |
| LOG-V25-03 | 6 | ✓ Done | commit `d3f4765` |
| LOG-V25-04 | 6 | ✓ Done | commit `f1ae41c` |
| COCKPIT-V25-01 | 7 | ⏸ Pending | — |
| TOKENS-V25-01 | 7 | ⏸ Pending | — |
| SEC-V2-01 | 6 (bonus) | ✓ Done | commit `bef552f` |
| SEC-V2-01-extended | 6 (bonus) | ✓ Done | commit `ec1ee49` |

**v2.5 Coverage:** 12 requirements total · 8/12 done · 2/12 deferred (tests) · 2/12 pending (Phase 7)

---

*Requirements v2.5 defined: 2026-05-04 — sourced from .planning/v2.4-MILESTONE-AUDIT.md tech_debt frontmatter (12 items) + SEC-V2-01 v2 backlog item.*
