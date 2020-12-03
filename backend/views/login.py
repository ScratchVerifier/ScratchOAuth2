from hashlib import sha256
from hmac import compare_digest
from aiohttp import web
import db
import objs
from config import COMMENTS_API, USERNAME_REGEX, COMMENTS_REGEX, timestamp

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
        # Step 22
        return web.Response(text=data, content_type='text/html')

    # Step 16
    async def nonce(self, request: web.Request):
        """Get nonce for this login."""
        session: objs.Session = request['session']
        if session.user_id is not None:
            raise web.HTTPConflict()
        if session.nonce is None: # Step 17
            session.nonce = await db.login.nonce(session.session_id)
        # Step 21
        return web.json_response(objs.Nonce(session.nonce)._asdict())

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
            if m.group(1).casefold() != username:
                continue
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
        returnto = data.get('returnto', '/')
        username = data.get('username', '').strip().casefold()
        if not USERNAME_REGEX.match(username):
            raise web.HTTPBadRequest()
        # Step 25
        if session.nonce is None:
            raise web.HTTPNotFound()
        code = self.gen_code('login', session.nonce, username)
        # TODO: Step 27-32
        async with request.config_dict['session'].get(COMMENTS_API.format(
            username, timestamp()
        )) as resp:
            if resp.status != 200:
                raise web.HTTPNotFound()
            data = (await resp.text()).strip()
        if not self.check_code(username, code, data):
            raise web.HTTPForbidden()

login = Login()
routes = [
    web.get('/login', login.login_page),
    web.get('/login/nonce', login.nonce),
    web.post('/login', login.submit_login)
]