# Installation et démarrage

Ce document décrit **pas à pas** comment installer et lancer l'application **AG-Vote** sur une machine Linux (poste de développement ou serveur).

À l'issue de ces étapes, vous disposerez :

* d'une base PostgreSQL initialisée (`vote_app`),
* d'une application PHP connectée,
* d'une interface accessible via un navigateur.

> Ce guide est volontairement **linéaire, explicite et reproductible**.
> Il ne suppose aucune connaissance préalable du projet.

---

## Périmètre de ce document

Ce document couvre uniquement :

* l'installation technique,
* la configuration minimale,
* le démarrage de l'application.

L'utilisation en séance, la démonstration et la conformité CDC sont décrites dans :

* [../UTILISATION_LIVE.md](../UTILISATION_LIVE.md)
* [../RECETTE_DEMO.md](../RECETTE_DEMO.md)
* [cahier_des_charges.md](cahier_des_charges.md)

---

## 1. Prérequis système

### Logiciels requis

* **PHP >= 8.4** avec extensions : `pdo_pgsql`, `mbstring`, `json`, `session`
* **PostgreSQL >= 16**
* (optionnel) **Git** pour cloner le dépôt

### Installation automatique (Ubuntu / Debian)

```bash
sudo bash scripts/install-deps.sh
```

Ce script installe PHP, PostgreSQL, Composer, git, et les extensions nécessaires.

### Installation manuelle

```bash
sudo apt update
sudo apt install -y php php-cli php-pgsql php-mbstring php-xml php-zip \
  php-gd php-curl postgresql postgresql-contrib git unzip curl composer
```

Vérification :

```bash
php -v          # PHP 8.4+
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
  app/        # logique métier, services, bootstrap, config
  public/     # racine web (HTML, HTMX, API)
  database/   # schéma SQL, seeds, migrations, setup.sh
  scripts/    # install-deps.sh (dépendances système)
  docs/       # documentation
  .env        # configuration environnement (à créer)
```

---

## 3. Base de données

L'application utilise PostgreSQL comme **source de vérité unique**.

### 3.1 Installation automatique (recommandé)

```bash
# Setup complet (rôle + base + schéma + migrations + seeds + .env)
sudo bash database/setup.sh

# Sans données de démo (minimal + comptes de test uniquement)
sudo bash database/setup.sh --no-demo
```

Le script est idempotent : il peut être relancé sans casser l'existant.

Options disponibles :

| Option | Description |
|--------|-------------|
| *(aucune)* | Setup complet |
| `--no-demo` | Setup sans données de démo (seeds 01 + 02 uniquement) |
| `--schema` | Schéma + migrations uniquement |
| `--seed` | Seeds uniquement |
| `--migrate` | Migrations uniquement |
| `--reset` | Supprime et recrée tout (DESTRUCTIF) |

### 3.2 Installation manuelle

```bash
# Démarrer PostgreSQL
sudo service postgresql start
pg_isready  # doit afficher "accepting connections"

# Créer le rôle applicatif
sudo -u postgres psql -c "CREATE ROLE vote_app LOGIN PASSWORD 'vote_app_dev_2026';"

# Créer la base de données
sudo -u postgres createdb vote_app -O vote_app

# Appliquer le schéma
sudo -u postgres psql -d vote_app -f database/schema-master.sql

# Appliquer les migrations
sudo -u postgres psql -d vote_app -f database/migrations/001_admin_enhancements.sql
sudo -u postgres psql -d vote_app -f database/migrations/002_rbac_meeting_states.sql
sudo -u postgres psql -d vote_app -f database/migrations/003_meeting_roles.sql
sudo -u postgres psql -d vote_app -f database/migrations/004_password_auth.sql

# Charger les données
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost \
  -f database/seeds/01_minimal.sql

PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost \
  -f database/seeds/02_test_users.sql

# (Optionnel) Données de démo
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost \
  -f database/seeds/03_demo.sql
```

### 3.3 Vérifier

```bash
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost -c "\dt"
```

Vous devez voir 40+ tables.

---

## 4. Configuration de l'application

### 4.1 Fichier .env

Le fichier `.env` à la racine du projet est la source de configuration.
Le script `setup.sh` le crée automatiquement si absent. Sinon :

```bash
cp .env.example .env
# Éditer les valeurs : DB_PASS, APP_SECRET, CORS_ALLOWED_ORIGINS
```

### 4.2 Variables d'environnement

| Variable | Description | Défaut dev |
|----------|-------------|------------|
| `APP_ENV` | Environnement (`development` / `production`) | `development` |
| `APP_DEBUG` | Mode debug (`1` = actif) | `1` |
| `APP_SECRET` | Secret HMAC pour hachage des clés API | (voir .env) |
| `DB_DSN` | DSN PDO PostgreSQL | `pgsql:host=localhost;port=5432;dbname=vote_app` |
| `DB_USER` | Utilisateur PostgreSQL | `vote_app` |
| `DB_PASS` | Mot de passe PostgreSQL | `vote_app_dev_2026` |
| `DEFAULT_TENANT_ID` | UUID du tenant par défaut | `aaaaaaaa-1111-2222-3333-444444444444` |
| `APP_AUTH_ENABLED` | Activer l'authentification | `1` |
| `CSRF_ENABLED` | Protection CSRF | `0` (dev) / `1` (prod) |
| `RATE_LIMIT_ENABLED` | Limitation de débit | `1` |
| `CORS_ALLOWED_ORIGINS` | Origines autorisées (séparées par virgule) | `http://localhost:8080` |

> **Important** : les clés API de test ne fonctionnent qu'avec le `APP_SECRET` de développement.
> Si vous changez le secret, vous devez régénérer les hash (voir section 7).

---

## 5. Lancer l'application

### Mode développement

```bash
php -S 0.0.0.0:8080 -t public
```

### Se connecter

Ouvrir `http://localhost:8080/login.html` et entrer les identifiants ci-dessous.

### Comptes de test

Créés par `database/seeds/02_test_users.sql` :

| Rôle | Email | Mot de passe | Description |
|------|-------|-------------|-------------|
| admin | `admin@ag-vote.local` | `Admin2026!` | Accès total |
| operator | `operator@ag-vote.local` | `Operator2026!` | Gestion courante |
| president | `president@ag-vote.local` | `President2026!` | Préside la séance |
| votant | `votant@ag-vote.local` | `Votant2026!` | Vote en séance |
| auditor | `auditor@ag-vote.local` | `Auditor2026!` | Conformité (lecture) |
| viewer | `viewer@ag-vote.local` | `Viewer2026!` | Lecture seule |

### Accès depuis un autre poste (hors VM)

Le serveur PHP écoute sur `0.0.0.0` (toutes les interfaces), donc il est
accessible depuis le réseau. Remplacer `localhost` par l'IP de la machine :

```
http://192.168.1.50:8080/login.html
```

Puis ajouter cette origine dans `.env` pour autoriser les requêtes CORS :

```
CORS_ALLOWED_ORIGINS=http://localhost:8080,http://192.168.1.50:8080
```

---

## 6. Interfaces principales

| Interface | URL | Rôle cible |
|-----------|-----|------------|
| Connexion | `/login.html` | Tous |
| Tableau de bord | `/dashboard.htmx.html` | operator |
| Séances | `/meetings.htmx.html` | operator |
| Création de séance | `/wizard.htmx.html` | operator |
| Hub de séance | `/hub.htmx.html` | operator, president |
| Console opérateur | `/operator.htmx.html` | operator |
| Vote (tablette) | `/vote.htmx.html` | voter (rôle de séance) |
| Post-séance | `/postsession.htmx.html` | operator |
| Contrôle et audit | `/trust.htmx.html` | auditor |
| Administration | `/admin.htmx.html` | admin |
| Membres | `/members.htmx.html` | operator |
| Archives | `/archives.htmx.html` | operator, auditor |
| Statistiques | `/analytics.htmx.html` | operator |
| Écran public | `/public.htmx.html` | public |
| Aide | `/help.htmx.html` | Tous |

---

## 7. Régénérer les clés API

Si vous changez le `APP_SECRET`, les hash en base deviennent invalides.

```bash
php -r '
$secret = "VOTRE_NOUVEAU_SECRET";
$keys = ["admin-key-2026-secret", "operator-key-2026-secret", "auditor-key-2026-secret", "viewer-key-2026-secret"];
foreach ($keys as $k) { echo "$k => " . hash_hmac("sha256", $k, $secret) . "\n"; }
'
```

Puis mettre à jour les `api_key_hash` dans la table `users` :

```sql
UPDATE users SET api_key_hash = '<nouveau_hash>' WHERE email = 'admin@ag-vote.local';
```

---

## 8. Réinitialiser la base (dev)

```bash
# Réappliquer les seeds (sans toucher au schéma)
sudo bash database/setup.sh --seed

# Reset complet (SUPPRIME et recrée tout)
sudo bash database/setup.sh --reset
```

---

## 9. Dépannage

### Checklist de démarrage rapide

Avant de lancer l'application, vérifiez ces points :

```bash
# 1. PostgreSQL est démarré ?
pg_isready -h localhost -p 5432
# Attendu : "localhost:5432 - accepting connections"

# 2. Autoload PHP est à jour ?
composer dump-autoload

# 3. Base de données initialisée ?
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost -c "\dt" | head -5

# 4. Lancer le serveur
php -S 0.0.0.0:8080 -t public
```

---

### `Class "..." not found` (ex: RateLimiter, AuthMiddleware)

**Cause** : L'autoloader Composer n'est pas synchronisé avec les fichiers PHP.

**Solution** :
```bash
composer dump-autoload
```

> **Quand exécuter ?** Après chaque `git pull`, `git merge`, ou ajout de nouvelles classes PHP.

---

### PostgreSQL non accessible / "Error" dans Statut système

**Symptômes** :
- Page admin affiche "Error" dans l'onglet Système
- Les APIs retournent des erreurs 500
- `pg_isready` retourne "no response"

**Solution** :
```bash
# Démarrer PostgreSQL
sudo systemctl start postgresql
# ou
sudo service postgresql start

# Vérifier
pg_isready -h localhost -p 5432
```

> **Conseil** : Ajoutez PostgreSQL au démarrage automatique :
> ```bash
> sudo systemctl enable postgresql
> ```

---

### `fe_sendauth: no password supplied`

1. Vérifier que PostgreSQL est démarré : `pg_isready`
2. Vérifier les identifiants dans `.env` (`DB_USER`, `DB_PASS`)
3. Tester la connexion :
   ```bash
   PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost
   ```
4. Si `pg_hba.conf` est en mode `peer`, ajouter :
   ```
   host    vote_app    vote_app    127.0.0.1/32    md5
   ```
   Puis `sudo service postgresql reload`.

---

### `relation does not exist`

Le schéma n'a pas été appliqué. Rejouer :

```bash
sudo bash database/setup.sh --schema
```

---

### `invalid_api_key`

Le `APP_SECRET` ne correspond pas aux hash en base.
Utiliser le secret de dev ou régénérer les hash (section 7).

---

### Pages affichent "Chargement..." en permanence

**Causes possibles** :
1. PostgreSQL non démarré (voir ci-dessus)
2. Autoload non synchronisé → `composer dump-autoload`
3. Non connecté → vérifier `/api/v1/whoami.php` dans la console navigateur

**Diagnostic rapide** :
```bash
# Tester une API
curl -s http://localhost:8080/api/v1/whoami.php | jq .
```

Si erreur 500, vérifier les logs PHP du serveur.

---

## 10. Étape suivante

Une fois l'application lancée :

* **Conduire une séance** : [../UTILISATION_LIVE.md](../UTILISATION_LIVE.md)
* **Tester rapidement (~10 min)** : [../RECETTE_DEMO.md](../RECETTE_DEMO.md)
* **Tests E2E** : [TESTS.md](TESTS.md)
* **Architecture technique** : [ARCHITECTURE.md](ARCHITECTURE.md)
* **Référence API** : [API.md](API.md)
* **Sécurité** : [SECURITY.md](SECURITY.md)
* **FAQ** : [../FAQ.md](../FAQ.md)
