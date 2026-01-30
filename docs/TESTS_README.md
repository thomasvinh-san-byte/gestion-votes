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

### Génération automatique

```bash
# Afficher les clés
php scripts/generate_api_keys.php

# Générer le SQL d'insertion
php scripts/generate_api_keys.php --sql > database/seeds/users.sql

# Générer les variables .env
php scripts/generate_api_keys.php --env >> .env
```

### Clés pré-générées (développement)

| Rôle | API Key | Email |
|------|---------|-------|
| **admin** | `a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2` | admin@ag-vote.local |
| **operator** | `op1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1` | operator@ag-vote.local |
| **president** | `pr1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1` | president@ag-vote.local |
| **auditor** | `tr1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1` | auditor@ag-vote.local |
| **viewer** | `ro1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1` | viewer@ag-vote.local |

⚠️ **Ces clés ne fonctionnent qu'avec:**
```
APP_SECRET=dev-secret-change-me-in-production-32ch
```

### Insertion en base

```bash
# Appliquer le seed
psql -U ca_app -d vote_app -f database/seeds/test_users.sql
```

### Test avec cURL

```bash
# Sans auth (devrait échouer si APP_AUTH_ENABLED=1)
curl http://localhost:8080/api/v1/meetings.php

# Avec auth admin
curl -H "X-Api-Key: a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2" \
     http://localhost:8080/api/v1/meetings.php

# Avec auth operator
curl -H "X-Api-Key: op1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1" \
     http://localhost:8080/api/v1/meetings.php
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
