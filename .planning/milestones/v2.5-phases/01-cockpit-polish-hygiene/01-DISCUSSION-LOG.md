# Phase 1: Cockpit Polish & Hygiène — Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions captured in `01-CONTEXT.md` — this log preserves the discussion process.

**Date:** 2026-05-04
**Phase:** 01-cockpit-polish-hygiene
**Mode:** discuss (interactive batched questioning)
**Areas analyzed:** 4 (périmètre, stratégie, sacrés, rouge danger)

## Codebase Scout Pre-Discussion

- `public/operator.htmx.html` : `grep -cE "<button"` = 82, total cliquables (incl. `<a class="btn">`) = 91
- `public/assets/css/operator.css` : `grep -cE "color-danger|--danger"` = 21
- v2.3 baseline héritée : COCKPIT-01..07 shipped, `<ag-health-bar>` custom element, `operator-keybindings.js` module
- Schoger S-1 mentionnait 70 boutons / S-6 mentionnait 21 — baseline réelle plus élevée que les estimations

## Questions Présentées (4)

### Q1 — Périmètre du compte ≤25 boutons

| Option | Description | Selected |
|---|---|:---:|
| Tous éléments cliquables visibles | Strict : `<button>` + anchors btn + tabs + KPI + pills + agenda items | ✓ |
| Boutons d'action uniquement | Permissif : exclut sub-tabs + KPI + agenda items | |
| Visible + immédiatement disponible | Contextuel : seulement les activables dans l'état UI courant | |

**Selection :** Strict — tous éléments cliquables visibles sur viewport ≥1024px.

### Q2 — Stratégie de réduction (combinaison)

| Option | Description | Selected |
|---|---|:---:|
| Disclosure + Contextual + Collapse | Sans persona-scoped (un set unique pour tous rôles) (Recommandé) | ✓ |
| Disclosure + Contextual + Persona-scoped | Plus puissant, demande matrice rôle×action | |
| Disclosure + Contextual seulement | Sans Collapse, possiblement insuffisant | |
| Tout (incl. Persona) | Maximalement réducteur | |

**Selection :** Disclosure + Contextual-only + Collapse sub-tabs (pas de persona-scoped).

### Q3 — Boutons sacrés (jamais masqués)

| Option | Description | Selected |
|---|---|:---:|
| Critical-path uniquement | Lancer/Fermer + Proclamer + Quorum override + retour dashboard (Recommandé) | ✓ |
| Critical-path + nav structurelle | Inclut sub-tabs principaux + KPI principaux | |
| Critical-path + urgences | Inclut Pause séance + Annoncer quorum | |

**Selection :** Critical-path uniquement.

### Q4 — Définition "état critique" rouge danger

| Option | Description | Selected |
|---|---|:---:|
| Strict | Quorum perdu animé + vote raté + erreurs bloquantes uniquement (Recommandé) | ✓ |
| Étendu | Strict + indicateurs anticipatifs (votes manquants forts, quorum à risque) | |
| Très strict | Uniquement CTA destructifs (supprimer / annuler vote) | |

**Selection :** Strict — quorum perdu animé + vote raté + erreurs bloquantes. Hero card live garde `--color-danger-subtle` (héritage v2.3 P3 B1 préservé).

## Corrections / Ajustements

Aucune correction post-questions. Toutes les recommandations validées au premier tour.

## Deferred Ideas Captured

- **Persona-scoped visibility** (président vs opérateur vs assesseur) — reporté v2.5+ (matrice rôle × action requise)
- **Animations transition disclosure** — reporté v2.6 (UX-V24-01 motion design)
- **Mobile-first opérateur** — reporté v2.6 (UX-V24-03)
- **Backlog rouge sur autres pages** (login/dashboard/audit) — hors scope P1, possiblement v2.4 P4 ou v2.5

## Claude's Discretion (post-discussion)

Décisions techniques laissées au planner :
- Naming exact des disclosures ("Plus d'actions" vs "Toutes les actions")
- Animation transition (instant vs 200ms slide)
- Choix `--color-warning` vs `--color-neutral-strong` pour pills d'état
- Stockage état disclosure (localStorage persistant ou session)

---

*Discussion gathered : 2026-05-04, batched via AskUserQuestion (4 questions, 1 turn).*
