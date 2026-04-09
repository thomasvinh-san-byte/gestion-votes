# Roadmap: AgVote

## Milestones

- ✅ **v1.0 Dette Technique** - Phases 1-4 (shipped 2026-04-07) — see `.planning/milestones/v1.0-ROADMAP.md`
- ✅ **v1.1 Coherence UI/UX et Wiring** - Phases 5-7 (shipped 2026-04-08) — see `.planning/milestones/v1.1-ROADMAP.md`
- ✅ **v1.2 Bouclage et Validation Bout-en-Bout** - Phases 8-13 (shipped 2026-04-09) — see `.planning/milestones/v1.2-ROADMAP.md`
- ✅ **v1.3 Polish Post-MVP** - Phases 14-17 (shipped 2026-04-09) — see `.planning/milestones/v1.3-ROADMAP.md`
- 📋 **v1.4** — Planned (run `/gsd:new-milestone` to start)

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
- Visual polish: toast system unifié, dark mode parity, role-specific sidebar, micro-interactions
- Cross-browser: chromium + firefox + webkit + mobile-chrome matrix (25/25 chromium, 25/25 firefox, 23/25 webkit, 21/25 mobile-chrome)
- Accessibility deep audit: 47 structural violations fixed, keyboard-nav spec (6/6), contrast audit produced (316 nodes DEFERRED to token remediation), WCAG 2.1 AA partial conformance
- Loose ends: settings loadSettings race fixed, eIDAS chip delegation fixed, Phase 12 SUMMARY audit ledger (6 findings, 3 deferred to v2)

**Deferred to v2:**
- CONTRAST-REMEDIATION (316 color-contrast nodes, design-token work)
- V2-OVERLAY-HITTEST (systematic `[hidden]`+flex overlay sweep)
- V2-TRUST-DEPLOY (trust.htmx.html auditor/assessor fixtures)
- V2-CSP-INLINE-THEME (strict CSP compatibility)

</details>

### 📋 v1.4 (Not started)

Run `/gsd:new-milestone` to start the next milestone cycle.

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
