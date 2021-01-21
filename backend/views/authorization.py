from urllib.parse import quote
from aiohttp import web
import db
import objs
from config import (INVALID_AUTH_TITLE, INVALID_AUTH_TEXT,
                    SCOPES_SPLIT_REGEX, SCOPES_DESC, error)

class Authorization:
    """Application approval confirmation."""

    async def page(self, request: web.Request):
        """Display the confirmation page."""
        # Step 34
        state = request.query.get('state', None)
        client_id = request.query.get('client_id', None)
        redirect_uri = request.query.get('redirect_uri', '/showcode')
        scopes = request.query.get('scopes', None)
        # ensure presence
        if None in {state, client_id, scopes}:
            return await error(INVALID_AUTH_TITLE, INVALID_AUTH_TEXT)
        # ensure valid scopes
        scopes = SCOPES_SPLIT_REGEX.split(scopes)
        if any(scope not in SCOPES_DESC for scope in scopes):
            return await error(INVALID_AUTH_TITLE, INVALID_AUTH_TEXT)
        # ensure valid client_id after scopes to avoid calling the DB
        try:
            client_id = int(client_id)
            app = await db.apps.application(None, client_id)
            if app is None:
                raise ValueError('No such application')
        except ValueError:
            return await error(INVALID_AUTH_TITLE, INVALID_AUTH_TEXT)
        # ensure valid redirect URI
        if redirect_uri != '/showcode':
            if redirect_uri not in app.redirect_uris:
                return await error(INVALID_AUTH_TITLE, INVALID_AUTH_TEXT)
        return web.Response(text='')

    async def confirm(self, request: web.Request):
        """Complete the confirmation."""
        return web.Response(text='')

    async def cancel(self, request: web.Request):
        """Cancel the confirmation."""
        return web.Response(text='')

@web.middleware
async def check_login(request: web.Request, handler):
    """Redirect non-logged-in requests to login first."""
    session: objs.Session = request['session']
    # Step 12-14
    if request.path.startswith('/authorize') and session.user_id is None:
        raise web.HTTPSeeOther('/login?returnto=' + quote(request.path_qs))
    return await handler(request)

auth = Authorization()
routes = [
    web.get('/authorize', auth.page),
    web.post('/authorize', auth.confirm),
    web.delete('/authorize', auth.cancel),
]
