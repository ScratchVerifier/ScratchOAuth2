from aiohttp import web
import views

def setup_routes(app: web.Application):
    """Add routes from the various views."""
    app.add_routes(views.website.routes)
    app.add_routes(views.login.routes)
    app.add_routes(views.applications.routes)
    app.add_routes(views.authorization.routes)
    app.add_routes(views.tokens.routes)
    app.add_routes(views.approvals.routes)
    app.add_routes(views.user.routes)