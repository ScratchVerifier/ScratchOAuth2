from aiohttp import web
import db
import objs

class Applications:
    """Client registration and management."""

    async def applications(self, request: web.Request):
        """List of applications."""
        session: objs.Session = request['session']
        return web.json_response([
            app._asdict()
            for app in await db.apps.applications(session.user_id)])

    async def application(self, request: web.Request):
        """Get data about a specific application."""
        session: objs.Session = request['session']
        if session.user_id is None:
            raise web.HTTPUnauthorized()
        client_id = int(request.match_info['client_id'])
        app = await db.apps.application(session.user_id, client_id)
        if app is None:
            raise web.HTTPNotFound()
        return web.json_response(app._asdict())

    # Step 1
    async def register(self, request: web.Request):
        """Register a new application."""
        session: objs.Session = request['session']
        if session.user_id is None:
            # redundant but it pleases Pyright
            raise web.HTTPUnauthorized()
        try:
            data = await request.json()
        except ValueError:
            raise web.HTTPBadRequest() from None
        app_name = data.get('app_name', None)
        try:
            redirect_uris = data['redirect_uris']
        except KeyError:
            raise web.HTTPBadRequest() from None
        if (
            not isinstance(app_name, (str, type(None)))
            or not isinstance(redirect_uris, list)
            or any(not isinstance(uri, str) for uri in redirect_uris)
        ):
            raise web.HTTPBadRequest()
        app = await db.apps.create_app(session.user_id,
                                       app_name, redirect_uris)
        return web.json_response(app._asdict())

    async def update(self, request: web.Request):
        """Update data about an application."""
        session: objs.Session = request['session']
        client_id = int(request.match_info['client_id'])
        app = await db.apps.application(session.user_id, client_id)
        if app is None:
            raise web.HTTPNotFound()
        try:
            data = await request.json()
        except ValueError:
            raise web.HTTPBadRequest() from None
        client_secret = 'reset' if 'client_secret' in data else None
        app_name = data.get('app_name', ...)
        redirect_uris = data.get('redirect_uris', None)
        if (
            not isinstance(app_name, (str, type(None), type(...)))
            or not isinstance(redirect_uris, (list, type(None)))
            or any(not isinstance(uri, str) for uri in redirect_uris or [])
        ):
            raise web.HTTPBadRequest()
        try:
            app = await db.apps.update_app(client_id, client_secret,
                                           app_name, redirect_uris)
        except ValueError:
            raise web.HTTPBadRequest() from None
        return web.json_response(app._asdict())

    async def delete(self, request: web.Request):
        """Destroy an application."""
        session: objs.Session = request['session']
        client_id = int(request.match_info['client_id'])
        app = await db.apps.application(session.user_id, client_id)
        if app is None:
            raise web.HTTPNotFound()
        await db.apps.delete_app(client_id)
        return web.HTTPNoContent()

@web.middleware
async def check_login(request: web.Request, handler):
    """Ensure `/applications` requests are logged in."""
    session: objs.Session = request['session']
    if request.path.startswith('/applications') and session.user_id is None:
        raise web.HTTPUnauthorized()
    return await handler(request)

apps = Applications()
routes = [
    web.get('/applications', apps.applications),
    web.get('/applications/{client_id:[0-9]+}', apps.application),
    web.put('/applications', apps.register),
    web.patch('/applications/{client_id:[0-9]+}', apps.update),
    web.delete('/applications/{client_id:[0-9]+}', apps.delete)
]
