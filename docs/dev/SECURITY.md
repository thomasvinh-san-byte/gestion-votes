# Sécurité — AG-VOTE

## Table des matières

1. [Architecture de sécurité](#architecture-de-sécurité)
2. [Authentification et autorisation](#authentification-et-autorisation)
3. [Protection CSRF](#protection-csrf)
4. [Validation des entrées](#validation-des-entrées)
5. [Headers de sécurité](#headers-de-sécurité)
6. [Rate Limiting](#rate-limiting)
7. [Audit et traçabilité](#audit-et-traçabilité)
8. [Configuration sécurisée](#configuration-sécurisée)
9. [Checklist déploiement](#checklist-déploiement)

---

## Architecture de sécurité

### Couches de défense

```
┌─────────────────────────────────────────────────────────────┐
│                     NAVIGATEUR / CLIENT                     │
├─────────────────────────────────────────────────────────────┤
│  1. Security Headers   │ CSP, HSTS, X-Frame-Options        │
├────────────────────────┼────────────────────────────────────┤
│  2. RateLimiter        │ Anti-brute-force, DDoS mitigation │
├────────────────────────┼────────────────────────────────────┤
│  3. CsrfMiddleware     │ Token synchronisé, double-submit  │
├────────────────────────┼────────────────────────────────────┤
│  4. AuthMiddleware     │ API Key HMAC, RBAC strict         │
├────────────────────────┼────────────────────────────────────┤
│  5. InputValidator     │ Schémas de validation stricts     │
├────────────────────────┼────────────────────────────────────┤
│  6. PDO Prepared       │ Protection injection SQL          │
├────────────────────────┼────────────────────────────────────┤
│  7. Audit Logging      │ Traçabilité append-only           │
└─────────────────────────────────────────────────────────────┘
```

### Fichiers clés

| Fichier | Responsabilité |
|---------|----------------|
| `app/Core/Security/CsrfMiddleware.php` | Protection CSRF |
| `app/Core/Security/AuthMiddleware.php` | Authentification RBAC |
| `app/bootstrap.php` (lignes 148-160) | Headers HTTP sécurisés |
| `app/Core/Security/RateLimiter.php` | Limitation de débit |
| `app/Core/Validation/InputValidator.php` | Validation entrées |

---

## Authentification et autorisation

### Méthode d'authentification

**API Key HMAC-SHA256** - Stockage sécurisé du hash, jamais de la clé brute.

```php
// Génération d'une API Key
$apiKey = AuthMiddleware::generateApiKey();
// $apiKey['key']  → à donner à l'utilisateur (une seule fois)
// $apiKey['hash'] → à stocker en base
```

### Hiérarchie des rôles

| Rôle (système) | Niveau | Permissions |
|------|--------|-------------|
| `admin` | 100 | Accès complet |
| `operator` | 80 | Gestion séances, membres, votes |
| `auditor` | 60 | Contrôle, audit |
| `viewer` | 20 | Lecture seule |

> **Note :** Les rôles de séance (`president`, `assessor`, `voter`) sont attribués par réunion et ne font pas partie de la hiérarchie système.

### Usage dans les endpoints

```php
// Exige un rôle spécifique
api_require_role('operator');

// Exige un parmi plusieurs rôles
api_require_role(['operator', 'admin']);

// Vérifie une permission
if (AuthMiddleware::can('meeting:write')) {
    // ...
}
```

### Variables d'environnement

```env
APP_AUTH_ENABLED=1    # OBLIGATOIRE en production
APP_SECRET=...        # Secret 64+ caractères, unique par instance
```

---

## Protection CSRF

### Implémentation

- **Pattern** : Synchronizer Token Pattern
- **Stockage** : Session PHP (HttpOnly, Secure, SameSite=Strict)
- **Durée** : 1 heure (renouvelable)

### Intégration frontend

```html
<!-- Dans le <head> de chaque page -->
<?= CsrfMiddleware::metaTag() ?>
<?= CsrfMiddleware::jsSnippet() ?>

<!-- Dans les formulaires -->
<form method="POST">
    <?= CsrfMiddleware::field() ?>
    ...
</form>
```

### Intégration HTMX

Le snippet JS configure automatiquement HTMX :
```javascript
// Ajouté automatiquement par CsrfMiddleware::jsSnippet()
document.body.addEventListener('htmx:configRequest', function(e) {
    e.detail.headers['X-CSRF-Token'] = csrfToken;
});
```

### Intégration API (fetch/axios)

```javascript
// Utiliser le wrapper sécurisé
await window.secureFetch('/api/v1/meetings.php', {
    method: 'POST',
    body: JSON.stringify(data)
});

// Ou manuellement
fetch('/api/v1/meetings.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': window.CSRF.token
    },
    body: JSON.stringify(data)
});
```

---

## Validation des entrées

### Schémas de validation

```php
use App\Core\Validation\Schemas\ValidationSchemas;

// Valide les données d'une séance
$result = ValidationSchemas::meeting()->validate($input);

if (!$result->isValid()) {
    api_fail('validation_failed', 422, ['errors' => $result->errors()]);
}

$cleanData = $result->data();
```

### Schémas disponibles

- `ValidationSchemas::meeting()` - Séances
- `ValidationSchemas::motion()` - Résolutions
- `ValidationSchemas::member()` - Membres
- `ValidationSchemas::ballot()` - Votes
- `ValidationSchemas::attendance()` - Présences
- `ValidationSchemas::proxy()` - Procurations
- `ValidationSchemas::quorumPolicy()` - Politiques quorum
- `ValidationSchemas::votePolicy()` - Politiques vote

### Validation personnalisée

```php
$schema = InputValidator::schema()
    ->string('title')->minLength(3)->maxLength(255)->required()
    ->email('email')->optional()
    ->integer('count')->min(1)->max(1000)->default(10)
    ->enum('status', ['draft', 'active', 'closed']);

$result = $schema->validate($input);
```

### Protection XSS

- **Par défaut** : `htmlspecialchars()` automatique sur les strings
- **Raw** : `->raw()` pour désactiver (contenu riche contrôlé)

---

## Headers de sécurité

### Headers envoyés

| Header | Valeur | Protection |
|--------|--------|------------|
| `Content-Security-Policy` | Script/style self + CDN | XSS |
| `Strict-Transport-Security` | max-age=1an | Downgrade HTTPS |
| `X-Frame-Options` | SAMEORIGIN | Clickjacking |
| `X-Content-Type-Options` | nosniff | MIME sniffing |
| `X-XSS-Protection` | 1; mode=block | XSS legacy |
| `Referrer-Policy` | strict-origin-when-cross-origin | Fuite info |
| `Permissions-Policy` | geolocation=(), etc. | Accès API |

### CSP détaillée

```
default-src 'self';
script-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.tailwindcss.com;
style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com;
img-src 'self' data: blob:;
connect-src 'self';
frame-ancestors 'self';
form-action 'self';
base-uri 'self';
object-src 'none';
```

---

## Rate Limiting

### Limites par défaut

| Contexte | Limite | Fenêtre |
|----------|--------|---------|
| `login` | 5 | 60s |
| `api` | 100 | 60s |
| `csv_import` | 10 | 3600s |
| `vote` | 20 | 60s |

### Usage

```php
// Vérifie et incrémente
RateLimiter::check('api', $userId, 100, 60);

// Vérifie sans incrémenter
if (RateLimiter::isLimited('login', $ip, 5, 60)) {
    // Trop de tentatives
}

// Headers dans la réponse
$headers = RateLimiter::getHeaders('api', $userId, 100, 60);
```

### Réponse 429

```json
{
    "ok": false,
    "error": "rate_limit_exceeded",
    "detail": "Too many requests. Please try again later.",
    "retry_after": 45
}
```

---

## Audit et traçabilité

### Événements audités

- Connexions (succès/échecs)
- Actions CRUD sur entités
- Validations de séance
- Exports de données
- Erreurs de sécurité

### Table audit_events

```sql
CREATE TABLE audit_events (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL,
    meeting_id UUID,
    actor_user_id UUID,
    actor_role TEXT,
    action TEXT NOT NULL,
    resource_type TEXT NOT NULL,
    resource_id UUID,
    payload JSONB,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

### Usage

```php
audit_log(
    'meeting_validated',        // action
    'meeting',                  // resource_type
    $meetingId,                 // resource_id
    ['president' => $name],     // payload
    $meetingId                  // meeting_id context
);
```

### Export CSV

```
GET /api/v1/audit_export.php?meeting_id={uuid}
```

---

## Configuration sécurisée

### Variables critiques

| Variable | Description | Obligatoire |
|----------|-------------|-------------|
| `APP_SECRET` | Secret HMAC 64+ chars | ✅ Prod |
| `APP_AUTH_ENABLED` | Activer auth | ✅ Prod |
| `DATABASE_URL` | Connexion PostgreSQL | ✅ |
| `DB_PASSWORD` | Mot de passe DB | ✅ |

### Génération APP_SECRET

```bash
# Linux/Mac
openssl rand -hex 32

# PHP
php -r "echo bin2hex(random_bytes(32));"
```

### Permissions fichiers

```bash
chmod 640 .env
chmod 750 app/
chmod -R 755 public/
```

---

## Checklist déploiement

### Avant mise en production

- [ ] `APP_SECRET` unique et aléatoire (64+ chars)
- [ ] `APP_AUTH_ENABLED=1`
- [ ] `APP_DEBUG=0`
- [ ] `APP_ENV=production`
- [ ] HTTPS configuré et forcé
- [ ] `.env` non accessible publiquement
- [ ] Mot de passe DB fort et unique
- [ ] Logs configurés et rotatifs
- [ ] Sauvegardes automatiques
- [ ] Firewall configuré

### Tests de sécurité

```bash
# Test CSRF
curl -X POST https://vote.example.com/api/v1/meetings.php \
    -H "Content-Type: application/json" \
    -d '{"title":"Test"}'
# Doit retourner 403 csrf_token_missing

# Test Auth
curl https://vote.example.com/api/v1/meetings.php
# Doit retourner 401 authentication_required

# Test Rate Limit
for i in {1..10}; do curl -s https://vote.example.com/api/v1/login.php -X POST; done
# Doit retourner 429 après 5 tentatives
```

### Surveillance

- Monitorer les logs `AUTH_FAILURE`
- Alertes sur erreurs 5xx
- Monitoring latence API
- Surveillance espace disque

---

## Signalement de vulnérabilités

Pour signaler une vulnérabilité de sécurité :

1. **Ne pas** créer d'issue publique
2. Contacter : security@example.com
3. Inclure : description, étapes de reproduction, impact potentiel

Réponse sous 48h ouvrées.
