# INFRA-V26-02 — Audit dual-install Playwright

**Date** : 2026-05-05
**Verdict** : ✓ Résolu (option B — dual-install volontaire, SOT dans `tests/e2e/`, proxies racine, garde-fou actif)

## Contexte

La requirement **INFRA-V26-02** demande explicitement :

> "soit un seul `npm install` à la racine résout tout, soit le double install
> est documenté avec rationale claire dans `tests/e2e/README.md`".

L'option **B** a été retenue (cf. `tests/e2e/README.md` §5 "Dual-install
Playwright") : `tests/e2e/package.json` reste la SOT (single source of truth),
les scripts racine `test:e2e` / `test:e2e:chromium` sont des proxies
`cd tests/e2e && npx playwright test ...`, et `bin/check-deps.sh` fait office
de garde-fou pour empêcher les régressions.

## État initial du worktree (avant patch)

L'audit a été lancé sur la base v2.0 de ce worktree. Trois écarts ont été
détectés par rapport au verdict cible :

| Élément | État initial | Cible INFRA-V26-02 | Action |
|---|---|---|---|
| `bin/check-deps.sh` | absent | présent + exit 0 | **créé** |
| `@playwright/test` dans root `package.json` | présent (1×) | absent | **retiré** |
| Scripts `test:e2e` racine | `npx playwright test --config=tests/e2e/playwright.config.js` | proxy `cd tests/e2e && npx playwright test` | **patchés** |
| Script `check:deps` racine | absent | `bash bin/check-deps.sh` | **ajouté** |
| README §5 "Dual-install" | absent | rationale + lien vers `bin/check-deps.sh` | **patché** |

Les patches sont chirurgicaux : pas de réorganisation du fichier, ajout des
lignes nécessaires uniquement. Ils sont visibles dans le `git diff` de ce
plan (commit unique).

## Garde-fou `bin/check-deps.sh`

Sortie après patches :

```
$ bash bin/check-deps.sh; echo "EXIT=$?"
OK: dual-install Playwright invariants tenus
    root @playwright/test  : 0 (attendu 0)
    e2e  @playwright/test  : 1 (attendu ≥1, SOT)
EXIT=0
```

Le script implémente trois codes d'erreur (cf. `bin/check-deps.sh` en-tête) :

| Exit | Signification |
|---|---|
| 0 | Invariants tenus |
| 1 | `@playwright/test` est apparu dans le root `package.json` (régression) |
| 2 | `@playwright/test` a disparu de `tests/e2e/package.json` (SOT cassé) |
| 3 | Un fichier `package.json` attendu est manquant |

## Invariants vérifiés par grep

| Invariant | Commande | Attendu | Réel |
|---|---|---|---|
| Pas de `@playwright/test` dans root | `grep -c '@playwright/test' package.json` | 0 | 0 |
| Présent dans tests/e2e (SOT) | `grep -c '@playwright/test' tests/e2e/package.json` | 1 | 1 |
| Scripts racine sont des proxies | `grep -nE '"test:e2e' package.json` | 2 lignes `cd tests/e2e &&` | 2 lignes `cd tests/e2e && npx playwright test` |

Sortie réelle :

```
$ grep -nE '"test:e2e' package.json
10:    "test:e2e": "cd tests/e2e && npx playwright test",
11:    "test:e2e:chromium": "cd tests/e2e && npx playwright test --project=chromium",
```

## Rationale documenté dans README

```
$ grep -nE 'Dual-install|SOT|source of truth|check-deps\.sh' tests/e2e/README.md
3:Ce dossier contient les tests end-to-end (E2E) utilisant Playwright. La SOT
4:(single source of truth) Playwright est `tests/e2e/package.json` — voir §5
5:"Dual-install Playwright" pour le rationale.
22:| 2 | Install Playwright (SOT) | `cd tests/e2e && npm install` | Installe `@playwright/test` + `@axe-core/playwright`. Voir §5. |
107:### Dual-install Playwright (historique v2.3 — résolu v2.4 P3)
```

La section §5 "Dual-install Playwright" du README explicite :
1. Que `tests/e2e/package.json` est la SOT,
2. Pourquoi pas un seul `npm install` à la racine (3 raisons : pollution
   ESLint frontend, isolation cache CI, chemins relatifs de
   `playwright.config.js`),
3. Le rôle du garde-fou `bin/check-deps.sh` avec ses exit codes,
4. Le pattern proxy des scripts racine.

## Conclusion

Le dual-install Playwright est résolu via l'**option B** de la requirement
INFRA-V26-02 : *"le double install (`tests/e2e/`) est documenté avec
rationale claire dans `tests/e2e/README.md`"*.

L'option A (single `npm install` à la racine) a été écartée :
- Polluer la racine avec `@playwright/test` (≈ 300 Mo de browsers via
  `npx playwright install`) ralentit les tâches frontend (eslint,
  minify assets) qui n'ont besoin que de `htmx.org` + `chart.js`.
- L'isolement permet à la CI de cacher séparément `node_modules/` racine
  et `tests/e2e/node_modules/`.
- `tests/e2e/playwright.config.js` utilise des chemins relatifs
  (`testDir: './specs'`, `globalSetup: './setup/auth.setup.js'`) qui
  supposent qu'on travaille **depuis** `tests/e2e/`.

La SOT `tests/e2e/` + scripts proxy + garde-fou shell + README §5 est le
bon compromis.

## Liens

- Garde-fou : [`bin/check-deps.sh`](../../../bin/check-deps.sh)
- Documentation : [`tests/e2e/README.md`](../../../tests/e2e/README.md) §5
  "Common pitfalls" → "Dual-install Playwright (historique v2.3 — résolu
  v2.4 P3)"
- Proxy scripts racine : `package.json` lignes 10-12 (`test:e2e`,
  `test:e2e:chromium`, `check:deps`)

---
*Audited: 2026-05-05 — Plan 04-02 (v2.6 Phase 4) — INFRA-V26-02*
