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


def md5_for_file(f, block_size=2 ** 20):
    md5 = hashlib.md5()
    while True:
        data = f.read(block_size)
        if not data:
            break
        md5.update(data)
    return md5.hexdigest()


class Server:
    socket = None
    socket_list = set()
    port = 7000
    buffersize = 1024*1024
    timeout = 20
    content = dict()

    session = dict()

    def __init__(self):
        filelist = ['test.html', 'upload.js', 'spark-md5.min.js']
        for i in filelist:
            with open(i, 'r') as f:
                self.content[i] = f.read()

    def wshandshake(self, conn, v):

        key = base64.b64encode(hashlib.sha1(v + '258EAFA5-E914-47DA-95CA-C5AB0DC85B11').digest())
        response = 'HTTP/1.1 101 Switching Protocols\r\n' \
                   'Upgrade: websocket\r\n' \
                   'Connection: Upgrade\r\n' \
                   'Sec-WebSocket-Accept:' + key + '\r\n\r\n'
        conn.send(response)
        self.socket_list.add(conn)
        # 超时时长， 文件名， 缓存大小
        self.session[conn.fileno()] = dict(buffer='', length=0, no=0)
        self.ws_send(conn, 'init')

    def ws_process(self, conn, size=1024*1024):
        data = conn.recv(size)
        sesskey = conn.fileno()
        if sesskey not in self.session or 'buffer' not in self.session[sesskey]:
            self.ws_send(conn, 'session error!')

            if conn in self.socket_list:
                self.socket_list.remove(conn)
            conn.close()
            return

        self.session[sesskey]['buffer'] += data
        # 可能关闭连接，销毁session
        while sesskey in self.session and self.session[sesskey]['buffer']:
            if self.session[sesskey]['length'] == 0:
                b = self.session[sesskey]['buffer']
                if len(b) < 14:
                    break
                len_flag = ord(b[1]) & 127  # 数据长度
                if len_flag == 126:
                    self.session[sesskey]['length'] = ord(b[2]) * 256 + ord(b[3]) + 8
                elif len_flag == 127:
                    self.session[sesskey]['length'] = reduce(lambda y, z: y * 256 + z, map(lambda x: ord(x), b[2:9])) + 14
                else:
                    self.session[sesskey]['length'] = len_flag + 6
                # logging.info("length %d, buffer %d" % (self.session[sesskey]['length'], len(self.session[sesskey]['buffer'])))

            if self.session[sesskey]['length'] <= len(self.session[sesskey]['buffer']) \
                    and self.session[sesskey]['length'] != 0:
                # 处理完整包
                pack_data = self.session[sesskey]['buffer'][:self.session[sesskey]['length']]

                if len(self.session[sesskey]['buffer']) > self.session[sesskey]['length']:
                    self.session[sesskey]['buffer'] = self.session[sesskey]['buffer'][self.session[sesskey]['length']:]
                else:
                    self.session[sesskey]['buffer'] = ''
                self.session[sesskey]['length'] = 0

                self.package_process(conn, pack_data)

            else:
                break

    def package_process(self, conn, data):
        # logging.info(data)

        FIN = ord(data[0]) & 128  # 结束位
        Opcode = ord(data[0]) & 112  # 操作码
        is_mask = ord(data[1]) & 128  # 是否加掩码
        len_flag = ord(data[1]) & 127  # 数据长度

        if len_flag == 126:
            mask = data[4:8]
            length = ord(data[2]) * 256 + ord(data[3])
            raw = data[8:]
        elif len_flag == 127:
            mask = data[10:14]
            raw = data[14:]
            length = reduce(lambda y, z: y * 256 + z, map(lambda x: ord(x), data[2:9]))
        else:
            mask = data[2:6]
            raw = data[6:]
            length = len_flag
        ret = ''
        for cnt, d in enumerate(raw):
            ret += chr(ord(d) ^ ord(mask[cnt % 4]))
        if not ret:
            pass
            # logging.debug("frame info FIN %d Opcode %d mask %d length %d " % (FIN, Opcode, is_mask, length))
            # hexstr = binascii.b2a_hex(data)
            # bsstr = bin(int(hexstr, 16))[2:]
            # logging.debug(bsstr)

        sesskey = conn.fileno()
        session = self.session[sesskey]

        if not ret or ret is False:
            # if conn in self.socket_list:
            #     self.socket_list.remove(conn)
            logging.info("ignore empty msg")
            self.ws_send(conn, 'empty:%d' % session['no'])
            # conn.close()
            return
        try:
            # logging.info(ret[:10])
            msg = self.params_data(ret)
        except Exception, e:
            # logging.exception(e)
            logging.debug("error:%d" % session['no'])
            self.ws_send(conn, "error:%d" % session['no'])
            return

        if "a" in msg:
            if msg['a'] == 'init':
                self.session[sesskey]['name'] = msg['name']
                # self.ws_send(conn, 'ok:0')
                self.session[sesskey]['filebuffer'] = []
                self.session[sesskey]['no'] = 0
                self.session[sesskey]['file'] = open(
                    os.path.join(os.path.dirname(__file__), 'upload', msg['name']), 'ab')
            elif msg['a'] == 'ping':
                self.ws_send(conn, "ok:%d" % (self.session[sesskey]['no']))
            elif msg['a'] == 'f':
                logging.info('a %s s %d e %d n %d' % (msg['a'], msg['s'], msg['e'], msg['n']))
                start, end = msg['s'], msg['e']
                length = end - start
                if msg['n'] != session['no']:
                    if msg['n'] < session['no']:
                        logging.info('already msg %d' % msg['n'])
                        self.ws_send(conn, 'already:%d' % msg['n'])
                    else:
                        logging.info("ignore msg %d %d" % (msg['n'], session['no']))
                        self.ws_send(conn, "retry:%d" % (session['no']))

                elif length != len(msg['d']):
                    logging.info("error length msg %d %d" % (length, len(msg['d'])))
                    self.ws_send(conn, "retry:%d" % (msg['n']))

                else:
                    self.session[sesskey]['filebuffer'].append(msg['d'])
                    self.session[sesskey]['no'] += 1
                    # logging.info('ok msg %d' % msg['n'])
                    # 每1M写入一次
                    if len(session['filebuffer']) > 128:
                        for i in session['filebuffer']:
                            self.session[sesskey]['file'].write(i)
                        self.session[sesskey]['filebuffer'] = []
                    self.ws_send(conn, "ok:%d" % (msg['n']))

            elif msg['a'] == 'over':
                for i in session['filebuffer']:
                    self.session[sesskey]['file'].write(i)
                self.session[sesskey]['filebuffer'] = []
                self.session[sesskey]['file'].close()
                logging.info("over")
                self.ws_send(conn, "over")

            elif msg['a'] == 'check':
                logging.info("check file md5 : %s" % msg['hash'])
                with open(os.path.join(os.path.dirname(__file__), 'upload', session['name']), 'rb') as f:
                    md5 = md5_for_file(f)
                    logging.info(md5)
                self.ws_send(conn, "check:%s" % md5)

            elif msg['a'] == 'closed':
                logging.info("closed")
                self.ws_close(conn)

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
        msg = '\x88\x00'
        conn.send(msg)
        fileno = conn.fileno()
        logging.info("close conn %d" % fileno)
        if fileno in self.session:
            self.session.pop(fileno)
        if conn in self.socket_list:
            self.socket_list.remove(conn)

    def protocol(self, conn):
        data = conn.recv(8192)
        is_ws = False
        query = data.split('\r\n\r\n')[0].split('\r\n')
        head = query[0].split(' ')
        path = '/'
        if len(head) > 2:
            path = head[1]
        logging.info(path)
        for line in query[1:]:
            k, v = line.split(': ')
            # 带key头，为ws连接
            if k == 'Sec-WebSocket-Key':
                is_ws = True
                self.wshandshake(conn, v)
        # 非ws连接时，采用json做接口协议
        if not is_ws:
            filename = 'test.html'
            if len(path) > 1:
                filename = path[1:]
            if filename not in self.content:
                filename = 'test.html'
                # @TODO 404
            response = 'HTTP/1.1 200 OK\r\nContent-Type:text/html\r\n' + \
                       'Content-Length:%d\r\n\r\n' % len(self.content[filename])

            conn.send(response)
            conn.send(self.content[filename])
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
        if msg['a'] == 'check' and len(data) == 2:
            msg['hash'] = data[1]
            return msg
        if len(data) > 4:
            msg['n'] = int(data[1])
            msg['s'] = int(data[2])
            msg['e'] = int(data[3])
            msg['d'] = base64.b64decode(data[4])
            return msg
        return msg

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
                    self.ws_process(sock)

            for sock in e:
                if sock in self.socket_list:
                    # sock.close()
                    self.socket_list.remove(sock)
                    logging.debug("remove sock %d" % sock.fileno())

            # logging.info(self.session)


if __name__ == "__main__":
    Server().run()
