---
gsd_state_version: 1.0
milestone: v2.3
milestone_name: Layout Refonte & UX Polish
status: Executing Phase 01
stopped_at: "v2.2 entièrement mergée dans main (PR #256 = edd7079). v2.3 bootstrap + UX review (Zhuo/Norman) appliquée — REQUIREMENTS étendu de 22 à 29, ROADMAP enrichi, screenshot panel gate ajouté."
last_updated: "2026-04-30T04:48:42.561Z"
progress:
  total_phases: 4
  completed_phases: 0
  total_plans: 4
  completed_plans: 0
  percent: 0
---

# AG-VOTE -- Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-29)

**Core value:** Test ultime — un utilisateur tiers regardant un screenshot avant/après doit dire "celui-là est plus rassurant" sans qu'on lui explique pourquoi.
**Current focus:** Phase 01 — Cockpit Opérateur live

## Current Position

Milestone: v2.3 Layout Refonte & UX Polish
Branch: claude/gsd-ux-review-YG5K0 (revue UX appliquée — base = main edd7079 = post-merge PR #256)
Phase: 01 (Cockpit Opérateur live) — EXECUTING
Plan: 1 of 4

Progress: [..........] 0% (planning)

**Base de planning :** v2.2 entièrement mergée dans main (PR #256, commit edd7079). Tokens, components, personas, ag-modal, CopyConventionsTest tous disponibles côté code.

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

- Planifier v2.3 Phase 1 (Cockpit Opérateur live) via /gsd:plan-phase 1 — sur base requirements amendée (COCKPIT-01..07)
- Décider si la branche d'exécution sera `feat/v2.3-cockpit-operateur` (recommandé : 1 PR par phase) ou continuer sur `claude/gsd-ux-review-YG5K0`

### Blockers/Concerns

None — main à jour, branche en avance d'1 commit (UX review). Rien à rebase.

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 1 | Sceller le setup: bloquer SetupController si un admin existe et exiger CSRF | 2026-04-29 | 8c0e64a | [1-sceller-le-setup-bloquer-setupcontroller](./quick/1-sceller-le-setup-bloquer-setupcontroller/) |

## Session Continuity

Last session: 2026-04-29
Stopped at: v2.2 entièrement mergée dans main (PR #256 = edd7079). v2.3 bootstrap + UX review (Zhuo/Norman) appliquée — REQUIREMENTS étendu de 22 à 29, ROADMAP enrichi, screenshot panel gate ajouté.
Resume file: None

**Next action:** `/gsd:plan-phase 1` pour Cockpit Opérateur live (COCKPIT-01..07). Recommandation : créer `feat/v2.3-cockpit-operateur` depuis main avant de planifier — la branche `claude/gsd-ux-review-YG5K0` reste réservée à la revue (1 commit, prête à merger ou cherry-pick).
