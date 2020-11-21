PRAGMA foreign_keys = ON;

-- permanent data

CREATE TABLE IF NOT EXISTS scratch_users (
  -- Scratch user ID
  user_id integer PRIMARY KEY,
  -- Scratch username
  user_name text UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS applications (
  -- client ID
  client_id integer PRIMARY KEY,
  -- client secret
  client_secret text UNIQUE NOT NULL,
  -- display name of app
  app_name text,
  -- Scratch user ID of owner
  owner_id integer NOT NULL,
  -- owner
  owner_name text NOT NULL,
  -- app names must be approved
  name_approved boolean DEFAULT FALSE,
  -- FK
  FOREIGN KEY(owner_id) REFERENCES scratch_users(user_id)
);

CREATE TABLE IF NOT EXISTS approvals (
  -- Scratch user ID of approver
  user_id integer NOT NULL,
  -- username as well
  user_name text NOT NULL,
  -- client ID being approved
  client_id integer NOT NULL,
  -- stringified list of scopes
  scopes text,
  -- keys
  PRIMARY KEY(user_id, client_id),
  FOREIGN KEY(user_id) REFERENCES scratch_users(user_id),
  FOREIGN KEY(client_id) REFERENCES applications(client_id)
);

CREATE TABLE IF NOT EXISTS redirect_uris (
  -- application ID this redirect URI is for
  client_id integer PRIMARY KEY,
  -- URI in question
  redirect_uri text NOT NULL,
  -- FK
  FOREIGN KEY(client_id) REFERENCES applications(client_id)
);

-- transient data

DROP TABLE IF EXISTS sessions;
CREATE TABLE sessions (
  session_id integer PRIMARY KEY,
  -- Scratch user ID of session owner
  user_id integer NOT NULL,
  -- expiry time: unix epoch integer
  expiry integer,
  -- reference to auth process data, if in the midst
  authing text,
  -- keys
  FOREIGN KEY(user_id) REFERENCES scratch_users(user_id),
  FOREIGN KEY(authing) REFERENCES authings(codetoken)
);

CREATE TABLE IF NOT EXISTS authings (
  -- token-get code, or refresh token
  codetoken text PRIMARY KEY,
  -- the following are all data used during auth
  client_id integer NOT NULL,
  redirect_uri text NOT NULL,
  scopes text NOT NULL,
  state text,
  -- FK
  FOREIGN KEY(client_id) REFERENCES applications(client_id)
);