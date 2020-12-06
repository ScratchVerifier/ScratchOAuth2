from aiohttp import web
import views

def setup_routes(app: web.Application):
    """Add routes from the various views."""
    app.add_routes(views.website.routes)