# 🔄 GUIDE DE MIGRATION SÉCURITÉ AG-VOTE

## Vue d'ensemble

Ce guide explique comment intégrer les composants de sécurité dans le projet AG-Vote existant.

---

## Fichiers à copier

### 1. Composants de sécurité (nouveaux)

```bash
# Créer les dossiers
mkdir -p app/Core/Security
mkdir -p app/Core/Validation

# Copier les composants
cp app/Core/Security/CsrfMiddleware.php    → app/Core/Security/
cp app/Core/Security/AuthMiddleware.php    → app/Core/Security/
cp app/Core/Security/RateLimiter.php       → app/Core/Security/
cp app/Core/Validation/InputValidator.php  → app/Core/Validation/
```

### 2. Fichiers à remplacer

```bash
# Sauvegarder les originaux
mv app/bootstrap.php app/bootstrap.php.bak
mv app/api.php app/api.php.bak
mv public/assets/js/utils.js public/assets/js/utils.js.bak

# Copier les nouveaux
cp app/bootstrap.php    → app/bootstrap.php
cp app/api.php          → app/api.php
cp public/assets/js/utils.js → public/assets/js/utils.js
```

### 3. Fichiers à ajouter

```bash
cp public/assets/js/csrf-helper.js → public/assets/js/
cp public/api/v1/members_import_csv.php → public/api/v1/
```

---

## Configuration .env

Ajouter ces variables à votre `.env` :

```env
# CRITIQUE - Générer avec: php -r "echo bin2hex(random_bytes(32));"
APP_SECRET=votre_secret_64_caracteres_ici

# Activer l'authentification (désactiver uniquement en dev)
APP_AUTH_ENABLED=1

# Activer CSRF (recommandé)
CSRF_ENABLED=1
```

---

## Mise à jour des pages HTML/HTMX

### Ajouter le support CSRF

Dans chaque fichier `.htmx.html`, ajouter dans le `<head>` :

```php
<?php
require_once __DIR__ . '/../app/Core/Security/CsrfMiddleware.php';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <?= CsrfMiddleware::metaTag() ?>
    <!-- ... autres meta ... -->
    <script src="/assets/js/utils.js"></script>
</head>
<body>
    <?= CsrfMiddleware::jsSnippet() ?>
    <!-- ... contenu ... -->
</body>
</html>
```

### Formulaires HTML natifs

Ajouter le champ CSRF :

```php
<form method="POST" action="/api/v1/exemple.php">
    <?= CsrfMiddleware::field() ?>
    <!-- ... champs ... -->
    <button type="submit">Envoyer</button>
</form>
```

---

## Mise à jour des endpoints API

### Avant (ancien code)

```php
<?php
require __DIR__ . '/../../../app/api.php';

api_require_role('operator');  // Ne valide pas CSRF, auth bypassée
$input = api_request('POST');
// ...
```

### Après (nouveau code)

```php
<?php
require __DIR__ . '/../../../app/api.php';

api_require_role('operator');  // Valide CSRF + Auth automatiquement
$input = api_request('POST');
// ...
```

**Note** : Aucun changement requis dans le code des endpoints ! Le nouveau `api_require_role()` gère automatiquement :
- Validation CSRF pour POST/PUT/PATCH/DELETE
- Authentification RBAC

---

## Liste des endpoints à vérifier

Ces endpoints utilisent déjà `api_require_role()` et fonctionneront automatiquement :

| Endpoint | Rôle | Action |
|----------|------|--------|
| `meeting_validate.php` | president | ✅ Auto |
| `motions_open.php` | operator | ✅ Auto |
| `motions.php` | operator | ✅ Auto |
| `motion_delete.php` | operator | ✅ Auto |
| `vote_tokens_generate.php` | operator | ✅ Auto |
| `ballots_cast.php` | public | ✅ Auto (pas de CSRF) |
| `invitations_create.php` | operator | ✅ Auto |
| `attendance_present_from.php` | operator | ✅ Auto |
| `meeting_quorum_settings.php` | — | Ajouter rôle |
| `meeting_vote_settings.php` | — | Ajouter rôle |

---

## Test de la migration

### 1. Vérifier CSRF

```bash
# Sans token CSRF → doit retourner 403
curl -X POST http://localhost:8080/api/v1/motions.php \
  -H "Content-Type: application/json" \
  -d '{"title":"Test"}'

# Réponse attendue:
# {"ok":false,"error":"csrf_token_missing"}
```

### 2. Vérifier Auth

```bash
# Sans API Key → doit retourner 401
curl http://localhost:8080/api/v1/meetings.php

# Réponse attendue (si APP_AUTH_ENABLED=1):
# {"ok":false,"error":"authentication_required"}
```

### 3. Vérifier Rate Limiting

```bash
# 10+ imports rapides → doit retourner 429
for i in {1..15}; do
  curl -X POST http://localhost:8080/api/v1/members_import_csv.php
done

# Réponse attendue après la limite:
# {"ok":false,"error":"rate_limit_exceeded","retry_after":...}
```

---

## Rollback en cas de problème

```bash
# Restaurer les fichiers originaux
mv app/bootstrap.php.bak app/bootstrap.php
mv app/api.php.bak app/api.php
mv public/assets/js/utils.js.bak public/assets/js/utils.js

# Supprimer les nouveaux composants
rm -rf app/Core/
```

---

## Désactiver temporairement la sécurité (DEV)

```env
# Dans .env
APP_AUTH_ENABLED=0
CSRF_ENABLED=0
```

⚠️ **NE JAMAIS déployer en production avec ces paramètres !**

---

## Support

En cas de problème :
1. Vérifier les logs : `error_log()` enregistre les échecs auth/CSRF
2. Activer le debug : `APP_DEBUG=1`
3. Vérifier la configuration .env
