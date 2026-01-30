# Installation & demarrage

Ce document decrit **pas a pas** comment installer et lancer l'application **AG-Vote** sur une machine Linux (poste de developpement ou serveur).

A l'issue de ces etapes, vous disposerez :

* d'une base PostgreSQL initialisee (`vote_app`),
* d'une application PHP connectee,
* d'une interface accessible via un navigateur.

> Ce guide est volontairement **lineaire, explicite et reproductible**.
> Il ne suppose aucune connaissance prealable du projet.

---

## Perimetre de ce document

Ce document couvre uniquement :

* l'installation technique,
* la configuration minimale,
* le demarrage de l'application.

L'utilisation en seance, la demonstration et la conformite CDC sont decrites dans :

* [UTILISATION_LIVE.md](UTILISATION_LIVE.md)
* [RECETTE_DEMO.md](RECETTE_DEMO.md)
* [CONFORMITE_CDC.md](CONFORMITE_CDC.md)

---

## 1. Prerequis systeme

### Logiciels requis

* **PHP >= 8.3** avec extensions : `pdo_pgsql`, `mbstring`, `json`, `session`
* **PostgreSQL >= 16**
* (optionnel) **Git** pour cloner le depot

Installation sur Debian / Ubuntu :

```bash
sudo apt update
sudo apt install -y php php-cli php-pgsql php-mbstring postgresql git
```

Verification :

```bash
php -v          # PHP 8.3+
psql --version  # psql 16+
```

---

## 2. Cloner le projet

```bash
git clone <url> gestion-votes
cd gestion-votes
```

Structure attendue :

```
gestion-votes/
  app/        # logique metier, services, bootstrap, config
  public/     # racine web (HTML, HTMX, API)
  database/   # schema SQL, seeds, migrations
  docs/       # documentation
  .env        # configuration environnement (a creer)
```

---

## 3. Configuration PostgreSQL

L'application utilise PostgreSQL comme **source de verite unique**.

### 3.1 Demarrer PostgreSQL

```bash
sudo service postgresql start
pg_isready  # doit afficher "accepting connections"
```

### 3.2 Creer le role applicatif

```bash
sudo -u postgres psql
```

```sql
CREATE ROLE vote_app LOGIN PASSWORD 'vote_app_dev_2026';
\q
```

> En production, utilisez un mot de passe fort et reportez-le dans `.env`.

### 3.3 Creer la base de donnees

```bash
sudo -u postgres createdb vote_app -O vote_app
```

### 3.4 Appliquer le schema

```bash
sudo -u postgres psql -d vote_app -f database/schema.sql
```

Le schema est **idempotent** (peut etre rejoue sans casser l'existant). Il :

* installe les extensions `pgcrypto` et `citext`,
* cree 29+ tables (tenants, users, meetings, motions, ballots, etc.),
* installe les types ENUM, triggers d'audit et garde-fous post-validation.

### 3.5 Charger les donnees

```bash
# Donnees minimales (tenant, politiques quorum/vote) — requis
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost \
  -f database/seed_minimal.sql

# Utilisateurs de test (4 roles avec cles API) — requis pour se connecter
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost \
  -f database/seeds/test_users.sql

# (Optionnel) Donnees de demo (seances, membres, motions, presences)
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost \
  -f database/seed_demo.sql
```

### 3.6 Verifier

```bash
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost -c "\dt"
```

Vous devez voir 29+ tables.

---

## 4. Configuration de l'application

### 4.1 Fichier .env

Le fichier `.env` a la racine du projet est la source de configuration.
Un fichier de developpement est fourni. En production, copier le template :

```bash
cp .env.production .env
# Editer les valeurs : DB_PASS, APP_SECRET, CORS_ALLOWED_ORIGINS
```

### 4.2 Variables d'environnement

| Variable | Description | Defaut dev |
|----------|-------------|------------|
| `APP_ENV` | Environnement (`development` / `production`) | `development` |
| `APP_DEBUG` | Mode debug (`1` = actif) | `1` |
| `APP_SECRET` | Secret HMAC pour hachage des cles API | (voir .env) |
| `DB_DSN` | DSN PDO PostgreSQL | `pgsql:host=localhost;port=5432;dbname=vote_app` |
| `DB_USER` | Utilisateur PostgreSQL | `vote_app` |
| `DB_PASS` | Mot de passe PostgreSQL | `vote_app_dev_2026` |
| `DEFAULT_TENANT_ID` | UUID du tenant par defaut | `aaaaaaaa-1111-2222-3333-444444444444` |
| `APP_AUTH_ENABLED` | Activer l'authentification | `1` |
| `CSRF_ENABLED` | Protection CSRF | `0` (dev) / `1` (prod) |
| `RATE_LIMIT_ENABLED` | Limitation de debit | `1` |
| `CORS_ALLOWED_ORIGINS` | Origines autorisees (separees par virgule) | `http://localhost:8080` |

> **Important** : les cles API de test ne fonctionnent qu'avec le `APP_SECRET` de developpement.
> Si vous changez le secret, vous devez regenerer les hash (voir section 7).

---

## 5. Lancer l'application

### Mode developpement

```bash
php -S 0.0.0.0:8080 -t public
```

### Se connecter

Ouvrir `http://localhost:8080/login.html` et entrer une cle API de test :

| Role | Cle API |
|------|---------|
| **admin** | `admin-key-2024-secret` |
| **operator** | `operator-key-2024-secret` |
| **auditor** | `auditor-key-2024-secret` |
| **viewer** | `viewer-key-2024-secret` |

### Tester via cURL

```bash
# Login (cree une session PHP)
curl -s http://localhost:8080/api/v1/auth_login.php \
  -H "Content-Type: application/json" \
  -d '{"api_key":"admin-key-2024-secret"}'

# Auth par header (sans session)
curl -s http://localhost:8080/api/v1/meetings_index.php \
  -H "X-Api-Key: operator-key-2024-secret"
```

---

## 6. Interfaces principales

| Interface | URL | Role cible |
|-----------|-----|------------|
| Connexion | `/login.html` | Tous |
| Tableau de bord | `/meetings.htmx.html` | operator |
| Console operateur | `/operator.htmx.html` | operator |
| Flux operateur | `/operator_flow.htmx.html` | operator |
| Cockpit president | `/president.htmx.html` | president (role de seance) |
| Controle & audit | `/trust.htmx.html` | auditor |
| Administration | `/admin.htmx.html` | admin |
| Vote (tablette) | `/vote.htmx.html` | voter (role de seance) |
| Ecran public | `/public.htmx.html` | public |

---

## 7. Regenerer les cles API

Si vous changez le `APP_SECRET`, les hash en base deviennent invalides.

```bash
php -r '
$secret = "VOTRE_NOUVEAU_SECRET";
$keys = ["admin-key-2024-secret", "operator-key-2024-secret", "auditor-key-2024-secret", "viewer-key-2024-secret"];
foreach ($keys as $k) { echo "$k => " . hash_hmac("sha256", $k, $secret) . "\n"; }
'
```

Puis mettre a jour les `api_key_hash` dans la table `users` :

```sql
UPDATE users SET api_key_hash = '<nouveau_hash>' WHERE email = 'admin@ag-vote.local';
```

---

## 8. Reinitialiser la base (dev)

Pour repartir de zero :

```bash
sudo -u postgres dropdb vote_app
sudo -u postgres createdb vote_app -O vote_app
sudo -u postgres psql -d vote_app -f database/schema.sql
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost -f database/seed_minimal.sql
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost -f database/seeds/test_users.sql
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost -f database/seed_demo.sql
```

---

## 9. Depannage

### `fe_sendauth: no password supplied`

1. Verifier que PostgreSQL est demarre : `pg_isready`
2. Verifier les identifiants dans `.env` (`DB_USER`, `DB_PASS`)
3. Tester la connexion :
   ```bash
   PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost
   ```
4. Si `pg_hba.conf` est en mode `peer`, ajouter :
   ```
   host    vote_app    vote_app    127.0.0.1/32    md5
   ```
   Puis `sudo service postgresql reload`.

### `relation does not exist`

Le schema n'a pas ete applique. Rejouer :

```bash
sudo -u postgres psql -d vote_app -f database/schema.sql
```

### `invalid_api_key`

Le `APP_SECRET` ne correspond pas aux hash en base.
Utiliser le secret de dev ou regenerer les hash (section 7).

---

## 10. Etape suivante

Une fois l'application lancee :

* **Conduire une seance** : [UTILISATION_LIVE.md](UTILISATION_LIVE.md)
* **Tester rapidement (~10 min)** : [RECETTE_DEMO.md](RECETTE_DEMO.md)
* **Architecture technique** : [ARCHITECTURE.md](ARCHITECTURE.md)
* **Reference API** : [API.md](API.md)
* **Securite** : [SECURITY.md](SECURITY.md)
* **FAQ** : [FAQ.md](FAQ.md)
