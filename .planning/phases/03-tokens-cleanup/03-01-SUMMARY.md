---
phase: 03-tokens-cleanup
plan: 01
subsystem: design-system
tags:
  - css
  - design-tokens
  - refactor
  - tokens-cleanup
requires:
  - .planning/v2.5-TOKENS-AUDIT.md (Phase 7.2/7.3/7.4 specs)
provides:
  - "design-system.css consolide : 10 tokens 1-site retires/renommes"
  - "--border-thick (canonique 3px) remplace --border-thick-3"
  - "--border-emphasis base + override inline pour les variants colorees"
  - "Rings unifies a 2px par defaut, cas extremes inlines"
affects:
  - public/assets/css/design-system.css
  - public/assets/css/login.css
  - public/assets/css/postsession.css
  - public/assets/css/analytics.css
  - public/assets/css/vote.css
  - public/assets/css/pages.css
  - public/assets/css/validate.css
  - public/assets/css/wizard.css
  - public/assets/css/meetings.css
  - public/assets/css/components/ag-health-bar.css
tech-stack:
  added: []
  patterns:
    - "Inline border-color override apres border base (Phase 7.3 strategy)"
    - "Inline box-shadow pour cas uniques d'emphasis (Phase 7.4 strategy)"
key-files:
  created:
    - .planning/phases/03-tokens-cleanup/03-01-SUMMARY.md
  modified:
    - public/assets/css/design-system.css
    - public/assets/css/login.css
    - public/assets/css/postsession.css
    - public/assets/css/analytics.css
    - public/assets/css/vote.css
    - public/assets/css/pages.css
    - public/assets/css/validate.css
    - public/assets/css/wizard.css
    - public/assets/css/meetings.css
    - public/assets/css/components/ag-health-bar.css
decisions:
  - "Phase 7.2 : --border-thin-1-5 callers migrent vers --border-default (1px). Perte de 0.5px imperceptible sur input login + divider postsession ; inspection visuelle deferee Plan 03-02."
  - "Phase 7.3 : 4 emphasis variants flatten -> base + override inline (border-color, border-style). Conserve --border-emphasis, dashed, success, danger comme tokens multi-callers."
  - "Phase 7.4 (wizard) : --shadow-ring-1px-primary -> --shadow-ring-2px-border. Token border (neutre) plus approprie qu'un 2px primary trop fort pour un hover."
  - "Phase 7.4 (vote/meetings/health-bar) : 3 cas d'emphasis intentionnels rendus inline pour eviter de creer plus de tokens 1-site."
  - "Conserve --shadow-ring-2px-{surface,border,danger,success} comme rings canoniques semantiques."
metrics:
  duration: ~7min
  completed: 2026-05-05
  commits: 3
  tasks: 3
  files-modified: 10
  tokens-removed: 10
---

# Phase 3 Plan 01 : Tokens Cleanup Phase 7.2/7.3/7.4 Summary

Mecanique CSS pure : retrait de 10 tokens 1-site (`--border-thick-3`, `--border-thin-1-5`, 4 `--border-emphasis-*` variants, 4 `--shadow-ring-*` variants), migration de 9 callers CSS vers tokens canoniques + override inline. 3 commits atomiques shippes (un par sub-phase de l'audit v2.5).

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Phase 7.2 : Width cleanup (`--border-thick-3` -> `--border-thick`, retrait `--border-thin-1-5`) | `0fc1d09` | design-system.css, login.css, postsession.css, analytics.css, vote.css |
| 2 | Phase 7.3 : Emphasis flatten (4 variants -> base + override inline) | `8846e0e` | design-system.css, validate.css, pages.css, vote.css |
| 3 | Phase 7.4 : Ring unification (4 variants retires, callers migrent vers ring-2px-border ou inline) | `93567c1` | design-system.css, wizard.css, vote.css, meetings.css, components/ag-health-bar.css |

## Tokens 1-site retires (decompte)

| Sub-phase | Avant | Apres | Tokens retires |
|-----------|-------|-------|----------------|
| 7.2 | 2 | 0 | `--border-thick-3` (rename), `--border-thin-1-5` (retrait) |
| 7.3 | 4 | 0 | `--border-emphasis-primary`, `--border-emphasis-warning`, `--border-emphasis-danger-dashed`, `--border-emphasis-text-inverse-alpha-30` |
| 7.4 | 4 | 0 | `--shadow-ring-1px-primary`, `--shadow-ring-3px-primary-strong`, `--shadow-ring-inset-2px-primary`, `--shadow-ring-inset-1px-danger` |
| **Total** | **10** | **0** | |

Net : -10 tokens 1-site. Cible TOKENS-V26-04 (passer sous 30 tokens 1-site) avancee de ~25%.

## Liste detaillee des migrations callers

| Fichier | Ligne (avant) | Avant | Apres |
|---------|--------------|-------|-------|
| login.css | 253 | `border: var(--border-thin-1-5);` | `border: var(--border-default);` |
| postsession.css | 59 | `border: var(--border-thin-1-5);` | `border: var(--border-default);` |
| analytics.css | 463 | `border: var(--border-thick-3);` | `border: var(--border-thick);` |
| vote.css | 307 | `border: var(--border-thick-3);` | `border: var(--border-thick);` |
| validate.css | 48 | `border: var(--border-emphasis-primary);` | `border: var(--border-emphasis); border-color: var(--color-primary);` |
| validate.css | 55 | `border: var(--border-emphasis-danger-dashed);` | `border: var(--border-emphasis); border-style: dashed; border-color: var(--color-danger);` |
| pages.css | 274 | `border: var(--border-emphasis-warning);` | `border: var(--border-emphasis); border-color: var(--color-warning);` |
| vote.css | 819 | `border: var(--border-emphasis-text-inverse-alpha-30);` | `border: var(--border-emphasis); border-color: color-mix(in oklch, var(--color-text-inverse) 30%, transparent);` |
| wizard.css | 656 | `box-shadow: var(--shadow-ring-1px-primary);` | `box-shadow: var(--shadow-ring-2px-border);` |
| vote.css | 1742 | `box-shadow: var(--shadow-ring-3px-primary-strong) !important;` | `box-shadow: 0 0 0 3px var(--color-primary) !important;` |
| meetings.css | 470 | `box-shadow: var(--shadow-ring-inset-2px-primary);` | `box-shadow: inset 0 0 0 2px var(--color-primary);` |
| ag-health-bar.css | 106 | `box-shadow: var(--shadow-ring-inset-1px-danger);` | `box-shadow: inset 0 0 0 1px var(--color-danger);` |

## Tokens conserves (multi-callers ou semantiques canoniques)

- `--border-emphasis` (2px solid var(--color-border)) — base utilisee comme override-prefix dans 4 callers
- `--border-emphasis-dashed`, `--border-emphasis-success`, `--border-emphasis-danger` — multi-callers, restent
- `--shadow-ring-2px-surface`, `--shadow-ring-2px-border`, `--shadow-ring-2px-danger`, `--shadow-ring-2px-success` — 4 rings canoniques semantiques (audit timeline, member avatar, etc.)

## Deviations from Plan

None — plan execute exactement comme ecrit. Les 3 grep gates obligatoires (un par sub-phase) ont passe pre-commit, et la verification plan-level finale retourne 0 hit pour les 10 tokens retires.

## Verifications passees

- Phase 7.2 : `grep -rn "border-thick-3\|border-thin-1-5"` -> 0 hit ; `--border-thick:` defini 1 fois.
- Phase 7.3 : `grep -rEn "border-emphasis-(primary|warning|danger-dashed|text-inverse-alpha-30)"` -> 0 hit ; `--border-emphasis:` base + 3 multi-caller variants conserves.
- Phase 7.4 : `grep -rEn "shadow-ring-(1px-primary|3px-primary-strong|inset-1px-danger|inset-2px-primary)"` -> 0 hit ; les 4 ring-2px-* canoniques conserves (count=4).
- Plan-level : balance des accolades CSS du design-system.css OK (949 open / 949 close).
- 3 commits atomiques presents en `git log`, un par sub-phase.

## Note pour Plan 03-02

Inspection visuelle a planifier sur 5 pages echantillon :

- **login** : verifier les inputs (anciennement 1.5px, maintenant 1px via `--border-default`).
- **dashboard / pages** : verifier `.vote-live-panel` (warning emphasis 2px, doit toujours apparaitre tel quel).
- **operator (validate)** : verifier `.validation-zone` (primary emphasis) et `.validation-zone-danger` (dashed danger emphasis).
- **wizard** : hover sur `.wiz-template-card` (anciennement ring 1px primary, maintenant ring 2px border — plus subtil, plus neutre).
- **vote** : selection bulletin (`.vote-btn-selected`, ring 3px primary !important, valeur inline preservee).
- **calendar (meetings)** : `.calendar-day.today` (inset ring 2px primary, valeur inline preservee).
- **health-bar** : `#viewExec[data-quorum-state="missed"]` (inset ring 1px danger, valeur inline preservee).

Visual regression deferee dev-machine (plan 03-02 a une checklist d'inspection finale avant tagging v2.6).

## Self-Check : PASSED

- [x] design-system.css existe et contient `--border-thick:` (1 occurrence).
- [x] 0 reference grep aux 10 tokens retires dans `public/assets/css/`, `app/`, `public/*.html`.
- [x] Commit `0fc1d09` (Phase 7.2) present dans `git log`.
- [x] Commit `8846e0e` (Phase 7.3) present dans `git log`.
- [x] Commit `93567c1` (Phase 7.4) present dans `git log`.
- [x] Balance accolades CSS design-system.css : 949 / 949.
- [x] 9 fichiers CSS callers migres (login, postsession, analytics, vote x3, pages, validate, wizard, meetings, ag-health-bar).
