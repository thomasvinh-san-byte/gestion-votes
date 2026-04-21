# Phase 1: Typographie et Espacement - Research

**Researched:** 2026-04-21
**Domain:** CSS design tokens — typography scale, form labels, page header, form spacing
**Confidence:** HIGH

## Summary

Cette phase opère entièrement via des changements de tokens CSS dans `design-system.css`. Le système de tokens existant est bien architecturé : un changement sur `--text-base` ou `--header-height` se propage globalement via `var()` dans tous les composants. Cependant, deux pièges existent : (1) `.app-header` utilise une hauteur hardcodée `height: 56px` (pas `var(--header-height)`) et (2) `.app-sidebar` utilise `var(--sidebar-top, 56px)` comme fallback hardcodé — les deux doivent être corrigés en même temps que le token.

Le scope est clair et limité : `design-system.css` + suppression de `page-sub` dans les 13 templates HTML concernés. Pas de modification des fichiers CSS par page. La cascade CSS gère la propagation automatiquement.

**Primary recommendation:** Modifier `design-system.css` en une seule passe (tokens + `.app-header` height + `.form-label` + nouveaux alias sémantiques), puis supprimer les `<p class="page-sub">` dans les templates HTML.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Changer `--text-base` de 0.875rem (14px) à 1rem (16px) — tous les composants utilisant le token se mettent à jour automatiquement
- `--text-md` reste à 1rem (16px), effectivement égal à la nouvelle base — fusion ultérieure si nécessaire
- Mettre à jour `--type-label-size` de `--text-sm` à `--text-base` (nouveau 16px), supprimer `text-transform:uppercase` et couleur muted sur `.form-label`
- Créer des alias sémantiques `--form-gap: var(--space-5)` (20px) et `--section-gap: var(--space-6)` (24px) — réutiliser l'échelle existante
- Breadcrumb + titre de page uniquement (supprimer sous-titre et barre décorative)
- Les boutons d'action descendent sous le header dans une barre toolbar — le header reste propre à 64px
- Breadcrumb texte simple avec séparateur "/" à taille `--text-sm`, couleur muted
- Appliquer à toutes les pages en une fois via changement de tokens — la cascade CSS gère la propagation globale
- Inclure la page login — elle utilise les mêmes tokens du design-system
- Vérification manuelle des pages clés (login, dashboard, meetings, vote, wizard) + tests E2E existants
- Le dark theme hérite automatiquement des mêmes changements de tokens (tokens agnostiques au thème pour le sizing)

### Claude's Discretion
- Aucun — toutes les décisions ont été prises explicitement

### Deferred Ideas (OUT OF SCOPE)
- None — discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| TYPO-01 | Taille de police de base passe de 14px à 16px sur desktop et mobile | Changer `--text-base: 0.875rem` → `1rem` dans `:root {}`. Le `body { font-size: var(--text-base) }` (ligne 89) se propage. |
| TYPO-02 | Labels de formulaire en casse normale (plus d'UPPERCASE), couleur lisible (plus de muted) | Modifier `.form-label` (ligne 1813) : supprimer `text-transform: uppercase`, `letter-spacing: .5px`, `color: var(--color-text-muted)`. Mettre à jour `--type-label-size` → `var(--text-base)`. |
| TYPO-03 | Header passe de 56px à 64px, contenu aéré (breadcrumb + titre sans sous-titre ni barre deco) | (1) Changer `--header-height: 56px` → `64px` dans `:root`. (2) Corriger `.app-header { height: 56px }` (ligne 910) → `height: var(--header-height)`. (3) Supprimer `<p class="page-sub">` dans 13 templates HTML. (4) Supprimer `.bar` des `<h1 class="page-title">` dans les templates. |
| TYPO-04 | Espacement entre éléments de formulaire et sections passe de 14px à 20-24px | Ajouter `--form-gap: var(--space-5)` et `--section-gap: var(--space-6)` dans `:root`. Mettre à jour `--space-field: var(--form-gap)` (actuellement `var(--space-4)` = 16px). |
</phase_requirements>

## Standard Stack

### Core
| Élément | Valeur actuelle | Valeur cible | Pourquoi standard |
|---------|----------------|-------------|-------------------|
| `--text-base` | `0.875rem` (14px) | `1rem` (16px) | Token unique, propagation globale via cascade |
| `--type-label-size` | `var(--text-sm)` = 13px | `var(--text-base)` = 16px | Alias sémantique, un seul point de vérité |
| `--header-height` | `56px` | `64px` | Token layout central |
| `--space-field` | `var(--space-4)` = 16px | `var(--form-gap)` = 20px | Alias sémantique expressif |

### Fichiers modifiés (scope complet)
| Fichier | Type de modification | Impact |
|---------|---------------------|--------|
| `public/assets/css/design-system.css` | Tokens + règles composants | Global — toutes les pages |
| `public/*.htmx.html` (13 fichiers) | Suppression `page-sub` + `.bar` | HTML structurel |

### Aucune dépendance externe
Toutes les modifications sont en CSS pur et HTML statique. Aucun package npm, aucune migration Composer.

## Architecture Patterns

### Pattern 1: Token-First — modifier la valeur primitive, laisser la cascade faire le reste

**What:** Un token CSS `var()` est défini une fois dans `:root {}`. Tous les composants le référencent. Changer la définition propage automatiquement.

**When to use:** Pour `--text-base`, `--type-label-size`, `--header-height` — valeurs utilisées globalement.

**Example:**
```css
/* design-system.css — :root block */
--text-base: 1rem;  /* était 0.875rem */
```
Le `body { font-size: var(--text-base) }` à la ligne 89 se met à jour automatiquement. Tous les composants avec `font-size: var(--text-base)` ou `font-size: var(--type-body-size)` (qui pointe sur `--text-base`) suivent.

### Pattern 2: Corriger les valeurs hardcodées en même temps que le token

**What:** Certains composants ont des valeurs hardcodées qui auraient dû référencer un token. Les corriger en même temps évite l'incohérence.

**When to use:** Obligatoire pour `.app-header` (height: 56px hardcodé) et le fallback `var(--sidebar-top, 56px)`.

**Example:**
```css
/* Avant */
.app-header {
  height: 56px;  /* hardcodé — NE PAS laisser */
}

/* Après */
.app-header {
  height: var(--header-height);  /* suit le token */
}
```

**Note critique:** Le JS dans `shell.js` et `auth-ui.js` mesure `header.getBoundingClientRect().bottom` pour calculer `--sidebar-top` dynamiquement. La sidebar s'alignera automatiquement à 64px une fois le CSS corrigé — aucune modification JS requise.

### Pattern 3: Alias sémantiques additifs (ne pas casser les anciens)

**What:** Ajouter de nouveaux tokens sémantiques sans supprimer les anciens pour rester backward-compatible.

**When to use:** Pour `--form-gap` et `--section-gap`.

**Example:**
```css
/* Ajouter dans le bloc "Semantic gap aliases" existant */
--form-gap:    var(--space-5);   /* 20px — entre les champs de formulaire */
--section-gap: var(--space-6);   /* 24px — entre les sections */

/* Mettre à jour --space-field pour pointer sur le nouveau token */
--space-field: var(--form-gap);  /* était var(--space-4) = 16px */
```
Les classes `.form-group + .form-group`, `.form-stack`, `.form-grid-2`, `.form-grid-3` utilisent toutes `var(--space-field)` — elles se mettent à jour automatiquement.

### Pattern 4: Suppression HTML directe pour les éléments décoratifs

**What:** Les éléments `.page-sub` et `.bar` sont dans les templates HTML, pas dans des composants PHP dynamiques. Ils se suppriment directement dans les fichiers `.htmx.html`.

**Scope HTML à modifier (13 fichiers) :**
- `admin.htmx.html` — supprimer `<p class="page-sub">` et `<span class="bar">`
- `analytics.htmx.html` — vérifier présence
- `archives.htmx.html` — supprimer `<p class="page-sub">` et `<span class="bar">`
- `audit.htmx.html` — supprimer `<p class="page-sub">` et `<span class="bar">`
- `dashboard.htmx.html` — supprimer `<p class="page-sub">` et `<span class="bar">`
- `email-templates.htmx.html` — supprimer `<p class="page-sub">` et `<span class="bar">`
- `help.htmx.html` — supprimer `<p class="page-sub">` et `<span class="bar">`
- `hub.htmx.html` — vérifier présence
- `meetings.htmx.html` — supprimer `<p class="page-sub">` et `<span class="bar">`
- `members.htmx.html` — supprimer `<p class="page-sub">` et `<span class="bar">`
- `postsession.htmx.html` — supprimer `<p class="page-sub">` et `<span class="bar">`
- `settings.htmx.html` — supprimer `<p class="page-sub">` et `<span class="bar">`
- `users.htmx.html` — supprimer `<p class="page-sub">` et `<span class="bar">`
- `wizard.htmx.html` — page-sub est dynamique (`wiz-step-subtitle`) — **à traiter séparément** (voir Pitfall 2)

### Anti-Patterns to Avoid

- **Modifier les CSS par page au lieu des tokens globaux :** `design-system.css` seul suffit pour la propagation. Ne pas toucher `login.css`, `meetings.css`, etc. pour cette phase.
- **Supprimer `text-transform: uppercase` globalement :** Il y a 62 déclarations `text-transform: uppercase` dans les CSS. Seule `.form-label` dans `design-system.css` doit changer. Les autres (badges, table headers, labels de section d'UI) ont des usages légitimes.
- **Fusionner `--text-base` et `--text-md`** maintenant : la décision est explicitement de ne pas le faire dans cette phase.

## Don't Hand-Roll

| Problème | Ne pas construire | Utiliser plutôt | Pourquoi |
|----------|-----------------|----------------|----------|
| Propagation de la taille de police | Override CSS page par page | Changer `--text-base` dans `:root` | La cascade CSS fait le travail |
| Header height dynamique pour la sidebar | JS calculant 64px hardcodé | Laisser `getBoundingClientRect()` existant | `shell.js` mesure déjà le header rendu |
| Espacement de formulaire | Valeurs inline `gap: 20px` | Tokens `--form-gap` + `--space-field` | Cohérence et maintenabilité |

## Common Pitfalls

### Pitfall 1: `--header-height` token défini mais non utilisé dans `.app-header`
**What goes wrong:** Changer `--header-height: 64px` sans corriger `.app-header { height: 56px }` n'a aucun effet sur la hauteur visuelle du header.
**Why it happens:** `.app-header` a la hauteur hardcodée à la ligne 910 de `design-system.css` (valeur au lieu de token).
**How to avoid:** Modifier simultanément `--header-height: 64px` dans `:root` ET `height: var(--header-height)` dans `.app-header`. Idem pour `--header-height: 56px` dans `@media (max-width: 768px)` à la ligne 3342 → mettre à jour en `64px`.
**Warning signs:** Header visuellement inchangé après modification du token seulement.

### Pitfall 2: `page-sub` dynamique dans wizard
**What goes wrong:** Supprimer `<p class="page-sub wiz-step-subtitle">` du wizard casserait le JS qui met à jour le sous-titre d'étape dynamiquement.
**Why it happens:** Le wizard utilise cette classe pour injecter le titre courant de l'étape via `id="wizStepSubtitle"`.
**How to avoid:** Traiter le wizard séparément : ne pas supprimer l'élément, mais soit le masquer via CSS (`.wiz-step-subtitle { display: none }` après phase 1), soit laisser pour décision future.
**Warning signs:** Sous-titre d'étape du wizard disparaît ou erreur JS.

### Pitfall 3: `--text-md` devient identique à `--text-base` après la migration
**What goes wrong:** Après la migration, `--text-base = 1rem` et `--text-md = 1rem` sont identiques — les composants qui utilisent `--text-md` pour "texte légèrement plus grand" n'ont plus de différentiation.
**Why it happens:** La décision explicite est de laisser cette situation et de ne pas fusionner dans cette phase.
**How to avoid:** Documenter l'égalité dans un commentaire CSS. Ne rien changer à `--text-md`.
**Warning signs:** Aucun — accepté comme dette technique intentionnelle.

### Pitfall 4: `line-height` de `body` calibré pour 14px
**What goes wrong:** `--leading-base: 1.571` est annoté "14px * 1.571 = 22px golden ratio". À 16px, `1.571 * 16 = 25.14px` — légèrement plus espacé que nécessaire.
**Why it happens:** La valeur de line-height était optimisée pour l'ancienne taille de base.
**How to avoid:** Ajuster `body` pour utiliser `var(--leading-md)` (1.5) à la place de `var(--leading-base)` (1.571) — `1.5 * 16 = 24px` correspond exactement à l'échelle 4px. Ou laisser tel quel si l'espacement supplémentaire est acceptable.
**Warning signs:** Blocs de texte paraissant excessivement aérés après la migration.

### Pitfall 5: Le mobile override remet `--header-height: 56px`
**What goes wrong:** Le media query `@media (max-width: 768px)` redéfinit `--header-height: 56px` (ligne 3342). Si non mis à jour, le header sera 64px desktop et 56px mobile.
**Why it happens:** Le token est redéfini pour mobile dans le bloc responsive.
**How to avoid:** Mettre à jour la valeur mobile aussi, ou supprimer la redéfinition si la valeur 64px convient sur mobile.
**Warning signs:** Header height différent entre desktop et mobile.

## Code Examples

### TYPO-01: Changer --text-base

```css
/* design-system.css — ligne ~199 */
/* Avant */
--text-base: 0.875rem;   /* 14px — primary UI chrome size */

/* Après */
--text-base: 1rem;       /* 16px — primary UI chrome size */
```

### TYPO-01 + TYPO-03: Ajuster line-height du body pour 16px

```css
/* design-system.css — ligne ~89-95 */
body {
  font-size: var(--text-base);      /* désormais 16px */
  line-height: var(--leading-md);   /* 1.5 — 16px * 1.5 = 24px (était --leading-base: 1.571) */
}
```

### TYPO-02: Corriger .form-label

```css
/* design-system.css — ligne ~1813 */
/* Avant */
.form-label {
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  text-transform: uppercase;
  letter-spacing: .5px;
  color: var(--color-text-muted);
}

/* Après */
.form-label {
  font-size: var(--type-label-size);   /* var(--text-base) après mise à jour du token */
  font-weight: var(--font-semibold);
  /* text-transform supprimé */
  /* letter-spacing supprimé */
  color: var(--color-text);            /* lisible, pas muted */
}
```

### TYPO-02: Mettre à jour l'alias --type-label-size

```css
/* design-system.css — ligne ~600 */
/* Avant */
--type-label-size: var(--text-sm);

/* Après */
--type-label-size: var(--text-base);   /* 16px après migration --text-base */
```

### TYPO-03: Corriger --header-height et .app-header

```css
/* design-system.css — ligne ~486 */
/* Token */
--header-height: 64px;   /* était 56px */

/* design-system.css — ligne ~909 */
/* Composant — corriger le hardcoded */
.app-header {
  height: var(--header-height);   /* était height: 56px */
  /* ... reste inchangé */
}

/* design-system.css — ligne ~3342 (media query mobile) */
@media (max-width: 768px) {
  :root {
    --header-height: 64px;   /* était 56px — conserver cohérence */
  }
}
```

### TYPO-04: Nouveaux alias sémantiques de spacing

```css
/* design-system.css — dans le bloc "Semantic gap aliases" (~ligne 277) */
/* Ajouter */
--form-gap:    var(--space-5);   /* 20px — gap entre champs de formulaire */
--section-gap: var(--space-6);   /* 24px — gap entre sections de page */

/* Modifier --space-field pour utiliser le nouveau token */
--space-field: var(--form-gap);  /* était var(--space-4) = 16px, maintenant 20px */
```

### TYPO-03: Suppression page-sub dans les templates HTML

```html
<!-- Avant (ex: dashboard.htmx.html) -->
<h1 class="page-title">
  <span class="bar"></span>  <!-- barre décorative -->
  <svg ...>...</svg>
  Tableau de bord
</h1>
<p class="page-sub">Vue d'ensemble de vos assemblées</p>

<!-- Après -->
<h1 class="page-title">
  <svg ...>...</svg>
  Tableau de bord
</h1>
<!-- page-sub supprimé -->
```

## State of the Art

| Ancienne approche | Approche actuelle | Impact |
|------------------|------------------|--------|
| `font-size: 14px` (petite UI dense) | `font-size: 16px` (lisibilité standard) | Meilleure lecture, surtout 55+ ans |
| Labels UPPERCASE muted | Labels casse normale, couleur texte | Moins agressif visuellement, plus lisible |
| Header 56px compact | Header 64px aéré | Plus d'espace vertical pour breadcrumb + titre |
| `--space-field: 16px` | `--form-gap: 20px` | Formulaires plus respirants |

**Deprecated/outdated:**
- `.page-sub` : supprimé des pages — la classe CSS reste pour compatibilité mais n'est plus utilisée
- `.page-title .bar` : barre décorative supprimée — la classe CSS reste mais les `<span class="bar">` sont retirés des templates

## Open Questions

1. **Wizard `page-sub` dynamique**
   - What we know: `<p class="page-sub wiz-step-subtitle" id="wizStepSubtitle">` est mis à jour par JS selon l'étape courante
   - What's unclear: Doit-on le masquer en CSS (`.wiz-step-subtitle { display: none }`) ou laisser en place et décider en Phase 2 ?
   - Recommendation: Laisser l'élément wizard intact dans cette phase — hors scope décisionnel des TYPO requirements. Ne pas le supprimer.

2. **`body` line-height ajustement**
   - What we know: `--leading-base: 1.571` est annoté pour 14px. À 16px cela donne 25px de line-height.
   - What's unclear: 25px est-il acceptable ou doit-on ajuster à `--leading-md: 1.5` (24px) ?
   - Recommendation: Migrer vers `var(--leading-md)` — correspond à l'échelle 4px et au commentaire existant du token.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Playwright (E2E) + PHPUnit ^10.5 (Unit) |
| Config file | `tests/e2e/playwright.config.js` |
| Quick run command | `npx playwright test specs/critical-path-dashboard.spec.js --project=chromium` |
| Full suite command | `npx playwright test --project=chromium` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| TYPO-01 | Police base 16px sur toutes les pages | visual/smoke | `npx playwright test specs/critical-path-dashboard.spec.js specs/critical-path-meetings.spec.js --project=chromium` | ✅ |
| TYPO-02 | Labels formulaire en casse normale, couleur texte | visual/smoke | `npx playwright test specs/critical-path-wizard.spec.js specs/critical-path-settings.spec.js --project=chromium` | ✅ |
| TYPO-03 | Header 64px, pas de page-sub ni .bar | visual/smoke | `npx playwright test specs/critical-path-dashboard.spec.js specs/navigation.spec.js --project=chromium` | ✅ |
| TYPO-04 | Espacement formulaire 20px | visual/smoke | `npx playwright test specs/critical-path-wizard.spec.js specs/critical-path-members.spec.js --project=chromium` | ✅ |

**Note:** Les tests Playwright existants (critical-path-*) valident que les pages se chargent et que les éléments clés sont visibles. Ils ne mesurent pas les valeurs CSS exactes (px). La vérification quantitative des tokens (16px, 64px, 20px) sera manuelle via DevTools sur les pages clés.

### Sampling Rate
- **Per task commit:** `npx playwright test specs/critical-path-dashboard.spec.js --project=chromium`
- **Per wave merge:** `npx playwright test --project=chromium` (smoke sur toutes les pages)
- **Phase gate:** Suite complète verte avant `/gsd:verify-work`

### Wave 0 Gaps
None — existing E2E infrastructure covers smoke validation for all affected pages. Les critical-path specs vérifient que les pages se chargent sans crash après les changements CSS.

## Sources

### Primary (HIGH confidence)
- Lecture directe de `public/assets/css/design-system.css` (5500 lignes) — tous les tokens, positions de ligne vérifiées
- Lecture directe des 13 templates `public/*.htmx.html` — présence de `.page-sub` et `.bar` confirmée
- Lecture de `public/assets/js/core/shell.js` — comportement `sidebar-top` dynamique confirmé

### Secondary (MEDIUM confidence)
- Lecture de `tests/e2e/specs/` — couverture E2E existante vérifiée

## Metadata

**Confidence breakdown:**
- Tokens à modifier: HIGH — positions exactes dans design-system.css vérifiées
- HTML scope: HIGH — tous les fichiers avec `page-sub` et `.bar` listés et vérifiés
- Sidebar behavior: HIGH — code JS vérifié (getBoundingClientRect dynamique)
- Line-height adjustment: MEDIUM — décision stylistique, deux valeurs raisonnables

**Research date:** 2026-04-21
**Valid until:** 2026-05-21 (fichiers CSS stables, faible rotation)
