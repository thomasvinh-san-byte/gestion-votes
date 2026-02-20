# Plan de correctifs et améliorations frontend

## Contexte

Backend figé (19 controllers, 103 endpoints, 348 tests verts). Contrat API stable.
Frontend vanilla JS + HTMX + Web Components. Pas de framework, pas de bundler.

**Phase 2 (split operator-tabs.js) : FAIT.**
- `operator-tabs.js` : 4 601 → 2 669 lignes
- 3 sub-modules extraits : speech (254), attendance (654), motions (1 245)
- Bridge `OpS` avec `Object.defineProperty` pour la communication

---

## Phase 1a — Fix sécurité escapeHtml (CRITIQUE)

**Problème** : 5 Web Components ont des copies incomplètes de `escapeHtml()`.

| Composant | Manque | Risque |
|-----------|--------|--------|
| `ag-searchable-select.js` | `'` | Moyen — données dans attributs |
| `ag-toast.js` | `'` | Moyen |
| `ag-kpi.js` | `'` | Moyen |
| `ag-spinner.js` | `"` et `'` | **Élevé** — attributs HTML non protégés |
| `ag-quorum-bar.js` | `"` et `'` | **Élevé** — idem |

**Action** : Corriger chaque méthode `escapeHtml()` dans les 5 composants pour inclure
tous les caractères (`& < > " '`). Aligner sur la version canonique `Utils.escapeHtml()`.

**Contrainte** : les Web Components utilisent Shadow DOM → copie locale OK,
pas besoin d'importer Utils. Juste corriger les lignes manquantes.

**Effort** : ~15 min, 5 fichiers, 5-10 lignes modifiées au total.
**Risque de casse** : zéro (ajout d'échappement, pas de suppression).

---

## Phase 1b — Nettoyage duplication morte

### 1b-1. Supprimer `Utils.getMeetingId()` (utils.js:339-352)

- 1 seul appelant : `shell.js:54` comme dernier fallback
- Remplacer dans shell.js par un accès direct à MeetingContext ou supprimer le fallback
- ~14 lignes supprimées

### 1b-2. Supprimer 3 wrappers globaux inutilisés (utils.js)

| Wrapper | Ligne | Appels réels |
|---------|-------|-------------|
| `isValidEmail()` | 574 | 0 |
| `parseCSV()` | 581 | 0 (callers utilisent `Utils.parseCSV()`) |
| `formatDate()` | 695 | 1 seul (trust.js) → refactorer en `Utils.formatDate()` |

- ~15 lignes supprimées
- Modifier trust.js pour appeler `Utils.formatDate()` directement

### 1b-3. NE PAS supprimer

- `escapeHtml()` global → 21 fichiers, 50+ appels. Le coût de refacto n'est pas justifié.
- `getApiError()` global → 5 fichiers. Idem.
- `api()` global → 14+ fichiers, API différente de `Utils.apiGet/apiPost`.

**Effort total Phase 1b** : ~20 min, ~30 lignes supprimées, 3 fichiers modifiés.

---

## Phase 1c — Audit innerHTML (SÉCURITÉ)

**Problème** : 177 `innerHTML =` dans 21 fichiers. Chaque affectation sans
`escapeHtml()` est un vecteur XSS potentiel.

**Action** : Auditer chaque `innerHTML` dans les fichiers suivants (par densité) :

| Fichier | innerHTML | Priorité |
|---------|----------|----------|
| `operator-tabs.js` | 40 | Haute |
| `admin.js` | 27 | Haute |
| `shell.js` | 24 | Haute |
| `operator-motions.js` | 18 | Moyenne |
| `trust.js` | 10 | Moyenne |
| `operator-attendance.js` | 8 | Moyenne |
| `vote.js` | ~8 | Moyenne |
| Autres | ~42 | Basse |

Pour chaque `innerHTML` :
1. Vérifier que les données dynamiques passent par `escapeHtml()`
2. Si données hardcodées (HTML statique) → OK, pas de risque
3. Si données API non échappées → corriger

**Effort** : ~1-2h d'audit + corrections ponctuelles.
**Risque de casse** : faible (ajout d'échappement, pas de changement de logique).

---

## Phase 3 — Split supplémentaire operator-tabs.js (OPTIONNEL)

Le fichier reste à 2 669 lignes. Les plus grosses sections restantes :

| Section | Lignes | Extractible ? |
|---------|--------|---------------|
| Dashboard & Devices | 257 | Oui — peu de cross-deps |
| Execution View | 251 | Oui — mais appelle beaucoup d'OpS.fn |
| Mode Switch | 187 | Non — trop couplé au core |
| Save Settings | 171 | Oui — autonome |
| Paramètres (Settings tab) | 129 | Oui — avec Save Settings |
| Conformity Checklist | 122 | Oui — autonome |
| Alerts | 122 | Oui — autonome |

**Candidates réalistes pour extraction** :
1. `operator-settings.js` (Settings + Save Settings) : ~300 lignes
2. `operator-dashboard.js` (Dashboard + Devices + Conformity + Alerts) : ~500 lignes

Cela ramènerait `operator-tabs.js` à ~1 850 lignes (core pur : tabs, modes, polling, members, meeting selection).

**Effort** : ~1h par module, même pattern que les extractions précédentes.
**Risque** : faible (même technique OpS bridge éprouvée).

**Jugement Linus** : 2 669 lignes avec des sections clairement délimitées,
c'est gérable. Ce split est un "nice to have", pas un "must do". Ne le faire
que si on prévoit des modifications fréquentes dans settings ou dashboard.

---

## Ce qu'il NE FAUT PAS faire

- **Ne pas introduire de framework** (React, Vue, Svelte). Le vanilla marche.
- **Ne pas ajouter de bundler** tant qu'il n'y a pas de vrais modules ES.
- **Ne pas réécrire le CSS**. Il est propre.
- **Ne pas toucher aux Web Components** (sauf le fix escapeHtml).
- **Ne pas unifier `api()` et `Utils.apiGet/apiPost()`** — API différentes.
- **Ne pas supprimer les globals massivement utilisés** (escapeHtml, api, icon, setNotif).
- **Ne pas ajouter de tests unitaires JS** pour l'instant — le ROI est trop faible
  sur du vanilla JS sans logique complexe testable isolément.

---

## Ordre d'exécution recommandé

```
Phase 1a  →  Fix escapeHtml (sécurité)           ~15 min
Phase 1b  →  Nettoyage duplication morte          ~20 min
Phase 1c  →  Audit innerHTML                      ~1-2h
Phase 3   →  Split settings + dashboard           ~2h (optionnel)
```

Chaque phase = 1 commit atomique, testable indépendamment.
