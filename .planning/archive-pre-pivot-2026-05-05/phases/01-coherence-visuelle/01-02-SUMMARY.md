---
phase: 01-coherence-visuelle
plan: 02
subsystem: visual-design-system
tags: [css-tokens, modals, transitions, ag-modal, structural-migration]
requires: [VISUAL-V27-01]
provides: [VISUAL-V27-02, VISUAL-V27-03, VISUAL-V27-05, VISUAL-V27-06]
affects:
  - public/assets/css/wizard.css
  - public/assets/css/operator.css
  - public/assets/css/public.css
  - public/assets/css/vote.css
  - public/assets/css/hub.css
  - public/assets/css/help.css
  - public/assets/css/members.css
  - public/members.htmx.html
  - public/operator.htmx.html
  - public/assets/js/pages/members.js
tech_stack_added: []
tech_stack_patterns:
  - "Use design-system.css tokens var(--duration-*) and var(--ease-*) for all CSS transitions; literal seconds reserved for documented animation exceptions (e.g., scrutin progress bars >500ms)."
  - "Use <ag-modal> custom element instead of native <dialog>; ag-modal provides shadow-DOM title bar, close button, backdrop click, Escape key handling — slot content does NOT need its own header or close listener."
  - "Avoid double-padding when slotting content into <ag-modal>: the component's .modal-b already applies var(--space-5); set inner wrapper padding to 0."
key_files_created: []
key_files_modified:
  - public/assets/css/wizard.css        # 8 transitions migrated to tokens
  - public/assets/css/operator.css      # 5 transitions migrated + op-quorum-modal CSS class renamed
  - public/assets/css/public.css        # 1 transition migrated; 4 documented exceptions kept
  - public/assets/css/vote.css          # 3 transitions migrated
  - public/assets/css/hub.css           # 3 transitions migrated
  - public/assets/css/help.css          # 2 transitions migrated
  - public/assets/css/members.css       # dead dialog rules removed, .dialog-body padding zeroed
  - public/members.htmx.html            # <dialog memberDetailDialog> → <ag-modal>
  - public/operator.htmx.html           # op-quorum-modal class renamed → op-quorum-card
  - public/assets/js/pages/members.js   # showModal/setAttribute → ag-modal.open(), close listeners simplified
  - .planning/v2.7-VISUAL-AUDIT.md      # added Verify clôture + 2 exception sections
decisions:
  - "Migrate transitions to var(--duration-*) + var(--ease-*) using audit mapping (0.15s→normal, 0.2s→moderate, 0.3s→deliberate, 0.4s→elaborate)."
  - "Keep 4 transitions in public.css as documented exceptions: scrutin progress bar animations use 1s/0.8s durations outside the --duration-* palette (max 500ms); creating new tokens for sub-second extensions would be premature."
  - "Keep 8 hex print values in editorial.css as documented exceptions (decision EDITORIAL-07 v2.3); no --color-print-* tokens exist in design-system.css and creating them in 01-02 risks cross-screen regression."
  - "Migrate <dialog id=memberDetailDialog> by replacing the wrapper element with <ag-modal>, dropping the inner dialog-header (component provides its own), and zeroing .dialog-body padding to avoid double-cadrage with .modal-b."
  - "Rename op-quorum-modal CSS class to op-quorum-card per audit V27-05 KEEP-RENAME verdict — full migration to <ag-modal> is out of 01-02 scope (would require operator.js refactor)."
  - "NO-OP all sub-classes (audit-modal-*, validate-modal-*, launch-modal-*) — they are CSS sub-classes inside <ag-modal> wrappers already confirmed by audit, no migration needed."
metrics:
  duration_minutes: ~25
  tasks_completed: 3
  files_modified: 11
  files_created: 0
  commits: 3
  completed_date: 2026-05-05
---

# Phase 1 Plan 02 : Migrations structurelles cohérence visuelle (V27-02/03/05/06)

Migrations CSS/HTML/JS structurelles pour clôturer 4 requirements visual du milestone v2.7 :
22 transitions tokenisées, modal native `<dialog>` migré vers `<ag-modal>`, classe ambiguë
`op-quorum-modal` renommée, et exceptions hex print + transitions scrutin documentées
explicitement dans l'audit pour traçabilité.

## What Changed

### Task 1 — Transitions tokenisées (V27-06)

22 sites migrés sur 26 réels (84%) à travers 6 fichiers CSS.

**Mapping figé utilisé** (toutes valeurs vérifiées contre `design-system.css:621-660`) :

| Durée source | Token cible | ms |
|--------------|-------------|----|
| `0.15s` / `.15s` | `var(--duration-normal)` | 150 |
| `0.2s` / `.2s` | `var(--duration-moderate)` | 200 |
| `0.3s` | `var(--duration-deliberate)` | 300 |
| `0.4s` | `var(--duration-elaborate)` | 400 |

| Easing source | Token cible |
|---------------|-------------|
| `ease`, `ease-in-out` | `var(--ease-standard)` |
| `ease-out` | `var(--ease-out)` |
| (pas d'easing, opacity-only) | `var(--ease-linear)` (vote.css:717) |

**Sites migrés par fichier** : wizard.css (8), operator.css (5 réels — L991 = commentaire),
public.css (1 sur 5), vote.css (3), hub.css (3), help.css (2). Total : 22.

### Task 2 — Modale `members.htmx.html` + renommage `op-quorum-modal` (V27-05)

Décisions site-par-site (verdict figé par audit + appliqué) :

| Verdict | Count | Détail |
|---------|-------|--------|
| **MIGRATE** | 1 | `members.htmx.html:332` `<dialog>` → `<ag-modal>` (+ adaptation `members.js`) |
| **KEEP-RENAME** | 1 | `operator.htmx.html:1677` + `operator.css:1192,1204` `op-quorum-modal` → `op-quorum-card` |
| **NO-OP** | 25 | sub-classes `audit-modal-*` (17 hits trust.htmx.html), `validate-modal-*` (2), `launch-modal-*` (2), `op-quorum-overlay`/`stats`/etc. — toutes internes à `<ag-modal>` wrappers déjà migrés |

**API `<ag-modal>` utilisée** :
- `.open()` au lieu de `dialog.showModal()`
- `.close()` (idempotent — appel sans garde sécurisé)
- Backdrop click + Escape + bouton de fermeture gérés par le shadow-DOM du composant

**Cleanup CSS** : suppression des règles mortes `dialog.member-detail-dialog` et
`.dialog-header` (le composant fournit son propre header). `.dialog-body` mis à `padding: 0`
pour éviter le double cadrage avec `.modal-b` interne (audit visuel : sans cette correction,
le contenu de la modale aurait `var(--space-5) + var(--space-5) = 40px` de padding total).

### Task 3 — Statut hex print + Verify clôture (V27-03 + clôture V27-02/05/06)

**Hex print** : décision audit V27-03 confirmée — pas de migration. 8 hits `editorial.css`
documentés en exception dans la section `## Exceptions hex acceptées`. Cible roadmap
renégociée de ≤5 à 8 hits documentés (renégociation explicite, pas de dette cachée).

**Verify clôture** : section `## Verify clôture (post-01-02)` ajoutée à `v2.7-VISUAL-AUDIT.md`
avec sorties brutes des 4 commandes de validation.

## Statut hex print

| Action | Statut |
|--------|--------|
| Migration vers tokens print | ❌ Non — pas de tokens `--color-print-*` dans design-system.css au 2026-05-05 |
| Documentation exception | ✅ Section `## Exceptions hex acceptées` ajoutée à v2.7-VISUAL-AUDIT.md |
| Cible roadmap (≤5) | ⚠️ Renégociée à 8 hits documentés (pattern EDITORIAL-07 v2.3) |

## Verify clôture (sorties brutes)

### V27-02 (typo)
```
$ grep -rnE "font-family:.*(Arial|Helvetica|Verdana|Times)" public/assets/css/ \
    --include="*.css" | grep -v design-system.css | grep -v email
(no output) → 0 hits
```
✅ CONFORME

### V27-03 (hex)
```
$ grep -nE "#[0-9a-fA-F]{3,6}\b" public/assets/css/editorial.css
8 hits, tous dans @page / @media print (lignes 137,145,146,194,224,232,233,243)
```
⚠️ EXCEPTION DOCUMENTÉE (8 hits print)

### V27-05 (dialog + ambiguïté)
```
$ grep -rnE "<dialog\b" public/*.html | grep -v "<!--"
(no output) → 0 hits
$ grep -rn "op-quorum-modal" public/
(no output) → 0 hits
```
✅ CONFORME

### V27-06 (transitions)
```
$ grep -rnE "transition:[^;]*[0-9]+(\.[0-9]+)?s" public/assets/css/ \
    --include="*.css" | grep -v design-system.css
public/assets/css/public.css:248: width 1s var(--ease-bounce, ...)   [exception scrutin]
public/assets/css/public.css:508: width 1s var(--ease-out)            [exception scrutin]
public/assets/css/public.css:851: width .8s cubic-bezier(.23,1,.32,1) [easing custom]
public/assets/css/public.css:872: left .8s cubic-bezier(.23,1,.32,1)  [easing custom]
public/assets/css/operator.css:991: ... commentaire ...               [faux positif]
```
⚠️ EXCEPTIONS DOCUMENTÉES (4 hits réels animations scrutin + 1 commentaire)

## Sites éventuellement non-traités

Aucun. Les 4 hits `public.css` restants sont des exceptions explicites documentées par
l'audit V27-06 (cas particuliers `1s` + `cubic-bezier(.23,1,.32,1)` custom). Si v2.8 décide
d'étendre la palette `--duration-*` au-delà de 500ms ou d'ajouter `--ease-decelerate`, ces
4 sites pourront être migrés en boucle de finition.

## Commits

| Hash | Message |
|------|---------|
| a342f6e | refactor(01-02): migrate transitions to duration/ease tokens (V27-06) |
| 5c0f6ad | refactor(01-02): migrate members dialog to ag-modal + rename op-quorum-modal (V27-05) |
| 2ce2cf3 | docs(01-02): document hex print exceptions + verify clôture V27-02/03/05/06 |

## Deviations from Plan

Plan executed as written. Mineure adaptation à signaler :
- Task 2 — la classe `dialog-body` est conservée dans members.css (vs suppression suggérée
  par l'action), mais avec `padding: 0` pour préserver le hook CSS sans casser le layout
  (slot ag-modal applique déjà `var(--space-5)` via `.modal-b`). Aucun impact fonctionnel,
  pure hygiène CSS.
- Task 3 — pas de migration hex (décision audit V27-03 "garder + documenter" confirmée).
  L'action originelle évoquait migration "si tokens print existent" — vérifié, ils n'existent
  pas, donc fallback documentation.

Aucune Rule 1/2/3 deviation déclenchée. Aucune authentification gate.

## Self-Check: PASSED

Files created/modified verification:
- public/assets/css/wizard.css : FOUND (modifié, 8 transitions migrées)
- public/assets/css/operator.css : FOUND (modifié, 5 transitions + op-quorum-card rename)
- public/assets/css/public.css : FOUND (modifié, 1 transition + 4 exceptions)
- public/assets/css/vote.css : FOUND (modifié, 3 transitions)
- public/assets/css/hub.css : FOUND (modifié, 3 transitions)
- public/assets/css/help.css : FOUND (modifié, 2 transitions)
- public/assets/css/members.css : FOUND (modifié, dead rules removed)
- public/members.htmx.html : FOUND (modifié, dialog → ag-modal)
- public/operator.htmx.html : FOUND (modifié, op-quorum-card)
- public/assets/js/pages/members.js : FOUND (modifié, ag-modal API)
- .planning/v2.7-VISUAL-AUDIT.md : FOUND (Verify clôture + 2 exception sections appended)
- .planning/phases/01-coherence-visuelle/01-02-SUMMARY.md : FOUND (this file)

Commits verification:
- a342f6e : FOUND in git log
- 5c0f6ad : FOUND in git log
- 2ce2cf3 : FOUND in git log
