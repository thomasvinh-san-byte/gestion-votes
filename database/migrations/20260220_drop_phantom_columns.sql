-- Migration: Drop phantom/legacy columns
-- These columns are superseded by their replacements and all application code
-- has been updated to use only the canonical columns.
--
-- Prerequisite: run the data migration FIRST, then drop the columns.

BEGIN;

-- 1. members.vote_weight → voting_power
-- Backfill any rows where voting_power was never set
UPDATE members
   SET voting_power = COALESCE(voting_power, vote_weight, 1.0)
 WHERE voting_power IS NULL;

-- Drop views that depend on members columns (vote_weight or m.*)
DROP VIEW IF EXISTS members_with_groups;
DROP VIEW IF EXISTS member_groups_with_count;

ALTER TABLE members DROP COLUMN IF EXISTS vote_weight;

-- Recreate views with canonical column names
CREATE OR REPLACE VIEW member_groups_with_count AS
SELECT
    mg.id,
    mg.tenant_id,
    mg.name,
    mg.description,
    mg.color,
    mg.sort_order,
    mg.is_active,
    mg.created_at,
    mg.updated_at,
    COUNT(mga.member_id) FILTER (WHERE m.is_active = true AND m.deleted_at IS NULL) AS member_count,
    COALESCE(SUM(COALESCE(m.voting_power, 1.0)) FILTER (WHERE m.is_active = true AND m.deleted_at IS NULL), 0) AS total_weight
FROM member_groups mg
LEFT JOIN member_group_assignments mga ON mga.group_id = mg.id
LEFT JOIN members m ON m.id = mga.member_id
GROUP BY mg.id, mg.tenant_id, mg.name, mg.description, mg.color, mg.sort_order, mg.is_active, mg.created_at, mg.updated_at;

CREATE OR REPLACE VIEW members_with_groups AS
SELECT
    m.*,
    COALESCE(STRING_AGG(mg.name, ', ' ORDER BY mg.sort_order, mg.name), '') AS group_names,
    COALESCE(ARRAY_AGG(mg.id ORDER BY mg.sort_order, mg.name) FILTER (WHERE mg.id IS NOT NULL), ARRAY[]::uuid[]) AS group_ids
FROM members m
LEFT JOIN member_group_assignments mga ON mga.member_id = m.id
LEFT JOIN member_groups mg ON mg.id = mga.group_id AND mg.is_active = true
GROUP BY m.id;

-- 2. ballots.choice → value
-- Backfill any rows where value was never set
UPDATE ballots
   SET value = choice
 WHERE value IS NULL AND choice IS NOT NULL;

ALTER TABLE ballots DROP COLUMN IF EXISTS choice;

-- 3. ballots.effective_power → weight
-- Backfill any rows where weight was never set
UPDATE ballots
   SET weight = COALESCE(weight, effective_power)
 WHERE weight IS NULL AND effective_power IS NOT NULL;

ALTER TABLE ballots DROP COLUMN IF EXISTS effective_power;

-- 4. motions.status (dead column — state determined by opened_at/closed_at)
ALTER TABLE motions DROP COLUMN IF EXISTS status;

-- 5. motions.sort_order → position
-- Backfill any rows where position was never set
UPDATE motions
   SET position = COALESCE(position, sort_order)
 WHERE position IS NULL AND sort_order IS NOT NULL;

ALTER TABLE motions DROP COLUMN IF EXISTS sort_order;

COMMIT;
