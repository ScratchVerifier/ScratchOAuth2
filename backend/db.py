from time import time
from secrets import randbits, token_hex
from typing import Optional, Tuple, List, Union, overload
import asyncio
import aiosqlite as sql
from config import AppFlags, LONG_EXPIRY, MEDIUM_EXPIRY, SHORT_EXPIRY, config, timestamp
import objs

__all__ = ['session', 'startup', 'teardown']

lock = asyncio.Lock()

class Database:
    """Handle database interactions."""

    db: sql.Connection

    def __init__(self, db: sql.Connection):
        self.db = db

class Session(Database):
    """Handle session database interactions."""

    async def get(self, session_id: int) -> Optional[objs.Session]:
        """Get a Session object by ID."""
        await self.expire()
        query = "SELECT * FROM sessions WHERE session_id=?"
        async with lock:
            await self.db.execute(query, (session_id,))
            row = await self.db.fetchone()
        if row is not None:
            return objs.Session(**row)
        return None

    async def create(self) -> int:
        """Create a new session and return its ID."""
        await self.expire()
        # Step 9
        session_id = randbits(62)
        expiry = int(time()) + SHORT_EXPIRY
        # Step 10
        query = "INSERT INTO sessions (session_id, expiry) VALUES (?, ?)"
        await self.db.execute(query, (session_id, expiry))
        return session_id

    async def expire(self):
        """Remove expired sessions."""
        query = "DELETE FROM sessions WHERE expiry<?"
        await self.db.execute(query, (int(time()),))

class Login(Database):
    """Handle login database interactions."""

    async def nonce(self, session_id: int) -> str:
        """Generate, set, and return a nonce."""
        nonce = token_hex(32) # Step 18
        # Step 19
        query = "UPDATE sessions SET nonce=? WHERE session_id=?"
        await self.db.execute(query, (nonce, session_id))
        # Step 20
        return nonce

    async def login_session(self, session_id: int, user_id: int):
        """Mark a session as logged in."""
        expiry = int(time()) + LONG_EXPIRY
        # Step 30-32
        query = "UPDATE sessions SET user_id=?, expiry=?, nonce=NULL " \
            "WHERE session_id=?"
        await self.db.execute(query, (user_id, expiry, session_id))

    async def logout(self, session_id: int):
        """Unmark a session as logged in."""
        expiry = int(time()) + SHORT_EXPIRY
        query = "UPDATE sessions SET user_id=NULL, expiry=?, " \
            "nonce=NULL WHERE session_id=?"
        await self.db.execute(query, (expiry, session_id))

class Applications(Database):
    """Handle client registration and management."""

    async def applications(self, owner_id: Optional[int]):
        """Get a list of partial applications."""
        query = "SELECT client_id, app_name FROM applications WHERE owner_id=?"
        async with lock:
            await self.db.execute(query, (owner_id,))
            data = await self.db.fetchall()
        return [objs.PartialApplication(**row) for row in data]

    async def application(self, owner_id: Optional[int], client_id: int):
        """Get a specific application."""
        if owner_id is not None:
            query1 = "SELECT client_id, client_secret, app_name, flags " \
                "FROM applications WHERE owner_id=? AND client_id=?"
            params = (owner_id, client_id)
        else:
            query1 = "SELECT client_id, client_secret, app_name, flags " \
                "FROM applications WHERE client_id=?"
            params = (client_id,)
        async with lock:
            await self.db.execute(query1, params)
            row = await self.db.fetchone()
        if row is None:
            return None
        data = dict(row)
        data['flags'] = AppFlags(data['flags'])
        query2 = "SELECT redirect_uri FROM redirect_uris WHERE client_id=?"
        async with lock:
            await self.db.execute(query2, (client_id,))
            rows = await self.db.fetchall()
        uris = [row[0] for row in rows]
        return objs.Application(redirect_uris=uris, **data)

    async def create_app(self, owner_id: int, app_name: Optional[str],
                         redirect_uris: List[str]):
        """Register a new application."""
        # Step 2
        client_id = randbits(32)
        client_secret = token_hex(64)
        # Step 3
        query1 = "INSERT INTO applications (client_id, client_secret, " \
            "app_name, owner_id) VALUES (?, ?, ?, ?)"
        await self.db.execute(query1, (client_id, client_secret,
                                       app_name, owner_id))
        # Step 4
        query2 = "INSERT INTO redirect_uris (client_id, redirect_uri) " \
            "VALUES (?, ?)"
        await self.db.executemany(query2, ((client_id, uri) for uri in redirect_uris))
        return await self.application(owner_id, client_id)

    async def update_app(self, client_id: int, client_secret: str = None,
                         app_name: str = ..., redirect_uris: List[str] = None):
        """Update data for an application."""
        query1 = "UPDATE applications SET "
        params = {}
        params['client_id'] = client_id
        if client_secret is not None:
            params['client_secret'] = token_hex(64)
            query1 += "client_secret=:client_secret, "
        if app_name is not ...:
            params['app_name'] = app_name
            query1 += "app_name=:app_name, "
        if query1.endswith(', '):
            query1 = query1[:-2] + " WHERE client_id=:client_id"
            await self.db.execute(query1, params)
        elif redirect_uris is None:
            raise ValueError('No update made')
        if redirect_uris is not None:
            query2 = "DELETE FROM redirect_uris WHERE client_id=?"
            query3 = "INSERT INTO redirect_uris (client_id, redirect_uri) " \
                "VALUES (?, ?)"
            await self.db.execute(query2, (client_id,))
            await self.db.executemany(
                query3, ((client_id, uri) for uri in redirect_uris))
        return await self.application(None, client_id)

    async def delete_app(self, client_id: int):
        """Delete an application."""
        querys = (
            "DELETE FROM redirect_uris WHERE client_id=?",
            "DELETE FROM approvals WHERE client_id=?",
            "DELETE FROM authings WHERE client_id=?",
            "DELETE FROM applications WHERE client_id=?"
        )
        for query in querys:
            await self.db.execute(query, (client_id,))

class Authorization(Database):
    """Handle app approval."""

    async def get_authing(self, query: str, params: Tuple):
        """Fetch data for an ongoing approval process."""
        await self.expire()
        async with lock:
            await self.db.execute(query, params)
            row = await self.db.fetchone()
        if row is None:
            return None
        data = dict(row)
        data['scopes'] = data['scopes'].split()
        return objs.Authing(**data)

    async def get_authing_by_creator(self, client_id: int, state: str):
        """Fetch ongoing approval data by a (client_id, state) pair."""
        query = "SELECT * FROM authings WHERE client_id=? AND state=?"
        return await self.get_authing(query, (client_id, state))

    async def get_authing_by_code(self, code: str):
        """Fetch ongoing approval data by the code."""
        query = "SELECT * FROM authings WHERE code=?"
        return await self.get_authing(query, (code,))

    async def start_auth(self, session_id: int, state: str, user_id: int,
                         client_id: int, redirect_uri: str, scopes: List[str]):
        """Begin the app approval process."""
        await self.expire()
        auth = await self.get_authing_by_creator(client_id, state)
        if auth is not None:
            return
        del auth
        # Step 35
        code = token_hex(32)
        expiry = int(time()) + SHORT_EXPIRY
        # Step 36
        query1 = "INSERT INTO authings (code, user_id, client_id, " \
            "redirect_uri, scopes, state, expiry) VALUES (?, ?, ?, ?, ?, ?, ?)"
        await self.db.execute(query1, (code, user_id, client_id, redirect_uri,
                                       ' '.join(scopes), state, expiry))
        # Step 37
        query2 = "UPDATE sessions SET authing=? WHERE session_id=?"
        await self.db.execute(query2, (code, session_id))

    async def cancel_auth(self, session_id: int, code: str):
        """Erase an approval process' records."""
        query1 = "UPDATE sessions SET authing=NULL WHERE session_id=?"
        await self.db.execute(query1, (session_id,))
        query2 = "DELETE FROM authings WHERE code=?"
        await self.db.execute(query2, (code,))
        await self.expire()

    async def expire(self):
        """Revoke authings that have expired."""
        now = int(time())
        query1 = "(SELECT code FROM authings WHERE expiry<?)"
        query2 = f"UPDATE sessions SET authing=NULL WHERE authing IN {query1}"
        await self.db.execute(query2, (now,))
        query3 = "DELETE FROM authings WHERE expiry<?"
        await self.db.execute(query3, (now,))

class Tokens(Database):
    """Manage refresh and access tokens."""

    async def new_access_token(self, code: str):
        """Generate and set an access token."""
        # almost like it was made for this
        access_token = token_hex(64) # Step 49
        expiry = int(time()) + MEDIUM_EXPIRY
        query = 'UPDATE authings SET code=?, expiry=?, state=NULL WHERE code=?'
        await self.db.execute(query, (access_token, expiry, code)) # Step 50, 51
        return (access_token, expiry)

    async def new_refresh_token(self, code: str, client_id: int,
                                access_token: str, scopes: List[str]):
        """Generate and set a refresh token."""
        # Step 52
        refresh_token = token_hex(64)
        expiry = int(time()) + LONG_EXPIRY
        # Step 53
        query1 = "SELECT user_id FROM sessions WHERE authing=?"
        async with lock:
            await self.db.execute(query1, (code,))
            row = await self.db.fetchone()
        if row is None or row[0] is None:
            raise ValueError('No authing')
        user_id = row[0]
        query2 = "DELETE FROM approvals WHERE user_id=? AND client_id=?"
        await self.db.execute(query2, (user_id, client_id))
        # Step 54
        query3 = "INSERT INTO approvals (refresh_token, user_id, client_id, " \
            "access_token, scopes, expiry) VALUES (?, ?, ?, ?, ?, ?)"
        await self.db.execute(query3, (refresh_token, user_id, client_id,
                                       access_token, ' '.join(scopes), expiry))
        # Step 55
        query4 = "UPDATE sessions SET authing=NULL WHERE authing=?"
        await self.db.execute(query4, (code,))
        return (refresh_token, expiry)

    async def get_access_token(self, client_id: int, refresh_token: str) \
        ->Union[Tuple[str, int, str], Tuple[None, None, None]]:
        query1 = "SELECT access_token FROM approvals WHERE " \
            "client_id=? AND refresh_token=?"
        async with lock:
            await self.db.execute(query1, (client_id, refresh_token))
            row = await self.db.fetchone()
        if row is None:
            return (None, None, None)
        access_token = row[0]
        query2 = "SELECT expiry, scopes FROM authings WHERE code=?"
        async with lock:
            await self.db.execute(query2, (access_token,))
            row = await self.db.fetchone()
        if row is None:
            return (None, None, None)
        return (access_token, row['expiry'], row['scopes'])

    async def get_refresh_token(self, client_id: int, refresh_token: str) \
        -> Union[Tuple[int, str], Tuple[None, None]]:
        query = "SELECT expiry, scopes FROM approvals " \
            "WHERE client_id=? AND refresh_token=?"
        async with lock:
            await self.db.execute(query, (client_id, refresh_token,))
            row = await self.db.fetchone()
        if row is None:
            return (None, None)
        return tuple(row)

    async def refresh_access_token(self, old: str, refresh_token: str):
        """Generate and set a new access token using a refresh token."""
        access_token = token_hex(64) # Step 61
        expiry = int(time()) + MEDIUM_EXPIRY # Step 63
        async with lock:
            # Step 63
            query1 = "INSERT INTO authings SELECT ?, user_id, client_id, "\
                "redirect_uri, scopes, state, ? FROM authings WHERE code=?"
            await self.db.execute(query1, (access_token, expiry, old))
            # Step 62
            query2 = "UPDATE approvals SET access_token=? WHERE refresh_token=?"
            await self.db.execute(query2, (access_token, refresh_token))
            query3 = "DELETE FROM authings WHERE code=?"
            await self.db.execute(query3, (old,))
        return (access_token, expiry)

    async def revoke_token(self, refresh_token: str, query: str):
        query1 = "SELECT access_token FROM approvals WHERE refresh_token=?"
        async with lock:
            await self.db.execute(query1, (refresh_token,))
            row = await self.db.fetchone()
        if row is None:
            return # looks like it's already revoked
        code: str = row[0]
        query2 = query
        await self.db.execute(query2, (refresh_token,))
        query3 = "DELETE FROM authings WHERE code=?"
        await self.db.execute(query3, (code,))

    async def revoke_refresh_token(self, refresh_token: str):
        """Revoke a refresh token."""
        query = "DELETE FROM approvals WHERE refresh_token=?"
        await self.revoke_token(refresh_token, query)

    async def revoke_access_token(self, refresh_token: str):
        """Revoke an access token."""
        query = "UPDATE approvals SET access_token=NULL WHERE refresh_token=?"
        await self.revoke_token(refresh_token, query)

class Approvals(Database):
    """Manage existing app approvals."""

    async def get(self, query: str = '', params: Tuple = (), method=None):
        """Get approval(s)."""
        await self.expire()
        query = (
            "SELECT refresh_token, approvals.client_id AS client_id, app_name"
            "app_name, scopes, expiry, flags FROM approvals JOIN applications "
            "WHERE approvals.client_id=applications.client_id" + query
        )
        async with lock:
            await self.db.execute(query, params)
            return await (method or self.db.fetchone)()

    async def get_by_id(self, user_id: Optional[int]):
        """Get all approvals by this user."""
        query = " AND user_id=?"
        rows = await self.get(query, (user_id,), self.db.fetchall)
        return [objs.Approval(**row) for row in rows]

    async def get_by_access_token(self, access_token: str):
        """Get an approval by its access token."""
        query = " AND access_token=?"
        row = await self.get(query, (access_token,), self.db.fetchone)
        return objs.Approval(**row) if row is not None else None

    async def delete(self, refresh_token: str, user_id: Optional[int]):
        """Revoke an approval by this user.
        Returns whether deletion was successful.
        """
        await self.expire()
        query1 = "SELECT access_token FROM approvals "\
            "WHERE refresh_token=? AND user_id=?"
        async with lock:
            await self.db.execute(query1, (refresh_token, user_id))
            row = await self.db.fetchone()
        if row is None:
            return False
        code = row[0]
        query2 = "DELETE FROM approvals WHERE refresh_token=? AND user_id=?"
        await self.db.execute(query2, (refresh_token, user_id))
        query3 = "DELETE FROM authings WHERE code=?"
        await self.db.execute(query3, (code,))
        return True

    async def expire(self):
        """Expire approvals."""
        now = int(time())
        query1 = "SELECT access_token FROM approvals WHERE expiry<?"
        async with lock:
            await self.db.execute(query1, (now,))
            rows = await self.db.fetchall()
        if not rows: # nothing expiring
            return # so skip more queries
        query2 = "DELETE FROM approvals WHERE expiry<?"
        await self.db.execute(query2, (now,))
        query3 = "DELETE FROM authings WHERE code=?"
        await self.db.executemany(query3, ((row[0],) for row in rows))

class User(Database):
    """Scratch user tracking."""

    @overload
    async def get(self, username: str) -> objs.User: ...

    @overload
    async def get(self, user_id: int) -> objs.User: ...

    async def get(self, user_key):
        """Get user info."""
        query = "SELECT * FROM scratchers WHERE "
        if isinstance(user_key, str):
            query += "user_name=?"
        elif isinstance(user_key, int):
            query += "user_id=?"
        else:
            query += "0=1"
        async with lock:
            await self.db.execute(query, (user_key,))
            row = await self.db.fetchone()
        if row is None:
            return None
        return objs.User(**row)

    async def get_by_access_token(self, access_token: str):
        """Get a user by an access token to their name."""
        query = "SELECT user_id FROM approvals WHERE access_token=?"
        async with lock:
            await self.db.execute(query, (access_token,))
            row = await self.db.fetchone()
        if row is None or row[0] is None:
            return None
        return await self.get(row[0])

    async def set(self, user_id: int, user_name: str):
        """Set or update user information."""
        query1 = "SELECT data FROM scratchers WHERE user_id=? OR user_name=?"
        query2 = "DELETE FROM scratchers WHERE user_id=? OR user_name=?"
        query3 = "INSERT INTO scratchers (user_id, user_name, data) " \
            "VALUES (?, ?, ?)"
        async with lock:
            await self.db.execute(query1, (user_id, user_name))
            row = self.db.fetchone()
            if row is None:
                data = None
            else:
                data = row[0]
            await self.db.execute(query2, (user_id, user_name))
            await self.db.execute(query3, (user_id, user_name, data))

async def upgrade(db: sql.Cursor):
    """Detect database version and upgrade to newest if necessary."""
    LATEST_DBV = 1
    await db.execute('PRAGMA user_version')
    dbv = ((await db.fetchone()) or [0])[0]
    if dbv < LATEST_DBV:
        print(f'Current DB version {dbv} < latest {LATEST_DBV}, upgrading:')
        for i in range(dbv, LATEST_DBV):
            try:
                with open(f'backend/sql/v{i}.sql') as f:
                    print(f' Upgrading v{i} to v{i+1}')
                    await db.executescript(f.read())
            except FileNotFoundError:
                print(f' No script for v{i}->v{i+1}, skipping')
            except sql.OperationalError as exc:
                print(f'  Running script failed: {exc!s}')

async def startup() -> Tuple[sql.Connection, sql.Cursor]:
    """Create and return the database connection and its cursor."""
    dbw = await sql.connect(config['db'])
    dbw.row_factory = sql.Row
    cursor = await dbw.cursor()
    await upgrade(cursor)
    return (dbw, cursor)

dbw, cursor = asyncio.get_event_loop().run_until_complete(startup())

session = Session(cursor)
login = Login(cursor)
apps = Applications(cursor)
auth = Authorization(cursor)
tokens = Tokens(cursor)
approvals = Approvals(cursor)
user = User(cursor)

async def teardown(app):
    """Shut down the database."""
    print(timestamp(), 'Committing and closing DB.')
    await dbw.commit()
    await dbw.close()
    await app['session'].close()
