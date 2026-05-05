# Tests End-to-End AgVote (Playwright)

Ce dossier contient les tests end-to-end (E2E) utilisant Playwright. **Single source of truth** Playwright pour le projet : c'est ici qu'est versionnée la dépendance `@playwright/test` (le `package.json` racine ne la contient plus depuis v2.4 Phase 3).

**Objectif fresh-clone → premier test vert : ≤ 30 minutes** (voir checklist ci-dessous).

---

## 1. Prerequisites

| Composant | Version minimale | Notes |
|---|---|---|
| Node.js | 18+ | LTS recommandé |
| PostgreSQL | 12+ | Local ou Docker (DSN dans `.env`) |
| Browsers Playwright | téléchargés via `playwright install` | Chromium suffit pour smoke ; Firefox/WebKit pour multi-navigateurs |
| PHP CLI | 8.4+ | Pour le serveur de dev `php -S localhost:8000` |

Vérification rapide :

```bash
node --version    # >= v18.0.0
php --version     # >= 8.4
psql --version    # >= 12
```

---

## 2. First-time setup

Checklist chronométrée pour passer du clone au premier test vert en moins de 30 minutes.

```bash
# 1. Cloner et entrer dans le dossier e2e (≤ 2 min)
git clone <repo-url> agvote
cd agvote/tests/e2e

# 2. Installer les dépendances Playwright (≤ 5 min)
npm install

# 3. Installer les browsers (≤ 10 min — premier download)
#    Sur machine de dev : sudo nécessaire pour --with-deps
sudo npx playwright install --with-deps chromium
# Alternative sans sudo (sans deps système, fonctionne si déjà installées) :
#   npx playwright install chromium

# 4. Configurer la base de données de test (≤ 5 min)
#    Depuis la racine du repo :
cd ../..
cp .env.example .env  # si nécessaire
# Éditer .env : DB_DSN, DB_USER, DB_PASS pointant vers une base test
php database/setup.sh
php bin/console db:seed --env=test  # seed minimal pour fixtures auth

# 5. Lancer le serveur de dev en arrière-plan (≤ 1 min)
php -S localhost:8000 -t public/ &
SERVER_PID=$!

# 6. Lancer le premier test (≤ 2 min)
cd tests/e2e
npx playwright test specs/auth.spec.js --project=chromium
```

Total cible : **≤ 25 min** sur connexion correcte, ≤ 30 min en cas de download Chromium lent.

---

## 3. Running tests

### Commandes courantes

| Commande | Description |
|---|---|
| `npx playwright test` | Tous les tests, tous les navigateurs |
| `npx playwright test --project=chromium` | Chromium uniquement (rapide) |
| `npx playwright test --project=firefox` | Firefox |
| `npx playwright test --project=webkit` | WebKit (Safari-like) |
| `npx playwright test --project="Mobile Chrome"` | Émulation mobile |
| `npx playwright test specs/<file>.spec.js` | Un seul fichier |
| `npx playwright test --grep "@integration"` | Filtrer par tag |
| `npx playwright test --ui` | Mode UI interactif (debug visuel) |
| `npx playwright test --debug` | Mode debug step-by-step (browser visible) |
| `npx playwright test --headed` | Browser visible, exécution normale |

### Depuis la racine du repo

Les scripts npm racine (`test:e2e`, `test:e2e:chromium`) sont des **proxies** vers `tests/e2e` (D-06 du contexte phase 3) :

```bash
# Depuis la racine
npm run test:e2e            # → cd tests/e2e && npx playwright test
npm run test:e2e:chromium   # → cd tests/e2e && npx playwright test --project=chromium
```

---

## 4. Auth-setup et rate-limit

Les tests utilisent des fixtures auth pour 4 rôles : `admin`, `operator`, `voter`, `assessor`.

### Cooldown rate-limit

Le rôle **assessor** est protégé par un rate-limit production (5 minutes entre 2 logins consécutifs). Sans précaution, deux runs E2E rapprochés échoueront sur le second avec une 429.

### Bypasser le rate-limit en dev

Exporter la variable d'environnement avant le run :

```bash
TEST_BYPASS_RATELIMIT=1 npx playwright test
```

Conditions :
- Backend doit avoir `APP_ENV=development|test|demo` (le bypass est ignoré en `production`)
- Côté tests, l'auth-setup respecte automatiquement la variable

### Identifiants de test

Les fixtures s'appuient sur les seeds `database/seeds/02_test_users.sql` :

| Rôle | API key | Usage |
|---|---|---|
| admin | `admin-key-2026-secret` | Tests admin/setup |
| operator | `operator-key-2026-secret` | Tests cockpit operator |
| voter | seed dynamique | Tests vote / parcours public |
| assessor | seed dynamique | Tests scrutin (cooldown 5 min) |

---

## 5. Common pitfalls

### Chromium CDN bloqué (sandbox)

Si l'environnement bloque les downloads CDN (`playwright install` retourne une 403/timeout), exécuter en local sur la machine de dev. Pas de workaround propre côté CI tant que le mirror Playwright n'est pas autorisé.

### `seedMeeting` fixtures

Les tests `@integration` (ex: `tests/e2e/specs/modal-focus-trap.spec.js` F-4) dépendent du helper `seedMeeting()` qui crée une réunion de test via l'endpoint dev `/api/test/seed-meeting`.

- Endpoint **dev-only** (404 si `APP_ENV=production`)
- Helper : [`helpers/seed-meeting.js`](helpers/seed-meeting.js) (livré par plan 03.1)
- Signature : `seedMeeting({tenantId, status, motionsCount}) → meetingId`

Si le helper n'est pas encore présent, les tests `@integration` restent skippés — ne pas tenter de les forcer.

### Cleanup des fixtures

Les meetings seed ne sont **pas auto-supprimés** après run. Pour purger après une session de tests longue :

```bash
php bin/console cleanup:test-meetings --tenant=test-tenant
```

### Dual-install Playwright (historique v2.3)

Avant v2.4 Phase 3, `@playwright/test` était déclaré à la fois dans `package.json` racine et dans `tests/e2e/package.json` — risque de divergence de version. Depuis v2.4 P3, **seul `tests/e2e/` contient la dépendance**. Le script `bin/check-deps.sh` (livré par plan 03.1) garde-fou cette règle.

---

## 6. Debug procedures

### Reporters

```bash
# Reporter détaillé en console (utile en CI)
npx playwright test --reporter=list

# Reporter HTML (ouvre un rapport navigable post-run)
npx playwright show-report
```

### Traces et screenshots

```bash
# Capture trace complète (DOM + network + console) — utile pour post-mortem
npx playwright test --trace=on

# Sur retry uniquement (compromis CI)
npx playwright test --trace=retain-on-failure
```

Visualiser une trace :

```bash
npx playwright show-trace test-results/<spec>/trace.zip
```

### Debug step-by-step

```bash
# Lance Playwright Inspector (pause / step / resume)
PWDEBUG=1 npx playwright test specs/<file>.spec.js

# Mode --debug (équivalent + breakpoints VS Code)
npx playwright test --debug
```

### Logs serveur

Le serveur PHP de dev logge dans la console qui l'a lancé. En cas de 500 inattendue côté test :

```bash
tail -f storage/logs/error.log    # logs application
```

---

## Structure du dossier

```
tests/e2e/
├── playwright.config.js    # Configuration Playwright (baseURL, projects, server)
├── helpers/                # Helpers partagés
│   ├── axeAudit.js         # Audit accessibilité axe-core
│   ├── waitForHtmxSettled.js  # Attente fin de swap HTMX
│   └── seed-meeting.js     # (livré 03.1) Création meetings de test
├── setup/                  # Fixtures auth (storageState par rôle)
├── specs/                  # Fichiers de test (.spec.js)
└── README.md               # Ce fichier
```

---

## Liens utiles

- [`helpers/seed-meeting.js`](helpers/seed-meeting.js) — helper test data (livré par plan 03.1)
- [`../../.planning/intel/EXPLORE-PATTERNS.md`](../../.planning/intel/EXPLORE-PATTERNS.md) — patterns de scan codebase sans faux-positifs
- [`../../.claude/agents/gsd-code-reviewer.md`](../../.claude/agents/gsd-code-reviewer.md) — agent code-review (`--scope`, `--timeout-min`)
- [`../../.planning/phases/03-test-infrastructure/03-CONTEXT.md`](../../.planning/phases/03-test-infrastructure/03-CONTEXT.md) — décisions phase test infra v2.4

## CI/CD

Pour GitHub Actions (référence) :

```yaml
- name: Install Playwright (e2e SOT)
  working-directory: tests/e2e
  run: |
    npm ci
    npx playwright install --with-deps chromium

- name: Run E2E tests
  working-directory: tests/e2e
  run: npx playwright test --project=chromium
  env:
    BASE_URL: http://localhost:8000
    TEST_BYPASS_RATELIMIT: 1
    APP_ENV: test
```
