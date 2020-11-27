import os
import mimetypes
from aiohttp import web

class Website:
    """Serve the website. This only has use when Apache isn't available."""
    def index(self, request: web.Request):
        """Redirect to /site"""
        raise web.HTTPMovedPermanently('/site')

    def site(self, request: web.Request):
        """Serve files"""
        path = request.match_info.get('path', 'index.html').strip() or 'index.html'
        path = 'site/' + path
        if os.path.isdir(path):
            path = path.rstrip('/') + '/' + 'index.html'
        if request.if_modified_since:
            if os.path.getmtime(path) <= \
                    request.if_modified_since.timestamp():
                raise web.HTTPNotModified()
        if request.if_unmodified_since:
            if os.path.getmtime(path) > \
                    request.if_unmodified_since.timestamp():
                raise web.HTTPPreconditionFailed()
        try:
            with open(path, 'rb') as f:
                range = request.http_range
                if range.stop:
                    if range.start:
                        f.seek(range.start)
                        rsize = range.stop - range.start
                    else:
                        rsize = range.stop
                elif range.start:
                    f.seek(range.start)
                    rsize = -1
                else:
                    rsize = -1
                data = f.read(rsize)
            ct = mimetypes.guess_type(path)
            return web.Response(body=data, content_type=ct[0], charset=ct[1])
        except FileNotFoundError:
            raise web.HTTPNotFound()

website = Website()
routes = [
    web.get('/', website.index),
    web.get('/site', website.site),
    web.get('/site/', website.site),
    web.get('/site/{path:.*}', website.site)
]
