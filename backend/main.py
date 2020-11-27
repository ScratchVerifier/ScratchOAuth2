import argparse
import aiohttp
from aiohttp import web
from config import config
from db import teardown
from routes import setup_routes
from middlewares import middlewares

argparser = argparse.ArgumentParser(description='Run ScratchOAuth2')
argparser.add_argument('--debug', action='store_true', default=False,
                       help='Allow requests that trigger debug mode')

def run(cmdargs=[]):
    """Run ScratchOAuth2."""
    args = argparser.parse_args(cmdargs)
    app = web.Application(middlewares=middlewares)
    app['session'] = aiohttp.ClientSession()
    app['debug'] = args.debug
    app.on_cleanup.append(teardown)
    app.on_cleanup.append(app['session'].close)
    setup_routes(app)
    web.run_app(app, **config['app'])