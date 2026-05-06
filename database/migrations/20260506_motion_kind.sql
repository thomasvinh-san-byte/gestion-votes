-- ─────────────────────────────────────────────────────────────────────────
-- Migration: motion.kind — typer la nature d'une motion (resolution / …)
-- ─────────────────────────────────────────────────────────────────────────
-- M-INFRA-CLEANUP / CLEANUP-CHEMIN-MOTION-KIND
--
-- Stage 1 audit (CRITICAL-PATH-AUDIT.md étape 03) a relevé que la table
-- motions ne portait pas la nature de la motion : tout passait pour une
-- résolution alors que le domaine en distingue plusieurs. Pas de scrutin
-- majoritaire dans le scope du pivot (cf. DECISION.md), mais on ajoute la
-- colonne pour rendre le gap explicite dans le schéma et préparer le
-- terrain sans réécriture future.
--
-- Comportement :
-- - kind TEXT NOT NULL DEFAULT 'resolution'
--   → toutes les motions existantes deviennent 'resolution' (compat retrofit)
--   → toute nouvelle motion sans kind explicite est 'resolution'
-- - CHECK constraint : pour l'instant uniquement 'resolution', à enrichir
--   quand un autre kind est implémenté (ex. 'election', 'consent').
--
-- Idempotence : ADD COLUMN IF NOT EXISTS + ADD CONSTRAINT IF NOT EXISTS
-- (PostgreSQL >= 9.6).
-- ─────────────────────────────────────────────────────────────────────────

ALTER TABLE motions
    ADD COLUMN IF NOT EXISTS kind TEXT NOT NULL DEFAULT 'resolution';

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM   pg_constraint
        WHERE  conname = 'motions_kind_check'
    ) THEN
        ALTER TABLE motions
            ADD CONSTRAINT motions_kind_check
            CHECK (kind IN ('resolution'));
    END IF;
END
$$;

-- Index opportuniste pour filtrer par kind quand de nouvelles valeurs
-- arriveront. Pas obligatoire aujourd'hui (1 seule valeur), mais préparer
-- le couvrant évite une migration supplémentaire au moment de l'extension.
CREATE INDEX IF NOT EXISTS idx_motions_kind
    ON motions (kind);
