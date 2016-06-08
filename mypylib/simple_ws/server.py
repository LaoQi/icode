# -*- coding: utf-8 -*-
# 简易http 与 websocket 服务端

import logging
import socket
import base64
import hashlib
import struct
import os
import binascii
import json

from select import select

logging.basicConfig(level=logging.DEBUG)


class Server:
    socket = None
    socket_list = set()
    port = 7000
    timeout = 20
    html = None

    session = dict()

    def __init__(self):
        with open("index.html", 'r') as f:
            self.html = f.read()

    def wshandshake(self, conn, v):

        key = base64.b64encode(hashlib.sha1(v + '258EAFA5-E914-47DA-95CA-C5AB0DC85B11').digest())
        response = 'HTTP/1.1 101 Switching Protocols\r\n' \
                   'Upgrade: websocket\r\n' \
                   'Connection: Upgrade\r\n' \
                   'Sec-WebSocket-Accept:' + key + '\r\n\r\n'
        conn.send(response)
        self.socket_list.add(conn)
        # 超时时长， 文件名， 缓存大小
        self.session[conn.fileno()] = dict()
        self.ws_send(conn, 'init')

    @staticmethod
    def ws_recv(conn, size=1024*1024):
        data = conn.recv(size)
        if not len(data):
            return False, 0

        FIN = ord(data[0]) & 128  # 结束位
        Opcode = ord(data[0]) & 112  # 操作码
        is_mask = ord(data[1]) & 128  # 是否加掩码
        length = ord(data[1]) & 127  # 数据长度

        if length == 126:
            mask = data[4:8]
            raw = data[8:]
        elif length == 127:
            mask = data[10:14]
            raw = data[14:]
        else:
            mask = data[2:6]
            raw = data[6:]
        ret = ''
        for cnt, d in enumerate(raw):
            ret += chr(ord(d) ^ ord(mask[cnt % 4]))
        if not ret:
            pass
            # logging.debug("frame info FIN %d Opcode %d mask %d length %d " % (FIN, Opcode, is_mask, length))
            # hexstr = binascii.b2a_hex(data)
            # bsstr = bin(int(hexstr, 16))[2:]
            # logging.debug(bsstr)
        return ret, len(raw)

    @staticmethod
    def ws_send(conn, data):
        head = '\x81'
        if len(data) < 126:
            head += struct.pack('B', len(data))
        elif len(data) <= 0xFFFF:
            head += struct.pack('!BH', 126, len(data))
        else:
            head += struct.pack('!BQ', 127, len(data))
        conn.send(head + data)

    def ws_close(self, conn):
        fileno = conn.fileno()
        logging.info("close conn %d" % fileno)
        if fileno in self.session:
            self.session.pop(fileno)
        if conn in self.socket_list:
            self.socket_list.remove(conn)

    def protocol(self, conn):
        data = conn.recv(8192)
        is_ws = False
        for line in data.split('\r\n\r\n')[0].split('\r\n')[1:]:
            k, v = line.split(': ')
            # 带key头，为ws连接
            if k == 'Sec-WebSocket-Key':
                is_ws = True
                self.wshandshake(conn, v)
        # 非ws连接时，采用json做接口协议
        if not is_ws:
            response = 'HTTP/1.1 200 OK\r\nContent-Type:text/html\r\n' + \
                       'Content-Length:%d\r\n\r\n' % len(self.html)
            conn.send(response)
            conn.send(self.html)
            conn.close()

    @staticmethod
    def params_data(raw_data):
        data = raw_data.split('|')
        msg = dict()
        if len(data) > 0:
            msg['a'] = data[0]
        if msg['a'] == 'init' and len(data) == 2:
            msg['name'] = data[1]
            return msg
        if len(data) > 4:
            msg['n'] = int(data[1])
            msg['s'] = int(data[2])
            msg['e'] = int(data[3])
            msg['d'] = base64.b64decode(data[4])
            return msg
        return msg

    def ws_process(self, conn):
        data, length = self.ws_recv(conn)
        sesskey = conn.fileno()
        # logging.info(data)

        if not data and data is False:
            return

        if sesskey not in self.session:
            self.ws_send(conn, 'session error!')

            if conn in self.socket_list:
                self.socket_list.remove(conn)
            conn.close()
            return

        session = self.session[sesskey]

        if data == '':
            # if conn in self.socket_list:
            #     self.socket_list.remove(conn)
            logging.info(data)
            logging.info("ignore empty msg")
            self.ws_send(conn, 'empty:%d' % session['no'])
            # conn.close()
            return
        try:
            msg = self.params_data(data)
        except Exception, e:
            # logging.exception(e)
            logging.debug("error:%d" % session['no'])
            self.ws_send(conn, "error:%d" % session['no'])
            return

        if "a" in msg:
            if msg['a'] == 'init':
                self.session[sesskey]['name'] = msg['name']
                self.ws_send(conn, 'ok:0')
                self.session[sesskey]['buffer'] = []
                self.session[sesskey]['no'] = 0
            elif msg['a'] == 'f':
                logging.info('a %s s %d e %d n %d' % (msg['a'], msg['s'], msg['e'], msg['n']))
                start, end = msg['s'], msg['e']
                length = end - start
                if msg['n'] != session['no']:
                    if msg['n'] < session['no']:
                        logging.info('already msg %d' % msg['n'])
                        self.ws_send(conn, 'ok:%d' % msg['n'])
                    else :
                        logging.info("ignore msg %d %d" % (msg['n'], session['no']))
                        self.ws_send(conn, "retry:%d" % (session['no']))

                elif length != len(msg['d']):
                    logging.info("error length msg %d %d" % (length, len(msg['d'])))
                    self.ws_send(conn, "retry:%d" % (msg['n']))

                else:
                    self.session[sesskey]['buffer'].append(msg['d'])
                    self.session[sesskey]['no'] += 1
                    logging.info('ok msg %d' % msg['n'])
                    self.ws_send(conn, "ok:%d" % (msg['n']))

            elif msg['a'] == 'over':
                logging.info("total recv %d" % (len(session['buffer'])))
                with open(os.path.join(os.path.dirname(__file__), 'upload', session['name']), 'ab') as f:
                    for i in session['buffer']:
                        f.write(i)
                self.ws_close(conn)

    def run(self):
        self.socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        try:
            self.socket.bind(('0.0.0.0', self.port))
            self.socket.listen(10)
            self.socket_list.add(self.socket)
        except Exception, e:
            print e
            exit()
        while True:
            r, w, e = select(self.socket_list, [], [])
            for sock in r:
                if sock == self.socket:
                    conn, addr = sock.accept()
                    logging.debug("accept addr %s:%d" % addr)
                    self.protocol(conn)
                else:  # 持续连接的都是ws
                    self.ws_process(conn)

            for sock in e:
                if sock in self.socket_list:
                    # sock.close()
                    self.socket_list.remove(sock)
                    logging.debug("remove sock %d" % sock.fileno())

            # logging.info(self.session)


if __name__ == "__main__":
    Server().run()
