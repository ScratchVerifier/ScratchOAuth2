import json

__all__ = ['config', 'SHORT_EXPIRY', 'LONG_EXPIRY']

with open('soa2.json') as _cfile:
    config = json.load(_cfile)

# defaults
SHORT_EXPIRY = 600
LONG_EXPIRY = 3600*24*265

globals().update(config.get('consts', {}))
