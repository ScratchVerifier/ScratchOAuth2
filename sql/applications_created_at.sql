BEGIN;

ALTER TABLE /*_*/soa2_applications ADD COLUMN IF NOT EXISTS created_at integer unsigned NOT NULL;
-- since we have no way of knowing now when any previous ones were created,
-- pretend they were all created one second after the other, up to now
SELECT @i:=UNIX_TIMESTAMP()-(SELECT COUNT(*) FROM /*_*/soa2_applications);
UPDATE /*_*/soa2_applications SET created_at=@i:=@i+1;

COMMIT;