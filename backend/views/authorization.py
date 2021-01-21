from aiohttp import web
import db
import objs

class Authorization:
    """Application approval confirmation."""

    async def page(self, request: web.Request):
        """Display the confirmation page."""
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
    return await handler(request)

auth = Authorization()
routes = [
    web.get('/authorize', auth.page),
    web.post('/authorize', auth.confirm),
    web.delete('/authorize', auth.cancel),
]
