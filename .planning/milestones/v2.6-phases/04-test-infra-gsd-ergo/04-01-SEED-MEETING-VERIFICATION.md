# INFRA-V26-01 — Vérification stabilité test @integration F-4

**Date** : 2026-05-05
**Branche / commit** : main @ `7bf5345`
**Stack** : sandbox d'exécution (PAS de stack docker dev disponible — voir section "Limitation runtime" plus bas)
**Spec** : `tests/e2e/specs/cockpit-keyboard-shortcuts.spec.js:145`
**Filtre** : `--grep "@integration L on real operator view"`
**Helper** : `tests/e2e/helpers/seed-meeting.js` (livré v2.4 plan 03.1, tag `v2.4`)
**Endpoint** : `POST /api/v1/test/seed-meeting` (`AgVote\Controller\TestSeedController::seedMeeting`, dev-only via `guardProduction()` + `EnvGuardMiddleware`)

---

## Audit statique (Task 1) — état réel du working tree

Toutes les pré-conditions du plan 04-01 ont été **vérifiées présentes** dans la working copy main `7bf5345`. Aucune fabrication.

| Vérification | Commande | Résultat |
|---|---|---|
| Spec contient le test `@integration L` | `grep -n '@integration' tests/e2e/specs/cockpit-keyboard-shortcuts.spec.js` | OK — ligne 145 : `test('@integration L on real operator view reaches the production toggle button', …)` |
| Helper `seedRunningMeeting` exporté | `grep -E 'module\.exports.*seedRunningMeeting' tests/e2e/helpers/seed-meeting.js` | OK — `module.exports = { seedMeeting, seedRunningMeeting, DEFAULT_TENANT_ID };` (ligne 80) |
| Route `/api/v1/test/seed-meeting` wirée | `grep -nE 'test/seed-meeting' app/routes.php` | OK — `app/routes.php:174` → `TestSeedController::class` (ligne 175) |
| Méthode `seedMeeting()` présente | `grep -n 'public function seedMeeting' app/Controller/TestSeedController.php` | OK — ligne 40 |
| Syntaxe PHP du controller | `php -l app/Controller/TestSeedController.php` | `No syntax errors detected in app/Controller/TestSeedController.php` |
| Pas d'autre `@integration` skippé inattendu | `grep -rn '@integration\|test\.skip\|describe\.skip\|test\.fixme' tests/e2e/specs/` | OK — uniquement `cockpit-keyboard-shortcuts.spec.js:145` (conforme au plan) |

**Verdict audit** : tous les artefacts attendus par v2.4 P3 sont **présents et cohérents** dans le working tree main. Le test n'est pas skippé : il s'exécutera dès qu'une stack dev est disponible.

---

## Limitation runtime — Task 2 (gate 3 runs verts) NON exécutée dans cette session

L'environnement d'exécution courant **ne dispose pas** de la stack dev nécessaire pour exécuter Playwright :

| Composant requis | État détecté |
|---|---|
| Serveur applicatif HTTP (port 8080) | `curl http://localhost:8080 → connection refused` |
| Serveur applicatif HTTP (port 80) | `curl http://localhost → connection refused` |
| Daemon Docker | `dial unix /var/run/docker.sock: no such file or directory` |
| Playwright installé | `tests/e2e/node_modules/.bin/playwright` introuvable (npm install non exécuté) |
| Browsers Playwright | non vérifié (bloqué par étape précédente) |

La gate `checkpoint:human-verify` Task 2 du plan 04-01 doit donc être exécutée **manuellement** par un opérateur ayant accès à la stack dev (docker compose up, ou setup local décrit dans `tests/e2e/README.md` §2).

### Procédure exacte à exécuter (copier-coller, telle quelle)

```bash
# Pré-requis : stack dev démarrée (docker compose up -d, ou php -S localhost:8080 + postgres + redis)
# et Playwright installé : cd tests/e2e && npm ci && npx playwright install chromium

cd tests/e2e

# Run #1
TEST_BYPASS_RATELIMIT=1 timeout 120 npx playwright test \
  specs/cockpit-keyboard-shortcuts.spec.js \
  --grep "@integration L on real operator view" \
  --project=chromium --reporter=line 2>&1 | tee /tmp/run1.log
echo "RUN1_EXIT=$?"

# Run #2
TEST_BYPASS_RATELIMIT=1 timeout 120 npx playwright test \
  specs/cockpit-keyboard-shortcuts.spec.js \
  --grep "@integration L on real operator view" \
  --project=chromium --reporter=line 2>&1 | tee /tmp/run2.log
echo "RUN2_EXIT=$?"

# Run #3
TEST_BYPASS_RATELIMIT=1 timeout 120 npx playwright test \
  specs/cockpit-keyboard-shortcuts.spec.js \
  --grep "@integration L on real operator view" \
  --project=chromium --reporter=line 2>&1 | tee /tmp/run3.log
echo "RUN3_EXIT=$?"
```

### Critères d'acceptation (du plan)

1. `RUN1_EXIT=0`, `RUN2_EXIT=0`, `RUN3_EXIT=0`
2. Chaque log contient `1 passed`
3. Aucun `(retry #N)` dans les logs (pas de flake masqué)
4. Durée par run ≤ 60 s (budget CLAUDE.md)

### Runs (à compléter par l'opérateur après exécution)

| # | Commande | Exit code | Résultat | Durée |
|---|---|---|---|---|
| 1 | `npx playwright test … --grep "@integration L on real operator view" --project=chromium` | _PENDING_ | _PENDING_ | _PENDING_ |
| 2 | idem | _PENDING_ | _PENDING_ | _PENDING_ |
| 3 | idem | _PENDING_ | _PENDING_ | _PENDING_ |

### Logs (extraits — à coller après exécution)

#### Run 1
```
{coller la sortie line reporter — chemin du test + 1 passed (Xs)}
```

#### Run 2
```
{idem}
```

#### Run 3
```
{idem}
```

---

## Verdict actuel

**Audit statique : VERT.** Tous les artefacts de v2.4 P3 sont présents et cohérents dans le working tree. La spec n'est pas skippée, le helper exporte la signature correcte, l'endpoint est wiré et le controller compile.

**Gate runtime 3 runs verts : NON EXÉCUTÉE** dans cette session — environnement sandbox sans stack dev (pas de docker, pas de serveur applicatif, Playwright non installé). La procédure exacte est ci-dessus pour exécution par un opérateur sur dev-machine.

**Recommandation INFRA-V26-01** :
- Status proposé : **Blocked on runtime verification** (pas Complete tant que les 3 runs verts ne sont pas archivés).
- Ce document peut être finalisé en place : l'opérateur qui exécute la gate complète les 3 lignes du tableau Runs et les 3 blocs Logs, puis re-commit.
- Aucune modification de code n'est nécessaire — le code source est en l'état attendu par v2.4 P3.

---

## Liens

- Helper : [`tests/e2e/helpers/seed-meeting.js`](../../../tests/e2e/helpers/seed-meeting.js)
- Spec : [`tests/e2e/specs/cockpit-keyboard-shortcuts.spec.js:145`](../../../tests/e2e/specs/cockpit-keyboard-shortcuts.spec.js)
- Controller dev-only : [`app/Controller/TestSeedController.php`](../../../app/Controller/TestSeedController.php)
- Route : [`app/routes.php:174`](../../../app/routes.php)
- ROADMAP : v2.6 Phase 4 INFRA-V26-01

---
*Verified statically: 2026-05-05 — Plan 04-01 (v2.6 Phase 4). Runtime verification pending dev-machine execution.*
