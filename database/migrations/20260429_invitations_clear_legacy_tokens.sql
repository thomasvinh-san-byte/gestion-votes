-- ─────────────────────────────────────────────────────────────────────────
-- Migration: clear legacy plaintext invitation tokens
-- ─────────────────────────────────────────────────────────────────────────
-- F07 (Phase 2 v2.1 hardening sécurité):
--
-- The 'invitations' table historically stored the raw token in the 'token'
-- column. Newer code (since 2025) stores SHA-256 hashes in 'token_hash' and
-- nulls 'token' on every upsert. However any row whose 'token_hash' was
-- backfilled (via repo write paths) but whose 'token' was never re-touched
-- still carries the plaintext token in the row. Such tokens are usable
-- one-shot URLs to the voter form — a database read primitive (SQL injection,
-- backup compromise) leaks them directly.
--
-- This migration eliminates the leak by NULLing the legacy 'token' column
-- for every row that already has a 'token_hash'. The lookup path
-- ('InvitationRepository::findByToken') prefers 'token_hash' and only falls
-- back to plaintext for rows missing a hash — so this is a no-op for those
-- legacy rows that have not been re-issued.
--
-- Idempotent: safe to re-run (the WHERE filter excludes rows already cleared).
-- ─────────────────────────────────────────────────────────────────────────

UPDATE invitations
SET token = NULL
WHERE token IS NOT NULL
  AND token_hash IS NOT NULL;

-- Ensure new rows cannot be inserted with token NOT NULL when a hash is set.
-- The repository code does the right thing already, but a CHECK gives us
-- defense in depth and also prevents accidental backfill scripts from
-- regressing.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'invitations_token_hash_no_plaintext'
    ) THEN
        ALTER TABLE invitations
            ADD CONSTRAINT invitations_token_hash_no_plaintext
            CHECK (token IS NULL OR token_hash IS NULL);
    END IF;
END$$;
