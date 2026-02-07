-- 008_default_export_templates.sql
-- Cree des templates d'export par defaut pour chaque type.
-- Ces templates servent de point de depart pour les utilisateurs.
-- Idempotent : ne reinsere pas si deja present.

BEGIN;

-- ============================================================
-- Fonction pour creer les templates par defaut si inexistants
-- ============================================================
CREATE OR REPLACE FUNCTION create_default_export_templates(p_tenant_id UUID)
RETURNS void AS $$
BEGIN
    -- Template: Feuille d'emargement (attendance)
    INSERT INTO export_templates (tenant_id, name, export_type, columns, is_default)
    SELECT p_tenant_id, 'Feuille d''emargement standard', 'attendance',
           '[
               {"field": "full_name", "label": "Nom", "order": 1},
               {"field": "voting_power", "label": "Pouvoir de vote", "order": 2},
               {"field": "mode", "label": "Mode de presence", "order": 3},
               {"field": "checked_in_at", "label": "Arrivee", "order": 4},
               {"field": "checked_out_at", "label": "Depart", "order": 5},
               {"field": "proxy_to_name", "label": "Represente par", "order": 6},
               {"field": "proxies_received", "label": "Procurations detenues", "order": 7}
           ]'::jsonb, true
    WHERE NOT EXISTS (
        SELECT 1 FROM export_templates WHERE tenant_id = p_tenant_id AND export_type = 'attendance' AND is_default = true
    );

    INSERT INTO export_templates (tenant_id, name, export_type, columns, is_default)
    SELECT p_tenant_id, 'Emargement complet avec emails', 'attendance',
           '[
               {"field": "full_name", "label": "Nom", "order": 1},
               {"field": "email", "label": "Email", "order": 2},
               {"field": "voting_power", "label": "Pouvoir de vote", "order": 3},
               {"field": "mode", "label": "Mode de presence", "order": 4},
               {"field": "checked_in_at", "label": "Arrivee", "order": 5},
               {"field": "checked_out_at", "label": "Depart", "order": 6},
               {"field": "proxy_to_name", "label": "Represente par", "order": 7},
               {"field": "proxies_received", "label": "Procurations detenues", "order": 8},
               {"field": "notes", "label": "Notes", "order": 9}
           ]'::jsonb, false
    WHERE NOT EXISTS (
        SELECT 1 FROM export_templates WHERE tenant_id = p_tenant_id AND name = 'Emargement complet avec emails'
    );

    -- Template: Votes nominatifs (votes)
    INSERT INTO export_templates (tenant_id, name, export_type, columns, is_default)
    SELECT p_tenant_id, 'Votes nominatifs standard', 'votes',
           '[
               {"field": "motion_position", "label": "N resolution", "order": 1},
               {"field": "motion_title", "label": "Resolution", "order": 2},
               {"field": "member_name", "label": "Votant", "order": 3},
               {"field": "choice", "label": "Vote", "order": 4},
               {"field": "weight", "label": "Poids", "order": 5},
               {"field": "cast_at", "label": "Date/Heure", "order": 6}
           ]'::jsonb, true
    WHERE NOT EXISTS (
        SELECT 1 FROM export_templates WHERE tenant_id = p_tenant_id AND export_type = 'votes' AND is_default = true
    );

    INSERT INTO export_templates (tenant_id, name, export_type, columns, is_default)
    SELECT p_tenant_id, 'Votes avec procurations', 'votes',
           '[
               {"field": "motion_position", "label": "N resolution", "order": 1},
               {"field": "motion_title", "label": "Resolution", "order": 2},
               {"field": "member_name", "label": "Votant", "order": 3},
               {"field": "choice", "label": "Vote", "order": 4},
               {"field": "weight", "label": "Poids", "order": 5},
               {"field": "is_proxy", "label": "Par procuration", "order": 6},
               {"field": "on_behalf_of", "label": "Au nom de", "order": 7},
               {"field": "source", "label": "Mode", "order": 8},
               {"field": "cast_at", "label": "Date/Heure", "order": 9}
           ]'::jsonb, false
    WHERE NOT EXISTS (
        SELECT 1 FROM export_templates WHERE tenant_id = p_tenant_id AND name = 'Votes avec procurations'
    );

    -- Template: Membres (members)
    INSERT INTO export_templates (tenant_id, name, export_type, columns, is_default)
    SELECT p_tenant_id, 'Liste des membres', 'members',
           '[
               {"field": "full_name", "label": "Nom", "order": 1},
               {"field": "email", "label": "Email", "order": 2},
               {"field": "voting_power", "label": "Pouvoir de vote", "order": 3},
               {"field": "role", "label": "Role", "order": 4},
               {"field": "is_active", "label": "Actif", "order": 5}
           ]'::jsonb, true
    WHERE NOT EXISTS (
        SELECT 1 FROM export_templates WHERE tenant_id = p_tenant_id AND export_type = 'members' AND is_default = true
    );

    INSERT INTO export_templates (tenant_id, name, export_type, columns, is_default)
    SELECT p_tenant_id, 'Annuaire complet', 'members',
           '[
               {"field": "full_name", "label": "Nom", "order": 1},
               {"field": "email", "label": "Email", "order": 2},
               {"field": "phone", "label": "Telephone", "order": 3},
               {"field": "voting_power", "label": "Pouvoir de vote", "order": 4},
               {"field": "role", "label": "Role", "order": 5},
               {"field": "is_active", "label": "Actif", "order": 6},
               {"field": "created_at", "label": "Cree le", "order": 7},
               {"field": "notes", "label": "Notes", "order": 8}
           ]'::jsonb, false
    WHERE NOT EXISTS (
        SELECT 1 FROM export_templates WHERE tenant_id = p_tenant_id AND name = 'Annuaire complet'
    );

    -- Template: Resolutions (motions)
    INSERT INTO export_templates (tenant_id, name, export_type, columns, is_default)
    SELECT p_tenant_id, 'Resultats des resolutions', 'motions',
           '[
               {"field": "position", "label": "N", "order": 1},
               {"field": "title", "label": "Resolution", "order": 2},
               {"field": "for_count", "label": "Pour", "order": 3},
               {"field": "against_count", "label": "Contre", "order": 4},
               {"field": "abstain_count", "label": "Abstention", "order": 5},
               {"field": "total_expressed", "label": "Total exprimes", "order": 6},
               {"field": "decision", "label": "Decision", "order": 7}
           ]'::jsonb, true
    WHERE NOT EXISTS (
        SELECT 1 FROM export_templates WHERE tenant_id = p_tenant_id AND export_type = 'motions' AND is_default = true
    );

    INSERT INTO export_templates (tenant_id, name, export_type, columns, is_default)
    SELECT p_tenant_id, 'Resultats detailles avec horaires', 'motions',
           '[
               {"field": "position", "label": "N", "order": 1},
               {"field": "title", "label": "Resolution", "order": 2},
               {"field": "description", "label": "Description", "order": 3},
               {"field": "opened_at", "label": "Ouverture", "order": 4},
               {"field": "closed_at", "label": "Cloture", "order": 5},
               {"field": "for_count", "label": "Pour", "order": 6},
               {"field": "against_count", "label": "Contre", "order": 7},
               {"field": "abstain_count", "label": "Abstention", "order": 8},
               {"field": "nspp_count", "label": "NSPP", "order": 9},
               {"field": "voter_count", "label": "Nb votants", "order": 10},
               {"field": "total_expressed", "label": "Total exprimes", "order": 11},
               {"field": "decision", "label": "Decision", "order": 12}
           ]'::jsonb, false
    WHERE NOT EXISTS (
        SELECT 1 FROM export_templates WHERE tenant_id = p_tenant_id AND name = 'Resultats detailles avec horaires'
    );

    -- Template: Audit (audit)
    INSERT INTO export_templates (tenant_id, name, export_type, columns, is_default)
    SELECT p_tenant_id, 'Journal d''audit standard', 'audit',
           '[
               {"field": "timestamp", "label": "Horodatage", "order": 1},
               {"field": "actor", "label": "Acteur", "order": 2},
               {"field": "action", "label": "Action", "order": 3},
               {"field": "entity_type", "label": "Entite", "order": 4},
               {"field": "entity_id", "label": "ID Entite", "order": 5},
               {"field": "details", "label": "Details", "order": 6}
           ]'::jsonb, true
    WHERE NOT EXISTS (
        SELECT 1 FROM export_templates WHERE tenant_id = p_tenant_id AND export_type = 'audit' AND is_default = true
    );

    INSERT INTO export_templates (tenant_id, name, export_type, columns, is_default)
    SELECT p_tenant_id, 'Audit complet avec IP', 'audit',
           '[
               {"field": "timestamp", "label": "Horodatage", "order": 1},
               {"field": "actor", "label": "Acteur", "order": 2},
               {"field": "action", "label": "Action", "order": 3},
               {"field": "entity_type", "label": "Entite", "order": 4},
               {"field": "entity_id", "label": "ID Entite", "order": 5},
               {"field": "details", "label": "Details", "order": 6},
               {"field": "ip_address", "label": "Adresse IP", "order": 7}
           ]'::jsonb, false
    WHERE NOT EXISTS (
        SELECT 1 FROM export_templates WHERE tenant_id = p_tenant_id AND name = 'Audit complet avec IP'
    );

    -- Template: Procurations (proxies)
    INSERT INTO export_templates (tenant_id, name, export_type, columns, is_default)
    SELECT p_tenant_id, 'Liste des procurations', 'proxies',
           '[
               {"field": "giver_name", "label": "Mandant", "order": 1},
               {"field": "receiver_name", "label": "Mandataire", "order": 2},
               {"field": "scope", "label": "Portee", "order": 3},
               {"field": "created_at", "label": "Creee le", "order": 4},
               {"field": "revoked_at", "label": "Revoquee le", "order": 5}
           ]'::jsonb, true
    WHERE NOT EXISTS (
        SELECT 1 FROM export_templates WHERE tenant_id = p_tenant_id AND export_type = 'proxies' AND is_default = true
    );

    INSERT INTO export_templates (tenant_id, name, export_type, columns, is_default)
    SELECT p_tenant_id, 'Procurations avec contacts', 'proxies',
           '[
               {"field": "giver_name", "label": "Mandant", "order": 1},
               {"field": "giver_email", "label": "Email mandant", "order": 2},
               {"field": "receiver_name", "label": "Mandataire", "order": 3},
               {"field": "receiver_email", "label": "Email mandataire", "order": 4},
               {"field": "scope", "label": "Portee", "order": 5},
               {"field": "created_at", "label": "Creee le", "order": 6},
               {"field": "revoked_at", "label": "Revoquee le", "order": 7}
           ]'::jsonb, false
    WHERE NOT EXISTS (
        SELECT 1 FROM export_templates WHERE tenant_id = p_tenant_id AND name = 'Procurations avec contacts'
    );
END;
$$ LANGUAGE plpgsql;

-- ============================================================
-- Creer les templates par defaut pour tous les tenants existants
-- ============================================================
DO $$
DECLARE
    t_id UUID;
BEGIN
    FOR t_id IN SELECT id FROM tenants LOOP
        PERFORM create_default_export_templates(t_id);
    END LOOP;
END;
$$;

-- ============================================================
-- Trigger pour creer les templates par defaut pour nouveaux tenants
-- ============================================================
CREATE OR REPLACE FUNCTION on_tenant_created_create_export_templates()
RETURNS TRIGGER AS $$
BEGIN
    PERFORM create_default_export_templates(NEW.id);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_tenant_create_export_templates ON tenants;
CREATE TRIGGER trg_tenant_create_export_templates
    AFTER INSERT ON tenants
    FOR EACH ROW
    EXECUTE FUNCTION on_tenant_created_create_export_templates();

COMMIT;
