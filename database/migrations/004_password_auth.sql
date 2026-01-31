-- 004_password_auth.sql
-- Ajoute l'authentification par email/mot de passe (bcrypt).
-- Idempotent : peut être relancé sans effet si déjà appliqué.

-- Colonne password_hash (bcrypt = 60 chars, on prend text pour flexibilité argon2)
ALTER TABLE users ADD COLUMN IF NOT EXISTS password_hash text;

-- Rendre email NOT NULL pour les futurs utilisateurs
-- (les existants peuvent avoir NULL, on ne force pas la migration)
COMMENT ON COLUMN users.password_hash IS 'Hash bcrypt/argon2 du mot de passe. NULL si auth par clé API uniquement.';
