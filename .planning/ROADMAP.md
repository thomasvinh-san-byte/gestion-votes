# Roadmap: AgVote

## Milestones

- ✅ **v1.0 Dette Technique** - Phases 1-4 (shipped 2026-04-07) — see `.planning/milestones/v1.0-ROADMAP.md`
- ✅ **v1.1 Coherence UI/UX et Wiring** - Phases 5-7 (shipped 2026-04-08) — see `.planning/milestones/v1.1-ROADMAP.md`
- 🚧 **v1.2 Bouclage et Validation Bout-en-Bout** - Phases 8-11 (in progress)

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

### 🚧 v1.2 Bouclage et Validation Bout-en-Bout (In Progress)

**Milestone Goal:** Boucler le projet — verifier que toutes les fonctionnalites du chemin critique fonctionnent pour les 4 roles, validees par tests automatises ET parcours manuel browser. Stop aux ajouts, on s'assure que le tout fonctionne comme un tout.

- [ ] **Phase 8: Test Infrastructure Docker** — Make Playwright actually run in a containerized environment with all browsers + system libs
- [ ] **Phase 9: Tests E2E par Role** — 4 critical-path E2E specs covering admin, operator, president, votant
- [ ] **Phase 10: Validation Manuelle Bout-en-Bout** — Conversational UAT par role, checklists, regression discovery
- [ ] **Phase 11: Reparation et Dette Technique v1.0** — Fix discovered regressions + close v1.0 carry-over debt

## Phase Details

### Phase 8: Test Infrastructure Docker
**Goal**: Faire que Playwright tourne reellement dans un environnement reproductible — fini "le test serait valide si seulement il pouvait s'executer"
**Depends on**: Phase 7 (v1.1 complete)
**Requirements**: INFRA-01, INFRA-02, INFRA-03
**Success Criteria** (what must be TRUE):
  1. Un Dockerfile.test (ou compose service) avec libatk + libcairo + libpango + chromium + firefox + webkit installes via mcr.microsoft.com/playwright ou apt
  2. `bin/test-e2e.sh` existe, lance la suite Playwright dans le container, et retourne le rapport (exit code reflete le pass/fail)
  3. Tous les specs existants v1.0 et v1.1 (~20 fichiers) plus page-interactions.spec.js et operator-e2e.spec.js passent en chromium dans le container — baseline verte
  4. Le rapport final est lisible (line reporter ou html) et identifie clairement les tests pass/fail

### Phase 9: Tests E2E par Role
**Goal**: Chacun des 4 roles a un test E2E qui exerce son chemin critique de bout-en-bout, sans intervention manuelle
**Depends on**: Phase 8 (infra must run)
**Requirements**: E2E-01, E2E-02, E2E-03, E2E-04
**Success Criteria** (what must be TRUE):
  1. `tests/e2e/specs/critical-path-admin.spec.js` execute login -> settings -> users management -> audit -> logout en passant
  2. `tests/e2e/specs/critical-path-operator.spec.js` execute login -> creer assemblee -> ajouter membres -> lancer vote -> cloturer -> rapport en passant
  3. `tests/e2e/specs/critical-path-president.spec.js` execute login -> assemblee active -> ouvrir vote -> modifier quorum -> cloturer en passant
  4. `tests/e2e/specs/critical-path-votant.spec.js` execute vote token -> page de vote -> soumettre -> confirmation en passant
  5. Les 4 specs sont re-runnable (unique IDs, pas de cleanup manuel requis)

### Phase 10: Validation Manuelle Bout-en-Bout
**Goal**: Toi + moi parcourons l'app en vrai dans un browser, role par role, page par page, et notons exactement ce qui marche et ce qui casse
**Depends on**: Phase 9
**Requirements**: UAT-01, UAT-02, UAT-03, FIX-02
**Success Criteria** (what must be TRUE):
  1. 4 checklists UAT existent (une par role: admin, operator, president, votant) avec 8-15 etapes verifiees chacune
  2. 1 checklist transverse par page-cle (8 pages: dashboard, hub, meetings, members, operator, vote, settings, admin) avec interactions principales verifiees
  3. Les 11 items "human verification deferred" de v1.1 sont confirmes ou marques cassees
  4. Un rapport `.planning/v1.2-UAT-REPORT.md` documente: ce qui marche, ce qui casse, par role et par page, avec evidence (screenshots/notes)

### Phase 11: Reparation et Dette Technique v1.0
**Goal**: Toutes les regressions decouvertes en Phase 10 sont reparees, et la dette technique v1.0 carry-over est close
**Depends on**: Phase 10 (FIX-01 scope is determined by Phase 10 UAT findings)
**Requirements**: FIX-01, DEBT-01, DEBT-02, DEBT-03
**Success Criteria** (what must be TRUE):
  1. Toutes les regressions du rapport Phase 10 ont un commit de fix associe (zero items "broken" residuels)
  2. `getDashboardStats()` est appele par `DashboardController` et la page dashboard charge ses metriques en une seule requete SQL (perf gain mesurable)
  3. `MeetingReportsController` est descendu sous 300 lignes via extraction `MeetingReportsService` (logique metier dans le service)
  4. `MotionsController` est descendu sous 300 lignes via extraction `MotionsService`
  5. Tous les tests existants (PHPUnit + Playwright) passent toujours apres les refactorings

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Infrastructure Redis | v1.0 | 2/2 | Complete | 2026-04-07 |
| 2. Optimisations Memoire et Requetes | v1.0 | 2/2 | Complete | 2026-04-07 |
| 3. Refactoring Controllers et Tests Auth | v1.0 | 3/3 | Complete | 2026-04-07 |
| 4. Tests et Decoupage Controllers | v1.0 | 3/3 | Complete | 2026-04-07 |
| 5. JS Audit et Wiring Repair | v1.1 | 3/3 | Complete | 2026-04-08 |
| 6. Application Design Tokens | v1.1 | 4/4 | Complete | 2026-04-08 |
| 7. Playwright Coverage | v1.1 | 4/4 | Complete | 2026-04-08 |
| 8. Test Infrastructure Docker | v1.2 | 0/? | Not started | - |
| 9. Tests E2E par Role | v1.2 | 0/? | Not started | - |
| 10. Validation Manuelle Bout-en-Bout | v1.2 | 0/? | Not started | - |
| 11. Reparation et Dette Technique v1.0 | v1.2 | 0/? | Not started | - |
