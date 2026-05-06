# Requirements: AgVote v2.7 — Cohérence visuelle & finitions perçues

**Defined:** 2026-05-05
**Core Value:** L'application doit être fiable en production — aucun crash lié à des fallbacks fichiers, fuites mémoire, ou timeouts silencieux.

**Goal :** Finir la migration vers le design system v2.x (cohérence visuelle complète, plus aucun écran "v1 brut") + livrer les finitions UX et perf qui se voient. Pas de capacité métier neuve. Cadré 1 semaine, 4 buckets.

## v1 Requirements

### Bucket 1 — Cohérence visuelle & migration design (Phase 1)

- [ ] **VISUAL-V27-01** : Audit cartographique des 30+ écrans de l'app (`postsession`, `archives`, `members`, `procurations`, `audit`, `analytics`, `admin/*`, `dashboard`, `operator`, `vote`, `login`, `setup`, etc.) → score de cohérence design system v2.x par écran (0-3 : 0 = v1 brut, 3 = pleinement migré). Livrable : `.planning/v2.7-VISUAL-AUDIT.md` avec tableau scoring + screenshots before reference.
- [ ] **VISUAL-V27-02** : Migration typographique 100% — Inter pour UI, Newsreader pour contenu éditorial (`/audit`, `/trust`, `/archives`, `/report`), JetBrains Mono pour hashes/UUID/codes. Aucun `font-family: Arial, ...` ou `Helvetica` brut résiduel. Audit grep livré.
- [ ] **VISUAL-V27-03** : Élimination `#hex` hardcoded dans CSS (hors `design-system.css` + emails) — tous les usages migrent vers `var(--color-*)`. Cible ≥99% borders + shadows + colors via tokens (au-delà des 95-97% v2.6). Audit final dans `.planning/v2.7-TOKENS-FINAL.md`.
- [ ] **VISUAL-V27-04** : Élimination spacing brut (`padding: 12px`, `margin: 16px`, etc.) en dehors de `design-system.css`. Tous via `var(--space-*)`. Cible ≥99%. Audit grep livré.
- [ ] **VISUAL-V27-05** : Migration HTML legacy → composants v2.x — chaque modale `<dialog>` ou `<div class="modal">` brute remplacée par `<ag-modal>`, chaque carte ad-hoc remplacée par `<ag-card>` ou pattern card design-system. Audit grep livré.
- [ ] **VISUAL-V27-06** : Cohérence interactions — animations + transitions homogènes (timings via `var(--motion-*)`, easing via `var(--ease-*)`), states hover/focus/active uniformes. Aucun `transition: 0.3s ease` brut. Audit grep livré.

### Bucket 2 — Loading states systematiques (Phase 2)

- [ ] **LOADING-V27-01** : Skeleton screens sur HTMX swaps >300ms — composant réutilisable `<ag-skeleton>` avec variants (text, card, table, avatar) + intégration HTMX via `htmx:beforeRequest` / `htmx:afterRequest` automatique sur les targets >300ms identifiées (dashboard cards, audit list, archives list, members list).
- [ ] **LOADING-V27-02** : Spinner systematic sur submit buttons — pendant la requête POST/PATCH/DELETE, le bouton affiche un spinner inline + `disabled` état. Pattern HTMX `hx-indicator` étendu, applicable sur tous les forms via attribut data. Aucun double-submit possible.
- [ ] **LOADING-V27-03** : Optimistic UI sur 3 actions critiques — vote cast (UI affiche le vote enregistré avant retour serveur, rollback visible si erreur), présence toggle, motion next. Pattern via `htmx:beforeRequest` event handler central + état "pending" CSS visible.

### Bucket 3 — 404 race graceful UX (Phase 3)

- [ ] **RACE-V27-01** : HTMX response handler central — listener global qui détecte `404 Not Found` sur swap target et substitue le contenu par un empty-state amical au lieu de toast d'erreur. Pattern via `htmx:responseError` event handler, lit le code d'erreur JSON (`meeting_not_found`, `motion_not_found`) pour personnaliser le message.
- [ ] **RACE-V27-02** : Empty-state component `<ag-empty-state>` réutilisable avec 3 variants (resource-deleted, no-data-yet, error). Variant "resource-deleted" affiche message + CTA retour (vers `/dashboard` pour meeting, vers parent meeting pour motion). Pattern aligné design system v2.x.
- [ ] **RACE-V27-03** : Test E2E Playwright `tests/e2e/specs/race-404-empty-state.spec.js` — simule séance supprimée entre liste-affichée et clic-action, vérifie empty-state affiché en place du toast.

### Bucket 4 — Query N+1 + HTTP cache (Phase 4)

- [ ] **PERF-V27-01** : Audit grep N+1 patterns dans `app/Controller/` — identifier les boucles `foreach` qui appellent un repo en interne. Livrable : `.planning/v2.7-N+1-AUDIT.md` avec liste sites + estimation gain (queries économisées par requête).
- [ ] **PERF-V27-02** : Refactor 5-10 hot paths identifiés via eager loading — ajouter méthodes `findManyByIds(array $ids)` aux repos concernés (probablement `MotionRepository`, `MemberRepository`, `BallotRepository`, `AuditEventRepository`), remplacer les boucles dans controllers. Test smoke PHPUnit qui prouve N queries → 1 query.
- [ ] **PERF-V27-03** : ETag/Last-Modified sur 3-5 GET HTMX hot endpoints idempotents (dashboard cards, audit list, archives list, members list). Header `Cache-Control: private, must-revalidate` + handler `If-None-Match` returns `304 Not Modified`. Test PHPUnit prouvant 304 sur même ETag.

## v2 Requirements (deferred / out-of-milestone)

Aucun déclaré formellement — voir Out of Scope ci-dessous pour items considérés et reportés.

## Out of Scope

| Feature | Reason |
|---------|--------|
| SSE scaling multi-op (Redis pub/sub natif refactor) | Complex + low ROI <5 op simultanés ; refacto stack live = risque élevé |
| Mobile responsive deep audit | Milestone séparée — scope trop large pour ~1 sem |
| Accessibility WCAG AAA push | Trop gros — multi-milestone, AA partiel suffisant pour l'instant |
| Lazy-load assets / defer scripts / image lazy / preload hints | Overlap avec Bucket 1 (audit cohérence) + Bucket 2 (loading) — plus efficace en finition continue post-v2.7 |
| Capacités métier neuves (signature électronique, archivage hash chain export, procuration en lot, workflow assermenté) | Milestone polish, pas un milestone feature |
| TOKENS mini-cleanup v2.6 (6 orphans + 3 emphasis variants) | Folded dans VISUAL-V27-03 (audit final tokens) |
| 2 résiduels French throws (AttendancesService, BallotsService) | Backlog v2.8 — dette ERR-V27-XX |
| 4 pre-existing ErrorDictionaryTest failures | Investigation séparée — pré-v2.6, pas dans scope cohérence visuelle |
| INFRA-V26-01/03/05 dev-machine observations | Restent OPS-CHECKLIST, pas du code à écrire |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| VISUAL-V27-01 | Phase 1 | ✓ Complete |
| VISUAL-V27-02 | Phase 1 | ✓ Complete |
| VISUAL-V27-03 | Phase 1 | ✓ Complete |
| VISUAL-V27-04 | Phase 1 | ✓ Complete |
| VISUAL-V27-05 | Phase 1 | ✓ Complete |
| VISUAL-V27-06 | Phase 1 | ✓ Complete |
| LOADING-V27-01 | Phase 2 | ✓ Complete |
| LOADING-V27-02 | Phase 2 | ✓ Complete |
| LOADING-V27-03 | Phase 2 | ✓ Complete |
| RACE-V27-01 | Phase 3 | ✓ Complete |
| RACE-V27-02 | Phase 3 | ✓ Complete |
| RACE-V27-03 | Phase 3 | ✓ Complete |
| PERF-V27-01 | Phase 4 | ✓ Complete |
| PERF-V27-02 | Phase 4 | ✓ Complete |
| PERF-V27-03 | Phase 4 | ✓ Complete |

**Coverage :**
- v1 requirements : 15 total
- Mapped to phases : 15
- Unmapped : 0 ✓

---
*Requirements defined : 2026-05-05*
*Last updated : 2026-05-05 after v2.7 milestone bootstrap*
