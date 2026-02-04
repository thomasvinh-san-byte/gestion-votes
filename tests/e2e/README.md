# Tests E2E — AG-VOTE (Playwright)

Ce dossier contient les tests end-to-end (E2E) utilisant Playwright.

## Prérequis

```bash
# Installer Playwright (une seule fois)
npm init -y
npm install -D @playwright/test
npx playwright install
```

## Exécution des tests

```bash
# Tous les tests
npx playwright test

# Un seul fichier
npx playwright test specs/auth.spec.js

# Mode interactif (UI)
npx playwright test --ui

# Avec le navigateur visible
npx playwright test --headed

# Un seul navigateur
npx playwright test --project=chromium
```

## Structure des tests

```
tests/e2e/
├── playwright.config.js    # Configuration Playwright
├── specs/                  # Fichiers de test
│   ├── auth.spec.js       # Authentification
│   ├── meetings.spec.js   # Gestion des séances
│   └── accessibility.spec.js  # Tests d'accessibilité
└── README.md
```

## Configuration

Le fichier `playwright.config.js` définit :
- URL de base : `http://localhost:8000`
- Navigateurs testés : Chrome, Firefox, Safari, Mobile
- Serveur de dev : Lance automatiquement `php -S localhost:8000`

## Identifiants de test

Les tests utilisent les clés API définies dans `database/seeds/02_test_users.sql` :
- `operator-key-2026-secret` (opérateur)
- `admin-key-2026-secret` (admin)

## Rapport

Après exécution, un rapport HTML est généré :

```bash
npx playwright show-report
```

## CI/CD

Pour GitHub Actions :

```yaml
- name: Install Playwright
  run: npx playwright install --with-deps

- name: Run E2E tests
  run: npx playwright test
  env:
    BASE_URL: http://localhost:8000
```
