---
gsd_state_version: 1.0
milestone: "v2.6"
milestone_name: "Clôture dette technique"
status: in_progress
stopped_at: ""
last_updated: "2026-05-05T11:00:00Z"
last_activity: 2026-05-05 -- Phase 3 (Tokens cleanup 7.2-7.4) ✓ COMPLETE. 10 tokens 1-site retirés (-2 width / -4 emphasis / -4 rings) sur design-system.css + 9 fichiers callers migrés. Audit final v2.6-TOKENS-AUDIT-FINAL.md : 31 tokens 1-site (cible <30, delta +1 documenté drift v2.5→v2.6 hors-scope), borders 97.7% + shadows 100% (cible ≥95%). 2 phases restantes (4/5).
progress:
  total_phases: 5
  completed_phases: 3
  total_plans: 9
  completed_plans: 6
  percent: 60
---

# AG-VOTE -- Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-05-05 after v2.6 milestone bootstrap)
**Single-source dev-machine runbook:** .planning/OPS-CHECKLIST.md (always read this before deploying)

**Core value:** L'application doit être fiable en production — aucun crash lié à des fallbacks fichiers, fuites mémoire, ou timeouts silencieux.
**Current focus:** v2.6 Clôture dette technique — Phases 1+2+3 ✓ shipped, 2 phases restantes (4/5 pioche libre).

## Current Position

Milestone: v2.6 Clôture dette technique
Phase: 3/5 ✓ COMPLETE — Tokens cleanup 7.2-7.4 (TOKENS-V26-01/02/03/04)
Plan: —
Status: Phases 1+2+3 verified, ready for next phase (4/5 indépendantes)
Last activity: 2026-05-05 — Phase 3 shipped. 10 tokens 1-site retirés sur design-system.css + 9 callers (login/postsession/analytics/vote/pages/validate/wizard/meetings/ag-health-bar). Audit final 31 tokens 1-site (delta +1 vs cible <30 documenté), ratios borders 97.7% + shadows 100% au-dessus cible 95%. 6 tokens orphelins détectés (candidats v2.7).

Progress: [######....] 60% (3/5 phases, 6/9 plans)

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting v2.2 / v2.6 :

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
- [v2.6 ordre phases] 5 phases indépendantes et parallélisables — la numérotation 1→5 reflète les buckets de REQUIREMENTS.md, pas une séquence d'exécution obligatoire. L'exécuteur peut piocher dans n'importe quel ordre selon disponibilité ou opportunisme.
- [v2.6 strict closure] Pas d'ajout opportuniste : toute découverte en cours de route ouvre une seed/todo, pas un ajout milestone. Cible : zéro carry-forward.

### Pending Todos

- Lancer la première phase v2.6 via `/gsd:plan-phase N` (N ∈ {1..5}, ordre arbitraire — toutes phases indépendantes).
- Optionnel : créer une branche `feat/v2.6-cloture-dette` depuis main avant de planifier (recommandation : 1 PR par phase, ou 1 PR global vu la taille modeste de chaque phase).

### Blockers/Concerns

None — main à jour post-v2.5 (commits ade443f / eeb9aa4 / d7bf36e shippés directement). v2.6 prêt à planifier.

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 1 | Sceller le setup: bloquer SetupController si un admin existe et exiger CSRF | 2026-04-29 | 8c0e64a | [1-sceller-le-setup-bloquer-setupcontroller](./quick/1-sceller-le-setup-bloquer-setupcontroller/) |
| 260505-001 | Docker autoload fix (split install/dump-autoload) + rebuild.sh --quick fast path | 2026-05-05 | 1a1dfb7 | [260505-001-docker-autoload-fix-rebuild-improve](./quick/260505-001-docker-autoload-fix-rebuild-improve/) |

## Session Continuity

Last session: 2026-05-05
Stopped at: Roadmap v2.6 défini (5 phases, 17 reqs, 100% coverage). Phases indépendantes — toutes parallélisables. Prêt pour /gsd:plan-phase.
Resume file: None

**Next action:** `/gsd:plan-phase N` (N ∈ {1..5}) pour démarrer la première phase v2.6. Phases disponibles (ordre libre) :
- 1. Tests heartbeat (PHPUnit + Playwright) — TEST-V26-01/02
- 2. Codes erreur ciblés + idempotency empty-state — ERR-V26-01/02/03
- 3. Tokens cleanup 7.2-7.4 (<30 tokens 1-site) — TOKENS-V26-01/02/03/04
- 4. Test infra + GSD ergo — INFRA-V26-01..05
- 5. Print/PDF polish — PDF-V26-01/02/03
