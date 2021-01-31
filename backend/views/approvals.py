from aiohttp import web
import db
import objs

class Approvals:
    """Approval management."""

    async def approvals(self, request: web.Request):
        """Get all approvals by this user."""
        session: objs.Session = request['session']
        return web.json_response([
            thing._asdict()
            for thing in await db.approvals.get_by_id(session.user_id)])

    async def revoke(self, request: web.Request):
        """Revoke an approval."""
        session: objs.Session = request['session']
        if await db.approvals.delete(
                request.match_info['refresh_token'], session.user_id):
            return web.HTTPNoContent()
        raise web.HTTPNotFound()

@web.middleware
async def check_login(request: web.Request, handler):
    """Ensure `/approvals` requests are logged in."""
    session: objs.Session = request['session']
    if request.path.startswith('/approvals') and session.user_id is None:
        raise web.HTTPUnauthorized()
    return await handler(request)

approvals = Approvals()
routes = [
    web.get('/approvals', approvals.approvals),
    web.delete('/approvals/{refresh_token:[0-9a-f]+}', approvals.revoke)
]