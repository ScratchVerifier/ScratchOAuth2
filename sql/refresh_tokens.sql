BEGIN;

CREATE TABLE IF NOT EXISTS /*_*/soa2_refresh_tokens (
	-- the refresh token
	token char(128) binary NOT NULL,
	-- who can use this token
	client_id integer unsigned NOT NULL,
	-- who this token is for
	user_id integer unsigned NOT NULL,
	-- space-separated list of scopes granted
	scopes varchar(255) binary NOT NULL,
	-- unix timestamp when this token expires
	expiry integer unsigned NOT NULL,
	-- keys
	PRIMARY KEY(token),
	FOREIGN KEY(client_id) REFERENCES /*_*/soa2_applications(client_id),
	FOREIGN KEY(user_id) REFERENCES /*_*/soa2_scratchers(user_id)
) /*$wgDBTableOptions*/;

COMMIT;