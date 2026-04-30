# INVENTORY — TECH-01 Consolidation box-shadow + border (Schoger S-2)

**Quick** : 260430-86c
**Date** : 2026-04-30
**Périmètre** : `public/assets/css/**/*.css` (hors `design-system.css` qui est la source de vérité des tokens)

---

## Méthode

Extraction exhaustive via :

```bash
grep -rn 'box-shadow:' public/assets/css/ | sed -E 's/.*box-shadow:\s*//; s/;.*//' | sort -u
grep -rnE '^\s*border(-top|-bottom|-left|-right)?:\s*[0-9]' public/assets/css/ | sed -E 's/.*border[a-z-]*:\s*//; s/;.*//' | sort -u
```

**Niveaux de confiance** :
- **HAUTE** = mapping évident (couleur 1:1 avec un token, valeur rigoureusement identique). Remplacement automatique en T3.
- **BASSE** = ad-hoc, multi-layer custom, couleur hex/rgb non-token, intention design ambiguë. À traiter par fichier en Phase 2/3.

---

## Box-shadow

73 valeurs distinctes recensées (occurrences totales : 166).

| Valeur source | Token suggéré | Confiance | # occ. | Note |
|---|---|---|---|---|
| `var(--shadow-sm)` | (déjà tokenisé) | — | 33 | Aucun changement |
| `var(--shadow-lg)` | (déjà tokenisé) | — | 24 | Aucun changement |
| `var(--shadow-md)` | (déjà tokenisé) | — | 18 | Aucun changement |
| `none` | `none` | — | 6 | Aucun changement |
| `var(--shadow-focus)` | (déjà tokenisé) | — | 3 | Aucun changement |
| `var(--shadow-focus-danger)` | (déjà tokenisé) | — | 3 | Aucun changement |
| `inset 0 1px 0 rgba(255,255,255,.15), var(--shadow-md)` | composite — laisser | BASSE | 3 | Glossy highlight pattern, intention spécifique |
| `0 0 0 3px var(--color-primary-glow)` | (focus glow custom) | BASSE | 4 | Variante focus glow, mapping à étudier en Phase 2 |
| `0 0 0 4px var(--color-primary-subtle)` | (focus halo) | BASSE | 3 | Idem |
| `0 0 0 2px var(--color-primary-subtle)` | (focus halo) | BASSE | 3 | Idem |
| `0 0 0 0 color-mix(...)` (animations) | (transition glow) | BASSE | 3+ | Animations keyframes, intention motion |
| Tous les `var(--vote-*-shadow*)` | (tokens locaux vote.css) | BASSE | 6 | Tokens spécifiques au composant vote — laisser |
| Tous les `var(--shadow-*-glow*)`, `var(--shadow-*-ring)` | (tokens custom glows) | BASSE | 6 | Variations glow déjà tokenisées localement |
| `inset 0 1px 0 rgba(255,255,255,.12), 0 1px 2px rgba(...)` x6 | (boutons colorés glossy) | BASSE | 6 | Pattern intentionnel, multi-layer |
| `12px 0 40px rgba(0, 0, 0, .4)` | (sidebar drawer mobile) | BASSE | 1 | Layout-specific |
| `0 32px 80px rgba(0,0,0,.22)` | (large overlay) | BASSE | 1 | Plus grand que `--shadow-lg` |
| `0 24px 60px rgba(0,0,0,.18)` | (large overlay) | BASSE | 1 | Idem |
| `0 -2px 8px rgba(0, 0, 0, 0.05)` | (top shadow custom) | BASSE | 1 | Direction négative, non-standard |
| `0 1px 3px rgba(0,0,0,.2)` | proche `var(--shadow-sm)` | BASSE | 1 | Alpha 0.20 vs 0.06 — trop différent |
| Autres valeurs uniques (color-mix, custom RGB, etc.) | — | BASSE | ≈25 | Pas de mapping évident, à examiner par fichier |

**Aucune occurrence** de la forme attendue `0 1px 2px rgba(0,0,0,0.0X)` avec X≤6 → **aucun remplacement HAUTE confiance pour `--shadow-xs`**. Le token est néanmoins ajouté en T2 pour usage Phase 2/3 (catalogue prêt à l'emploi).

### Bilan box-shadow
- HAUTE confiance remplaçable : **0** occurrence (les shadows tokenisées le sont déjà ; pas de match exact pour `--shadow-xs`).
- BASSE confiance à reporter : **≈45 occurrences** sur ≈30 valeurs distinctes.

---

## Borders

69 valeurs distinctes recensées (occurrences totales : 452).

| Valeur source | Token suggéré | Confiance | # occ. | Note |
|---|---|---|---|---|
| `1px solid var(--color-border)` | `var(--border-default)` | **HAUTE** | 241 (dont 45 dans design-system.css **exclus**) | Mapping 1:1 strict |
| `1px solid var(--color-border-subtle)` | `var(--border-subtle)` | **HAUTE** | 38 (dont 3 dans design-system.css **exclus**) | Mapping 1:1 strict |
| `1px dashed var(--color-border-strong)` | `var(--border-dashed)` | **HAUTE** | 1 | Couleur identique à `--color-border-dash` (oklch(0.70 0.010 257)) |
| `2px solid var(--color-border)` | `var(--color-border-strong)` (couleur ≠) | BASSE | 21 | Width différente, pas de token 2px shorthand |
| `1px solid var(--color-border-subtle, var(--color-border))` | `var(--border-subtle)` (avec fallback) | BASSE | 16 | Présence d'un fallback inline, replacement riskerait de perdre la dégradation |
| `1px solid var(--color-danger)` | (border-danger) | BASSE | 8 | Pas de token shorthand pour danger |
| `2px dashed var(--color-border)` | (border-dashed-2) | BASSE | 7 | Width 2px, pas de token |
| `3px solid var(--color-success)` | (accent-success) | BASSE | 6 | Width 3px = accent visuel, pas du chrome |
| `2px solid transparent` | (placeholder focus ring) | BASSE | 6 | Transparent intentionnel |
| `1px solid var(--color-warning)` | — | BASSE | 6 | Même raison que danger |
| `1px solid var(--color-primary)` | — | BASSE | 6 | Idem |
| `2px solid var(--color-primary)` | — | BASSE | 5 | Idem |
| `1px solid var(--color-success)` | — | BASSE | 5 | Idem |
| `1px solid var(--color-border-subtle, #e8e7e2)` | — | BASSE | 3 | Fallback hex inline |
| `1px solid var(--color-border-alpha)` | — | BASSE | 3 | Couleur ≠ `--color-border` |
| `1px solid transparent` | — | BASSE | 3 | Transparent intentionnel |
| `1.5px solid var(--color-border)` | — | BASSE | 2 | Width non-standard |
| `1px solid var(--color-border, #d5dbd2)` | `var(--border-default)` (avec fallback perdu) | BASSE | 2 | Fallback inline, pas de remplacement direct |
| `1px solid oklch(1 0 0 / 0.18)` | — | BASSE | 2 | Couleur ad-hoc dark mode |
| `1px dashed var(--color-border)` | (border-dashed sur color-border) | BASSE | 2 | Couleur ≠ `--color-border-dash` |
| `1px solid #d1d5db` | — | BASSE | 1 | Hex en dur |
| `1px solid var(--color-border, #eee)` | — | BASSE | 1 | Fallback hex |
| `1px solid var(--color-border, #e5e7eb)` | — | BASSE | 1 | Fallback hex |
| Tous les `1px solid color-mix(...)` | — | BASSE | 7 | Mélanges custom, intention design |
| Tous les `1px solid var(--color-*-border*)` (success-border, danger-border, warning-border-25, etc.) | — | BASSE | 4 | Tokens spécifiques sémantiques |
| `1px solid var(--sidebar-border)` | — | BASSE | 3 | Token local sidebar |
| `1px solid var(--persona-*)`, `2px solid var(--persona-*)`, `3px solid var(--persona-*)` | — | BASSE | 3 | Tokens persona |
| `0` | (reset) | — | 1 | Aucun changement |

### Bilan borders
- HAUTE confiance remplaçable : **241 + 38 + 1 = 280 occurrences** brutes, dont **45 + 3 + 0 = 48 dans `design-system.css` exclues**.
- **Net à remplacer en T3 : 196 (default) + 35 (subtle) + 1 (dashed) = 232 occurrences** sur 25 fichiers.
- BASSE confiance à reporter : **≈140 occurrences** sur ≈45 valeurs distinctes (à traiter en Phase 2/3 par fichier).

---

## Fichiers couverts en T3 (HAUTE confiance, hors design-system.css)

| Fichier | default | subtle | dashed | Total |
|---|---:|---:|---:|---:|
| `admin.css` | 1 | 0 | 0 | 1 |
| `analytics.css` | 10 | 0 | 0 | 10 |
| `app.css` | 10 | 4 | 0 | 14 |
| `archives.css` | 4 | 3 | 0 | 7 |
| `audit.css` | 5 | 0 | 0 | 5 |
| `components/ag-shortcuts-overlay.css` | 2 | 0 | 0 | 2 |
| `doc.css` | 9 | 0 | 0 | 9 |
| `email-templates.css` | 9 | 0 | 0 | 9 |
| `help.css` | 8 | 0 | 0 | 8 |
| `hub.css` | 6 | 0 | 0 | 6 |
| `landing.css` | 9 | 1 | 0 | 10 |
| `login.css` | 2 | 0 | 0 | 2 |
| `meetings.css` | 9 | 0 | 0 | 9 |
| `members.css` | 24 | 0 | 0 | 24 |
| `operator.css` | 25 | 8 | 1 | 34 |
| `pages.css` | 12 | 2 | 0 | 14 |
| `postsession.css` | 9 | 0 | 0 | 9 |
| `public.css` | 6 | 0 | 0 | 6 |
| `report.css` | 1 | 0 | 0 | 1 |
| `settings.css` | 4 | 0 | 0 | 4 |
| `trust.css` | 10 | 2 | 0 | 12 |
| `users.css` | 7 | 0 | 0 | 7 |
| `validate.css` | 2 | 0 | 0 | 2 |
| `vote.css` | 9 | 1 | 0 | 10 |
| `wizard.css` | 3 | 14 | 0 | 17 |
| **Total** | **196** | **35** | **1** | **232** |

> **Note** : `design-system.css` est exclu de T3 — c'est la source de vérité des tokens. Modifier ses propres définitions internes risque d'introduire un cycle (`--border-default: 1px solid var(--color-border)` puis utiliser `var(--border-default)` dans le même fichier). Les 48 occurrences dans `design-system.css` resteront sous forme literal — ce sont les *définitions* canoniques.

---

## Mapping de remplacement (T3)

Pour chaque fichier listé ci-dessus, appliquer **par ordre** (les regex sont cumulatives et orthogonales) :

```
border:        1px solid var(--color-border);         → border:        var(--border-default);
border-top:    1px solid var(--color-border);         → border-top:    var(--border-default);
border-bottom: 1px solid var(--color-border);         → border-bottom: var(--border-default);
border-left:   1px solid var(--color-border);         → border-left:   var(--border-default);
border-right:  1px solid var(--color-border);         → border-right:  var(--border-default);

border:        1px solid var(--color-border-subtle);  → border:        var(--border-subtle);
border-top:    1px solid var(--color-border-subtle);  → border-top:    var(--border-subtle);
border-bottom: 1px solid var(--color-border-subtle);  → border-bottom: var(--border-subtle);
border-left:   1px solid var(--color-border-subtle);  → border-left:   var(--border-subtle);
border-right:  1px solid var(--color-border-subtle);  → border-right:  var(--border-subtle);

border:        1px dashed var(--color-border-strong); → border:        var(--border-dashed);
```

**Garde-fous** :
- Variantes avec fallback (`var(--color-border, #xyz)`) → laisser intactes (BASSE confiance).
- Variantes avec `--color-border-subtle, var(--color-border)` → laisser intactes (le fallback porte une intention).

---

## Résumé

| Métrique | Shadows | Borders |
|---|---:|---:|
| Distinct (avant) | 73 | 69 |
| Occurrences totales | 166 | 452 |
| HAUTE confiance — remplaçables en T3 | 0 | 232 (sur 25 fichiers) |
| BASSE confiance — à reporter Phase 2/3 | ≈45 occ. / ≈30 valeurs | ≈140 occ. / ≈45 valeurs |
| Tokens manquants à créer (T2) | 1 (`--shadow-xs`) | 5 (`--border-default/subtle/strong/dashed/focus`) |

**Conclusion** : la dette est principalement sur les *borders* — la consolidation T3 va supprimer ~50% des variantes borders distinctes (les 232 occurrences haute-confiance se collapsent en 3 tokens). Les shadows sont déjà bien tokenisées ; le travail résiduel est de la chirurgie fine sur des cas custom (focus glows, glossy buttons, overlays larges) qui doit accompagner le refactor visuel de chaque page en Phase 2/3.

---

## Replacement Report (T3 — exécution)

Remplacements effectivement appliqués par le `sed` (legacy → token). La commande appliquée n'a pas l'ancrage `^\s*`, donc 2 occurrences inline (déclarations sur la même ligne qu'un sélecteur) ont été remplacées en bonus dans `operator.css` — toujours dans le périmètre HAUTE confiance (`1px solid var(--color-border-subtle);` strict, pas de fallback).

| Fichier | Shadows remplacées / total | Borders remplacées / total | Reportées (basse conf) | Commit |
|---|---:|---:|---:|---|
| `admin.css` | 0/0 | 1/1 | — | 62fa71d |
| `analytics.css` | 0/0 | 10/10 | — | d73df5b |
| `app.css` | 0/0 | 14/14 | borders avec fallback inline laissées | 494b1d5 |
| `archives.css` | 0/0 | 7/7 | — | b16b1c6 |
| `audit.css` | 0/0 | 5/5 | — | 00a5114 |
| `doc.css` | 0/0 | 9/9 | — | ffa79ad |
| `email-templates.css` | 0/0 | 9/9 | — | 8185065 |
| `help.css` | 0/0 | 8/8 | — | c0f2b0b |
| `hub.css` | 0/0 | 6/6 | — | 1dafbd1 |
| `landing.css` | 0/0 | 10/10 | — | b376d0b |
| `login.css` | 0/0 | 2/2 | — | 9c96874 |
| `meetings.css` | 0/0 | 9/9 | — | 96953b4 |
| `members.css` | 0/0 | 24/24 | — | d10fcde |
| `operator.css` | 0/0 | **36/36** (10 subtle au lieu de 8 estimés — voir note) | — | 7b59e6a |
| `pages.css` | 0/0 | 14/14 | — | 0de33fa |
| `postsession.css` | 0/0 | 9/9 | — | ee07e77 |
| `public.css` | 0/0 | 6/6 | — | 432df67 |
| `report.css` | 0/0 | 1/1 | — | ece7148 |
| `settings.css` | 0/0 | 4/4 | — | c8de7a4 |
| `trust.css` | 0/0 | 12/12 | — | 69d0905 |
| `users.css` | 0/0 | 7/7 | — | aa28061 |
| `validate.css` | 0/0 | 2/2 | — | 2b8d9c6 |
| `vote.css` | 0/0 | 10/10 | borders `--vote-*-shadow*` (tokens locaux) laissés | 064f980 |
| `wizard.css` | 0/0 | 17/17 | — | 06d5824 |
| `components/ag-shortcuts-overlay.css` | 0/0 | 2/2 | — | 58ef5e2 |
| **Total** | **0** | **234** | ≈140 occ. BASSE | — |

**Garde-fous respectés** :
- `design-system.css` non modifié (T3) — c'est la source de vérité des tokens.
- Aucune ligne avec fallback inline (`var(--color-border, #...)`, `var(--color-border-subtle, var(--color-border))`) n'a été touchée.
- Aucune occurrence avec `2px`, `1.5px`, `dashed var(--color-border)` (couleur ≠ dash) n'a été touchée.
- Toutes les accolades `{` / `}` restent équilibrées sur les 25 fichiers modifiés.

**Note operator.css** : 10 occurrences `1px solid var(--color-border-subtle);` remplacées au lieu de 8 estimées en T1 — l'estimation initiale s'appuyait sur un grep ancré `^\s*border` qui ratait 2 lignes inline (sélecteur + propriété sur la même ligne). Le `sed` non ancré les a captées correctement, dans le strict respect du pattern HAUTE confiance.
