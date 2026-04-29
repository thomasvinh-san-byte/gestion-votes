---
gsd_state_version: 1.0
milestone: v2.3
milestone_name: Layout Refonte & UX Polish
status: planning
stopped_at: "v2.3 milestone bootstrap + UX review (lentille Zhuo/Norman) — REQUIREMENTS étendu de 22 à 29, ROADMAP enrichi (3 états DASHBOARD-02, hiérarchie 2 niveaux cockpit, screenshot panel gate). Phase 1 (Cockpit Opérateur live) prête à planifier sur base amendée."
last_updated: "2026-04-29T15:00:00Z"
last_activity: 2026-04-29 -- v2.3 UX review on branche claude/gsd-ux-review-YG5K0 (.planning/v2.3-UX-REVIEW.md) — 7 nouveaux requirements (COCKPIT-06/07, EDITORIAL-07, DASHBOARD-04, MODAL-03, ERR-03/04) + 6 amendements (COCKPIT-01/02, EDITORIAL-04/05, DASHBOARD-01/02) + screenshot panel gate
progress:
  total_phases: 4
  completed_phases: 0
  total_plans: 0
  completed_plans: 0
  percent: 0
---

# AG-VOTE -- Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-29)

**Core value:** Test ultime — un utilisateur tiers regardant un screenshot avant/après doit dire "celui-là est plus rassurant" sans qu'on lui explique pourquoi.
**Current focus:** v2.3 Layout Refonte & UX Polish — cockpit santé, pages éditoriales, lexique unifié, modales focus trap

## Current Position

Milestone: v2.3 Layout Refonte & UX Polish
Branch: feat/v2.2-design-tokens (v2.3 bootstrap docs ajoutés à la même branche que v2.2 PR #256)
Phase: 0 of 4 — milestone initialized, ready_to_plan Phase 1

Progress: [..........] 0% (planning)

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting v2.2:

- [v2.2 strategy] Pyramide stricte : tokens → components → personas → layout. Une PR par étage, pas de stack.
- [v2.2 brand] Bleu République `oklch(0.45 0.180 265)` (#2c468f) — plus profond que l'ancien (0.480) et harmonisé avec le DSFR sans le copier.
- [v2.2 sémantiques] Harmonisées au brand (chroma 0.13-0.18, lightness 0.45-0.62) — pas Material Default. Vert sénat hue 165, rouge huissier désat, ocre archive hue 75, bleu instruction hue 230.
- [v2.2 surfaces light] Modern tech — neutral pur `oklch(0.985 0.001 0)` = #fbfbfb. `#ffffff` réservé aux modals/popovers.
- [v2.2 dark mode] Designé indépendamment, pas un light inversé. 5 niveaux d'élévation, hue 260, saturation -25%, lightness inversée. Aucun noir pur.
- [v2.2 personas] 6 rôles dans le spectre froid 240°-330° (admin/président/opérateur/auditeur/votant/public). Différenciation par lightness/saturation, pas par teintes opposées. Distincts des `--persona-*` historiques (sections sidebar).
- [v2.2 polices] Inter pour UI, Newsreader pour contenu éditorial (PV/audit/archives), JetBrains Mono pour hashes/UUID.
- [v2.2 emails] Hex en dur conservé (compat clients email), source de vérité = DESIGN.md table de mapping.
- [v2.2 dark detection] `prefers-color-scheme` natif via `:where()` pour spécificité 0 ; toggle JS utilisateur reste prioritaire.
- [v2.2 lexique] Convention "membre/participant/votant" + "confirmer/valider/verrouiller-archiver" appliquée Phase 4 (avec layout).

### Pending Todos

- Review + merge PR #256 (Phase 1 Design Tokens)
- Planifier Phase 2 (Components) via /gsd:plan-phase 2

### Blockers/Concerns

None.

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 1 | Sceller le setup: bloquer SetupController si un admin existe et exiger CSRF | 2026-04-29 | 8c0e64a | [1-sceller-le-setup-bloquer-setupcontroller](./quick/1-sceller-le-setup-bloquer-setupcontroller/) |

## Session Continuity

Last session: 2026-04-29
Stopped at: v2.2 Phase 1 (Design Tokens) shipped via PR #256, milestone GSD bootstrappé sur la même branche
Resume file: None

**Next action:** Une fois PR #256 mergée, créer branche `feat/v2.3-cockpit-operateur` depuis main puis `/gsd:plan-phase 1` pour Cockpit Opérateur live (COCKPIT-01..05).
