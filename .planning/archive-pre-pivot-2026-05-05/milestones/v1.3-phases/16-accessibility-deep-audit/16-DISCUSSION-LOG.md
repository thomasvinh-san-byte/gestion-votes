# Phase 16: Accessibility Deep Audit - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> See `16-CONTEXT.md` for the decisions that actually drive downstream work.

**Mode:** `--auto` (no interactive Q&A; Claude picked recommended defaults)
**Date:** 2026-04-09

## Gray areas identified

1. **Audit scope & coverage strategy** — Comment structurer les tests sur 21 pages
2. **Color contrast policy** — Re-enable color-contrast dans axeAudit.js ou séparer ?
3. **Violation resolution strategy** — Fixer par page ou par type de violation ?
4. **Waiver mechanism** — Comment justifier les violations irréparables ?
5. **Keyboard navigation & focus management** — Manuel, auto, ou mixte ?
6. **Report format** — Structure du `v1.3-A11Y-REPORT.md`
7. **Handling existing WIP** — Les fixes a11y déjà amorcés

## Auto-selections

### GA1: Audit scope & coverage
- **Q:** Test unique paramétrisé ou fichier par page ?
- **Selected:** Paramétrisation via liste `PAGES = [...]` dans un seul fichier (recommended)
- **Rationale:** DRY, scale à 21 pages, skip facile

### GA2: Color contrast policy
- **Q:** Activer color-contrast dans axeAudit.js ?
- **Selected:** Garder désactivé dans le runner structurel + audit séparé one-shot (recommended)
- **Rationale:** Évite faux positifs (dark mode, design tokens), rapport dédié

### GA3: Violation resolution
- **Q:** Fixer page-par-page ou par type de violation ?
- **Selected:** Batch-fix par type de violation (recommended)
- **Rationale:** Enforce patterns cohérents, plus rapide

### GA4: Waiver mechanism
- **Q:** Comment gérer les violations irréparables ?
- **Selected:** Commentaires inline `// A11Y-WAIVER: rule — reason — expires` + paramètre `extraDisabledRules` dans axeAudit (recommended)
- **Rationale:** Traceable, expirable, reviewable

### GA5: Keyboard nav
- **Q:** Test automatisé, manuel, ou mixte ?
- **Selected:** Nouveau spec `keyboard-nav.spec.js` (skip-link, focus trap modales) + audit manuel flows critiques (recommended mix)
- **Rationale:** axe ne test pas focus dynamique, mais manuel exhaustif trop coûteux

### GA6: Report format
- **Q:** Structure du rapport ?
- **Selected:** 7 sections (scope, résultats per-page, contraste, keyboard, waivers, conformance, annexe)
- **Rationale:** Satisfait ROADMAP success criterion #3

### GA7: Existing WIP
- **Q:** Jeter, refaire, ou utiliser comme seed ?
- **Selected:** Seed pour plan 16-01, commit en début d'exécution
- **Rationale:** Déjà diagnostiqué, éviter double-travail

## Scope redirections

Aucune — le scope de la phase est clair, pas de dérive.

## Deferred ideas

- Screen reader manual testing (NVDA/VoiceOver/JAWS)
- i18n
- Conformance AAA
- Audit a11y des emails HTML
- Keyboard shortcuts (confirmé out-of-scope par feedback memory)

---

*Auto-generated — revisit with interactive `/gsd:discuss-phase 16` if decisions need adjustment before planning.*
