# Roadmap: AgVote

## Milestones

- ✅ **v1.0 Dette Technique** - Phases 1-4 (shipped 2026-04-07) — see `.planning/milestones/v1.0-ROADMAP.md`
- ✅ **v1.1 Coherence UI/UX et Wiring** - Phases 5-7 (shipped 2026-04-08) — see `.planning/milestones/v1.1-ROADMAP.md`
- 🚧 **v1.2 Bouclage et Validation Bout-en-Bout** - Phases 8-13 (in progress, escalated to MVP scope)

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

**Milestone Goal (v2 — escalated):** Livrer un MVP qui passe 3 criteres stricts sur les 21 pages : (1) UI moderne pleine largeur (sauf pages de contenu docs/help qui restent ~80ch), (2) design language partage via design-system.css, (3) **chaque element fonctionnel** prouve par Playwright. Plus jamais "shows cards != fonctionne".

- [x] **Phase 8: Test Infrastructure Docker** — Playwright runs in container (completed 2026-04-08)
- [x] **Phase 9: Tests E2E par Role** — 4 critical-path E2E specs GREEN (completed 2026-04-08)
- [x] **Phase 10: Validation Manuelle Bout-en-Bout** — UAT artifacts generated, awaiting human walkthrough OR superseded by Phase 12 page sweep (completed 2026-04-08)
- [x] **Phase 11: Backend Wiring Fixes** — Fix 5 phantom endpoints + wire 6 dead settings + close v1.0 carry-over tech debt (completed 2026-04-08)
- [ ] **Phase 12: Page-by-Page MVP Sweep** — 21 pages each must pass 3 gates: width audit, design tokens audit, function audit (Playwright assertion of real result)
- [ ] **Phase 13: MVP Validation Finale** — Full Playwright suite + final UAT + ship verdict

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

**Plans**: 3 plans
  - [ ] 08-01-PLAN.md — Add tests service to docker-compose.yml + conditional playwright.config.js
  - [ ] 08-02-PLAN.md — Create bin/test-e2e.sh wrapper script
  - [ ] 08-03-PLAN.md — Baseline green run + human-verified HTML report

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

**Plans:** 5/5 plans complete
- [ ] 09-01-PLAN.md — Cookie domain fix in auth.setup.js (prerequisite, Wave 1)
- [ ] 09-02-PLAN.md — E2E-01 admin critical path spec (Wave 2)
- [ ] 09-03-PLAN.md — E2E-02 operator critical path spec (Wave 2)
- [ ] 09-04-PLAN.md — E2E-03 president critical path spec (Wave 2)
- [ ] 09-05-PLAN.md — E2E-04 votant critical path spec (Wave 2)

### Phase 10: Validation Manuelle Bout-en-Bout
**Goal**: Toi + moi parcourons l'app en vrai dans un browser, role par role, page par page, et notons exactement ce qui marche et ce qui casse
**Depends on**: Phase 9
**Requirements**: UAT-01, UAT-02, UAT-03, FIX-02
**Success Criteria** (what must be TRUE):
  1. 4 checklists UAT existent (une par role: admin, operator, president, votant) avec 8-15 etapes verifiees chacune
  2. 1 checklist transverse par page-cle (8 pages: dashboard, hub, meetings, members, operator, vote, settings, admin) avec interactions principales verifiees
  3. Les 11 items "human verification deferred" de v1.1 sont confirmes ou marques cassees
  4. Un rapport `.planning/v1.2-UAT-REPORT.md` documente: ce qui marche, ce qui casse, par role et par page, avec evidence (screenshots/notes)

### Phase 11: Backend Wiring Fixes
**Goal**: Eliminer tous les endpoints fantomes et settings DEAD identifies dans v1.2-PAGES-AUDIT.md, plus la dette tech v1.0 carry-over. Phase prerequis avant Phase 12 (page sweep ne peut pas tester "le bouton fonctionne" si l'endpoint n'existe pas).
**Depends on**: Phase 9 (audit results)
**Requirements**: FIX-01, FIX-02, DEBT-01, DEBT-02, DEBT-03
**Success Criteria** (what must be TRUE):
  1. Les 3 settings core vote (`settVoteMode`, `settQuorumThreshold`, `settMajority`) sont LUS par VoteEngine + QuorumEngine, prouve par un test PHPUnit qui change la valeur et verifie que le calcul change
  2. Les 5 endpoints fantomes sont crees et retournent 200 OK pour un appel valide : `procuration_pdf.php`, `motions_override_decision.php`, `invitations_send_reminder.php`, `meeting_attachments_public.php`, `meeting_attachment_serve.php`
  3. Les 4 boutons orphelins (3 dans /trust + 1 #btnStartTour /meetings) sont soit wires soit supprimes du HTML
  4. Les 3 settings DEAD restants (`settMaxLoginAttempts`, `settPasswordMinLength`, `settHighContrast`) sont soit wires dans le code metier soit supprimes du HTML
  5. `getDashboardStats()` est appele par `DashboardController`, dashboard charge en une seule requete (perf gain mesurable)
  6. `MeetingReportsController` < 300 lignes via extraction `MeetingReportsService`
  7. `MotionsController` < 300 lignes via extraction `MotionsService`
  8. Tous les tests existants (PHPUnit + Playwright) passent apres les refactorings

**Plans:** 7/7 plans complete
- [ ] 11-01-PLAN.md — Wire settVoteMode/settQuorumThreshold/settMajority into VoteEngine + QuorumEngine [FIX-01, Wave 1]
- [ ] 11-02-PLAN.md — Integration tests for procuration_pdf, motions_override_decision, invitations_send_reminder [FIX-01, Wave 1]
- [ ] 11-03-PLAN.md — Integration tests for meeting_attachments_public + meeting_attachment_serve dual-auth [FIX-01, Wave 1]
- [ ] 11-04-PLAN.md — Remove 4 orphan buttons + 3 dead settings, lock with regression test [FIX-02, Wave 2]
- [ ] 11-05-PLAN.md — Wire getDashboardStats in DashboardController [DEBT-01, Wave 2]
- [ ] 11-06-PLAN.md — Extract MeetingReportsService, slim controller to <300 lines [DEBT-02, Wave 2]
- [ ] 11-07-PLAN.md — Extract MotionsService, slim controller to <300 lines [DEBT-03, Wave 2]

### Phase 12: Page-by-Page MVP Sweep
**Goal**: Sweep des 21 pages, chacune doit passer 3 gates avant d'etre marquee done. Le coeur du MVP. Aucune page ne ship sans les 3 gates verts.
**Depends on**: Phase 11 (backend wiring complet)
**Requirements**: MVP-01 (width), MVP-02 (design language), MVP-03 (function)
**Success Criteria** (what must be TRUE for EACH page):
  1. **Width gate** : main content uses full screen width responsibly. Pages applicatives = aucune `max-width` artificielle. Pages de contenu (docs, help) = `max-width: 80ch` pour la lisibilite. Verifie par grep CSS + visual snapshot.
  2. **Design language gate** : zero hex/oklch literal dans le CSS de la page. Tout en `var(--*)` du design-system.css. Verifie par grep `! grep -nE 'oklch\\(\\|#[0-9a-f]{6}' public/assets/css/{page}.css`.
  3. **Function gate** : un test Playwright `critical-path-{page}.spec.js` (ou extension de l'existant) qui pour CHAQUE bouton/input/lien principal de la page declenche l'interaction et assert un changement d'etat reel : DOM update OU API 2xx response OU DB row visible apres reload OU state metier verifie.

**Execution model**: 5 waves with mandatory user checkpoint between waves. Each wave = 4-5 page plans (parallelizable, no file conflicts).

**Plans:** 5/8 plans executed
- [ ] 12-01-PLAN.md — settings page sweep (3 gates) [Wave 1]
- [ ] 12-02-PLAN.md — operator page sweep (3 gates) [Wave 1]
- [ ] 12-03-PLAN.md — hub page sweep (3 gates) [Wave 1]
- [ ] 12-04-PLAN.md — dashboard page sweep (3 gates) [Wave 1]
- [ ] 12-05-PLAN.md — meetings page sweep (3 gates) [Wave 2]
- [ ] 12-06-PLAN.md — members page sweep (3 gates) [Wave 2]
- [ ] 12-07-PLAN.md — vote page sweep (3 gates) [Wave 2]
- [ ] 12-08-PLAN.md — wizard page sweep (3 gates) [Wave 2]
- [ ] Wave 3 (TBD) — audit, archives, users, admin
- [ ] Wave 4 (TBD) — analytics, report, postsession, validate
- [ ] Wave 5 (TBD) — trust, public, email-templates, docs, help

### Phase 13: MVP Validation Finale
**Goal**: Lancer la suite Playwright complete sur toutes les pages, executer le UAT manuel par les 4 roles, produire le verdict final "MVP shipped" ou liste de blockers.
**Depends on**: Phase 12 (toutes les pages swept)
**Requirements**: VAL-01, VAL-02
**Success Criteria** (what must be TRUE):
  1. `bin/test-e2e.sh` (full suite, ~25 specs) retourne exit 0 sans flake (3 runs consecutifs identiques)
  2. UAT manuel (checklists Phase 10) PASS pour les 4 roles, zero blocker, zero gap critique
  3. Rapport `.planning/v1.2-MVP-VALIDATION.md` documente : suite Playwright verte, UAT verdict, screenshots de toutes les pages cles, liste des items "polish" (non-blocking) reportes a v1.3
**Plans**: TBD

---

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
| 8. Test Infrastructure Docker | 3/3 | Complete   | 2026-04-08 | - |
| 9. Tests E2E par Role | 5/5 | Complete   | 2026-04-08 | - |
| 10. Validation Manuelle Bout-en-Bout | v1.2 | 0/? | Complete    | 2026-04-08 |
| 11. Backend Wiring Fixes | 7/7 | Complete   | Complete    | 2026-04-08 |
| 12. Page-by-Page MVP Sweep | 5/8 | In Progress|  | - |
| 13. MVP Validation Finale | v1.2 | 0/? | Not started | - |
