PRAGMA foreign_keys = ON;

-- permanent data

CREATE TABLE IF NOT EXISTS scratch_users (
  -- Scratch user ID
  user_id integer PRIMARY KEY,
  -- Scratch username
  user_name text UNIQUE NOT NULL,
  -- arbitrary data in case we need it
  data text
);
CREATE INDEX IF NOT EXISTS usernames ON scratch_users(user_name);

CREATE TABLE IF NOT EXISTS applications (
  -- client ID
  client_id integer PRIMARY KEY,
  -- client secret
  client_secret text UNIQUE NOT NULL,
  -- display name of app
  app_name text,
  -- Scratch user ID of owner
  owner_id integer NOT NULL,
  -- app names must be approved
  approved boolean DEFAULT FALSE,
  -- FK
  FOREIGN KEY(owner_id) REFERENCES scratch_users(user_id)
);

CREATE TRIGGER IF NOT EXISTS reset_approval AFTER UPDATE OF app_name ON applications
BEGIN UPDATE applications SET approved=(NEW.app_name IS NULL) WHERE client_id=NEW.client_id; END;
CREATE TRIGGER IF NOT EXISTS set_approval AFTER INSERT ON applications
BEGIN UPDATE applications SET approved=(NEW.app_name IS NULL) WHERE client_id=NEW.client_id; END;

CREATE TABLE IF NOT EXISTS approvals (
  -- refresh token to get new access token
  refresh_token text PRIMARY KEY,
  -- Scratch user ID of approver
  user_id integer NOT NULL,
  -- client ID being approved
  client_id integer NOT NULL,
  -- access token currently in use, nullable
  access_token text,
  -- stringified list of scopes
  scopes text NOT NULL,
  -- approvals don't last forever, only until this time
  expiry integer NOT NULL,
  -- FKs
  FOREIGN KEY(user_id) REFERENCES scratch_users(user_id),
  FOREIGN KEY(client_id) REFERENCES applications(client_id),
  FOREIGN KEY(access_token) REFERENCES authings(code)
);

CREATE TABLE IF NOT EXISTS redirect_uris (
  -- URI in question
  redirect_uri text PRIMARY KEY,
  -- application ID this redirect URI is for
  client_id integer NOT NULL,
  -- FK
  FOREIGN KEY(client_id) REFERENCES applications(client_id)
);

-- transient data

CREATE TABLE IF NOT EXISTS sessions (
  session_id integer PRIMARY KEY,
  -- Scratch user ID of session owner
  user_id integer,
  -- expiry time: unix epoch integer
  expiry integer NOT NULL,
  -- reference to auth process data, if in the midst
  authing text,
  -- nonce used for login
  nonce text,
  -- keys
  FOREIGN KEY(user_id) REFERENCES scratch_users(user_id),
  FOREIGN KEY(authing) REFERENCES authings(code)
);

CREATE TABLE IF NOT EXISTS authings (
  -- token-get code, or access token
  code text PRIMARY KEY,
  -- data associated with this access token
  -- or if is token-get code, data associated
  -- with this to-be-access token
  client_id integer NOT NULL,
  redirect_uri text NOT NULL,
  scopes text NOT NULL,
  state text,
  expiry integer,
  -- FK
  FOREIGN KEY(client_id) REFERENCES applications(client_id)
  -- this is an alternate index
  UNIQUE(client_id, state)
);
CREATE INDEX IF NOT EXISTS authing_creators
ON authings(client_id, state) WHERE state IS NOT NULL;

PRAGMA user_version = 1;
