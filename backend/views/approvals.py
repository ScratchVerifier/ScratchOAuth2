from aiohttp import web
import db
import objs

class Approvals:
    """Approval management."""

    async def approvals(self, request: web.Request):
        """Get all approvals by this user."""
        return web.Response(text='')

    async def revoke(self, request: web.Request):
        """Revoke an approval."""
        return web.HTTPNoContent()

@web.middleware
async def check_login(request: web.Request, handler):
    """Ensure `/applications` requests are logged in."""
    session: objs.Session = request['session']
    if request.path.startswith('/approvals') and session.user_id is None:
        raise web.HTTPUnauthorized()
    return await handler(request)

approvals = Approvals()
routes = [
    web.get('/approvals', approvals.approvals),
    web.delete('/approvals/{refresh_token:[0-9a-f]+}', approvals.revoke)
]