BEGIN;

CREATE TABLE IF NOT EXISTS /*_*/soa2_access_tokens (
	-- the access token
	token char(128) binary NOT NULL,
	-- the refresh token that granted it
	refresh_token char(128) binary NOT NULL,
	-- who can use this token
	client_id integer unsigned NOT NULL,
	-- who this token is for
	user_id integer unsigned NOT NULL,
	-- unix timestamp when this token expires
	expiry integer unsigned NOT NULL,
	-- keys
	PRIMARY KEY(token),
	FOREIGN KEY(refresh_token) REFERENCES /*_*/soa2_refresh_tokens(token),
	FOREIGN KEY(client_id) REFERENCES /*_*/soa2_applications(client_id),
	FOREIGN KEY(user_id) REFERENCES /*_*/soa2_scratchers(user_id)
) /*$wgDBTableOptions*/;

CREATE INDEX IF NOT EXISTS /*i*/soa2_access_refresh ON /*_*/soa2_access_tokens(refresh_token);

COMMIT;