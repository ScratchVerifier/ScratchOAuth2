from aiohttp import web
import db
import objs

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

login = Login()
routes = [
    web.get('/login', login.login_page),
    web.get('/login/nonce', login.nonce),
]