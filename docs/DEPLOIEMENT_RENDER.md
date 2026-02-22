# Deploiement sur Render — AG-VOTE

Guide complet pour deployer AG-VOTE sur [Render](https://render.com), en mode **demo** ou **production**.

---

## Table des matieres

1. [Deux modes de deploiement](#deux-modes-de-deploiement)
2. [Deploiement demo (one-click)](#deploiement-demo-one-click)
3. [Deploiement production](#deploiement-production)
4. [Variables d'environnement obligatoires](#variables-denvironnement-obligatoires)
5. [Generer APP_SECRET](#generer-app_secret)
6. [Configurer les variables dans le dashboard Render](#configurer-les-variables-dans-le-dashboard-render)
7. [Diagnostiquer un echec de build](#diagnostiquer-un-echec-de-build)
8. [Verification post-deploiement](#verification-post-deploiement)
9. [Troubleshooting](#troubleshooting)

---

## Deux modes de deploiement

| | Demo | Production |
|---|------|------------|
| **Blueprint** | `render.yaml` | `render-production.yaml` |
| **Auth** | Desactivee | Activee |
| **CSRF** | Desactive | Active |
| **Rate limiting** | Desactive | Active |
| **Donnees de demo** | Chargees | Interdites |
| **APP_SECRET** | Pas requis | **Obligatoire** |
| **Usage** | Tests, presentations | Deploiement reel |

---

## Deploiement demo (one-click)

Le bouton "Deploy to Render" du README utilise `render.yaml` et cree automatiquement :
- Un service web Docker (PHP/Nginx)
- Une base PostgreSQL
- Les variables d'environnement pre-configurees (auth et CSRF desactives)

Aucune configuration supplementaire requise. Les donnees de test sont chargees automatiquement.

> Compte de test : `admin@ag-vote.local` / `Admin2026!`

---

## Deploiement production

### Etape 1 — Creer les services

Dans le dashboard Render :
1. **New** > **Blueprint**
2. Connecter le depot GitHub `thomasvinh-san-byte/gestion-votes`
3. Selectionner le fichier **`render-production.yaml`** comme blueprint
4. Render cree automatiquement le service web + la base PostgreSQL

### Etape 2 — Configurer les variables obligatoires

Apres la creation, aller dans **Dashboard** > **ag-vote** > **Environment** et ajouter manuellement :

| Variable | Valeur | Pourquoi |
|----------|--------|----------|
| `APP_SECRET` | Chaine hexadecimale de 64 caracteres ([voir comment generer](#generer-app_secret)) | Secret cryptographique pour HMAC, sessions, tokens |
| `CORS_ALLOWED_ORIGINS` | `https://vote.mondomaine.fr` | Restreindre les origines CORS a votre domaine |
| `DEFAULT_TENANT_ID` | UUID de votre organisation | Identifiant du tenant principal |

### Etape 3 — Redemarrer le service

Apres avoir ajoute les variables, cliquer **Manual Deploy** > **Deploy latest commit** pour que le conteneur redemarre avec la nouvelle configuration.

---

## Variables d'environnement obligatoires

Le script `deploy/entrypoint.sh` effectue des verifications strictes au demarrage en mode production (`APP_ENV=production`). Si une verification echoue, **le conteneur refuse de demarrer** et affiche un message `[FATAL]`.

### Variables verifiees automatiquement

| Variable | Valeur attendue | Erreur si absent |
|----------|----------------|------------------|
| `APP_AUTH_ENABLED` | `1` | `[FATAL] APP_AUTH_ENABLED doit etre 1 en production.` |
| `CSRF_ENABLED` | `1` | `[FATAL] CSRF_ENABLED doit etre 1 en production.` |
| `RATE_LIMIT_ENABLED` | `1` | `[FATAL] RATE_LIMIT_ENABLED doit etre 1 en production.` |
| `APP_SECRET` | Chaine non vide, differente des valeurs par defaut | `[FATAL] APP_SECRET non configure pour la production.` |
| `LOAD_DEMO_DATA` | `0` (ou absent) | `[FATAL] LOAD_DEMO_DATA=1 interdit en production.` |

> **Note :** `render-production.yaml` pre-configure deja `APP_AUTH_ENABLED=1`, `CSRF_ENABLED=1`, `RATE_LIMIT_ENABLED=1` et `LOAD_DEMO_DATA=0`. Seul `APP_SECRET` doit etre ajoute manuellement.

### Valeurs APP_SECRET rejetees

Le entrypoint rejette explicitement ces valeurs :
- Chaine vide
- `change-me-in-prod` (defaut de `app/config.php`)
- `dev-secret-do-not-use-in-production-change-me-now-please-64chr` (defaut de `.env.example`)

---

## Generer APP_SECRET

Utiliser l'une de ces commandes pour generer une chaine hexadecimale de 64 caracteres :

```bash
# PHP (recommande)
php -r "echo bin2hex(random_bytes(32));"

# OpenSSL
openssl rand -hex 32

# Python
python3 -c "import secrets; print(secrets.token_hex(32))"
```

Exemple de resultat :
```
a3f8b2c1d4e5f6071829304a5b6c7d8e9f0a1b2c3d4e5f607182930415263748
```

Copier cette valeur et la coller dans le dashboard Render comme valeur de `APP_SECRET`.

---

## Configurer les variables dans le dashboard Render

1. Aller sur [dashboard.render.com](https://dashboard.render.com)
2. Cliquer sur le service **ag-vote**
3. Onglet **Environment**
4. Cliquer **Add Environment Variable**
5. Ajouter chaque variable :

```
Cle :    APP_SECRET
Valeur : <votre chaine de 64 caracteres>
```

```
Cle :    CORS_ALLOWED_ORIGINS
Valeur : https://vote.mondomaine.fr
```

```
Cle :    DEFAULT_TENANT_ID
Valeur : <uuid-de-votre-organisation>
```

6. Cliquer **Save Changes**
7. Le service redemarre automatiquement avec les nouvelles variables

---

## Diagnostiquer un echec de build

### Symptome : le service ne demarre pas apres le deploy

**Cause la plus probable : variables d'environnement manquantes.**

Le `entrypoint.sh` durci refuse de demarrer si les verifications de securite echouent. Chercher dans les logs Render (onglet **Logs**) des messages `[FATAL]` :

```
=== AG-VOTE : Initialisation ===
[FATAL] APP_SECRET non configure pour la production.
        Generer avec: php -r "echo bin2hex(random_bytes(32));"
[FATAL] Verifications production echouees. Arret.
```

### Marche a suivre

1. **Ouvrir les logs** : Dashboard > ag-vote > Logs
2. **Identifier le message [FATAL]** dans les dernieres lignes
3. **Corriger** selon le tableau ci-dessous :

| Message [FATAL] | Solution |
|-----------------|----------|
| `APP_SECRET non configure` | Ajouter `APP_SECRET` avec une valeur generee ([voir ci-dessus](#generer-app_secret)) |
| `APP_AUTH_ENABLED doit etre 1` | Ajouter `APP_AUTH_ENABLED=1` dans Environment |
| `CSRF_ENABLED doit etre 1` | Ajouter `CSRF_ENABLED=1` dans Environment |
| `RATE_LIMIT_ENABLED doit etre 1` | Ajouter `RATE_LIMIT_ENABLED=1` dans Environment |
| `LOAD_DEMO_DATA=1 interdit` | Retirer `LOAD_DEMO_DATA` ou le mettre a `0` |
| `PostgreSQL non disponible` | Verifier que la base ag-vote-db est active dans Render |

4. **Redeployer** apres correction : Manual Deploy > Deploy latest commit

### Ce n'est PAS un probleme de code

Si les logs montrent un `[FATAL]` lie aux variables d'environnement, le code est correct. L'entrypoint fonctionne exactement comme prevu en refusant un demarrage non securise. Il suffit de configurer les variables manquantes dans le dashboard Render.

---

## Verification post-deploiement

### Health check

```bash
curl https://votre-app.onrender.com/api/v1/health.php
# Reponse attendue : {"ok":true, ...}
```

### Tests de securite

```bash
# Test CSRF — doit retourner 403
curl -X POST https://votre-app.onrender.com/api/v1/meetings.php \
  -H "Content-Type: application/json" \
  -d '{"title":"Test"}'

# Test Auth — doit retourner 401
curl https://votre-app.onrender.com/api/v1/meetings.php

# Test Rate Limit — doit retourner 429 apres 5 tentatives
for i in $(seq 1 10); do
  curl -s -o /dev/null -w "%{http_code}\n" \
    -X POST https://votre-app.onrender.com/api/v1/login.php
done
```

### Checklist finale

- [ ] `APP_SECRET` configure (64 caracteres hex)
- [ ] `APP_AUTH_ENABLED=1`
- [ ] `CSRF_ENABLED=1`
- [ ] `RATE_LIMIT_ENABLED=1`
- [ ] `LOAD_DEMO_DATA=0`
- [ ] `CORS_ALLOWED_ORIGINS` restreint au domaine de production
- [ ] Health check `/api/v1/health.php` repond `{"ok":true}`
- [ ] HTTPS actif (Render le fournit automatiquement)
- [ ] Pas d'acces aux fichiers sensibles (`.env`, `/app/`, `/database/`)

---

## Troubleshooting

### Le service redemarre en boucle

Verifier les logs pour un message `[FATAL]`. Le conteneur quitte avec `exit 1` si les verifications echouent, Render le relance, il echoue a nouveau, etc. Solution : corriger les variables manquantes.

### "PostgreSQL non disponible apres 30s"

- Verifier que la base `ag-vote-db` est bien creee dans Render
- Verifier que les variables `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS` sont bien injectees (elles le sont automatiquement via `fromDatabase` dans le blueprint)
- Si la base est sur le plan free, elle peut etre suspendue apres inactivite — la reactiver dans le dashboard

### Les migrations echouent

- Verifier que l'utilisateur PostgreSQL a les droits suffisants sur la base `vote_app`
- Consulter les logs pour identifier quelle migration echoue : `[FATAL] Migration failed: <nom>.sql`

### CORS bloque les requetes

- Verifier que `CORS_ALLOWED_ORIGINS` correspond exactement a votre domaine (avec `https://`, sans slash final)
- Exemple correct : `https://vote.monasso.fr`
- Exemple incorrect : `vote.monasso.fr` ou `https://vote.monasso.fr/`

---

## Architecture du deploiement

```
┌─────────────────────────────────────────────────────────┐
│                      Render                              │
│                                                          │
│  ┌──────────────────────┐    ┌────────────────────────┐ │
│  │   Service web         │    │   PostgreSQL            │ │
│  │   ag-vote             │    │   ag-vote-db            │ │
│  │                       │    │                         │ │
│  │  ┌─────────────────┐ │    │   Base : vote_app       │ │
│  │  │  Supervisord     │ │    │   User : vote_app       │ │
│  │  │  ├── Nginx       │ │    │                         │ │
│  │  │  └── PHP-FPM     │ │    │                         │ │
│  │  └─────────────────┘ │    │                         │ │
│  │                       │    │                         │ │
│  │  Port : 8080          │◄──►│   Port : 5432           │ │
│  │  Health: /api/v1/     │    │                         │ │
│  │         health.php    │    │                         │ │
│  └──────────────────────┘    └────────────────────────┘ │
│                                                          │
│  Variables injectees automatiquement :                   │
│  DB_HOST, DB_PORT, DB_DATABASE, DB_USER, DB_PASS         │
│                                                          │
│  Variables a ajouter manuellement :                      │
│  APP_SECRET, CORS_ALLOWED_ORIGINS, DEFAULT_TENANT_ID     │
└─────────────────────────────────────────────────────────┘
```

### Fichiers impliques

| Fichier | Role |
|---------|------|
| `render.yaml` | Blueprint demo (auth desactivee) |
| `render-production.yaml` | Blueprint production (securite complete) |
| `Dockerfile` | Image Docker (PHP 8.4 Alpine + Nginx) |
| `deploy/entrypoint.sh` | Script d'initialisation avec verifications securite |
| `deploy/nginx.conf` | Configuration Nginx (rate limiting, headers securite) |
| `deploy/supervisord.conf` | Gestionnaire de processus (Nginx + PHP-FPM) |
| `deploy/php.ini` | Configuration PHP production (OPcache, sessions) |
