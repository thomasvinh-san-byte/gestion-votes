-- Migration: Ajout de slugs pour obfuscation des URLs
-- Date: 2026-02-04
-- Description: Ajoute des colonnes slug aux tables meetings et motions pour des URLs plus lisibles et opaques

-- ============================================================
-- Ajout du slug sur meetings
-- ============================================================
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS slug TEXT;

-- Index unique pour recherche rapide par slug
CREATE UNIQUE INDEX IF NOT EXISTS ux_meetings_tenant_slug ON meetings(tenant_id, slug);

-- ============================================================
-- Ajout du slug sur motions
-- ============================================================
ALTER TABLE motions ADD COLUMN IF NOT EXISTS slug TEXT;

-- Index unique pour recherche rapide par slug
CREATE UNIQUE INDEX IF NOT EXISTS ux_motions_meeting_slug ON motions(meeting_id, slug);

-- ============================================================
-- Sécurisation des tokens d'invitation
-- ============================================================
-- Ajout d'une colonne pour le hash du token (le token brut sera supprimé)
ALTER TABLE invitations ADD COLUMN IF NOT EXISTS token_hash CHAR(64);

-- Index pour recherche par hash
CREATE INDEX IF NOT EXISTS idx_invitations_token_hash ON invitations(token_hash);

-- Ajout d'une colonne d'expiration pour les invitations
ALTER TABLE invitations ADD COLUMN IF NOT EXISTS expires_at TIMESTAMPTZ;

-- ============================================================
-- Fonction pour générer un slug automatiquement
-- ============================================================
CREATE OR REPLACE FUNCTION generate_slug(title TEXT, uuid_val UUID)
RETURNS TEXT AS $$
DECLARE
    base_slug TEXT;
    suffix TEXT;
BEGIN
    -- Normaliser le titre
    base_slug := lower(translate(title, 'àâäéèêëîïôùûüç', 'aaaeeeeiioouuc'));
    base_slug := regexp_replace(base_slug, '[^a-z0-9]+', '-', 'g');
    base_slug := trim(both '-' from base_slug);
    base_slug := left(base_slug, 40);

    -- Ajouter un suffixe basé sur l'UUID
    suffix := encode(substring(uuid_val::text::bytea from 1 for 4), 'hex');

    RETURN base_slug || '-' || suffix;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

-- ============================================================
-- Migration des données existantes (génération des slugs)
-- ============================================================
-- Générer les slugs pour les meetings existants sans slug
UPDATE meetings
SET slug = generate_slug(title, id)
WHERE slug IS NULL;

-- Générer les slugs pour les motions existantes sans slug
UPDATE motions
SET slug = generate_slug(title, id)
WHERE slug IS NULL;

-- ============================================================
-- Contraintes NOT NULL après migration des données
-- ============================================================
-- Note: Décommentez ces lignes après vérification de la migration
-- ALTER TABLE meetings ALTER COLUMN slug SET NOT NULL;
-- ALTER TABLE motions ALTER COLUMN slug SET NOT NULL;

-- ============================================================
-- Trigger pour génération automatique des slugs
-- ============================================================
CREATE OR REPLACE FUNCTION auto_generate_meeting_slug()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.slug IS NULL THEN
        NEW.slug := generate_slug(NEW.title, NEW.id);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_meetings_auto_slug ON meetings;
CREATE TRIGGER trg_meetings_auto_slug
    BEFORE INSERT ON meetings
    FOR EACH ROW
    EXECUTE FUNCTION auto_generate_meeting_slug();

CREATE OR REPLACE FUNCTION auto_generate_motion_slug()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.slug IS NULL THEN
        NEW.slug := generate_slug(NEW.title, NEW.id);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_motions_auto_slug ON motions;
CREATE TRIGGER trg_motions_auto_slug
    BEFORE INSERT ON motions
    FOR EACH ROW
    EXECUTE FUNCTION auto_generate_motion_slug();

-- ============================================================
-- Commentaires sur les nouvelles colonnes
-- ============================================================
COMMENT ON COLUMN meetings.slug IS 'Identifiant URL court et opaque pour cette séance';
COMMENT ON COLUMN motions.slug IS 'Identifiant URL court et opaque pour cette résolution';
COMMENT ON COLUMN invitations.token_hash IS 'Hash SHA256 du token d''invitation (sécurité)';
COMMENT ON COLUMN invitations.expires_at IS 'Date d''expiration de l''invitation';
