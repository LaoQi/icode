# -*- coding: utf-8 -*-
# simple http and websocket server

import logging
import socket
import select
import json
import urllib
import time
import base64
import hashlib
import struct
import os

__version__ = "1.0.1"

config = {
    'expire': 3600,
    'root': os.path.dirname(__file__),
}

alogger = logging.getLogger('access')
elogger = logging.getLogger('error')

cache = {}


def ret200(response):
    if type(response) == 'str':
        return 200, 'text/html', response
    elif hasattr(response, '__str__'):
        return 200, 'text/html', str(response)
    else:
        return 200, 'text/html', 'error'


def ret404():
    return 404, 'text/html', 'Not Found'


def ret500(errmsg=None):
    if errmsg:
        return 500, 'text/html', errmsg
    return 500, 'text/html', 'Internal Server Error'


def static_file(path, nocache=False):
    if path.startswith('/'):
        path = path[1:]
    ts = time.time()
    if not nocache and path in cache and cache[path]['expire'] > ts:
        return 200, cache[path]['mime'], cache[path]['response']
    response = _get_file(path)
    mimetype = _get_mimetype(path)
    if response is not None:
        if not nocache:
            cache[path] = {
                'mime': mimetype,
                'response': response,
                'expire': ts + config['expire']
            }
        return 200, mimetype, response
    return ret404()


def _get_mimetype(path):
    mimes = {
        'js': 'application/x-javascript',
        'css': 'text/css',
        'html': 'text/html',
    }
    p, ext = os.path.splitext(path)
    if len(ext) > 1:
        ext = ext[1:].lower()
    if ext in mimes:
        return mimes[ext]
    return 'application/octet-stream'


def _get_file(path):
    rpath = os.path.join(config['root'], path)
    if not os.path.exists(rpath):
        return None

    with open(rpath, 'r') as fp:
        response = fp.read()

    return response


def jsonp_response(function, code, msg='', data=None):
    rtn = dict(code=code, msg=msg)
    if data is not None:
        rtn['data'] = data
    try:
        outstr = function + '(' + json.dumps(rtn) + ');'
        return ret200(outstr)
    except Exception, e:
        return ret500('Server Error: %s' % str(e))


def json_response(data):
    try:
        outstr = json.dumps(data)
        return ret200(outstr)
    except Exception, e:
        return ret500('Server Error: %s' % str(e))


class App:
    def __init__(self):
        pass

    debug = False
    _routes = {}
    _globals = {}
    _ws_handler = {}

    ws_open, ws_message, ws_close, ws_error = None, None, None, None

    def route(self, route_str):
        def wrapper(func):
            self._routes[route_str] = func
            return func

        return wrapper

    def ws(self, handle_str):
        if handle_str not in ['open', 'message', 'close', 'error']:
            raise Exception("Error websocket handler, just support 'open', 'message', 'close', 'error'")

        def wrapper(func):
            self._ws_handler[handle_str] = func
            return func

        return wrapper

    def ws_handler(self, handle_str, *args, **kwargs):
        func = self._ws_handler.get(handle_str)
        if func:
            func(*args, **kwargs)

    def dispatch(self, path, args):
        func = self._routes.get(path)
        if func:
            return func(args)
        else:
            return static_file(path, nocache=self.debug)

    def set(self, key, value):
        self._globals[key] = value

    def get(self, key, default=None):
        return self._globals.get(key, default)


app = App()


class HttpHandler:
    HTTP_CODE = {
        200: 'HTTP/1.0 200 OK',
        400: 'HTTP/1.0 400 Bad Request',
        403: 'HTTP/1.0 403 Forbidden',
        404: 'HTTP/1.0 404 Not Found',
        418: 'HTTP/1.0 418 I\'m a teapot',
        500: 'HTTP/1.0 500 Internal Server Error',
    }

    def __init__(self):
        pass

    def http_code(self, code, response=None):
        if code in self.HTTP_CODE:
            return self.HTTP_CODE[code]
        elif response is not None:
            return 'HTTP/1.0 %d %s' % (code, response)
        else:
            return 'HTTP/1.0 %d Unknown Code'

    def _head(self, code, content_type, length):
        return '{0}\r\nServer: httpy 0.1\r\n' \
               'Content-Type:{1}\r\n' \
               'Content-Length:{2}\r\n' \
               'Connection: close\r\n\r\n'.format(self.http_code(code), content_type, length)

    def process(self, header, conn):
        # 仅支持GET方法
        if header['method'] != 'GET':
            response = "Bad Request"
            conn.send(self._head(400, "text/html", len(response)))
            conn.send(response)
            return
        args = {}
        if header['GET']:
            get = header['GET'].split('&')
            for i in get:
                if not i:
                    continue
                if '=' in i:
                    k, v = i.split('=')[:2]
                    args[k] = urllib.unquote_plus(v)
                else:
                    args[i] = ''
        try:
            ret = app.dispatch(header['path'], args)
            if not ret or len(ret) < 3:
                ret = ret500()
        except Exception, e:
            elogger.exception(e)
            ret = ret500('Server Error: %s' % str(e))

        response = self._head(ret[0], ret[1], len(ret[2]))
        conn.send(response)
        conn.send(ret[2])


class WsMessage:
    FIN = 0
    Opcode = 0
    Mask = 0
    data = ''
    length = 0

    complete = False

    def __init__(self):
        pass

    def __str__(self):
        return self.data


class WsContext:
    fileno = None
    _buffer = ""
    _i_buffer = []
    _o_buffer = []

    def __init__(self, fileno):
        self.fileno = fileno

    def close(self):
        self._o_buffer.append(b'\x88\x00')

    def send(self, buf):
        self._o_buffer.append(buf)

    def get_send(self):
        if len(self._o_buffer) > 0:
            return self._o_buffer.pop(0)
        return None

    def push(self, buf):
        self._i_buffer.append(buf)

    def pop(self):
        msg = WsMessage()
        while len(self._buffer) < 2 and len(self._i_buffer) > 0:
            self._buffer += self._i_buffer.pop(0)
        if len(self._buffer) < 2:
            return msg

        msg.FIN = ord(self._buffer[0]) & 0x80
        msg.Opcode = ord(self._buffer[0]) & 0xF
        msg.Mask = ord(self._buffer[1]) & 0x80

        if msg.Opcode == 8:     # 关闭指令
            msg.complete = True
            return msg

        len_flag = ord(self._buffer[1]) & 0x7F  # 数据长度
        if len_flag == 126:
            length = ord(self._buffer[2]) * 256 + ord(self._buffer[3]) + 8
        elif len_flag == 127:
            length = reduce(lambda y, z: y * 256 + z, map(lambda x: ord(x), self._buffer[2:9])) + 14
        else:
            length = len_flag + 6

        while len(self._buffer) < length and len(self._i_buffer) > 0:
            self._buffer += self._i_buffer.pop(0)
        if len(self._buffer) < length:
            return msg

        data = self._buffer[:length]
        self._buffer = self._buffer[length:]

        if len_flag == 126:
            mask = data[4:8]
            raw = data[8:]
        elif len_flag == 127:
            mask = data[10:14]
            raw = data[14:]
        else:
            mask = data[2:6]
            raw = data[6:]
        if msg.Mask == 0:
            msg.data = raw
        else:
            for cnt, d in enumerate(raw):
                msg.data += chr(ord(d) ^ ord(mask[cnt % 4]))
        msg.complete = True
        return msg


class WsHandler:
    context = None
    last_update = 0
    fileno = 0

    def __init__(self, conn):
        self.fileno = conn.fileno()
        self.context = WsContext(self.fileno)
        self.last_update = time.time()

    def wshandshake(self, conn, v):
        key = base64.b64encode(hashlib.sha1(v + '258EAFA5-E914-47DA-95CA-C5AB0DC85B11').digest())
        response = 'HTTP/1.1 101 Switching Protocols\r\n' \
                   'Upgrade: websocket\r\n' \
                   'Connection: Upgrade\r\n' \
                   'Sec-WebSocket-Accept:' + key + '\r\n\r\n'
        conn.send(response)
        self.context = WsContext(conn.fileno())
        app.ws_handler('open', self.context)

    @staticmethod
    def ws_send(conn, data):
        head = b'\x81'
        if len(data) < 126:
            head += struct.pack('B', len(data))
        elif len(data) <= 0xFFFF:
            head += struct.pack('!BH', 126, len(data))
        else:
            head += struct.pack('!BQ', 127, len(data))
        conn.send(head + data)

    def ws_close(self, conn):
        msg = b'\x88\x00'
        conn.send(msg)
        self.handler_close()

    def handler_close(self):
        logging.info("close conn %d" % self.fileno)
        self.fileno = -1
        app.ws_handler('close', self.context)

    def recv(self, conn, size=1024 * 1024):
        self.last_update = time.time()
        try:
            data = conn.recv(size)
        except Exception, e:
            elogger.error(e)
            return False
        if not data:
            return False
        self.context.push(data)
        msg = self.context.pop()
        if msg.complete:
            if msg.Opcode == 8:
                self.handler_close()
                return False
            else:
                app.ws_handler('message', self.context, msg)
        out = self.context.get_send()
        while out:
            if out == b'\x88\x00':
                return False
            self.ws_send(conn, out)
            out = self.context.get_send()
        return True


class Httpy:
    socket = None
    socket_list = set()
    httpHandler = None
    handlers = {}
    is_close = False
    handle_timeout = 10

    def __init__(self, address='127.0.0.1', port=5678):
        self.httpHandler = HttpHandler()
        self.socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        try:
            self.socket.bind((address, port))
            self.socket.listen(100)
            self.socket_list.add(self.socket)
        except Exception, e:
            elogger.warning(str(e))
            exit()

    def __del__(self):
        self.socket.close()
        self.socket.shutdown(socket.SHUT_RDWR)
        alogger.info('connect closed')

    def clean(self):
        for s in self.socket_list:
            if s is not self.socket:
                s.close()
        self.socket_list.clear()
        self.handlers.clear()
        self.socket_list.add(self.socket)

    def scan_handlers(self):
        now = time.time()
        remove = []
        for k, v in self.handlers.items():
            if v.fileno < 0 or now - v.last_update > self.handle_timeout:
                remove.append(k)
        for i in remove:
            handler = self.handlers.pop(i)
            handler.handler_close()

    def close_sock(self, conn):
        fileno = conn.fileno()
        if fileno in self.handlers:
            self.handlers.pop(fileno)
        if conn in self.socket_list:
            self.socket_list.remove(conn)
        conn.close()

    def close(self):
        alogger.info("wait for Server exit")
        self.is_close = True

    @staticmethod
    def get_header(data):
        hd = data.split('\r\n\r\n')[0]
        ld = hd.split('\r\n')
        head = dict()
        h1 = ld[0].split(' ')
        if len(h1) != 3:
            alogger.info('Error request %s' % data)
            return None
        head['method'], head['raw_path'], head['http_version'] = h1
        if '?' in head['raw_path']:
            head['path'], head['GET'] = head['raw_path'].split('?')
        else:
            head['path'], head['GET'] = head['raw_path'], ''

        for i in ld[1:]:
            hx = i.split(':')
            if len(hx) == 2:
                head[hx[0]] = hx[1].lstrip()
        return head

    def protocol(self, conn, addr, port):
        data = conn.recv(8192)
        if data is None:
            self.close_sock(conn)
            return
        header = self.get_header(data)
        if header is None:
            return
        header['remote_address'] = addr
        header['remote_port'] = port
        alogger.info("{0}:{1} {2}".format(addr, port, header['raw_path']))
        if 'Sec-WebSocket-Key' in header:
            # 带key头，为ws连接
            self.socket_list.add(conn)
            wshandler = WsHandler(conn)
            wshandler.wshandshake(conn, header['Sec-WebSocket-Key'])
            self.handlers[conn.fileno()] = wshandler
        else:
            self.httpHandler.process(header, conn)
            conn.close()

    def run(self):
        """
        :param address:
        :param port:
        """
        count = 0
        while True:
            if self.is_close:
                break
            r, w, e = select.select(self.socket_list, [], [], 1)
            for sock in r:
                if sock is self.socket:
                    conn, (addr, port) = sock.accept()
                    # logging.info("accept addr %s:%d" % (addr, port))
                    try:
                        self.protocol(conn, addr, port)
                    except Exception, e:
                        elogger.error(str(e))
                        conn.close()
                else:  # 持续连接 http keep alive
                    fileno = sock.fileno()
                    if fileno in self.handlers:
                        handler = self.handlers[fileno]
                        if handler.fileno == fileno:
                            res = handler.recv(sock)
                            if not res:
                                self.close_sock(sock)
                    else:
                        elogger.error("None handler")
                        if sock in self.socket_list:
                            fileno = sock.fileno
                            self.socket_list.remove(sock)
                        sock.close()

            for sock in e:
                if fileno in self.handlers:
                    self.handlers.pop(fileno)
                    logging.debug("remove handler")
                if sock in self.socket_list:
                    fileno = sock.fileno
                    self.socket_list.remove(sock)
                    logging.debug("remove sock %d" % sock.fileno())

            # scan handlers
            if count > 10:
                self.scan_handlers()
                count = 0
            else:
                count += 1


if __name__ == "__main__":
    logging.basicConfig(level=logging.DEBUG)
    Httpy().run()
