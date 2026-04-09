# Requirements: AgVote v1.3 — Polish Post-MVP

**Defined:** 2026-04-09
**Core Value:** L'application doit etre fiable en production — aucun crash lie a des fallbacks fichiers, des fuites memoire, ou des timeouts silencieux.

## v1.3 Requirements

### Visual Polish

- [ ] **POLISH-01**: Toast notification system unifie pour save/error/info feedback (deferred de v1.1)
- [ ] **POLISH-02**: Dark mode parity audit + fixes — chaque page rendue identiquement bonne en light et dark (4 items deferred de v1.1)
- [ ] **POLISH-03**: Role-specific sidebar nav — chaque role (admin/operator/president/votant) voit son menu adapte (deferred de v1.1)
- [ ] **POLISH-04**: Micro-interactions — focus rings coherents, hover states, loading transitions, button press feedback

### Multi-Browser Tests

- [ ] **CROSS-01**: Etendre playwright.config.js pour activer firefox + webkit + mobile-chromium (chromium-only en v1.2)
- [ ] **CROSS-02**: Run les 23 critical-path specs sur les 4 browsers, fix les divergences (CSS prefixes, focus behavior, layout differences)
- [ ] **CROSS-03**: bin/test-e2e.sh accepte un --project flag pour cibler un browser specifique

### Accessibility Deep Audit

- [ ] **A11Y-01**: axe-core scan complet sur les 21 pages applicatives (Phase 7 a la baseline, deep audit ici)
- [ ] **A11Y-02**: Fix toutes les violations critical + serious detectees
- [ ] **A11Y-03**: Conformance WCAG 2.1 AA documentee (aria-labels, color contrast, keyboard nav, focus management)

### Loose Ends Phase 12

- [ ] **LOOSE-01**: Settings.js loadSettings race condition — input #settQuorumThreshold ne se populate pas apres reload (documente dans 12-01 SUMMARY)
- [ ] **LOOSE-02**: Postsession eIDAS chip click delegation fragile (documente dans 12-15 SUMMARY)
- [ ] **LOOSE-03**: Audit autres SUMMARY files de Phase 12 pour trouver les notes "documented but not fixed"

## v2 Requirements (deferred to next milestone)

- HTMX 2.0 upgrade (breaking changes)
- Visual regression testing (snapshot comparison)
- Lighthouse perf baseline + budget
- phpredis on host install (PHPUnit run on host)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Nouvelles fonctionnalites metier | v1.3 = polish, pas features |
| Refonte majeure | MVP shipped, on raffine |
| HTMX 2.0 upgrade | Breaking changes — milestone separe |
| Visual regression snapshots | Overkill, 23 critical-path tests catchent deja |
| Lighthouse perf baseline | Pas de pain point mesure |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| POLISH-01 | Phase 14 | Pending |
| POLISH-02 | Phase 14 | Pending |
| POLISH-03 | Phase 14 | Pending |
| POLISH-04 | Phase 14 | Pending |
| CROSS-01 | Phase 15 | Pending |
| CROSS-02 | Phase 15 | Pending |
| CROSS-03 | Phase 15 | Pending |
| A11Y-01 | Phase 16 | Pending |
| A11Y-02 | Phase 16 | Pending |
| A11Y-03 | Phase 16 | Pending |
| LOOSE-01 | Phase 17 | Pending |
| LOOSE-02 | Phase 17 | Pending |
| LOOSE-03 | Phase 17 | Pending |

**Coverage:**
- v1.3 requirements: 13 total
- Mapped to phases: 13
- Unmapped: 0

---
*Requirements defined: 2026-04-09*
