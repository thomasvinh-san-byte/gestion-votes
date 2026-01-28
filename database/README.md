# Bundle SQL PostgreSQL — source de vérité

Ce bundle remplace les variantes historiques (`setup_bdd_postgre.sql`, `schema.sql`, `setup.sql`, `seed_*`)
par **une source unique** alignée sur les endpoints actuels (`/public/api/v1`).

## Fichiers

- `database/schema.sql` : schéma canonique Postgres (idempotent)
- `database/seed_minimal.sql` : 1 tenant (DEFAULT_TENANT_ID) + politiques + users RBAC (sans API key)
- `database/seed_demo.sql` : séance/membres/motions pour smoke test UI
- `database/setup.sql` : rappel d'ordre d'exécution

## Installation

```bash
psql "$DATABASE_URL" -f database/schema.sql
psql "$DATABASE_URL" -f database/seed_minimal.sql
psql "$DATABASE_URL" -f database/seed_demo.sql
```

## Notes importantes

- `DEFAULT_TENANT_ID` dans `app/bootstrap.php` est `aaaaaaaa-1111-2222-3333-444444444444` : le seed le crée.
- Si `APP_AUTH_ENABLED=1`, il faut créer/rotater les clés via l'endpoint `ADMIN` (ex: `POST /api/v1/admin_users.php`),
  car le seed ne peut pas calculer `api_key_hash` sans connaître `APP_SECRET`.
- Compat ajoutée : `motions.body` est auto-remplie depuis `motions.description` pour les écrans qui lisent `body`.
- Compat ajoutée : `ballots.meeting_id` est auto-remplie depuis `motions.meeting_id` (certains services n'envoient pas meeting_id).

## Prochaine étape (code)

Après ce bundle SQL, la prochaine étape est de supprimer/bypasser définitivement la branche MySQL (`/public/agvote`, `bootstrap_agvote.php`)
et de vérifier les derniers `require` cassés (vote.php et quelques endpoints legacy).