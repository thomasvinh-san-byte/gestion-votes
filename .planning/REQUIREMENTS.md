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
