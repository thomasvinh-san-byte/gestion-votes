# 🔐 AG-VOTE Security Package

## Tests Unitaires

### Prérequis

```bash
# Installer PHPUnit via Composer
composer require --dev phpunit/phpunit ^10.0
```

### Exécution des tests

```bash
# Tous les tests
./vendor/bin/phpunit

# Tests avec couverture
./vendor/bin/phpunit --coverage-html coverage-report

# Un fichier spécifique
./vendor/bin/phpunit tests/Unit/CsrfMiddlewareTest.php

# Un test spécifique
./vendor/bin/phpunit --filter testGetTokenGeneratesToken
```

### Structure des tests

```
tests/
├── bootstrap.php           # Configuration des tests
├── Unit/
│   ├── CsrfMiddlewareTest.php      # 15 tests CSRF
│   ├── AuthMiddlewareTest.php      # 18 tests Auth/RBAC
│   ├── RateLimiterTest.php         # 12 tests Rate Limiting
│   └── InputValidatorTest.php      # 35 tests Validation
└── Integration/
    └── (à venir)
```

---

## API Keys de Test

### Cles pre-generees (developpement)

| Role | API Key | Email |
|------|---------|-------|
| **admin** | `admin-key-2024-secret` | admin@ag-vote.local |
| **operator** | `operator-key-2024-secret` | operator@ag-vote.local |
| **auditor** | `auditor-key-2024-secret` | auditor@ag-vote.local |
| **viewer** | `viewer-key-2024-secret` | viewer@ag-vote.local |

Hash = HMAC-SHA256(api_key, APP_SECRET). Ces cles ne fonctionnent qu'avec :

```
APP_SECRET=dev-secret-do-not-use-in-production-change-me-now-please-64chr
```

### Insertion en base

```bash
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost \
  -f database/seeds/02_test_users.sql
```

### Test avec cURL

```bash
# Login (cree une session PHP)
curl -s http://localhost:8080/api/v1/auth_login.php \
  -H "Content-Type: application/json" \
  -d '{"api_key":"admin-key-2024-secret"}'

# Auth par header X-Api-Key
curl -s http://localhost:8080/api/v1/meetings_index.php \
  -H "X-Api-Key: operator-key-2024-secret"

# Sans auth (doit echouer si APP_AUTH_ENABLED=1)
curl -s http://localhost:8080/api/v1/meetings_index.php
```

---

## Patch HTMX pour CSRF

### Automatique

```bash
# Prévisualisation (dry-run)
php scripts/patch_htmx_csrf.php --dry-run

# Appliquer les patches
php scripts/patch_htmx_csrf.php
```

### Manuel

Ajouter dans le `<head>` de chaque page HTMX :

```php
<?php require_once __DIR__ . '/../app/Core/Security/CsrfMiddleware.php'; ?>
<?= CsrfMiddleware::metaTag() ?>
```

Et avant le premier `<script src=` :

```php
<?= CsrfMiddleware::jsSnippet() ?>
```

---

## Configuration Production

```env
# Générer un vrai secret
APP_SECRET=$(php -r "echo bin2hex(random_bytes(32));")

# Activer toutes les protections
APP_AUTH_ENABLED=1
CSRF_ENABLED=1
APP_DEBUG=0
APP_ENV=production

# HTTPS obligatoire
SESSION_SECURE=true
```

---

## Couverture de tests

Après exécution avec `--coverage-html`, ouvrir `coverage-report/index.html` dans un navigateur.

Objectif : **80%+ de couverture** sur les composants de sécurité.
