# Roadmap: AgVote

## Milestones

- ✅ **v1.0 Dette Technique** - Phases 1-4 (shipped 2026-04-07) — see `.planning/milestones/v1.0-ROADMAP.md`
- ✅ **v1.1 Coherence UI/UX et Wiring** - Phases 5-7 (shipped 2026-04-08) — see `.planning/milestones/v1.1-ROADMAP.md`
- ✅ **v1.2 Bouclage et Validation Bout-en-Bout** - Phases 8-13 (shipped 2026-04-09) — see `.planning/milestones/v1.2-ROADMAP.md`
- ✅ **v1.3 Polish Post-MVP** - Phases 14-17 (shipped 2026-04-09) — see `.planning/milestones/v1.3-ROADMAP.md`
- ✅ **v1.4 Regler Deferred et Dette Technique** - Phases 1-6 (shipped 2026-04-10) — see `.planning/milestones/v1.4-ROADMAP.md`
- ✅ **v1.5 Nettoyage et Refactoring Services** - Phases 1-7 (shipped 2026-04-20) — see `.planning/milestones/v1.5-ROADMAP.md`
- ✅ **v1.6 Reparation UI et Polish Fonctionnel** - Phases 1-4 (shipped 2026-04-20) — see `.planning/milestones/v1.6-ROADMAP.md`
- 🚧 **v1.7 Audit Idempotence** - Phases 1-3 (in progress)

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

### v1.7 Audit Idempotence (In Progress)

**Milestone Goal:** Verifier et renforcer l'idempotence des routes POST/PATCH/DELETE critiques -- aucun doublon sur vote, creation seance, creation membre, envoi email.

- [ ] **Phase 1: Audit et Classification** - Inventaire complet des routes mutantes, classification par risque, documentation des lacunes
- [ ] **Phase 2: Gardes Backend** - Ajout IdempotencyGuard sur routes critiques non protegees + idempotence workflow
- [ ] **Phase 3: Frontend et Validation** - Header HTMX X-Idempotency-Key + tests unitaires

## Phase Details

### Phase 1: Audit et Classification
**Goal**: Toutes les routes mutantes sont inventoriees et classees par risque -- la couverture actuelle et les lacunes sont documentees
**Depends on**: Nothing (first phase)
**Requirements**: IDEM-01, IDEM-02
**Success Criteria** (what must be TRUE):
  1. Un document liste chaque route POST/PATCH/DELETE avec sa protection actuelle (IdempotencyGuard, UNIQUE constraint, CSRF seul)
  2. Chaque route porte une classification de risque (critique/moyen/bas) basee sur l'impact d'un doublon en production
  3. Les routes non protegees critiques sont identifiees comme cibles pour Phase 2
**Plans**: 1 plan

Plans:
- [ ] 01-01-PLAN.md -- Audit complet des routes mutantes avec classification par risque

### Phase 2: Gardes Backend
**Goal**: Les routes critiques non protegees resistent au double-submit -- aucune creation en doublon possible sur les endpoints identifies en Phase 1
**Depends on**: Phase 1
**Requirements**: IDEM-03, IDEM-04, IDEM-05
**Success Criteria** (what must be TRUE):
  1. Les routes de creation sans contrainte UNIQUE (email_templates, export_templates, member_groups, reminders, attachments, resolution_documents) rejettent un second appel avec le meme idempotency key
  2. L'import bulk de membres avec le meme fichier ne cree pas de doublons quand soumis deux fois
  3. Demarrer ou cloturer une seance deja dans l'etat cible retourne un succes sans effet de bord (operation idempotente)
**Plans**: 1 plan

Plans:
- [ ] 01-01-PLAN.md -- Audit complet des routes mutantes avec classification par risque

### Phase 3: Frontend et Validation
**Goal**: Le frontend envoie automatiquement un identifiant unique par action et des tests prouvent que le systeme rejette les doublons
**Depends on**: Phase 2
**Requirements**: IDEM-06, IDEM-07
**Success Criteria** (what must be TRUE):
  1. Chaque formulaire HTMX avec methode POST/PATCH envoie un header X-Idempotency-Key unique genere cote client
  2. Un second clic sur un bouton de soumission ne produit pas de requete avec le meme idempotency key
  3. Les tests unitaires demontrent qu'IdempotencyGuard retourne une erreur 409 sur requete dupliquee et accepte une requete avec un nouveau key
**Plans**: 1 plan

Plans:
- [ ] 01-01-PLAN.md -- Audit complet des routes mutantes avec classification par risque

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
| 3. Wizard Single-Page | v1.6 | 1/1 | Complete | 2026-04-20 |
| 4. Validation Gate | v1.6 | 1/1 | Complete | 2026-04-20 |
| 1. Audit et Classification | v1.7 | 0/? | Not started | - |
| 2. Gardes Backend | v1.7 | 0/? | Not started | - |
| 3. Frontend et Validation | v1.7 | 0/? | Not started | - |
