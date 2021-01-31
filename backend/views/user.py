import base64
from aiohttp import web
import db

class User:
    """Get user information."""

    async def identify(self, request: web.Request):
        """Identify a user by their access token."""
        return web.Response()

@web.middleware
async def check_token(request: web.Request, handler):
    """Ensure scoped requests are authenticated."""
    path: str = request.path
    if not path.startswith((
        '/user',
    )):
        return await handler(request)
    try:
        scheme, encoded = request.headers.get('Authorization', None).split()
    except TypeError:
        raise web.HTTPUnauthorized() from None
    if scheme != 'Bearer':
        raise web.HTTPUnauthorized()
    token = base64.b64decode(encoded)
    auth = await db.auth.get_authing_by_code(token.decode())
    if auth is None:
        raise web.HTTPUnauthorized()
    request['auth'] = auth
    return await handler(request)

user = User()
routes = [
    web.get('/user', user.identify),
]