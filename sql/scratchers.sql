BEGIN;

CREATE TABLE IF NOT EXISTS /*_*/soa2_scratchers (
	-- Scratch user ID
	user_id integer unsigned NOT NULL,
	-- Scratch username
	user_name varchar(20) binary NOT NULL UNIQUE,
	-- Arbitrary data that may be used later
	user_data varchar(1024) binary,
	-- keys
	PRIMARY KEY(user_id)
) /*$wgDBTableOptions*/;

CREATE INDEX IF NOT EXISTS /*i*/soa2_usernames ON /*_*/soa2_scratchers(user_name);

COMMIT;