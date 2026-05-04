-- Idempotent: normalise any 'men'/'women' values in sport_events.gender
-- into the canonical 'male'/'female' that the athlete profile uses.
-- Safe to paste into phpMyAdmin → SQL.

-- Step 1: widen the ENUM temporarily so the UPDATEs don't get coerced
-- to '' under strict mode (no-op if the column already admits the
-- legacy spellings).
ALTER TABLE sport_events
  MODIFY COLUMN gender ENUM('male','female','mixed','men','women') NOT NULL;

-- Step 2: rewrite legacy values onto canonical ones.
UPDATE sport_events SET gender = 'male'   WHERE gender = 'men';
UPDATE sport_events SET gender = 'female' WHERE gender = 'women';

-- Step 3: narrow the ENUM back to the canonical three values.
ALTER TABLE sport_events
  MODIFY COLUMN gender ENUM('male','female','mixed') NOT NULL;

-- Sanity check (optional): expected to return 0 rows.
SELECT id, name, gender FROM sport_events WHERE gender NOT IN ('male','female','mixed');
