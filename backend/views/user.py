import base64
from aiohttp import web
from config import COMMENTS_API, timestamp
import db
import objs

class User:
    """Get user information."""

    # Step 66
    async def identify(self, request: web.Request):
        """Identify a user by their access token."""
        auth: objs.Authing = request['auth']
        if 'identify' not in auth.scopes:
            raise web.HTTPForbidden()
        # Step 67
        user = await db.user.get(auth.user_id)
        if user is None:
            raise web.HTTPNotFound()
        async with request.config_dict['session'].get(COMMENTS_API.format(
            user.user_name, timestamp()
        )) as resp:
            found = resp.status == 200
        if not found:
            # we have data, but Scratch !200s
            # they may just be banned, or the account may have been deleted
            # or, in rare cases, renamed
            return web.json_response({
                'user_id': user.user_id,
                'user_name': user.user_name
            }, status=203)
        # Step 68
        return web.json_response({'user_id': user.user_id,
                                  'user_name': user.user_name})

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