BEGIN;

-- Scratch username, case sensitive
ALTER TABLE /*_*/soa2_scratchers ADD COLUMN IF NOT EXISTS user_name_cased varchar(20) binary UNIQUE;

COMMIT;