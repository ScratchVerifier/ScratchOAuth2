from hmac import compare_digest
from aiohttp import web
import db
from config import SCOPES_DESC, SCOPES_SPLIT_REGEX

class Tokens:
    """Token management."""

    async def refresh_token(self, request: web.Request):
        """Request a refresh token and an access token."""
        # Step 45
        try:
            data = await request.json()
            client_id = int(data['client_id'])
            client_secret = data['client_secret']
            code = data['code']
            scopes = data['scopes']
        except (KeyError, ValueError):
            raise web.HTTPBadRequest() from None
        scopes = SCOPES_SPLIT_REGEX.split(scopes)
        if not (set(scopes) <= set(SCOPES_DESC.keys())):
            # scopes that don't exist were requested
            raise web.HTTPBadRequest()
        app = await db.apps.application(None, client_id) # Step 46
        # Step 47
        if app is None or not compare_digest(app.client_secret, client_secret):
            raise web.HTTPUnauthorized()
        auth = await db.auth.get_authing_by_code(code) # Step 48
        if auth is None:
            raise web.HTTPNotFound()
        if set(auth.scopes) != set(scopes):
            raise web.HTTPExpectationFailed()
        access_token, access_expiry = await db.tokens.new_access_token(code)
        refresh_token, refresh_expiry = await db.tokens.new_refresh_token(
            code, client_id, access_token, auth.scopes)
        scopes = ' '.join(auth.scopes)
        # Step 56
        return web.json_response({
            'access_token': access_token,
            'access_expiry': access_expiry,
            'refresh_token': refresh_token,
            'refresh_expiry': refresh_expiry,
            'scopes': scopes
        })

    async def access_token(self, request: web.Request):
        """Request an access token using a refresh token."""
        return web.Response(text='')

    async def delete_token(self, request: web.Request):
        """Revoke an access token or refresh token."""
        return web.HTTPNoContent()

tokens = Tokens()
routes = [
    web.post('/tokens', tokens.refresh_token),
    web.patch('/tokens', tokens.access_token),
    web.delete('/tokens', tokens.delete_token)
]