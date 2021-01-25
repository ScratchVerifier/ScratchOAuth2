import time
import re
import json

__all__ = ['config', 'SHORT_EXPIRY', 'LONG_EXPIRY']

with open('soa2.json') as _cfile:
    config = json.load(_cfile)

# defaults
SHORT_EXPIRY = 600
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

globals().update(config.get('consts', {}))

def timestamp() -> str:
    return time.strftime('%Y-%m-%dT%H:%M:%SZ')
