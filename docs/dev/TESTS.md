# Tests — AG-VOTE

Ce document consolide la documentation des tests unitaires et end-to-end.

---

## 1. Tests Unitaires (PHPUnit)

### Installation

```bash
composer require --dev phpunit/phpunit ^10.0
```

### Exécution

```bash
# Tous les tests
./vendor/bin/phpunit

# Avec couverture HTML
./vendor/bin/phpunit --coverage-html coverage-report

# Un fichier spécifique
./vendor/bin/phpunit tests/Unit/CsrfMiddlewareTest.php

# Un test spécifique
./vendor/bin/phpunit --filter testGetTokenGeneratesToken
```

### Structure

```
tests/
├── bootstrap.php               # Configuration des tests
├── Unit/
│   ├── CsrfMiddlewareTest.php  # 15 tests CSRF
│   ├── AuthMiddlewareTest.php  # 18 tests Auth/RBAC
│   ├── RateLimiterTest.php     # 12 tests Rate Limiting
│   └── InputValidatorTest.php  # 35 tests Validation
└── Integration/
    └── (à venir)
```

### API Keys de test

| Rôle | API Key | Email |
|------|---------|-------|
| admin | `admin-key-2026-secret` | admin@ag-vote.local |
| operator | `operator-key-2026-secret` | operator@ag-vote.local |
| auditor | `auditor-key-2026-secret` | auditor@ag-vote.local |
| viewer | `viewer-key-2026-secret` | viewer@ag-vote.local |

Ces clés fonctionnent avec :
```
APP_SECRET=dev-secret-do-not-use-in-production-change-me-now-please-64chr
```

### Test avec cURL

```bash
# Login
curl -s http://localhost:8080/api/v1/auth_login.php \
  -H "Content-Type: application/json" \
  -d '{"api_key":"admin-key-2026-secret"}'

# Auth par header
curl -s http://localhost:8080/api/v1/meetings_index.php \
  -H "X-Api-Key: operator-key-2026-secret"
```

---

## 2. Tests End-to-End (Playwright)

### Installation

```bash
cd tests/e2e
npm init -y
npm install -D @playwright/test
npx playwright install
```

### Exécution

```bash
# Tous les tests
npx playwright test

# Un seul fichier
npx playwright test specs/auth.spec.js

# Mode interactif (UI)
npx playwright test --ui

# Avec navigateur visible
npx playwright test --headed

# Un seul navigateur
npx playwright test --project=chromium
```

### Structure

```
tests/e2e/
├── playwright.config.js   # Configuration
├── specs/
│   ├── auth.spec.js       # Authentification
│   ├── meetings.spec.js   # Gestion des séances
│   └── accessibility.spec.js  # Accessibilité
└── README.md
```

### Configuration

Le fichier `playwright.config.js` définit :
- URL de base : `http://localhost:8000`
- Navigateurs : Chrome, Firefox, Safari, Mobile
- Serveur de dev : Lance automatiquement `php -S localhost:8000`

### Rapport

```bash
npx playwright show-report
```

---

## 3. Parcours de Test E2E Complet

### Prérequis

```bash
# Initialiser la base avec les seeds de test
sudo bash database/setup.sh --seed

# Lancer le serveur
php -S 0.0.0.0:8000 -t public

# Vérifier le .env
APP_ENV=dev
APP_AUTH_ENABLED=1
CSRF_ENABLED=0
RATE_LIMIT_ENABLED=0
```

### Comptes de test

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Admin | admin@ag-vote.local | Admin2026! |
| Opérateur | operator@ag-vote.local | Operator2026! |
| Président | president@ag-vote.local | President2026! |
| Votant | votant@ag-vote.local | Votant2026! |

### Flux de test (résumé)

```
DRAFT ──→ SCHEDULED ──→ FROZEN ──→ LIVE ──→ CLOSED ──→ VALIDATED ──→ ARCHIVED
```

| Phase | Acteur | Action |
|-------|--------|--------|
| 1. Préparation | Opérateur | Vérifier données, DRAFT → SCHEDULED |
| 2. Ouverture | Président | SCHEDULED → FROZEN → LIVE |
| 3. Présences | Opérateur | Enregistrer les présences |
| 4. Votes | Opérateur + Votant | Ouvrir, voter, clôturer les résolutions |
| 5. Contrôle | Assesseur | Vérifier anomalies et cohérence |
| 6. Clôture | Président | LIVE → CLOSED → VALIDATED |
| 7. Archivage | Admin | VALIDATED → ARCHIVED, PV, exports |

### Réinitialiser le scénario

```bash
sudo bash database/setup.sh --seed
```

---

## 4. CI/CD

### GitHub Actions

```yaml
- name: Install PHPUnit
  run: composer install

- name: Run Unit Tests
  run: ./vendor/bin/phpunit

- name: Install Playwright
  run: npx playwright install --with-deps

- name: Run E2E tests
  run: npx playwright test
  env:
    BASE_URL: http://localhost:8000
```

---

## 5. Couverture de code

### Objectif

80%+ de couverture sur les composants de sécurité.

### Génération

```bash
./vendor/bin/phpunit --coverage-html coverage-report
```

Ouvrir `coverage-report/index.html` dans un navigateur.

---

## Références

- `database/seeds/02_test_users.sql` — Comptes de test
- `database/seeds/04_e2e.sql` — Données du scénario E2E
- `tests/e2e/playwright.config.js` — Configuration Playwright
