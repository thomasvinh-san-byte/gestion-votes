# Roadmap: AgVote

## Milestones

- ✅ **v1.0 Dette Technique** - Phases 1-4 (shipped 2026-04-07) — see `.planning/milestones/v1.0-ROADMAP.md`
- ✅ **v1.1 Coherence UI/UX et Wiring** - Phases 5-7 (shipped 2026-04-08) — see `.planning/milestones/v1.1-ROADMAP.md`
- ✅ **v1.2 Bouclage et Validation Bout-en-Bout** - Phases 8-13 (shipped 2026-04-09) — see `.planning/milestones/v1.2-ROADMAP.md`
- ✅ **v1.3 Polish Post-MVP** - Phases 14-17 (shipped 2026-04-09) — see `.planning/milestones/v1.3-ROADMAP.md`
- ✅ **v1.4 Regler Deferred et Dette Technique** - Phases 1-6 (shipped 2026-04-10) — see `.planning/milestones/v1.4-ROADMAP.md`
- ✅ **v1.5 Nettoyage et Refactoring Services** - Phases 1-7 (shipped 2026-04-20) — see `.planning/milestones/v1.5-ROADMAP.md`

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

### v1.6 Reparation UI et Polish Fonctionnel (In Progress)

**Milestone Goal:** Reparer les interactions JS cassees sur les 21 pages HTMX, moderniser les formulaires pour exploiter les ecrans horizontaux, et verifier chaque page bout-en-bout.

- [x] **Phase 1: JS Interaction Audit & Repair** - Audit systematique des 21 pages HTMX, reparer boutons, formulaires, et SSE casses (completed 2026-04-20)
- [x] **Phase 2: Form Layout Modernization** - Layouts multi-colonnes, champs compacts, utilisation agressive de la largeur (completed 2026-04-20)
- [ ] **Phase 3: Wizard Single-Page** - L'assistant de creation de seance tient sur un seul viewport
- [ ] **Phase 4: Validation Gate** - Verification bout-en-bout de chaque page, zero regression

## Phase Details

### Phase 1: JS Interaction Audit & Repair
**Goal**: Toutes les interactions JS/HTMX fonctionnent sur les 21 pages — zero erreur console, zero bouton mort, zero formulaire qui recharge la page
**Depends on**: Nothing (first phase)
**Requirements**: JSFIX-01, JSFIX-02, JSFIX-03, JSFIX-04
**Success Criteria** (what must be TRUE):
  1. Les 21 pages HTMX chargent sans erreur dans la console JS du navigateur
  2. Chaque bouton d'action (creer, modifier, supprimer, ouvrir modale) repond au clic et produit l'effet attendu
  3. Tous les formulaires soumettent via HTMX sans rechargement de page complet
  4. Les mises a jour SSE temps reel apparaissent sur les pages operator et vote sans intervention manuelle
**Plans:** 3 plans

Plans:
- [ ] 01-01-PLAN.md — Audit operator page (6 JS files + SSE) and vote page
- [ ] 01-02-PLAN.md — Audit wizard, postsession, validate, meetings, members, settings
- [ ] 01-03-PLAN.md — Audit dashboard, admin, users, trust, hub, archives, audit, analytics, docs, email-templates, help, public, report

### Phase 2: Form Layout Modernization
**Goal**: Les formulaires exploitent l'espace horizontal avec des layouts multi-colonnes et des champs compacts et modernes
**Depends on**: Phase 1
**Requirements**: FORM-01, FORM-02, FORM-03
**Success Criteria** (what must be TRUE):
  1. Sur un ecran >1024px, les formulaires affichent 2-3 colonnes de champs cote a cote au lieu d'une pile verticale
  2. Les inputs, selects, et textareas ont un style uniforme compact (meme hauteur, meme espacement, meme typographie)
  3. Aucun formulaire mono-colonne ne depasse 60% de la largeur disponible du viewport
**Plans:** 1/3 plans executed

Plans:
- [ ] 02-01-PLAN.md — Grid layouts for operator, settings, postsession, email-templates (heavy forms)
- [ ] 02-02-PLAN.md — Grid layouts for members, users, meetings, admin, validate (medium forms)
- [ ] 02-03-PLAN.md — Field class normalization for vote, report, archives, trust, audit, analytics, help (light forms)

### Phase 3: Wizard Single-Page
**Goal**: L'assistant de creation de seance (4 etapes) tient entierement dans un seul viewport sans scroll vertical
**Depends on**: Phase 2
**Requirements**: WIZ-01
**Success Criteria** (what must be TRUE):
  1. Les 4 etapes du wizard sont visibles simultanement ou navigables sans scroll vertical sur un ecran 1080p
  2. L'utilisateur peut creer une seance complete (remplir les 4 etapes et valider) sans scroller la page
**Plans**: TBD

### Phase 4: Validation Gate
**Goal**: Chaque page HTMX verifiee bout-en-bout — zero regression visuelle ni fonctionnelle apres les modifications des phases 1-3
**Depends on**: Phase 3
**Requirements**: VALID-01
**Success Criteria** (what must be TRUE):
  1. Les 21 pages HTMX passent une verification manuelle bout-en-bout dans le navigateur sans regression
  2. Les tests Playwright existants restent green (zero regression E2E)
  3. Les tests PHPUnit existants restent green (zero regression backend)
**Plans**: TBD

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Infrastructure Redis | v1.0 | 2/2 | Complete | 2026-04-07 |
| 2. Optimisations Memoire et Requetes | 1/3 | In Progress|  | 2026-04-07 |
| 3. Refactoring Controllers et Tests Auth | v1.0 | 3/3 | Complete | 2026-04-07 |
| 4. Tests et Decoupage Controllers | v1.0 | 3/3 | Complete | 2026-04-07 |
| 5. JS Audit et Wiring Repair | v1.1 | 1/1 | Complete | 2026-04-08 |
| 6. Application Design Tokens | v1.1 | 4/4 | Complete | 2026-04-08 |
| 7. Playwright Coverage | v1.1 | 4/4 | Complete | 2026-04-08 |
| 8. Test Infrastructure Docker | v1.2 | 3/3 | Complete | 2026-04-08 |
| 9. Tests E2E par Role | v1.2 | 5/5 | Complete | 2026-04-08 |
| 10. Validation Manuelle Bout-en-Bout | v1.2 | — | Complete | 2026-04-08 |
| 11. Backend Wiring Fixes | v1.2 | 7/7 | Complete | 2026-04-08 |
| 12. Page-by-Page MVP Sweep | v1.2 | 20/21 | Complete | 2026-04-09 |
| 13. MVP Validation Finale | v1.2 | — | Complete | 2026-04-09 |
| 14. Visual Polish | v1.3 | 4/4 | Complete | 2026-04-09 |
| 15. Multi-Browser Tests | v1.3 | — | Complete | 2026-04-09 |
| 16. Accessibility Deep Audit | v1.3 | 5/5 | Complete | 2026-04-09 |
| 17. Loose Ends Phase 12 | v1.3 | 3/3 | Complete | 2026-04-09 |
| 1. Contrast AA Remediation | v1.4 | 3/3 | Complete | 2026-04-10 |
| 2. Overlay Hittest Sweep | v1.4 | 2/2 | Complete | 2026-04-10 |
| 3. Trust Fixtures Deploy | v1.4 | 2/2 | Complete | 2026-04-10 |
| 4. HTMX 2.0 Upgrade | v1.4 | 2/2 | Complete | 2026-04-10 |
| 5. CSP Nonce Enforcement | v1.4 | 2/2 | Complete | 2026-04-10 |
| 6. Controller Refactoring | v1.4 | 3/3 | Complete | 2026-04-10 |
| 1. Nettoyage Codebase | v1.5 | 2/2 | Complete | 2026-04-10 |
| 2. Refactoring AuthMiddleware | v1.5 | 2/2 | Complete | 2026-04-11 |
| 3. Refactoring ImportService | v1.5 | 1/1 | Complete | 2026-04-12 |
| 4. Refactoring ExportService | v1.5 | 1/1 | Complete | 2026-04-13 |
| 5. Refactoring MeetingReportsService | v1.5 | 1/1 | Complete | 2026-04-15 |
| 6. Refactoring EmailQueueService | v1.5 | 1/1 | Complete | 2026-04-20 |
| 7. Validation Gate | v1.5 | 1/1 | Complete | 2026-04-20 |
| 1. JS Interaction Audit & Repair | v1.6 | 3/3 | Complete | 2026-04-20 |
| 2. Form Layout Modernization | v1.6 | 3/3 | Complete | 2026-04-20 |
| 3. Wizard Single-Page | v1.6 | 0/? | Not started | - |
| 4. Validation Gate | v1.6 | 0/? | Not started | - |
