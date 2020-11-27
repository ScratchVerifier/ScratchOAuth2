from collections import namedtuple

Session = namedtuple('Session', [
    'session_id', 'user_id', 'expiry',
    'authing', 'nonce',
])
Session.__doc__ += ': Session data'
Session.session_id.__doc__ = 'Random session ID (int)'
Session.user_id.__doc__ = 'User ID logged into session (int?)'
Session.expiry.__doc__ = 'Unix epoch time when session expires (int)'
Session.authing.__doc__ = 'Token-getter code (str?)'
Session.nonce.__doc__ = 'Nonce used for logging in (str?)'