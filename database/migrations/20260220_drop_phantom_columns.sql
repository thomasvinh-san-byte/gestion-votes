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

ALTER TABLE members DROP COLUMN IF EXISTS vote_weight;

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
