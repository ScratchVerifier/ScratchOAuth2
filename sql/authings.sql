BEGIN;

CREATE TABLE IF NOT EXISTS /*_*/soa2_authings (
	-- the token-getter code
	code char(64) binary NOT NULL,
	-- who is getting the token
	client_id integer unsigned NOT NULL,
	-- who is giving the token
	user_id integer unsigned NOT NULL,
	-- arbitrary string
	state varchar(255) binary NOT NULL,
	-- URI to redirect to (can be null)
	redirect_uri varchar(512) binary,
	-- space-separated list of scopes being requested
	scopes varchar(255) binary NOT NULL,
	-- unix timestamp when this request expires
	expiry integer unsigned NOT NULL,
	-- keys
	PRIMARY KEY(code),
	FOREIGN KEY(client_id) REFERENCES /*_*/soa2_applications(client_id),
	FOREIGN KEY(user_id) REFERENCES /*_*/soa2_scratchers(user_id)
) /*$wgDBTableOptions*/;

COMMIT;