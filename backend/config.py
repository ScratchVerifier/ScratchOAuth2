import time
import re
from enum import IntFlag
import json
from aiohttp import web

__all__ = ['config', 'SHORT_EXPIRY', 'LONG_EXPIRY']

with open('soa2.json') as _cfile:
    config = json.load(_cfile)

# defaults
SHORT_EXPIRY = 3600
MEDIUM_EXPIRY = 3600*24
LONG_EXPIRY = 3600*24*365

# usually not modified, but defining it here allows
# configuration to workaround modifying source
COMMENTS_API = 'https://scratch.mit.edu/site-api/comments/user/{}/' \
    '?page=1&salt={}'
USERS_API = 'https://api.scratch.mit.edu/users/{}'
USERNAME_REGEX = re.compile('^[A-Za-z0-9_-]{3,20}$')
COMMENTS_REGEX = re.compile(
    r"""<div id="comments-\d+" class="comment +" data-comment-id="\d+">.*?"""
    r"""<div class="actions-wrap">.*?<div class="name">\s+"""
    r"""<a href="/users/([_a-zA-Z0-9-]+)">\1</a>\s+</div>\s+"""
    r"""<div class="content">\s*(.*?)\s*</div>""", re.S)
INVALID_AUTH_TITLE = 'Invalid Auth URL'
INVALID_AUTH_TEXT = '''You have been given a faulty authorization URL.
<br/>Please contact whoever gave you this URL and inform them of this.'''
SCOPES_SPLIT_REGEX = re.compile(r'(?<=[a-z])(?=[, +])(?:\+|,? ?)(?=[a-z])')
SCOPES_DESC = {
    'identify': {
        'en': 'Know who you are on Scratch'
    }
}

globals().update(config.get('consts', {}))

def timestamp() -> str:
    return time.strftime('%Y-%m-%dT%H:%M:%SZ')

async def error(title: str, message: str, status: int = 400):
    """Helper function to throw an HTML 400 page."""
    with open('templates/error.html', 'r') as f:
        data = f.read()
    data = (data.replace('__status__', str(status))
            .replace('__message__', message)
            .replace('__title__', title))
    return web.Response(status=status, text=data, content_type='text/html')

class AppFlags(IntFlag):
    NONE = 0
    NAME_APPROVED = 1