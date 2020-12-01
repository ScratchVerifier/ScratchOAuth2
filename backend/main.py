import argparse
import asyncio
import aiohttp
from aiohttp import web
from config import config, timestamp
from db import teardown
from routes import setup_routes
from middlewares import middlewares

argparser = argparse.ArgumentParser(description='Run ScratchOAuth2')
argparser.add_argument('--debug', action='store_true', default=False,
                       help='Allow requests that trigger debug mode')

async def wakeup():
    while 1:
        await asyncio.sleep(1)

def run(cmdargs=[]):
    """Run ScratchOAuth2."""
    args = argparser.parse_args(cmdargs)
    app = web.Application(middlewares=middlewares)
    app['session'] = aiohttp.ClientSession()
    app['debug'] = args.debug
    app.on_cleanup.append(teardown)
    setup_routes(app)
    # create a dummy periodic callback so that Ctrl+C
    # gets handled on Windows properly
    asyncio.get_event_loop().create_task(wakeup())
    web.run_app(app, **config['app'])
    print(timestamp(), 'Goodbye.')