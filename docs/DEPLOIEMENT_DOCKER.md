# Deploiement Docker local — AG-VOTE

Guide complet pour deployer et exploiter AG-VOTE en local avec Docker Compose.

> **Installation de Docker** : voir [DOCKER_INSTALL.md](DOCKER_INSTALL.md) (Linux) ou [INSTALL_MAC.md](INSTALL_MAC.md) (macOS).

---

## Table des matieres

1. [Architecture des services](#architecture-des-services)
2. [Demarrage rapide](#demarrage-rapide)
3. [Mode developpement vs production](#mode-developpement-vs-production)
4. [Reference des variables d'environnement](#reference-des-variables-denvironnement)
5. [Fonctionnement du entrypoint](#fonctionnement-du-entrypoint)
6. [Base de donnees](#base-de-donnees)
7. [Volumes et persistance](#volumes-et-persistance)
8. [Reseau et ports](#reseau-et-ports)
9. [Securite locale](#securite-locale)
10. [Monitoring et logs](#monitoring-et-logs)
11. [Sauvegarde et restauration](#sauvegarde-et-restauration)
12. [Commandes utiles](#commandes-utiles)
13. [Troubleshooting](#troubleshooting)

---

## Architecture des services

Docker Compose orchestre **3 services** :

```
┌──────────────────────────────────────────────────────────────┐
│                       Docker Host                            │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐  │
│  │              agvote-app (PHP 8.4 Alpine)               │  │
│  │                                                        │  │
│  │  supervisord                                           │  │
│  │   ├── nginx     → :8080 (HTTP)                         │  │
│  │   └── php-fpm   → :9000 (interne FastCGI)              │  │
│  │                                                        │  │
│  │  Volume: app-storage → /tmp/ag-vote                    │  │
│  │  Memoire: 512M max, 128M reserve                       │  │
│  └───────┬──────────────────────────┬─────────────────────┘  │
│          │ PDO (pgsql)              │ Redis                  │
│  ┌───────▼────────────────┐  ┌──────▼──────────────────┐    │
│  │   agvote-db             │  │   agvote-redis           │    │
│  │   PostgreSQL 16 Alpine  │  │   Redis 7 Alpine         │    │
│  │                         │  │                          │    │
│  │   Port: 5432 (interne)  │  │   Port: 6379 (interne)   │    │
│  │   Port: 5433 (host,     │  │   Port: 6380 (host,      │    │
│  │         localhost only)  │  │         localhost only)   │    │
│  │                         │  │                          │    │
│  │   Volume: pgdata        │  │   Pas de persistance     │    │
│  │   Memoire: 256M max     │  │   64 Mo max, LRU evict   │    │
│  └─────────────────────────┘  └──────────────────────────┘   │
│                                                              │
│  Ports exposes sur le host :                                 │
│    0.0.0.0:8080  → app HTTP                                  │
│    127.0.0.1:5433 → PostgreSQL (localhost uniquement)        │
│    127.0.0.1:6380 → Redis (localhost uniquement)             │
└──────────────────────────────────────────────────────────────┘
```

| Service | Image | Role | Health check |
|---------|-------|------|-------------|
| `app` | Build local (`Dockerfile`) | Nginx + PHP-FPM (supervisord) | `curl /api/v1/health.php` toutes les 30s |
| `db` | `postgres:16-alpine` | Base de donnees PostgreSQL | `pg_isready` toutes les 10s |
| `redis` | `redis:7-alpine` | Cache, rate limiter, queue | `redis-cli ping` toutes les 10s |

### Ordre de demarrage

1. `db` et `redis` demarrent en parallele
2. `app` attend que `db` et `redis` soient `healthy` (`depends_on: condition`)
3. `entrypoint.sh` s'execute : verifications, init DB, migrations
4. `supervisord` lance Nginx + PHP-FPM

---

## Demarrage rapide

```bash
git clone https://github.com/thomasvinh-san-byte/gestion-votes.git
cd gestion-votes
cp .env.example .env
docker compose up -d
```

Premier lancement : 3-5 min (telechargement images + compilation extensions PHP).
Lancements suivants : ~5 secondes.

Ouvrir **http://localhost:8080** — compte test : `admin@ag-vote.local` / `Admin2026!`

---

## Mode developpement vs production

### Developpement (par defaut)

Le `.env.example` est pre-configure pour le developpement local :

```env
APP_ENV=development
APP_DEBUG=1
APP_SECRET=dev-secret-do-not-use-in-production-change-me-now-please-64chr
APP_AUTH_ENABLED=1
CSRF_ENABLED=1
RATE_LIMIT_ENABLED=1
LOAD_DEMO_DATA=1
```

En mode `development` :
- Le entrypoint ne fait **aucune verification de securite** (les checks production sont ignores)
- Les cookies ne sont pas marques `Secure` (HTTP fonctionne)
- Les donnees de demo sont chargees automatiquement
- `APP_DEBUG=1` active les logs detailles

### Production locale

Pour utiliser Docker Compose en production (serveur auto-heberge) :

```bash
cp .env.example .env
```

Puis editer `.env` :

```env
APP_ENV=production
APP_DEBUG=0
APP_SECRET=<generer avec la commande ci-dessous>
APP_AUTH_ENABLED=1
CSRF_ENABLED=1
RATE_LIMIT_ENABLED=1
LOAD_DEMO_DATA=0
DB_PASS=<mot_de_passe_fort>
CORS_ALLOWED_ORIGINS=https://vote.mondomaine.fr
```

Generer les secrets :

```bash
# APP_SECRET (64 caracteres hex)
php -r "echo bin2hex(random_bytes(32));"
# ou
openssl rand -hex 32

# DB_PASS (mot de passe fort)
openssl rand -base64 24
```

Puis demarrer :

```bash
docker compose up -d
```

### Tableau comparatif

| Parametre | Developpement | Production |
|-----------|---------------|------------|
| `APP_ENV` | `development` | `production` |
| `APP_DEBUG` | `1` | `0` |
| `APP_SECRET` | Valeur par defaut | Chaine unique 64 chars hex |
| `APP_AUTH_ENABLED` | `1` (ou `0` pour tester sans auth) | `1` **obligatoire** |
| `CSRF_ENABLED` | `1` (ou `0` pour tester) | `1` **obligatoire** |
| `RATE_LIMIT_ENABLED` | `1` (ou `0` pour tester) | `1` **obligatoire** |
| `LOAD_DEMO_DATA` | `1` | `0` **obligatoire** |
| `DB_PASS` | `vote_app_dev_2026` | Mot de passe fort unique |
| Cookie `Secure` | OFF (HTTP) | ON (HTTPS) |
| Logs | Detailles | Erreurs uniquement |

---

## Reference des variables d'environnement

Toutes les variables sont definies dans `.env` (copie de `.env.example`).

### Application

| Variable | Defaut | Description |
|----------|--------|-------------|
| `APP_ENV` | `development` | Environnement : `development`, `demo`, `production` |
| `APP_DEBUG` | `1` | Activer les logs detailles (`0` en prod) |
| `APP_SECRET` | *(dev placeholder)* | Secret cryptographique pour HMAC/sessions — **64 chars hex en prod** |
| `APP_PORT` | `8080` | Port HTTP expose sur le host |

### Base de donnees

| Variable | Defaut | Description |
|----------|--------|-------------|
| `DB_DSN` | `pgsql:host=localhost;port=5432;dbname=vote_app` | DSN PDO (surcharge par docker-compose) |
| `DB_USER` | `vote_app` | Utilisateur PostgreSQL |
| `DB_PASS` | **obligatoire** | Mot de passe PostgreSQL — docker-compose refuse de demarrer sans |
| `DB_DATABASE` | `vote_app` | Nom de la base |
| `DB_EXTERNAL_PORT` | `5433` | Port PostgreSQL expose sur localhost |

### Securite

| Variable | Defaut | Description |
|----------|--------|-------------|
| `APP_AUTH_ENABLED` | `1` | Activer l'authentification. **Doit etre `1` en production** |
| `CSRF_ENABLED` | `1` | Activer la protection CSRF. **Doit etre `1` en production** |
| `RATE_LIMIT_ENABLED` | `1` | Activer le rate limiting. **Doit etre `1` en production** |
| `CSRF_LIFETIME` | `3600` | Duree de vie du token CSRF (secondes) |
| `RATE_LIMIT_REQUESTS` | `100` | Nombre max de requetes par periode |
| `RATE_LIMIT_PERIOD` | `60` | Periode du rate limiter (secondes) |
| `LOAD_DEMO_DATA` | `1` | Charger les donnees de demo. **Doit etre `0` en production** |

### Reseau

| Variable | Defaut | Description |
|----------|--------|-------------|
| `CORS_ALLOWED_ORIGINS` | `http://localhost:8080,http://127.0.0.1:8080` | Origines CORS autorisees (virgule comme separateur) |

### Redis

| Variable | Defaut | Description |
|----------|--------|-------------|
| `REDIS_HOST` | `127.0.0.1` (surcharge par docker-compose : `redis`) | Hote Redis |
| `REDIS_PORT` | `6379` | Port Redis |
| `REDIS_PASSWORD` | *(vide)* | Mot de passe Redis (optionnel) |
| `REDIS_DATABASE` | `0` | Numero de base Redis |
| `REDIS_PREFIX` | `agvote:` | Prefixe des cles Redis |
| `REDIS_EXTERNAL_PORT` | `6380` | Port Redis expose sur localhost |

### Multi-tenant

| Variable | Defaut | Description |
|----------|--------|-------------|
| `DEFAULT_TENANT_ID` | `aaaaaaaa-1111-2222-3333-444444444444` | UUID du tenant par defaut |

### Email (optionnel)

| Variable | Defaut | Description |
|----------|--------|-------------|
| `MAIL_HOST` | `smtp.example.com` | Serveur SMTP |
| `MAIL_PORT` | `587` | Port SMTP |
| `MAIL_USER` | *(vide)* | Utilisateur SMTP |
| `MAIL_PASS` | *(vide)* | Mot de passe SMTP |
| `MAIL_FROM` | `noreply@example.com` | Adresse expediteur |
| `MAIL_FROM_NAME` | `AG-VOTE` | Nom expediteur |

### Stockage et PDF

| Variable | Defaut | Description |
|----------|--------|-------------|
| `STORAGE_PATH` | `/tmp/ag-vote` | Repertoire de stockage (logs, cache, exports) |
| `DOMPDF_FONT_DIR` | `/tmp/ag-vote/fonts` | Repertoire des polices DomPDF |
| `DOMPDF_CACHE_DIR` | `/tmp/ag-vote/dompdf` | Repertoire de cache DomPDF |

---

## Fonctionnement du entrypoint

Le script `deploy/entrypoint.sh` s'execute a chaque demarrage du conteneur `app`. Voici ce qu'il fait, dans l'ordre :

### 1. Verifications de securite (production uniquement)

Si `APP_ENV=production` ou `APP_ENV=prod`, le entrypoint verifie :

| Verification | Condition | Message [FATAL] |
|-------------|-----------|------------------|
| Authentification | `APP_AUTH_ENABLED` doit etre `1` | `APP_AUTH_ENABLED doit etre 1 en production.` |
| CSRF | `CSRF_ENABLED` doit etre `1` | `CSRF_ENABLED doit etre 1 en production.` |
| Rate limiting | `RATE_LIMIT_ENABLED` doit etre `1` | `RATE_LIMIT_ENABLED doit etre 1 en production.` |
| Secret | `APP_SECRET` ne doit etre ni vide, ni `change-me-in-prod`, ni la valeur par defaut de `.env.example` | `APP_SECRET non configure pour la production.` |
| Donnees de demo | `LOAD_DEMO_DATA` ne doit pas etre `1` | `LOAD_DEMO_DATA=1 interdit en production.` |

**Si une verification echoue, le conteneur refuse de demarrer** (`exit 1`).

En mode `development` ou `demo`, ces verifications sont ignorees.

### 2. Normalisation des variables

Le entrypoint convertit les variables Docker Compose (`DB_HOST`, `DB_PORT`, etc.) en format attendu par l'application PHP (`DB_DSN`, `DB_USER`, `DB_PASS`).

### 3. Attente PostgreSQL

Le entrypoint attend jusqu'a 30 secondes que PostgreSQL soit pret (`pg_isready`). Au-dela, il sort en erreur fatale.

### 4. Initialisation de la base

- **Base vide** (< 5 tables) : applique `database/schema-master.sql` + seeds de demo si `LOAD_DEMO_DATA=1`
- **Base existante** : aucune action sur le schema

### 5. Migrations

A chaque demarrage, toutes les migrations de `database/migrations/*.sql` sont appliquees. Elles sont idempotentes (`IF NOT EXISTS`, etc.), donc sans danger.

### 6. Configuration PHP runtime

- Si `APP_ENV` n'est pas `development`/`dev`, force `session.cookie_secure=1`
- Ecrit dans `/usr/local/etc/php/conf.d/zz-runtime.ini`

### 7. Demarrage

Execute `supervisord` qui lance Nginx + PHP-FPM.

---

## Base de donnees

### Schema et migrations

```
database/
├── schema-master.sql         ← Schema complet (tables, index, contraintes)
├── seeds/
│   ├── 01_minimal.sql        ← Donnees minimales (tenant, roles)
│   ├── 02_test_users.sql     ← Comptes de test (admin, operator, etc.)
│   └── 03_demo.sql           ← Donnees de demonstration (AG, resolutions, votes)
└── migrations/
    └── *.sql                 ← Migrations incrementales (idempotentes)
```

### Premiere initialisation

Au premier demarrage, si la base est vide :
1. Le schema est applique
2. Si `LOAD_DEMO_DATA=1` : les 3 fichiers de seeds sont charges

### Acces direct a PostgreSQL

```bash
# Console psql depuis le conteneur
docker compose exec db psql -U vote_app -d vote_app

# Depuis le host (port 5433, localhost uniquement)
psql -h 127.0.0.1 -p 5433 -U vote_app -d vote_app
```

### Reinitialiser la base

```bash
# Supprimer le volume et reconstruire
docker compose down -v
docker compose up -d
```

La base sera recreee a partir du schema + seeds au prochain demarrage.

---

## Volumes et persistance

| Volume | Point de montage | Contenu | Persistance |
|--------|-----------------|---------|-------------|
| `pgdata` | `/var/lib/postgresql/data` | Donnees PostgreSQL | Survit a `docker compose down` |
| `app-storage` | `/tmp/ag-vote` | Logs PHP, cache PDF, polices DomPDF | Survit a `docker compose down` |

### Supprimer les volumes (reset complet)

```bash
# Arrete les conteneurs ET supprime les volumes
docker compose down -v

# Relancer — base recreee from scratch
docker compose up -d
```

> **Attention** : `docker compose down -v` detruit toutes les donnees PostgreSQL.

---

## Reseau et ports

### Ports exposes

| Port host | Service | Ecoute | Acces |
|-----------|---------|--------|-------|
| `8080` (configurable via `APP_PORT`) | Nginx HTTP | `0.0.0.0` | Accessible depuis le LAN |
| `5433` (configurable via `DB_EXTERNAL_PORT`) | PostgreSQL | `127.0.0.1` | Localhost uniquement |
| `6380` (configurable via `REDIS_EXTERNAL_PORT`) | Redis | `127.0.0.1` | Localhost uniquement |

### Changer le port HTTP

Dans `.env` :

```env
APP_PORT=9090
```

Puis `docker compose up -d`. L'application sera sur http://localhost:9090.

### Acces LAN

L'application ecoute sur `0.0.0.0:8080`, elle est donc accessible depuis tout le reseau local.

Depuis un autre poste :

```
http://<ip-du-serveur>:8080
```

Trouver l'IP du serveur :

```bash
# Linux
ip -4 addr show | grep -oP '(?<=inet\s)\d+\.\d+\.\d+\.\d+'

# macOS
ipconfig getifaddr en0
```

Penser a ajouter cette IP dans `CORS_ALLOWED_ORIGINS` :

```env
CORS_ALLOWED_ORIGINS=http://localhost:8080,http://192.168.1.50:8080
```

### Reseau Docker interne

Les 3 conteneurs communiquent via le reseau Docker `default` :
- `app` → `db` : hostname `db`, port `5432`
- `app` → `redis` : hostname `redis`, port `6379`

PostgreSQL et Redis ne sont **pas** accessibles depuis le reseau externe (ports host en `127.0.0.1` uniquement).

---

## Securite locale

### Conteneurs

- `no-new-privileges: true` sur les 3 services (empeche l'escalade de privileges)
- Limites memoire/CPU sur chaque service
- PostgreSQL et Redis non exposes sur le reseau

### Nginx (dans le conteneur app)

- **Security headers** : CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy
- **Rate limiting** : 10 req/s par IP sur l'API, 3 req/s sur le login
- **Limite connexions** : 50 connexions simultanees par IP
- **Chemins bloques** : `.env`, `.git`, `/app/`, `/database/`, `/vendor/`, `/deploy/` retournent 404
- **Upload max** : 10 Mo (CSV/XLSX)

### PHP

- `expose_php = Off` (pas de header `X-Powered-By`)
- `allow_url_include = Off`
- `session.cookie_httponly = 1`
- `session.cookie_samesite = Lax`
- `session.use_strict_mode = 1`
- OPcache active (cache bytecode)

### Firewall (optionnel)

```bash
# Debian/Ubuntu (ufw)
sudo ufw allow 8080/tcp comment "AG-VOTE HTTP"

# iptables
sudo iptables -A INPUT -p tcp --dport 8080 -j ACCEPT
```

---

## Monitoring et logs

### Health check

```bash
# Depuis le host
curl -s http://localhost:8080/api/v1/health.php | python3 -m json.tool

# Etat des conteneurs (doit etre "healthy")
docker compose ps
```

Resultat attendu :

```
NAME           STATUS                  PORTS
agvote-app     Up (healthy)            0.0.0.0:8080->8080/tcp
agvote-db      Up (healthy)            127.0.0.1:5433->5432/tcp
agvote-redis   Up (healthy)            127.0.0.1:6380->6379/tcp
```

### Logs

```bash
# Logs de tous les services
docker compose logs -f

# Logs de l'application uniquement
docker compose logs -f app

# Logs de PostgreSQL
docker compose logs -f db

# Logs de Redis
docker compose logs -f redis

# Dernières 100 lignes
docker compose logs --tail=100 app
```

### Processus dans le conteneur

```bash
# Etat supervisord (Nginx + PHP-FPM)
docker compose exec app supervisorctl status

# Resultat attendu :
# nginx        RUNNING   pid 10, uptime 0:05:00
# php-fpm      RUNNING   pid 11, uptime 0:05:00
```

### Status systeme (API admin)

```bash
# Necessite un token admin
curl -s http://localhost:8080/api/v1/admin_system_status.php \
  -H "Authorization: Bearer <admin-api-key>"
```

---

## Sauvegarde et restauration

### Sauvegarder la base

```bash
# Dump SQL lisible
docker compose exec db pg_dump -U vote_app -d vote_app \
  > backup_$(date +%Y%m%d_%H%M%S).sql

# Dump compresse (recommande pour les grosses bases)
docker compose exec db pg_dump -U vote_app -d vote_app -Fc \
  > backup_$(date +%Y%m%d_%H%M%S).dump
```

### Restaurer

```bash
# Depuis un dump SQL
docker compose exec -T db psql -U vote_app -d vote_app < backup_20260222.sql

# Depuis un dump compresse
docker compose exec -T db pg_restore -U vote_app -d vote_app --clean \
  < backup_20260222.dump
```

### Automatiser (cron)

```bash
# Sauvegarde quotidienne a 2h du matin
crontab -e
# Ajouter la ligne :
0 2 * * * cd /chemin/vers/gestion-votes && docker compose exec -T db pg_dump -U vote_app -d vote_app -Fc > /backups/agvote_$(date +\%Y\%m\%d).dump
```

---

## Commandes utiles

### Cycle de vie

```bash
# Demarrer tous les services
docker compose up -d

# Arreter (conserve les donnees)
docker compose down

# Redemarrer l'application seule (après modification .env)
docker compose restart app

# Reconstruire apres modification du code
docker compose up -d --build

# Reconstruire sans cache (apres modification Dockerfile)
docker compose build --no-cache && docker compose up -d
```

### Debug

```bash
# Shell dans le conteneur app
docker compose exec app sh

# Console PostgreSQL
docker compose exec db psql -U vote_app -d vote_app

# Console Redis
docker compose exec redis redis-cli

# Verifier les extensions PHP
docker compose exec app php -m

# Verifier la configuration PHP
docker compose exec app php -i | grep -i "session\|opcache\|memory"
```

### Mise a jour

```bash
# Mettre a jour le code
git pull origin main

# Reconstruire et relancer
docker compose up -d --build
```

Les migrations SQL sont appliquees automatiquement au redemarrage.

---

## Troubleshooting

### Le conteneur app ne demarre pas

```bash
docker compose logs app
```

| Symptome | Cause | Solution |
|----------|-------|----------|
| `DB_PASS requis — definir dans .env` | `.env` manquant ou `DB_PASS` non defini | `cp .env.example .env` |
| `[FATAL] APP_SECRET non configure` | Mode production sans `APP_SECRET` | Generer : `openssl rand -hex 32` et ajouter dans `.env` |
| `[FATAL] APP_AUTH_ENABLED doit etre 1` | Mode production avec auth desactivee | `APP_AUTH_ENABLED=1` dans `.env` |
| `[FATAL] CSRF_ENABLED doit etre 1` | Mode production avec CSRF desactive | `CSRF_ENABLED=1` dans `.env` |
| `[FATAL] RATE_LIMIT_ENABLED doit etre 1` | Mode production sans rate limiting | `RATE_LIMIT_ENABLED=1` dans `.env` |
| `[FATAL] LOAD_DEMO_DATA=1 interdit` | Mode production avec donnees de demo | `LOAD_DEMO_DATA=0` dans `.env` |
| `[FATAL] PostgreSQL non disponible apres 30s` | Le conteneur `db` n'est pas pret | Verifier `docker compose ps db` |
| `[FATAL] Migration failed: xxx.sql` | Erreur dans une migration SQL | Consulter les logs pour le detail |

### Port 8080 deja utilise

```bash
# Identifier le processus
sudo lsof -i :8080

# Changer le port dans .env
APP_PORT=9090

# Relancer
docker compose down && docker compose up -d
```

### "Connection refused" sur PostgreSQL

```bash
# Verifier que le conteneur db est healthy
docker compose ps db

# Tester la connexion
docker compose exec db pg_isready -U vote_app
```

### Erreurs CORS

Verifier que `CORS_ALLOWED_ORIGINS` dans `.env` correspond exactement a l'URL utilisee :

```env
# Correct
CORS_ALLOWED_ORIGINS=http://localhost:8080,http://192.168.1.50:8080

# Incorrect (manque le protocole / slash en trop)
CORS_ALLOWED_ORIGINS=localhost:8080
CORS_ALLOWED_ORIGINS=http://localhost:8080/
```

Apres modification, redemarrer : `docker compose restart app`

### Le conteneur redemarre en boucle (production)

Le entrypoint echoue aux verifications de securite, le conteneur sort en `exit 1`, Docker le relance (`restart: unless-stopped`), il echoue a nouveau, etc.

**Solution** : corriger les variables d'environnement dans `.env` puis `docker compose restart app`.

### Tout reinitialiser

```bash
# Supprime conteneurs, volumes, donnees — repart de zero
docker compose down -v
docker compose up -d --build
```

---

## Fichiers de configuration

| Fichier | Role |
|---------|------|
| `docker-compose.yml` | Orchestration des 3 services |
| `Dockerfile` | Image Docker (PHP 8.4 Alpine + Nginx + supervisord) |
| `.env.example` | Template des variables d'environnement |
| `deploy/entrypoint.sh` | Script d'initialisation (verifications, DB, migrations) |
| `deploy/nginx.conf` | Configuration Nginx (rate limiting, headers securite, routing) |
| `deploy/supervisord.conf` | Gestionnaire de processus (Nginx + PHP-FPM) |
| `deploy/php.ini` | Configuration PHP (OPcache, sessions, limites) |
| `deploy/php-fpm.conf` | Configuration PHP-FPM (workers, timeouts) |
