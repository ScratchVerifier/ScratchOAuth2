from hashlib import sha256
from hmac import compare_digest
from html import escape
from urllib.parse import quote
from aiohttp import web
import db
import objs
from config import (COMMENTS_API, USERNAME_REGEX, COMMENTS_REGEX,
                    timestamp, USERS_API)

def hexdigest(data: str) -> str:
    return sha256(data.encode()).hexdigest()

class Login:
    """Login endpoints and SOA2-Scratch interaction."""

    # Step 15
    async def login_page(self, request: web.Request):
        """HTML login page."""
        # Step 14
        returnto = request.query.get('returnto', '/')
        session: objs.Session = request['session']
        if session.user_id is not None:
            raise web.HTTPSeeOther(returnto)
        with open('templates/login.html', 'r') as f:
            data = f.read()
        failed = 'failed' in request.query
        data = (data.replace('var(username)', escape(request.query.get('username', '')))
                .replace('var(returnto)', escape(returnto))
                .replace('var(failed)', 'block' if failed else 'none'))
        # Step 22
        return web.Response(text=data, content_type='text/html')

    # Step 16
    async def nonce(self, request: web.Request):
        """Get nonce for this login."""
        session: objs.Session = request['session']
        if session.user_id is not None:
            raise web.HTTPConflict()
        if session.nonce is None: # Step 17
            nonce = await db.login.nonce(session.session_id)
        else:
            nonce = session.nonce
        # Step 21
        return web.json_response(objs.Nonce(nonce)._asdict())

    @staticmethod
    def gen_code(purpose: str, nonce: str, username: str) -> str:
        """Generate login code."""
        # Step 26 - must mirror client-side
        final = hexdigest(hexdigest(username) + hexdigest(purpose)
                          + hexdigest(nonce))
        return final.translate({ord('0') + i: ord('A') + i for i in range(10)})

    @staticmethod
    def check_code(username: str, code: str, text: str) -> bool:
        """Check whether the code was commented."""
        if not text:
            return False
        for m in COMMENTS_REGEX.finditer(text):
            # Step 28
            if m.group(1).casefold() != username:
                continue
            # Step 29
            if compare_digest(m.group(2).strip(), code):
                return True
        return False

    async def submit_login(self, request: web.Request):
        """Process a login."""
        session: objs.Session = request['session']
        data = await request.post()
        if not data:
            try:
                data = await request.json()
            except ValueError:
                raise web.HTTPBadRequest() from None
        returnto = str(data.get('returnto', request.query.get('returnto', '/')))
        username = str(data.get('username', '')).strip().casefold()
        if not USERNAME_REGEX.match(username):
            raise web.HTTPBadRequest()
        # Step 25
        if session.nonce is None:
            raise web.HTTPNotFound()
        code = self.gen_code('login', session.nonce, username)
        async with request.config_dict['session'].get(COMMENTS_API.format(
            username, timestamp()
        )) as resp: # Step 27
            if resp.status != 200:
                raise web.HTTPNotFound()
            data = (await resp.text()).strip()
        if not self.check_code(username, code, data):
            loc = request.path
            loc += f'?returnto={quote(returnto)}&username={quote(username)}&failed=1'
            raise web.HTTPSeeOther(loc)
        async with request.config_dict['session'].get(
            USERS_API.format(username)
        ) as resp:
            if resp.status != 200:
                raise web.HTTPNotFound()
            data = await resp.json()
        user_id = data['id']
        await db.login.save_user(user_id, username)
        await db.login.login_session(session.session_id, user_id)
        return web.HTTPSeeOther(returnto)

    async def logout(self, request: web.Request):
        """Unset the login of the session."""
        await db.login.logout(request['session'].session_id)
        return web.HTTPSeeOther('/')

login = Login()
routes = [
    web.get('/login', login.login_page),
    web.get('/login/nonce', login.nonce),
    web.post('/login', login.submit_login),
    web.delete('/login', login.logout)
]