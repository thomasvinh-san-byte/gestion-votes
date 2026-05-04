---
gsd_state_version: 1.0
milestone: v2.5
milestone_name: Real-time Live Cockpit + Logger Migration
status: planning
stopped_at: "v2.5 milestone bootstrap post v2.4-MILESTONE-AUDIT.md tech_debt verdict (12 items deferred). 3 phases (5/6/7) continuing numbering depuis v2.3. 10 reqs (HEARTBEAT-V25-01..04 / LOG-V25-01..04 / COCKPIT-V25-01 / TOKENS-V25-01). 2 reqs déjà satisfied ahead-of-planning (heartbeat backend + frontend, commit 02179ea sur claude/gsd-ux-review-YG5K0)."
last_updated: "2026-05-04T08:30:00Z"
last_activity: 2026-05-04 -- v2.5 milestone scoped from v2.4 audit tech debt + heartbeat work shipped commit 02179ea (events.php meeting.heartbeat 10s tick + applyHeartbeat frontend)
progress:
  total_phases: 3
  completed_phases: 0
  total_plans: 0
  completed_plans: 0
  percent: 0
---

# AG-VOTE -- Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-05-04)

**Core value:** L'application doit être fiable en production — observabilité serveur réelle, signal SSE temps réel autonome, finitions cockpit.
**Current focus:** v2.5 Real-time Live Cockpit + Logger Migration — Phase 5 SSE Live Pulse (50% in flight, tests pending)

## Current Position

Milestone: v2.5 Real-time Live Cockpit + Logger Migration
Branch: claude/gsd-ux-review-YG5K0
Phase: 5 of 7 (continuing numbering from v2.3) — SSE Live Pulse, heartbeat backend+frontend livrés commit 02179ea ahead of GSD planning, tests (HEARTBEAT-V25-03 PHPUnit + HEARTBEAT-V25-04 Playwright) pending
Plan: 0 of N — phase 5 plan not yet created

Progress: [#.........] 5% (2/10 reqs satisfied)

**Base de planning :** v2.2 mergée (PR #256), v2.3 shipped (PR #259), v2.4 shipped (PR #260, awaiting merge). Heartbeat work landed on this branch sans cadre GSD — formalisé rétroactivement par ce milestone.

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
