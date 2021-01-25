from aiohttp import web

class Tokens:
    """Token management."""

    async def refresh_token(self, request: web.Request):
        """Request a refresh token and an access token."""
        return web.Response(text='')

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