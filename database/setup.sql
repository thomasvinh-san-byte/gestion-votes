-- database/setup.sql
-- Appliquer le schéma + seeds.
-- Usage:
--   psql "$DATABASE_URL" -f database/schema.sql
--   psql "$DATABASE_URL" -f database/seed_minimal.sql
--   psql "$DATABASE_URL" -f database/seed_demo.sql

\echo 'Apply database/schema.sql then database/seed_minimal.sql then (optional) database/seed_demo.sql'