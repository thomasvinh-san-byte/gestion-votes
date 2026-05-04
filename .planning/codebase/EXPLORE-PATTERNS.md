# Explore Patterns — Anti-BEM-substring scan guide

> **Quand utiliser quoi pour scanner le codebase sans faux-positifs.**

**Source historique** : v2.3 Phase 3 Schoger S-8 — un scan initial `grep -c shortcut-cards public/dashboard.htmx.html` avait retourné **15 matches** alors qu'il n'y a en réalité **3 cards `.shortcut-card`** dans le markup. Les 12 matches supplémentaires étaient les enfants BEM (`__icon`, `__text`, `__title`, `__sub` × 3 cards). Cette erreur a propagé une UX review erronée (S-8) et un plan 03.2 entier basé sur "réduire 15 cards à 5". Voir `.planning/phases/03-layouts-secondaires/03.2-SUMMARY.md` (v2.3) pour le post-mortem.

Ce document évite la répétition.

---

## Règle d'or

**Un `grep` sur un nom de classe BEM ou un identifiant doit toujours être ancré.** Le pattern naïf `grep "<token>"` est presque toujours faux dès que le token a des dérivés (`__elem`, `--mod`, `-suffix`).

Choix du délimiteur en fonction du token :

| Le token contient... | Délimiteur conseillé | Pourquoi |
|---|---|---|
| ASCII pur (`meeting`, `Foo`) | `\b` (word boundary POSIX) | Fonctionne, `\b` reconnaît les caractères non-alphanumériques |
| Hyphens (`shortcut-cards`, `op-tab`) | **`($\|[^a-zA-Z_-])`** | `\b` casse sur `-`, donc `\b` ne distingue PAS `op-tab` de `op-tab-panel` |
| Slash / namespace (`AgVote\Service`) | Terminaison explicite (`;`, `\\`) | Le `\` doit être doublement échappé en regex |

**Conséquence pratique** : pour les classes BEM avec hyphens, **n'utilisez pas `\b`**. Utilisez la classe de caractères négative `[^a-zA-Z_-]` (ou `[^[:alnum:]_-]`).

---

## Anti-pattern #1 — BEM substring sans ancrage (cas Schoger S-8)

**Symptôme** : `grep "shortcut-cards"` matche `shortcut-cards`, `shortcut-cards__title`, `shortcut-cards--variant`, `shortcut-cards-grid`. Le compte est gonflé d'un facteur 4-5x.

### Mauvais

```bash
grep -cE 'shortcut-cards' public/assets/css/*.css
```

### Bon

```bash
# Pour HTML (compter des éléments avec la classe exacte)
grep -cE 'class="shortcut-card"' public/dashboard.htmx.html
grep -cE 'class="[^"]*\bshortcut-card\b[^"]*"' public/dashboard.htmx.html

# Pour CSS (compter les sélecteurs `.shortcut-cards` sans BEM enfant)
grep -cE '\.shortcut-cards($|[^a-zA-Z_-])' public/assets/css/*.css
```

**Test gardien** :

```bash
$ printf '.shortcut-cards__title\n.shortcut-cards\n.shortcut-cards-grid\n.shortcut-cards--variant\n' \
    | grep -cE '\.shortcut-cards($|[^a-zA-Z_-])'
1   # OK : seul ".shortcut-cards" matche
```

---

## Anti-pattern #2 — Identifiant JS / module name (préfixe partagé)

**Symptôme** : `grep "import { meeting }"` peut matcher `import { meetingRepo }` ou `import { meetings }` selon le formatage. Le compte d'imports d'un symbole est faux.

### Mauvais

```bash
grep -E 'import \{ meeting \}' app/**/*.js   # casse si formatage variable
grep -E 'meeting' app/**/*.js                # explose en faux-positifs
```

### Bon

```bash
# Délimiter par caractère non-identifier
grep -nE 'import \{[^}]*\bmeeting\b[^}]*\}' app/**/*.js
# Ou pour usages dans le code
grep -nE '\bmeeting\b' app/**/*.js
```

**Test gardien** :

```bash
$ printf 'meeting\nmeetingRepo\nmeeting.id\n' | grep -cE '\bmeeting\b'
2   # OK : "meeting" et "meeting.id" matchent, "meetingRepo" exclu
```

(Ici `\b` fonctionne car le token est ASCII pur, sans hyphen.)

---

## Anti-pattern #3 — PHP namespace shadow

**Symptôme** : `grep "namespace AgVote\\Service"` (sans terminaison) matche aussi `namespace AgVote\Service\Auth\Foo` — alors que le besoin est de compter les fichiers strictement dans `AgVote\Service`.

### Mauvais

```bash
grep -rE 'namespace AgVote\\Service' app/Services/
# matche aussi AgVote\Service\Auth, AgVote\Service\Mail, etc.
```

### Bon

```bash
# Terminaison explicite par point-virgule (fin de déclaration namespace PHP)
grep -rnE 'namespace AgVote\\Service;' app/Services/

# Pour scope étendu (sous-namespaces inclus mais distincts)
grep -rnE 'namespace AgVote\\Service\\[A-Z][a-zA-Z]+;' app/Services/
```

**Test gardien** :

```bash
$ printf 'namespace AgVote\\Service;\nnamespace AgVote\\Service\\Auth;\n' \
    | grep -cE 'namespace AgVote\\Service;'
1   # OK : seule la déclaration exacte matche
```

---

## Pattern correct générique

Pour un token `<T>` à matcher exactement :

| Contexte | Regex | Exemple concret |
|---|---|---|
| Classe CSS BEM (avec hyphens) | `\.<T>($\|[^a-zA-Z_-])` | `\.shortcut-cards($\|[^a-zA-Z_-])` |
| Attribut HTML `class="<T>"` | `class="<T>"` ou `class="[^"]*\b<T>\b[^"]*"` | `class="op-tab"` |
| Identifiant JS/PHP (sans hyphen) | `\b<T>\b` | `\bmeetingId\b` |
| Namespace PHP (déclaration) | `namespace <T>;` | `namespace AgVote\\Service;` |
| Import JS (named) | `import \{[^}]*\b<T>\b[^}]*\}` | `import { seedMeeting }` |

**Astuce** : avant de committer un compte basé sur grep, exécuter le pattern sur un échantillon contrôlé (`printf 'cas1\ncas2\n...' \| grep ...`) pour vérifier qu'il rejette bien les variantes.

---

## Cas d'usage validation v2.4 — `.op-tab` cockpit operator

**Contexte** : le fichier `public/assets/css/operator.css` contient plusieurs sélecteurs en famille `.op-tab*` :

- `.op-tabs` (conteneur)
- `.op-tab` (onglet de base)
- `.op-tab:hover`, `.op-tab.active` (états)
- `.op-tab-panel` (panel associé)

On veut compter les **`.op-tab` bare** (onglets de base et leurs états directs `:hover`, `.active`) sans matcher `.op-tabs` ni `.op-tab-panel`.

```bash
# Compte naïf (faux) — match toute la famille .op-tab*
grep -cE '\.op-tab' public/assets/css/operator.css
# → 7 (inclut .op-tabs, .op-tab-panel, .op-tab-panel.active, etc.)

# Compte correct (bare .op-tab + ses pseudo-classes / states)
grep -cE '\.op-tab($|[^a-zA-Z_-])' public/assets/css/operator.css
# → 3 (.op-tab seul, .op-tab:hover, .op-tab.active — `:` et `.` sont hors [a-zA-Z_-])
```

Détails : la classe `[^a-zA-Z_-]` autorise les terminaisons `:` (pseudo-classe), `.` (pseudo-état combiné), `,` (sélecteur multiple), `{` (début de bloc), espace, etc. — exactement les caractères qui suivent un sélecteur "complet" en CSS, tout en rejetant `s` (`.op-tabs`) et `-` (`.op-tab-panel`).

Ces commandes ont été exécutées contre `public/assets/css/operator.css` durant la v2.4 P3 — résultats reproductibles tant que le fichier existe.

---

## Liens

- **Source historique** : [`.planning/phases/03-layouts-secondaires/03.2-SUMMARY.md`](../phases/03-layouts-secondaires/03.2-SUMMARY.md) (v2.3) — post-mortem Schoger S-8
- **Agent associé** : [`.claude/agents/gsd-code-reviewer.md`](../../.claude/agents/gsd-code-reviewer.md) — arguments `--scope` et `--exclude` pour cadrer un scan
- **README e2e** : [`tests/e2e/README.md`](../../tests/e2e/README.md) — référence ce guide depuis la section "Common pitfalls"
