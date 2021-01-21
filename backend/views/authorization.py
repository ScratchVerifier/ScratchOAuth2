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
        session: objs.Session = request['session']
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
        if session.authing is None:
            # this is against the GET specification, but it's the best I can do
            await db.auth.start_auth(
                session.session_id, state, client_id, redirect_uri, scopes)
        # Step 38
        with open('templates/auth.html', 'r') as f:
            data = f.read()
        if app.app_name is None:
            name = '[unnamed app]'
        elif not app.approved:
            name = f'<span class="unmoderated"><span>{app.app_name}</span></span>'
        else:
            name = app.app_name
        data = (data.replace('__appname__', name)
                .replace('__scopes__', '\n'.join(
                    '<li>%s</li>' % SCOPES_DESC[scope]['en']
                    for scope in scopes
                )))
        return web.Response(text=data, content_type='text/html')

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
