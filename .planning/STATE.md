---
gsd_state_version: 1.0
milestone: v2.2
milestone_name: Refonte Visuelle & Cohérence
status: in_progress
stopped_at: "Phase 1 (Design Tokens) implementation in PR #256 — awaiting review/merge. Phases 2-4 ready to plan."
last_updated: "2026-04-29T11:00:00Z"
last_activity: 2026-04-29 -- v2.2 milestone bootstrapped (REQUIREMENTS + ROADMAP + Phase 1 SUMMARY) ; Phase 1 design tokens shipped via PR #256
progress:
  total_phases: 4
  completed_phases: 0
  total_plans: 1
  completed_plans: 1
  percent: 25
---

# AG-VOTE -- Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-29)

**Core value:** L'application doit dégager le sérieux civique de sa promesse à chaque écran ; cohérence visuelle complète (palette, dark mode, identité par rôle) ; lexique unifié.
**Current focus:** v2.2 Refonte Visuelle & Cohérence — pyramide en 4 phases (tokens → components → personas → layout)

## Current Position

Milestone: v2.2 Refonte Visuelle & Cohérence
Branch: feat/v2.2-design-tokens (Phase 1 work)
Phase: 1 of 4 (Design Tokens) — implémentation shipped via PR #256, en review
Status: Phase 1 ready_to_merge ; Phase 2 (Components) ready_to_plan dès que PR #256 mergée

Progress: [██▌.......] 25% (1/4 phases done)

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

**Next action:** Review + merge PR #256 ; puis sur main : `/gsd:plan-phase 2` pour Phase 2 Components
