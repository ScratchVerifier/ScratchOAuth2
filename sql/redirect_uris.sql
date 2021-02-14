BEGIN;

CREATE TABLE IF NOT EXISTS /*_*/soa2_redirect_uris (
	-- the URI
	redirect_uri varchar(512) binary NOT NULL,
	-- which app's URI?
	client_id integer unsigned NOT NULL,
	-- keys
	PRIMARY KEY(redirect_uri),
	FOREIGN KEY(client_id) REFERENCES /*_*/soa2_applications(client_id)
) /*$wgDBTableOptions*/;

COMMIT;