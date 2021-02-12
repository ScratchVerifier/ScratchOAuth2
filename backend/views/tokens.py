from time import time
from hmac import compare_digest
from aiohttp import web
import db
from config import SCOPES_DESC, SCOPES_SPLIT_REGEX
from objs import TokensResponse

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
        scopes = auth.scopes
        # Step 56
        return web.json_response(TokensResponse(
            access_token, access_expiry, refresh_token,
            refresh_expiry, scopes)._asdict())

    async def access_token(self, request: web.Request):
        """Request an access token using a refresh token."""
        # Step 57
        try:
            data = await request.json()
            client_id = int(data['client_id'])
            client_secret = data['client_secret']
            refresh_token = data['refresh_token']
        except (KeyError, ValueError):
            raise web.HTTPBadRequest() from None
        app = await db.apps.application(None, client_id) # Step 58
        # Step 59
        if app is None or not compare_digest(app.client_secret, client_secret):
            raise web.HTTPUnauthorized()
        # Step 60
        old, _, _ = await db.tokens.get_access_token(client_id, refresh_token)
        refresh_expiry, scopes = \
            await db.tokens.get_refresh_token(client_id, refresh_token)
        if (
            old is None
            or refresh_token is None
            or refresh_expiry is None
            or scopes is None
        ):
            raise web.HTTPNotFound()
        if refresh_expiry < time(): # expired
            raise web.HTTPGone()
        access_token, access_expiry = \
            await db.tokens.refresh_access_token(old, refresh_token)
        scopes = scopes.split()
        # Step 64
        return web.json_response(TokensResponse(
            access_token, access_expiry, refresh_token,
            refresh_expiry, scopes)._asdict())

    async def delete_token(self, request: web.Request):
        """Revoke an access token or refresh token."""
        try:
            data = await request.json()
            client_id = int(data['client_id'])
            client_secret = data['client_secret']
            refresh_token = data['token']
            token_type = data['type']
            if token_type not in {'refresh', 'access'}:
                raise ValueError('Invalid token type')
        except (KeyError, ValueError):
            raise web.HTTPBadRequest() from None
        app = await db.apps.application(None, client_id)  # Step 46
        # Step 47
        if app is None or not compare_digest(app.client_secret, client_secret):
            raise web.HTTPUnauthorized()
        # TODO: this doesn't make sense, you can't get a refresh token by client ID
        # must fix this in next commit but it's being left this way for now
        refresh_expiry, _ = \
            await db.tokens.get_refresh_token(client_id, refresh_token)
        if refresh_token is None or refresh_expiry is None:
            raise web.HTTPNotFound()
        if refresh_expiry < time():
            raise web.HTTPGone()
        if token_type == 'refresh':
            await db.tokens.revoke_refresh_token(refresh_token)
        else:
            await db.tokens.revoke_access_token(refresh_token)
        return web.HTTPNoContent()

tokens = Tokens()
routes = [
    web.post('/tokens', tokens.refresh_token),
    web.patch('/tokens', tokens.access_token),
    web.delete('/tokens', tokens.delete_token)
]
