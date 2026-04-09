# Roadmap: AgVote

## Milestones

- ✅ **v1.0 Dette Technique** - Phases 1-4 (shipped 2026-04-07) — see `.planning/milestones/v1.0-ROADMAP.md`
- ✅ **v1.1 Coherence UI/UX et Wiring** - Phases 5-7 (shipped 2026-04-08) — see `.planning/milestones/v1.1-ROADMAP.md`
- ✅ **v1.2 Bouclage et Validation Bout-en-Bout** - Phases 8-13 (shipped 2026-04-09) — see `.planning/milestones/v1.2-ROADMAP.md`
- 🚧 **v1.3 Polish Post-MVP** - Phases 14-17 (in progress)

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

### 🚧 v1.3 Polish Post-MVP (In Progress)

**Milestone Goal:** Faire passer l'app de "fonctionnelle prouvee" (v1.2) a "delicieuse a utiliser et solide cross-browser". Polish visuel + robustesse tests + a11y deep audit.

- [x] **Phase 14: Visual Polish** — Toast notifications + dark mode parity + role-specific sidebar + micro-interactions (completed 2026-04-09)
- [x] **Phase 15: Multi-Browser Tests** — Etendre Playwright a firefox + webkit + mobile-chromium, fix divergences (completed 2026-04-09)
- [ ] **Phase 16: Accessibility Deep Audit** — axe-core complet sur 21 pages, fix violations critical+serious, WCAG 2.1 AA
- [ ] **Phase 17: Loose Ends Phase 12** — Settings race condition, postsession chip, autres notes "documented but not fixed"

## Phase Details — Current Milestone

### Phase 14: Visual Polish
**Goal**: L'app feel pro — feedback unifie, dark mode coherent, navigation adaptee par role
**Depends on**: Phase 13 (v1.2 shipped)
**Requirements**: POLISH-01, POLISH-02, POLISH-03, POLISH-04
**Success Criteria** (what must be TRUE):
  1. Un systeme de toast notification existe et est utilise par toutes les pages pour save/error/info feedback (au moins 5 pages converties)
  2. Audit dark mode parity execute sur les 21 pages, toutes les regressions documentees fixees
  3. Sidebar nav filtre les items en fonction du role (admin voit tout, votant voit minimum)
  4. Focus rings, hover states, et loading transitions coherents sur tous les boutons / inputs / liens
**Plans:** 4/4 plans complete
- [ ] 14-01-PLAN.md — Toast notification system unification (POLISH-01)
- [ ] 14-02-PLAN.md — Dark mode parity audit + Shadow DOM fixes (POLISH-02)
- [ ] 14-03-PLAN.md — Role-specific sidebar nav audit (POLISH-03)
- [ ] 14-04-PLAN.md — Micro-interactions polish for .btn variants (POLISH-04)

### Phase 15: Multi-Browser Tests
**Goal**: Les 23 critical-path specs passent en firefox + webkit + mobile-chromium en plus de chromium
**Depends on**: Phase 14 (visual polish stable avant cross-browser)
**Requirements**: CROSS-01, CROSS-02, CROSS-03
**Success Criteria** (what must be TRUE):
  1. playwright.config.js active firefox + webkit + mobile-chromium projects
  2. bin/test-e2e.sh accepte --project flag pour cibler un browser
  3. Les 23 critical-path specs passent en chromium + firefox + webkit + mobile-chromium (ou divergences documentees + fixes appliques)
  4. Aucun spec en error ou skip non-justifie

### Phase 16: Accessibility Deep Audit
**Goal**: Aucune violation a11y critical/serious sur les 21 pages, conformance WCAG 2.1 AA
**Depends on**: Phase 14
**Requirements**: A11Y-01, A11Y-02, A11Y-03
**Success Criteria** (what must be TRUE):
  1. axe-core scan execute sur les 21 pages applicatives (rapport produit)
  2. Toutes les violations critical + serious sont fixees ou explicitement justifiees (avec waiver documente)
  3. Conformance WCAG 2.1 AA documentee dans .planning/v1.3-A11Y-REPORT.md (aria-labels, color contrast, keyboard nav, focus management)
  4. Tests Playwright a11y integrent axe-core sur chaque critical-path spec

### Phase 17: Loose Ends Phase 12
**Goal**: Fix les issues "documented but not blocking" remontees pendant Phase 12
**Depends on**: Phase 14
**Requirements**: LOOSE-01, LOOSE-02, LOOSE-03
**Success Criteria** (what must be TRUE):
  1. Settings.js loadSettings race condition fixed — input #settQuorumThreshold se populate apres reload (test Playwright assertion ajoutee)
  2. Postsession eIDAS chip click delegation robuste (pas de page.evaluate workaround dans les tests)
  3. Audit complet des SUMMARY files de Phase 12 — toutes les notes "documented but not fixed" sont soit fixees soit reportees a v2 explicitement

---

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

**Plans:** 1/0 plans complete
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

**Plans:** 21/21 plans complete
- [ ] 12-01-PLAN.md — settings page sweep (3 gates) [Wave 1]
- [ ] 12-02-PLAN.md — operator page sweep (3 gates) [Wave 1]
- [ ] 12-03-PLAN.md — hub page sweep (3 gates) [Wave 1]
- [ ] 12-04-PLAN.md — dashboard page sweep (3 gates) [Wave 1]
- [ ] 12-05-PLAN.md — meetings page sweep (3 gates) [Wave 2]
- [ ] 12-06-PLAN.md — members page sweep (3 gates) [Wave 2]
- [ ] 12-07-PLAN.md — vote page sweep (3 gates) [Wave 2]
- [ ] 12-08-PLAN.md — wizard page sweep (3 gates) [Wave 2]
- [ ] 12-09-PLAN.md — audit page sweep (3 gates) [Wave 3]
- [ ] 12-10-PLAN.md — archives page sweep (3 gates) [Wave 3]
- [ ] 12-11-PLAN.md — users page sweep (3 gates) [Wave 3]
- [ ] 12-12-PLAN.md — admin page sweep (3 gates) [Wave 3]
- [ ] 12-13-PLAN.md — analytics page sweep (3 gates) [Wave 4]
- [ ] 12-14-PLAN.md — report page sweep (3 gates) [Wave 4]
- [ ] 12-15-PLAN.md — postsession page sweep (3 gates) [Wave 4]
- [ ] 12-16-PLAN.md — validate page sweep (3 gates) [Wave 4]
- [ ] 12-17-PLAN.md — trust page sweep (3 gates) [Wave 5]
- [ ] 12-18-PLAN.md — public projection page sweep (3 gates) [Wave 5]
- [ ] 12-19-PLAN.md — email-templates page sweep (3 gates) [Wave 5]
- [ ] 12-20-PLAN.md — docs content page sweep (3 gates, 80ch cap) [Wave 5]
- [ ] 12-21-PLAN.md — help content page sweep (3 gates, 80ch cap) [Wave 5]

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
| 12. Page-by-Page MVP Sweep | 20/21 | In Progress| Complete    | 2026-04-09 |
| 13. MVP Validation Finale | v1.2 | 0/? | Complete    | 2026-04-09 |
| 14. Visual Polish | 4/4 | Complete   | Complete    | 2026-04-09 |
| 15. Multi-Browser Tests | v1.3 | 0/? | Complete    | 2026-04-09 |
| 16. Accessibility Deep Audit | v1.3 | 0/? | Not started | - |
| 17. Loose Ends Phase 12 | v1.3 | 0/? | Not started | - |
