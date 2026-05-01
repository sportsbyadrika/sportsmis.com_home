-- Migrate events.status to the new 4-state vocabulary.
-- Idempotent: safe to paste into phpMyAdmin → SQL and re-run.

-- 1. Widen the ENUM so old + new values coexist.
ALTER TABLE events MODIFY COLUMN status ENUM(
  'draft','pending_approval','approved','rejected','completed','cancelled','active','suspended'
) NOT NULL DEFAULT 'draft';

-- 2. Backfill legacy values onto the new vocabulary.
UPDATE events SET status = 'active'    WHERE status IN ('pending_approval','approved');
UPDATE events SET status = 'suspended' WHERE status IN ('rejected','cancelled');

-- The application normalises any remaining legacy values at display time,
-- so narrowing the ENUM to just the four new values is optional and
-- intentionally NOT included here to avoid data loss on rows that
-- haven't been backfilled (e.g. partial backups).
