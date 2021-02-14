BEGIN;

CREATE TABLE IF NOT EXISTS /*_*/soa2_applications (
	-- application ID
	client_id integer unsigned NOT NULL,
	-- secret key
	client_secret char(128) binary NOT NULL UNIQUE,
	-- app name, must be moderated
	app_name varchar(255) binary,
	-- Scratch user ID of owner
	owner_id integer unsigned NOT NULL,
	-- misc flags
	flags integer unsigned NOT NULL DEFAULT 0,
	-- keys
	PRIMARY KEY(client_id),
	FOREIGN KEY(owner_id) REFERENCES /*_*/soa2_scratchers(user_id)
) /*$wgDBTableOptions*/;

CREATE INDEX IF NOT EXISTS /*i*/soa2_owners ON /*_*/soa2_applications(owner_id);

COMMIT;