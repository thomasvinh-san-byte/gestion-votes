# Phase 1: Cockpit Polish & Hygiène — Context

**Gathered:** 2026-05-04
**Status:** Ready for planning
**Milestone:** v2.4 Polish & Robustness

<domain>
## Phase Boundary

Réduire la charge cognitive du cockpit opérateur en mode `exec` à un niveau maîtrisé : ≤25 éléments cliquables visibles simultanément (audit baseline ~91 actuellement) et palette rouge danger confinée aux états véritablement critiques (audit baseline 21 occurrences `--color-danger*` dans `operator.css`).

**Hors scope** : refonte fonctionnelle (sub-tabs preservées, KPI conservés), changement responsive (déjà fait v2.3), animations (différé v2.6+).

</domain>

<decisions>
## Implementation Decisions

### Périmètre du compte ≤25 boutons (D-01)

- **D-01**: Comptage **strict** : tout élément cliquable visible sur viewport ≥1024px en mode `exec` compte. Ça inclut `<button>`, `<a class="btn">`, `.op-tab` cliquables, KPI tiles cliquables, pills d'état cliquables, agenda items cliquables, sidebar nav, etc. Toute action atteignable sans hover/scroll. La cible **≤25 stricte** sur viewport ≥1024px.
- **D-02**: Audit baseline livré dans le PLAN.md : `grep -cE` sur les sélecteurs cliquables + capture screenshot annotée comptant chaque cliquable visible. Mesure post-implémentation par même méthode = succès si ≤25.

### Stratégie de réduction (D-03..D-05)

- **D-03**: **Disclosure** appliquée — boutons de second rang masqués derrière un bouton "Plus d'actions" / panel rétractable. Pattern Schoger Ch.10.
- **D-04**: **Contextual-only** appliquée — boutons activables uniquement quand utiles : "Fermer scrutin" invisible quand pas de vote actif, "Proclamer" invisible avant fermeture, etc. Audit livré : matrice action × état UI dans le PLAN.md.
- **D-05**: **Group + collapse sub-tabs** — sub-tabs peu utilisés repliés derrière disclosure. Audit livré identifiant top 3 sub-tabs visibles et le reste replié.
- **NON décidé** : persona-scoped (président / opérateur / assesseur sets différents) reporté v2.5+ — demande matrice rôle × action.

### Boutons sacrés (D-06)

- **D-06**: **Critical-path uniquement** toujours visibles, jamais masqués sous aucune condition :
  1. Lancer / Fermer scrutin (`#opBtnLaunchVote` + `#opBtnCloseVote` + `#opBtnToggleVote`)
  2. Proclamer résultat (`#opBtnProclaim`)
  3. Quorum override / Forcer ouverture (si présent dans le DOM courant — audit identifie le ou les ID)
  4. Liens sécurité racine (logout, retour dashboard) toujours accessibles via sidebar/header
  
  Tout le reste (Pause séance, Annoncer quorum, Délégations, Procurations import...) est éligible à disclosure ou contextual-only.

### Définition "état critique" rouge danger (D-07..D-08)

- **D-07**: **Strict** : `--color-danger`, `--color-danger-subtle`, `--color-danger-strong` n'apparaissent **uniquement** dans :
  1. Quorum perdu animé (pulse `1.5s`, `prefers-reduced-motion: reduce` respecté)
  2. Vote raté / erreur bloquante (toast / modal d'erreur)
  3. Erreurs bloquantes (`<ag-error>` ou équivalent)
  4. Hero card `--hero-card--live` (décision v2.3 P3 B1 conservée — `--color-danger-subtle` background)
- **D-08**: **Migrations cibles** :
  - Pills d'état "X non-votants" → `--color-neutral-strong` ou `--color-warning` selon ratio (audit dans PLAN.md)
  - Sidebar opérateur : 0 rouge décoratif (vérifié grep)
  - Indicateurs de présence/connexion : neutre ou success
  - SSE state "offline" reste warning, pas danger

### Granularité des plans (D-09)

- **D-09**: 1 PLAN par requirement (2 plans atomiques) — `01.1-PLAN.md` declutter (D-01..D-06) + `01.2-PLAN.md` palette danger (D-07..D-08). Parallélisables (zones disjointes : structure DOM/JS vs CSS color tokens).

### Mesure & vérification (D-10)

- **D-10**: 2 tests gardiens permanents :
  1. **Playwright** `cockpit-button-count.spec.js` (nouveau) : `page.locator('button:visible, a.btn:visible, .op-tab:visible, ...').count()` ≤ 25 sur les 3 états UI principaux (idle / voting / proclaiming).
  2. **PHPUnit** `tests/Security/CockpitPaletteTest.php` (nouveau) : scanne CSS opérateur + sidebar pour vérifier que `--color-danger` n'apparaît pas sur sélecteurs interdits (sidebar-*, op-tab-*, op-kpi--info, etc.).
  Régression sur `cockpit-keyboard-shortcuts.spec.js` + `critical-path-operator.spec.js` doit rester verte.

### Capture avant/après (D-11)

- **D-11**: Screenshot baseline cockpit `mode=exec` AVANT implémentation (v2.4-P1-baseline.png), capture APRÈS (v2.4-P1-after.png). Comparaison côte-à-côte dans le SUMMARY.md. Sinon "je sais que c'était mieux" est non-falsifiable.

### Branche & timing (D-12)

- **D-12**: Phase 1 v2.4 démarre **après merge PR #259** (v2.3) sur main. Branche `feat/v2.4-cockpit-polish` créée depuis main. Pas de worktree parallèle (overhead injustifié pour 2 plans).

### Claude's Discretion

- Naming exact des disclosures ("Plus d'actions" vs "Toutes les actions" vs "Voir plus")
- Animation transition disclosure (instant vs 200ms slide)
- Choix entre `--color-warning` et `--color-neutral-strong` pour pills d'état (à valider par contraste WCAG dans PLAN.md)
- Stockage état disclosure (localStorage persistant ou session-only)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### v2.3 Cockpit baseline (héritage)
- `public/assets/js/components/ag-health-bar.js` — Custom element health bar shipped v2.3 P1
- `public/assets/css/components/ag-health-bar.css` — Stylesheet 3 états quorum
- `public/assets/js/pages/operator-keybindings.js` — Module L/F/→/N/? shipped v2.3 P1
- `public/operator.htmx.html` — Page cockpit cible (~91 cliquables actuels)
- `public/assets/css/operator.css` — Stylesheet (21 occurrences `--color-danger*` à auditer)

### v2.3 Decisions héritées (à respecter)
- `.planning/milestones/v2.3-REQUIREMENTS.md` — COCKPIT-01..07 baseline shipped
- `.planning/v2.3-UX-REVIEW-SCHOGER.md` — Schoger S-1 (declutter) + S-6 (persona color confinement) source des reqs
- `.planning/phases/03-layouts-secondaires/03.1-PLAN.md` — décision hero card `--color-danger-subtle` (B1 fix iter 2) à préserver

### Tests gardiens
- `tests/e2e/specs/cockpit-keyboard-shortcuts.spec.js` — non-régression L/F/→/N/?
- `tests/e2e/specs/critical-path-operator.spec.js` — non-régression flow opérateur
- `tests/Security/EditorialConventionsTest.php` + `tests/Security/UxConventionsTest.php` — patterns établis pour tests gardiens DOM/CSS

### Design tokens
- `public/assets/css/design-system.css` — palette OKLCH + tokens `--color-danger*`, `--color-warning*`, `--color-neutral-*`

### Backlog source
- `.planning/v2.4-BACKLOG-PLAN.md` — découpage thématique v2.4 (Phase 1 = COCKPIT-V24-01/02)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets

- **`<details>` HTML natif** : disclosure pattern accessible par défaut (Tab + Enter), pas besoin de JS custom
- **`hidden` attribute** + `[hidden]` CSS : pattern utilisé v2.3 P1 (`<ag-integrity-modal>` auto-hidden)
- **`localStorage`** : déjà utilisé par `operator-tabs.js` pour persister sub-tab actif — pattern réutilisable pour persister état disclosure
- **`window.O.currentMode`** : exposé par `operator-tabs.js`, utilisable pour contextual-only (`if (mode !== 'exec') return`)
- **`prefers-reduced-motion: reduce`** : pattern v2.3 ag-health-bar.css

### Established Patterns

- **Custom elements vanilla JS** : `<ag-health-bar>`, `<ag-integrity-modal>`, `<ag-empty-state>`, `<ag-modal>` — extension via `<ag-action-disclosure>` envisageable mais probablement overkill pour ce phase (un simple `<details>` + CSS suffit)
- **Sentinel anti-double-bind** : `window.AG_OPERATOR_KEYBINDINGS = true` pattern — réutiliser pour le module declutter si JS dynamique
- **CSS BEM** : `.op-action-bar--collapsed`, `.op-tab--hidden` pattern conformes au codebase existant

### Integration Points

- **`operator-exec.js`** : ligne 442 keydown handler agenda — ne pas casser
- **`operator-tabs.js` `OpS` bridge** : exposé sur `window.OpS`, fournit `currentMode`, `selectMotion`, etc.
- **`operator-realtime.js` SSE** : mirror `data-quorum-state` sur `#viewExec` — la disclosure ne doit pas casser ce mirror
- **Sidebar shared** : `public/partials/sidebar.html` — vérifier que rouge décoratif (s'il existe) est dans sidebar.css, pas inline

### Audit baseline numbers (à confirmer dans PLAN.md)

- `grep -cE "<button|class=\"btn|op-tab|op-agenda-item" public/operator.htmx.html` : ~91 (à valider mode exec uniquement)
- `grep -cE "color-danger|--danger" public/assets/css/operator.css` : 21
- `grep -cE "color-danger" public/assets/css/sidebar*.css public/assets/css/components/ag-*.css` : à mesurer

</code_context>

<specifics>
## Specific Ideas

- "70 boutons → ≤25" — la cible est la mesure. Si l'audit baseline donne 91 et qu'on atteint 25, le gain perçu sera massif. Si on atteint 30, on échoue formellement même si le gain perçu est bon. Fixer la mesure dans le PLAN.md, sans triche (pas de "je compte différemment pour atteindre ≤25").
- Refactoring UI Schoger Ch.10 ("Every tab is a tax") + Ch.4 ("Use color sparingly") — appliquer littéralement.
- Pas de surprise pour l'opérateur sous stress : disclosure doit être manuellement ouverte, jamais auto-fermée par un autre événement. State persistant entre sessions (localStorage).

</specifics>

<deferred>
## Deferred Ideas

- **Persona-scoped visibility** (président vs opérateur vs assesseur) — reporté v2.5 ou v2.6, demande matrice rôle × action validée produit
- **Animations transition disclosure** — reporté v2.6 (UX-V24-01 motion design tokens)
- **Mobile-first opérateur** — reporté v2.6 (UX-V24-03)
- **Backlog cleanup persona color sur autres pages** (login, dashboard, audit) — hors scope Phase 1, traité v2.4 P4 si pertinent ou v2.5

</deferred>

---

*Phase: 01-cockpit-polish-hygiene*
*Context gathered: 2026-05-04*
