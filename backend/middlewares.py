from time import time
import json
import traceback
from aiohttp import web
from config import config, SHORT_EXPIRY, timestamp
import db
import views

# log requests
@web.middleware
async def errors(request: web.Request, handler):
    """Log to stdout and log errors to stdout and Discord webhook.

    ``request``: aiohttp.Request object
    ``handler``: method of this class

    Returns whatever the ``handler`` returns, or raises some exception.
    """
    print('%s %s %s' % (
        timestamp(),
        request.method,
        request.path_qs
    ))
    _debug = request.config_dict.get('debug', False)
    try:
        return await handler(request)
    except web.HTTPException as exc:
        raise exc from None
    except json.JSONDecodeError:
        raise web.HTTPBadRequest() from None
    except Exception as exc:
        print('Error in', f'{request.method} {request.path}',
              end='' if _debug else '\n')
        if _debug:
            print(':')
            print(traceback.format_exc())
        if 'discord_hook' in config:
            await request.config_dict['session'].post(config['discord_hook'],
                                                      json={
                'username': '{}ScratchOAuth2 Errors'.format(
                    (config['name'] + "'s ") if 'name' in config else ''
                ),
                'embeds': [{
                    'color': 0xff0000,
                    'title': '500 Response Sent',
                    'fields': [
                        {'name': 'Request Path',
                            'value': f'`{request.method} {request.path_qs}`',
                            'inline': False},
                        {'name': 'Error traceback',
                            'value': f'```{traceback.format_exc()}```',
                            'inline': False}
                    ]
                }, {
                    'color': 0xff0000,
                    'title': 'Headers',
                    'fields': [{'name': k,
                                'value': f'`{v}`',
                                'inline': True}
                                for k, v in request.headers.items()]
                }, {
                    'color': 0xff0000,
                    'title': 'Session Data',
                    'fields': [
                        {'name': k,
                         'value': f'`{v}`',
                         'inline': True}
                        for k, v in request['session']._asdict().items()
                    ]
                }]
            })
        raise web.HTTPInternalServerError() from exc

# Step 8
@web.middleware
async def cookie_check(request: web.Request, handler):
    """If no session ID is present in cookies, generate and send one."""
    session_id = request.cookies.get('session', None)
    try:
        session_id = int(session_id)
    except:
        session_id = -1
    try:
        session = await db.session.get(session_id)
    except OverflowError:
        # session ID was too big for SQLite
        session = None
    if session is None:
        session_id = await db.session.create()
        # Step 11
        resp = web.HTTPTemporaryRedirect(request.raw_path)
        resp.set_cookie('session', str(session_id), max_age=SHORT_EXPIRY)
        raise resp
    request['session'] = session
    return await handler(request)

middlewares = [
    cookie_check,
    errors,
    views.applications.check_login,
    views.authorization.check_login,
    views.approvals.check_login,
    views.user.check_token,
]