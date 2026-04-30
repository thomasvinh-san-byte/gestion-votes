---
gsd_state_version: 1.0
milestone: v2.3
milestone_name: Layout Refonte & UX Polish
status: Executing Phase 02
stopped_at: "Plan 02.1 livré sur `feat/v2.3-cockpit-operateur` — fichier `public/assets/css/editorial.css` créé (110 lignes, commit 9775aea). Wrapper `.ag-editorial` en CSS grid (colonnes minmax(0, 720px) minmax(0, 1fr)), verrou `text-align: left` sur enfants directs, typography duality complète (Fraunces sur contenu, Bricolage sur contrôles UI, JetBrains Mono sur hashes), `.ag-resolution-pill` mono réutilisable (radius-full + border-subtle + shadow-xs), breakpoints collapse <1024px et padding réduit <768px, `.ag-editorial-print-header` masqué (révélation prévue Plan 02.4). 100% tokens design-system, aucun !important, aucune valeur numérique padding/margin/gap. EDITORIAL-01/02/03/06/08 satisfaits."
last_updated: "2026-04-30T07:00:00.000Z"
progress:
  total_phases: 4
  completed_phases: 1
  total_plans: 10
  completed_plans: 5
  percent: 50
---

# AG-VOTE -- Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-29)

**Core value:** Test ultime — un utilisateur tiers regardant un screenshot avant/après doit dire "celui-là est plus rassurant" sans qu'on lui explique pourquoi.
**Current focus:** Phase 02 — Pages éditoriales

## Current Position

Milestone: v2.3 Layout Refonte & UX Polish
Branch: feat/v2.3-cockpit-operateur (Phase 01 work)
Phase: 02 (Pages éditoriales) — EXECUTING
Plan: 2 of 6 (Plan 02.1 done)

Progress: [##########] 100% (4/4 plans of phase 01) + 1/6 plans of phase 02 — 1/4 phases of milestone v2.3 done

**Base de planning :** v2.2 entièrement mergée dans main (PR #256, commit edd7079). Tokens, components, personas, ag-modal, CopyConventionsTest tous disponibles côté code.

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting v2.3 Phase 02:

- [v2.3 P2.1] Token `--radius-pill` absent du design-system : utilisation de `--radius-full` (= 9999px) directement sur `.ag-resolution-pill`. Le seul usage existant de `--radius-pill` dans le codebase est en fallback `var(--radius-pill, 9999px)` — pas un token réel.
- [v2.3 P2.1] Wrapper `.ag-editorial` en CSS grid (display: grid) — `display: flex` proscrit sur le wrapper par EDITORIAL-08 ; `inline-flex` autorisé pour alignement intra-cellule (`.ag-resolution-pill`).
- [v2.3 P2.1] Collapse responsive au breakpoint 1024px (max-width: 1023.98px) — sidebar passe sous le contenu, pas à droite. Breakpoint 768px ajoute padding réduit + max-width 100% sur la colonne contenu.
- [v2.3 P2.1] `box-shadow: var(--shadow-xs)` ajouté sur `.ag-resolution-pill` (non explicite dans le plan, mais cohérent avec consolidation Schoger S-2 / TECH-01 — élévation discrète).
- [v2.3 P2.1] Sélecteur `details > summary` inclus dans la typography duality (Bricolage sur contrôles) — couvre le pattern HTMX accordéon des pages audit/trust.

Recent decisions affecting v2.3 Phase 01:

- [v2.3 P1.1] `<ag-health-bar>` en light DOM (pas shadow DOM) — nécessaire pour l'héritage des tokens design-system et pour que le stylesheet companion adresse `#viewExec` dans la même cascade.
- [v2.3 P1.1] Pulse "missed" sur la zone vote (`#viewExec[data-quorum-state="missed"]`) plutôt que sur le bar lui-même — fix F-2 du plan-checker, aligné sur ROADMAP SC #2.
- [v2.3 P1.1] Substitutions de tokens documentées dans le CSS : `--color-surface` (≠ `--surface-base`), `--color-bg-subtle` (≠ `--surface-sunken`), valeur littérale `999px` (pas de `--radius-pill` dans design-system.css).
- [v2.3 P1.2] Module raccourcis = IIFE (pas ES module) — convention de `public/assets/js/pages/*` ; intégré par `<script src>` dans Plan 01.3, sans build step.
- [v2.3 P1.2] Anti-trap COCKPIT-06 : exclusion `isContentEditable` ajoutée (le handler legacy de `operator-exec.js` la manquait) ; modifier keys exclus avant tout dispatch.
- [v2.3 P1.2] Fallback chain L/F → `#opBtnToggleVote` : permet à 01.2 de fonctionner avant ET après que Plan 01.3 sépare le toggle en `#opBtnLaunchVote` / `#opBtnCloseVote`.
- [v2.3 P1.2] Overlay `?` reste accessible hors mode exec — c'est de la documentation passive ; seules les actions L/F/→/N sont gated par `_isExecMode()`.
- [v2.3 P1.3] Seuil at-risk = `c < r * 1.10` (10% buffer au-dessus du quorum requis) — formule unique dans `_computeQuorumState`, appelée uniquement depuis `quorum.updated`.
- [v2.3 P1.3] Mirror `data-quorum-state` sur `#viewExec` intégré dans `_setHb` (helper unique) — pas d'event-bus, pas de listener supplémentaire ; idempotence via `getAttribute() !== s` avant `setAttribute()`.
- [v2.3 P1.3] `window.O.fn.notifyMotionChange` défini dans `operator-realtime.js` (pas dans motions.js) — garde toutes les écritures `<ag-health-bar>` dans un seul fichier ; `operator-motions.js` ne voit que le hook public.
- [v2.3 P1.3] `#opSseIndicator` retiré entièrement (DOM + writes) — la pastille ambient `sse-state` du `<ag-health-bar>` est désormais l'unique surface d'état SSE (COCKPIT-01).
- [v2.3 P1.3] Toast `Quorum atteint !` retiré — l'indicateur persistant remplace la notif éphémère (COCKPIT-02).
- [v2.3 P1.3] Re-anchor `opPresenceBadge` sur `.op-meeting-bar-right` (avec fallbacks `#opHealthBar` puis `document.body`) — fix Rule 1 nécessaire suite à la suppression de `#opSseIndicator` qui hébergeait le badge.

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

- **Avant /gsd:ship Phase 1 :** exécuter manuellement les 3 specs Playwright (cockpit-health-bar, cockpit-keyboard-shortcuts, critical-path-operator) sur machine dev — sandbox sans Playwright. Voir `.planning/phases/01-cockpit-operateur/01.4-SUMMARY.md` § Followups.
- ~~**Avant /gsd:plan-phase 2 :** exécuter quick task **TECH-01**~~ → DONE (quick 260430-86c, 28 commits, 234 borders consolidées, 6 nouveaux tokens). Voir `.planning/quick/260430-86c-consolidation-73-box-shadow-57-borders-v/260430-86c-SUMMARY.md`. Cas BASSE confiance (≈140 borders + ≈45 shadows custom) reportés dans Phase 2/3 par fichier.
- Planifier v2.3 Phase 2 (Pages éditoriales) via `/gsd:plan-phase 2` — sur base requirements amendée Schoger (EDITORIAL-01..09 dont nouveaux 08 grid + 09 cleanup hardcoded).

### Blockers/Concerns

None — main à jour, branche en avance d'1 commit (UX review). Rien à rebase.

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 1 | Sceller le setup: bloquer SetupController si un admin existe et exiger CSRF | 2026-04-29 | 8c0e64a | [1-sceller-le-setup-bloquer-setupcontroller](./quick/1-sceller-le-setup-bloquer-setupcontroller/) |
| 2 | TECH-01 — Consolidation 73 box-shadow + 57 borders → tokens design-system (Schoger S-2) : 234 occurrences remplacées sur 25 fichiers, 6 nouveaux tokens (`--shadow-xs`, `--border-default/subtle/strong/dashed/focus`) | 2026-04-30 | 0ec33a2 | [260430-86c-consolidation-73-box-shadow-57-borders-v](./quick/260430-86c-consolidation-73-box-shadow-57-borders-v/) |

## Session Continuity

Last session: 2026-04-30
Stopped at: Plan 02.1 livré sur `feat/v2.3-cockpit-operateur` — `public/assets/css/editorial.css` créé (commit 9775aea, 110 lignes). Wrapper `.ag-editorial` CSS grid + verrou `text-align: left` + typography duality (Fraunces / Bricolage / JetBrains Mono) + `.ag-resolution-pill` + breakpoints 1024/768. SUMMARY 02.1 écrit. EDITORIAL-01/02/03/06/08 satisfaits.
Resume file: None

**Next action:** Plan 02.2 — BalancedTitleService PHP (text-wrap: balance polyfill côté serveur pour titres). Aucune dépendance avec 02.1, peut démarrer immédiatement via `/gsd:execute-phase`.
