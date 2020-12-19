from time import time
from secrets import randbits, token_hex
from typing import Optional, Tuple
import asyncio
import aiosqlite as sql
from config import LONG_EXPIRY, config, SHORT_EXPIRY, timestamp
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
        query = "SELECT * FROM sessions WHERE session_id=?"
        async with lock:
            await self.db.execute(query, (session_id,))
            row = await self.db.fetchone()
        if row is not None:
            return objs.Session(**row)
        return None

    async def create(self) -> int:
        """Create a new session and return its ID."""
        # Step 9
        session_id = randbits(62)
        expiry = int(time()) + SHORT_EXPIRY
        # Step 10
        query = "INSERT INTO sessions (session_id, expiry) VALUES (?, ?)"
        await self.db.execute(query, (session_id, expiry))
        return session_id

class Login(Database):
    """Handle login database interactions."""

    db: sql.Connection

    async def nonce(self, session_id: int) -> str:
        """Generate, set, and return a nonce."""
        nonce = token_hex(32) # Step 18
        # Step 19
        query = "UPDATE sessions SET nonce=? WHERE session_id=?"
        await self.db.execute(query, (nonce, session_id))
        # Step 20
        return nonce

    async def save_user(self, user_id: int, user_name: str):
        """Set or update user information."""
        query1 = "INSERT OR IGNORE INTO scratch_users(user_id) VALUES (?)"
        query2 = "UPDATE scratch_users SET user_name=? WHERE user_id=?"
        async with lock:
            await self.db.execute(query1, (user_id,))
            await self.db.execute(query2, (user_name, user_id))

    async def login_session(self, session_id: int, user_id: int):
        """Mark a session as logged in."""
        expiry: int = LONG_EXPIRY
        # Step 30-32
        query = "UPDATE sessions SET user_id=?, expiry=?, nonce=NULL " \
            "WHERE session_id=?"
        await self.db.execute(query, (user_id, expiry, session_id))

    async def logout(self, session_id: int):
        expiry: int = SHORT_EXPIRY
        query = "UPDATE sessions SET user_id=NULL, expiry=?, " \
            "nonce=NULL WHERE session_id=?"
        await self.db.execute(query, (expiry, session_id))

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

async def teardown(app):
    """Shut down the database."""
    print(timestamp(), 'Committing and closing DB.')
    await dbw.commit()
    await dbw.close()
    await app['session'].close()
