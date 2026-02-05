# database/ — Schéma, migrations et seeds PostgreSQL

Source de vérité unique pour la base de données AG-VOTE.

## Structure

```
database/
  schema-master.sql       Schéma complet consolidé (recommandé)
  setup.sh                Script d'initialisation automatique
  setup_demo_az.sh        Script demo A-Z (setup rapide)
  migrations/             Migrations incrémentales (pour mises à jour)
    001_admin_enhancements.sql
    002_rbac_meeting_states.sql
    003_meeting_roles.sql
    004_password_auth.sql
    005_email_system.sql
    006_member_groups.sql
    007_export_templates.sql
    20260204_add_slugs.sql
  seeds/                  Données de peuplement (numérotées, idempotent)
    01_minimal.sql        Tenant, politiques quorum/vote, users RBAC
    02_test_users.sql     Comptes de test (admin, operator, president, votant...)
    03_demo.sql           Séance live, 12 membres, 5 motions, bulletins
    04_e2e.sql            Séance E2E complète (cycle de vie entier)
    05_test_simple.sql    Dataset : AG 20 membres, quorum simple
    06_test_weighted.sql  Dataset : copro 100 membres avec tantièmes
    07_test_incidents.sql Dataset : scénarios d'incidents
    08_demo_az.sql        Dataset : demo A-Z (1 séance, 10 membres, 2 résolutions)
```

## Installation rapide (nouvelle base)

Pour une nouvelle installation, utilisez le script automatique (recommandé) :

```bash
# Setup complet automatique (crée user, base, schéma, seeds)
sudo bash database/setup.sh

# OU demo A-Z (setup optimisé pour démonstration)
sudo bash database/setup_demo_az.sh
```

### Installation manuelle

Si vous préférez une installation manuelle :

```bash
# 1. Créer le rôle et la base (en tant que superuser postgres)
sudo -u postgres createuser vote_app --pwprompt
sudo -u postgres createdb vote_app -O vote_app

# 2. Extensions (requiert superuser)
sudo -u postgres psql -d vote_app -c "CREATE EXTENSION IF NOT EXISTS pgcrypto; CREATE EXTENSION IF NOT EXISTS citext;"

# 3. Schéma complet (en tant que user applicatif)
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost -f database/schema-master.sql

# 4. Seeds obligatoires
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost -f database/seeds/01_minimal.sql
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost -f database/seeds/02_test_users.sql

# 5. Seeds optionnels (données de démo)
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost -f database/seeds/03_demo.sql
```

> **Note** : Les valeurs par défaut sont `vote_app` / `vote_app_dev_2026`. Adaptez selon votre `.env`.

## Installation automatique

```bash
# Setup complet (recommandé)
sudo bash database/setup.sh

# Sans données de démo (01 + 02 uniquement)
sudo bash database/setup.sh --no-demo

# Schéma + migrations uniquement
sudo bash database/setup.sh --schema

# Seeds uniquement
sudo bash database/setup.sh --seed

# Reset complet (DESTRUCTIF)
sudo bash database/setup.sh --reset
```

## Mise à jour d'une base existante

Si vous avez déjà une base en production, appliquez les migrations incrémentales :

```bash
# Via le script (recommandé)
sudo bash database/setup.sh --migrate

# OU manuellement - appliquer une migration spécifique
PGPASSWORD=$DB_PASS psql -U $DB_USER -d $DB_NAME -h localhost -f database/migrations/006_member_groups.sql

# OU toutes les nouvelles migrations
for f in database/migrations/*.sql; do
  PGPASSWORD=$DB_PASS psql -U $DB_USER -d $DB_NAME -h localhost -f "$f"
done
```

> **Note** : Remplacez `$DB_USER`, `$DB_PASS`, `$DB_NAME` par vos valeurs ou sourcez votre `.env`.

## Contenu du schéma maître

`schema-master.sql` inclut :

- **Tables** : tenants, users, members, meetings, motions, ballots, etc.
- **Migrations** : toutes les migrations consolidées (001-006 + slugs)
- **Systèmes** :
  - Email (templates, queue, rappels)
  - Groupes de membres
  - Slugs URL pour obfuscation
  - RBAC avec rôles système et rôles de séance
- **Données de référence** :
  - `role_permissions` : permissions par rôle
  - `meeting_state_transitions` : transitions d'état valides
- **Vues** :
  - `email_stats_by_meeting` : statistiques email
  - `member_groups_with_count` : groupes avec compteurs
  - `members_with_groups` : membres avec leurs groupes

## Seeds — détail

| Fichier | Requis | Contenu |
|---------|--------|---------|
| `01_minimal.sql` | oui | Tenant par défaut, 5 politiques quorum, 4 politiques vote, 5 users RBAC |
| `02_test_users.sql` | oui | 6 comptes de test avec mots de passe bcrypt et clés API |
| `03_demo.sql` | non | 1 séance LIVE + 1 DRAFT, 12 membres, 5 motions, présences, proxy |
| `04_e2e.sql` | non | Séance E2E complète (conseil municipal, 5 résolutions, parcours) |
| `05-07_test_*.sql` | non | Jeux de données de recette (volume, pondération, incidents) |
| `08_demo_az.sql` | non | Demo A-Z : 1 séance scheduled, 10 membres, 2 résolutions |

## Comptes de test

Créés par `02_test_users.sql` :

| Rôle | Email | Mot de passe |
|------|-------|-------------|
| admin | `admin@ag-vote.local` | `Admin2026!` |
| operator | `operator@ag-vote.local` | `Operator2026!` |
| president | `president@ag-vote.local` | `President2026!` |
| votant | `votant@ag-vote.local` | `Votant2026!` |
| auditor | `auditor@ag-vote.local` | `Auditor2026!` |
| viewer | `viewer@ag-vote.local` | `Viewer2026!` |

## Notes

- `DEFAULT_TENANT_ID` = `aaaaaaaa-1111-2222-3333-444444444444` (doit matcher `app/bootstrap.php`)
- Tous les seeds sont idempotent (ON CONFLICT)
- Le schéma utilise les extensions `pgcrypto` et `citext`
- `motions.body` est auto-remplie depuis `motions.description` (compat écrans legacy)
- `ballots.meeting_id` est auto-remplie depuis `motions.meeting_id` (compat services)
