# database/ — Schéma, migrations et seeds PostgreSQL

Source de vérité unique pour la base de données AG-VOTE.

## Structure

```
database/
  schema.sql              Schéma PostgreSQL complet (35+ tables, idempotent)
  setup.sh                Script d'initialisation automatique
  migrations/             Migrations incrémentales (numérotées)
    001_admin_enhancements.sql
    002_rbac_meeting_states.sql
    003_meeting_roles.sql
    004_password_auth.sql
  seeds/                  Données de peuplement (numérotées, idempotent)
    01_minimal.sql        Tenant, politiques quorum/vote, users RBAC
    02_test_users.sql     Comptes de test (admin, operator, president, votant...)
    03_demo.sql           Séance live, 12 membres, 5 motions, bulletins
    04_e2e.sql            Séance E2E complète (cycle de vie entier)
    05_test_simple.sql    Dataset : AG 20 membres, quorum simple
    06_test_weighted.sql  Dataset : copro 100 membres avec tantièmes
    07_test_incidents.sql Dataset : scénarios d'incidents
```

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

## Installation manuelle

```bash
# 1. Créer le rôle et la base
sudo -u postgres createuser vote_app --pwprompt
sudo -u postgres createdb vote_app -O vote_app

# 2. Schéma
sudo -u postgres psql -d vote_app -f database/schema.sql

# 3. Migrations
sudo -u postgres psql -d vote_app -f database/migrations/001_admin_enhancements.sql
sudo -u postgres psql -d vote_app -f database/migrations/002_rbac_meeting_states.sql
sudo -u postgres psql -d vote_app -f database/migrations/003_meeting_roles.sql
sudo -u postgres psql -d vote_app -f database/migrations/004_password_auth.sql

# 4. Seeds (dans l'ordre)
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost \
  -f database/seeds/01_minimal.sql
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost \
  -f database/seeds/02_test_users.sql
PGPASSWORD=vote_app_dev_2026 psql -U vote_app -d vote_app -h localhost \
  -f database/seeds/03_demo.sql
```

## Seeds — détail

| Fichier | Requis | Contenu |
|---------|--------|---------|
| `01_minimal.sql` | oui | Tenant par défaut, 5 politiques quorum, 4 politiques vote, 5 users RBAC |
| `02_test_users.sql` | oui | 6 comptes de test avec mots de passe bcrypt et clés API |
| `03_demo.sql` | non | 1 séance LIVE + 1 DRAFT, 12 membres, 5 motions, présences, proxy |
| `04_e2e.sql` | non | Séance E2E complète (conseil municipal, 5 résolutions, parcours) |
| `05-07_test_*.sql` | non | Jeux de données de recette (volume, pondération, incidents) |

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
