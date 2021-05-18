BEGIN;

ALTER TABLE /*_*/soa2_redirect_uris DROP PRIMARY KEY,
ADD PRIMARY KEY(redirect_uri, client_id);

COMMIT;