# AG-VOTE — Guide d'installation et de test

## Prérequis

- PHP 8.1+
- PostgreSQL 13+ (extensions `pgcrypto`, `citext`)
- Navigateur moderne

## 1. Base de données

```bash
# Créer la base (une seule fois)
sudo -u postgres createdb vote_app

# Réinitialiser le schéma (supprime TOUT)
sudo -u postgres psql -d vote_app -v ON_ERROR_STOP=1 -c "DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;"

# Charger le schéma
cd database
sudo -u postgres psql -d vote_app -v ON_ERROR_STOP=1 -f ./schema.sql

# Charger les données de base (tenant, users, politiques)
sudo -u postgres psql -d vote_app -v ON_ERROR_STOP=1 -f ./seed_minimal.sql

# Charger les données de démo (séance live, membres, motions, votes)
sudo -u postgres psql -d vote_app -v ON_ERROR_STOP=1 -f ./seed_demo.sql
```

### Vérifications

```bash
# Tables créées
sudo -u postgres psql -d vote_app -c "\dt"

# Séances
sudo -u postgres psql -d vote_app -c "SELECT id, title, status, started_at FROM meetings;"

# Motions
sudo -u postgres psql -d vote_app -c "SELECT id, meeting_id, title, status, opened_at, closed_at FROM motions ORDER BY sort_order;"

# Membres
sudo -u postgres psql -d vote_app -c "SELECT full_name, vote_weight, email FROM members ORDER BY vote_weight DESC;"

# Présences
sudo -u postgres psql -d vote_app -c "SELECT m.full_name, a.mode, a.effective_power FROM attendances a JOIN members m ON m.id = a.member_id WHERE a.meeting_id = '44444444-4444-4444-4444-444444444001';"
```

## 2. Configuration

Le fichier `.env` à la racine est chargé automatiquement par `bootstrap.php`.

```bash
# Copier et adapter si besoin
cp .env.example .env
```

Le `.env` fourni pour le dev désactive l'authentification :

```
APP_AUTH_ENABLED=0    # Pas de clé API requise
CSRF_ENABLED=0        # Pas de token CSRF
RATE_LIMIT_ENABLED=0  # Pas de rate limiting
```

**Variables de connexion DB à ajuster :**

```
DB_DSN=pgsql:host=localhost;port=5432;dbname=vote_app
DB_USER=postgres
DB_PASS=
```

## 3. Lancer le serveur

```bash
cd /chemin/vers/gestion-votes
php -S 0.0.0.0:8000 -t public
```

## 4. Accès aux interfaces

| Page | URL | Rôle |
|---|---|---|
| Accueil | http://localhost:8000/ | — |
| Opérateur | http://localhost:8000/operator.htmx.html | operator |
| Président | http://localhost:8000/president.htmx.html | president |
| Résolutions | http://localhost:8000/motions.htmx.html | operator |
| Présences | http://localhost:8000/attendance.htmx.html | operator |
| Membres | http://localhost:8000/members.htmx.html | operator |
| Procurations | http://localhost:8000/proxies.htmx.html | operator |
| Invitations | http://localhost:8000/invitations.htmx.html | operator |
| Contrôle (trust) | http://localhost:8000/trust.htmx.html | trust |
| Vote public | http://localhost:8000/public.htmx.html | public |
| Admin | http://localhost:8000/admin.htmx.html | admin |
| PV / Export | http://localhost:8000/report.htmx.html | operator |
| Archives | http://localhost:8000/archives.htmx.html | operator |
| Validation | http://localhost:8000/validate.htmx.html | president |

## 5. Données de démo

Après le seed, la base contient :

- **1 séance LIVE** : "Assemblée Générale Ordinaire" (démarrée, en cours)
- **1 séance DRAFT** : "AGE — Prochaine" (dans 14 jours)
- **12 membres** avec poids de vote variés (35 à 120 tantièmes)
- **10 présents** sur 12 (2 absents : Thomas, Lambert)
- **1 procuration** : Lambert → Martin
- **5 motions** :
  - Approbation des comptes 2024 — **fermée, adoptée** (avec 10 bulletins)
  - Budget travaux toiture — **ouverte** (vote en cours)
  - Élection du président — à venir (secret)
  - Changement de syndic — à venir
  - Questions diverses — à venir
- **5 politiques de quorum** + **4 politiques de vote**

## 6. Réinitialiser

Pour revenir à l'état initial :

```bash
sudo -u postgres psql -d vote_app -v ON_ERROR_STOP=1 -c "DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;"
cd database
sudo -u postgres psql -d vote_app -v ON_ERROR_STOP=1 -f ./schema.sql
sudo -u postgres psql -d vote_app -v ON_ERROR_STOP=1 -f ./seed_minimal.sql
sudo -u postgres psql -d vote_app -v ON_ERROR_STOP=1 -f ./seed_demo.sql
```

## 7. API rapide

Avec `APP_AUTH_ENABLED=0`, tous les endpoints répondent sans clé :

```bash
# Liste des séances
curl -s http://localhost:8000/api/v1/meetings_index.php | python3 -m json.tool

# Détail d'une séance
curl -s 'http://localhost:8000/api/v1/meetings.php?id=44444444-4444-4444-4444-444444444001' | python3 -m json.tool

# Motions de la séance
curl -s 'http://localhost:8000/api/v1/motions.php?meeting_id=44444444-4444-4444-4444-444444444001' | python3 -m json.tool

# Présences
curl -s 'http://localhost:8000/api/v1/attendances.php?meeting_id=44444444-4444-4444-4444-444444444001' | python3 -m json.tool

# Ping (health check)
curl -s http://localhost:8000/api/v1/ping.php
```
