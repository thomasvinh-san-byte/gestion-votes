# Phase 81: Fix UX interactivity — Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-03
**Phase:** 81-fix-ux-interactivity-blocking-popups-broken-layouts-fragile-frontend-wiring
**Areas discussed:** Popups & confirmations, Layouts & mise en page, Frontend wiring & API, Coherence visuelle

---

## Popups & confirmations

| Option | Description | Selected |
|--------|-------------|----------|
| Modales bloquent la page | Les modales empechent l'interaction avec le reste de la page | |
| Confirmations trop frequentes | Trop de confirm() pour des actions mineures | |
| Incoherence des patterns | Melange de window.confirm(), AgConfirm.ask(), ag-modal.open() | |
| Tout a la fois | Les trois problemes ci-dessus sont presents | ✓ |

**User's choice:** Tout a la fois
**Notes:** Les trois problemes sont presents simultanement

---

| Option | Description | Selected |
|--------|-------------|----------|
| AgConfirm partout | Toute action destructive passe par AgConfirm.ask() | |
| Inline confirm | Le bouton change de texte sans popup | |
| Confirm seulement pour irreversible | AgConfirm uniquement pour les actions vraiment irreversibles | |

**User's choice:** Other — "Il faut choisir un design pattern et le solidifier"
**Notes:** L'enjeu est la coherence, pas un choix specifique. Claude a discretion pour choisir et appliquer uniformement.

---

| Option | Description | Selected |
|--------|-------------|----------|
| Inline/expansion | Preferer l'expansion inline plutot que des modales | |
| Modale ok mais corrigee | Modales acceptables si backdrop-click, Escape, focus trap | |
| Claude decide selon le contexte | Inline pour petits formulaires, modale pour gros | ✓ |

**User's choice:** Claude decide selon le contexte

---

| Option | Description | Selected |
|--------|-------------|----------|
| Toasts ok | Notifications toast fonctionnent correctement | |
| Toasts a ameliorer | Manque de feedback toast apres certaines actions | |
| Claude audite | Claude fait un audit des toasts et corrige | ✓ |

**User's choice:** Claude audite

---

## Layouts & mise en page

| Option | Description | Selected |
|--------|-------------|----------|
| Wizard | Formulaire creation — trop vertical, n'utilise pas la largeur | ✓ |
| Operator console | Panneau operateur — layout deux colonnes mal calibre | ✓ |
| Settings/Admin | Pages de parametres — sidebar tabs mal proportionnes | ✓ |
| Toutes les pages | Le probleme est general | ✓ |

**User's choice:** Toutes les pages (multiselect: all selected)

---

| Option | Description | Selected |
|--------|-------------|----------|
| Pleine largeur | Pas de max-width, utiliser l'espace | |
| Max 1400px centre | Contenu limite a 1400px centre | |
| Claude decide par page | Certaines pages full-width, d'autres max-width | ✓ |

**User's choice:** Claude decide par page

---

| Option | Description | Selected |
|--------|-------------|----------|
| Une seule page | Tout le formulaire visible d'un coup | |
| Wizard corrige | Garder le stepper mais corriger le layout | ✓ |
| Wizard simplifie | Reduire le nombre d'etapes | |

**User's choice:** Wizard corrige

---

| Option | Description | Selected |
|--------|-------------|----------|
| Champs horizontaux | Label a gauche, input a droite | |
| Champs empiles modernises | Label au-dessus, input pleine largeur sur 2-3 colonnes grid | ✓ |
| Floating labels | Labels flottants partout | |

**User's choice:** Champs empiles modernises

---

## Frontend wiring & API

| Option | Description | Selected |
|--------|-------------|----------|
| Fetch sans feedback | Appels API sans indicateur de chargement | ✓ |
| Formulaires qui cassent | Soumissions qui echouent silencieusement | ✓ |
| SSE/temps reel | Mises a jour en direct qui decrochent | ✓ |
| Navigation/routing | Changements de page qui perdent des donnees | ✓ |

**User's choice:** All selected — tous les types d'interactions sont fragiles

---

| Option | Description | Selected |
|--------|-------------|----------|
| Loading + Toast systematique | Spinner/disabled pendant fetch, toast succes/erreur | |
| Optimistic + Undo | UI mise a jour immediatement avec rollback | |
| Claude standardise | Claude audite et applique le pattern adapte au contexte | ✓ |

**User's choice:** Claude standardise

---

| Option | Description | Selected |
|--------|-------------|----------|
| HTML5 natif | required, pattern, min/max | |
| JS custom inline | Validation JS avec messages d'erreur inline | |
| Les deux | HTML5 natif + JS pour regles complexes | |

**User's choice:** Other — "Celui qui colle le mieux aux standards et au codebase"
**Notes:** Claude choisit selon le contexte et les patterns existants

---

## Coherence visuelle

| Option | Description | Selected |
|--------|-------------|----------|
| Spacing incoherent | Marges, paddings, gaps varient d'une page a l'autre | ✓ |
| Composants inconsistants | Boutons, cards, tables n'ont pas le meme style partout | ✓ |
| Transitions manquantes | Pas d'animations de transition entre etats | ✓ |
| Tout a la fois | Les trois problemes simultanement | ✓ |

**User's choice:** Tout a la fois

---

| Option | Description | Selected |
|--------|-------------|----------|
| Audit + fix page par page | Claude audite chaque page et corrige | |
| Design tokens + cascade | Renforcer tokens CSS et forcer usage via layers | |
| Les deux | Consolider tokens puis passer page par page | ✓ |

**User's choice:** Les deux

---

| Option | Description | Selected |
|--------|-------------|----------|
| Subtiles et rapides | Fade 150-200ms, slide leger — professionnel | |
| Riches et fluides | Animations 300-400ms, ease-out, scale — app native | |
| Claude decide | Claude choisit le bon niveau selon le contexte | ✓ |

**User's choice:** Claude decide

---

## Claude's Discretion

- Confirmation pattern choice (AgConfirm vs inline vs undo toast)
- Per-page width decisions
- Animation timing and easing
- Validation approach per form
- Toast coverage audit
- Loading state implementation
- Modal vs inline expansion per context

## Deferred Ideas

None — discussion stayed within phase scope
