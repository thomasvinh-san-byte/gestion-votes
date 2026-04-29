---
phase: 4
phase_name: Layout & Lexique (partial)
milestone: v2.2
verdict: implemented_partial
date: 2026-04-29
pr: 256
---

# Phase 4 SUMMARY — Layout & Lexique (livraison partielle)

| Req | Verdict | Approche |
|---|---|---|
| DESIGN-L01 — Cockpit santé opérateur | ⏭ Reporté | Refonte de operator.htmx.html en exécution. Risque visuel élevé sans observation production. PR `feat/v2.3-layout-refonte` séparée. |
| DESIGN-L02 — Vote screen typo | ✓ | `.vote-btn` : 88px → 96px min-height, `font-size: var(--text-lg)`, `font-weight: 600`. |
| DESIGN-L03 — PV/Trust/Archives éditorial | ⏭ Reporté | Nécessite self-host Newsreader + tests visuels par page. |
| DESIGN-L04 — Dashboard simplifié | ⏭ Reporté | Refonte structurelle. |
| DESIGN-L05 — Login moins marketing | ⏭ Reporté | Polish secondaire. |
| DESIGN-X01 — Convention membre/participant/votant | ⏭ Reporté | 73× / 28× / 22× — migration cas-par-cas, risque sémantique juridique. |
| DESIGN-X02 — "secrétaire de séance" éliminé | ✓ | 1 seule occurrence (operator.htmx.html:1473). Migrée vers "opérateur de séance". Label "Notes secrétaire" → "Notes opérateur". |
| DESIGN-X03 — Migration grep+replace | ✓ (conservateur) | Limité à X02. La migration plus large attend X01. |
| DESIGN-X04 — Tests convention copy | ✓ | `tests/Security/CopyConventionsTest.php` : 3 cas (terms forbidden, no secrétaire de séance, no leftover placeholders). 708 assertions. |

## Items reportés à une PR séparée v2.3

Les 4 items de layout (L01, L03, L04, L05) et la migration lexique large (X01) justifient une PR dédiée avec revue visuelle attentive — ils touchent la perception du produit, pas juste les tokens.

## Commits
- `feat(v2.2 phase 4): vote typo lift + lexique guard`
