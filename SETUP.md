# AG-VOTE — Guide d'installation et de test

## Prérequis

- PHP 8.4+ avec extensions : `pdo_pgsql`, `mbstring`, `json`, `session`
- PostgreSQL 16+ (extensions `pgcrypto`, `citext`)
- Navigateur moderne

### Installation des dépendances sur Ubuntu/Debian

```bash
sudo bash scripts/install-deps.sh
```

Ou manuellement :

```bash
sudo apt update
sudo apt install -y php php-cli php-pgsql php-mbstring php-xml php-zip \
  php-gd php-curl postgresql postgresql-contrib git unzip curl composer
```

## 1. Base de données

### Installation automatique (recommandé)

```bash
# Setup complet (schéma + migrations + tous les seeds)
sudo bash database/setup.sh

# Setup sans données de démo (minimal + comptes de test uniquement)
sudo bash database/setup.sh --no-demo
```

Le script crée automatiquement le rôle PostgreSQL, la base, applique le schéma,
les migrations, charge les seeds et configure le `.env`.

### Installation manuelle

```bash
# Créer la base
sudo -u postgres createuser vote_app --pwprompt
sudo -u postgres createdb vote_app -O vote_app

# Schéma
sudo -u postgres psql -d vote_app -f database/schema-master.sql

# Seeds (dans l'ordre)
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost \
  -f database/seeds/01_minimal.sql
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost \
  -f database/seeds/02_test_users.sql
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost \
  -f database/seeds/03_demo.sql
```

### Vérifications

```bash
# Tables créées
sudo -u postgres psql -d vote_app -c "\dt"

# Séances
sudo -u postgres psql -d vote_app -c "SELECT id, title, status FROM meetings;"

# Membres
sudo -u postgres psql -d vote_app -c "SELECT full_name, vote_weight FROM members ORDER BY vote_weight DESC;"
```

## 2. Configuration

Le fichier `.env` à la racine est chargé automatiquement par `bootstrap.php`.

```bash
# Copier et adapter si besoin
cp .env.example .env
```

Le `.env` fourni pour le dev désactive l'authentification :

```
APP_AUTH_ENABLED=0    # Pas de login requis
CSRF_ENABLED=0        # Pas de token CSRF
RATE_LIMIT_ENABLED=0  # Pas de rate limiting
```

Pour tester avec l'authentification activée (`APP_AUTH_ENABLED=1`), voir les comptes de test ci-dessous.

**Variables de connexion DB à ajuster :**

```
DB_DSN=pgsql:host=localhost;port=5432;dbname=vote_app
DB_USER=vote_app
DB_PASS=vote_app_dev_2026
```

## 3. Lancer le serveur

```bash
php -S 0.0.0.0:8080 -t public
```

## 4. Accès aux interfaces

| Page | URL | Rôle |
|---|---|---|
| Accueil | http://localhost:8080/ | — |
| Dashboard | http://localhost:8080/dashboard.htmx.html | viewer |
| Séances | http://localhost:8080/meetings.htmx.html | viewer |
| Créer une séance | http://localhost:8080/wizard.htmx.html | operator |
| Fiche séance | http://localhost:8080/hub.htmx.html | operator |
| Opérateur | http://localhost:8080/operator.htmx.html | operator |
| Votant (tablette) | http://localhost:8080/vote.htmx.html | voter |
| Écran salle | http://localhost:8080/public.htmx.html | public |
| Post-session (PV) | http://localhost:8080/postsession.htmx.html | operator |
| Membres | http://localhost:8080/members.htmx.html | operator |
| Audit | http://localhost:8080/trust.htmx.html | auditor |
| Archives | http://localhost:8080/archives.htmx.html | viewer |
| Statistiques | http://localhost:8080/analytics.htmx.html | operator |
| Admin | http://localhost:8080/admin.htmx.html | admin |
| Aide | http://localhost:8080/help.htmx.html | — |

## 5. Comptes de test

Créés par `database/seeds/02_test_users.sql` (chargé automatiquement par `setup.sh`).

### Rôles système

| Rôle | Email | Mot de passe | Description |
|---|---|---|---|
| Admin | `admin@ag-vote.local` | `Admin2026!` | Accès total |
| Opérateur | `operator@ag-vote.local` | `Operator2026!` | Gestion courante |
| Auditeur | `auditor@ag-vote.local` | `Auditor2026!` | Conformité (lecture) |
| Lecteur | `viewer@ag-vote.local` | `Viewer2026!` | Lecture seule |

### Rôles de séance

| Rôle | Email | Mot de passe | Description |
|---|---|---|---|
| Président | `president@ag-vote.local` | `President2026!` | Préside la séance |
| Votant | `votant@ag-vote.local` | `Votant2026!` | Vote en séance |

## 6. Données de démo

Après le setup, la base contient :

### Seed démo (`seeds/03_demo.sql`)

- **1 séance LIVE** : "Assemblée Générale Ordinaire" (démarrée, en cours)
- **1 séance DRAFT** : "AGE — Prochaine" (dans 14 jours)
- **12 membres** avec poids de vote variés (35 à 120 tantièmes)
- **10 présents** sur 12 (2 absents : Thomas, Lambert)
- **1 procuration** : Lambert → Martin
- **5 motions** :
  - Approbation des comptes 2024 — **fermée, adoptée** (avec 10 bulletins)
  - Budget travaux toiture — **ouverte** (vote en cours)
  - Élection du président — **en attente** (scrutin secret)
  - Changement de syndic — **en attente**
  - Questions diverses — **en attente**
- **5 politiques de quorum** + **4 politiques de vote**

### Seed E2E (`seeds/04_e2e.sql`)

- **1 séance DRAFT** : "Conseil Municipal — Séance E2E"
- **12 élus municipaux** (poids de vote égal : 1 voix)
- **5 résolutions** avec politiques variées (majorité simple, absolue, 2/3, scrutin secret)
- **1 procuration** : Fontaine → Dupont
- **Rôles de séance** : président, assesseur, électeur

> Pour le parcours de test complet, voir la démo guidée : **[docs/RECETTE_DEMO.md](docs/RECETTE_DEMO.md)**.

## 7. Réinitialiser

```bash
# Réappliquer tous les seeds (sans toucher au schéma)
sudo bash database/setup.sh --seed

# Reset complet (SUPPRIME et recrée tout)
sudo bash database/setup.sh --reset
```

## 8. API rapide

Avec `APP_AUTH_ENABLED=0`, tous les endpoints répondent sans clé :

```bash
# Liste des séances
curl -s http://localhost:8080/api/v1/meetings_index.php | python3 -m json.tool

# Détail d'une séance
curl -s 'http://localhost:8080/api/v1/meetings.php?id=44444444-4444-4444-4444-444444444001' | python3 -m json.tool

# Motions de la séance
curl -s 'http://localhost:8080/api/v1/motions.php?meeting_id=44444444-4444-4444-4444-444444444001' | python3 -m json.tool

# Ping (health check)
curl -s http://localhost:8080/api/v1/ping.php
```
