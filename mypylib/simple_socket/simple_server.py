# -*- coding:utf-8 -*-
"""
Simple Socket server
"""

import socket
import logging
import os


# 64kb buff
RECV_MAX = 1024 * 64


class SSocketError(Exception):
    pass


class SSServer:

    def __init__(self, family=socket.AF_UNIX, type=socket.SOCK_STREAM, address="0.0.0.0", port=9016, client_num=10):
        """
        simple server, default client num is 1
        :param family:
        :param type:
        :param address:
        :param port:
        :param client_num:
        :return:
        """
        self.family = family
        self.type = type
        self.client_num = client_num
        self.not_close = True

        # FILE
        if family == socket.AF_UNIX:
            self.address = address
            if os.path.exists(address):
                raise SSocketError("Server is running, or you can check file \"%s\"" % address)

        # NET
        elif family == socket.AF_INET:
            self.port = port
            self.address = (address, port)

        logging.debug('Socket created')
        self.socket = socket.socket(family, type)
        # bind address
        self._bind()

    def _bind(self):
        try:
            self.socket.bind(self.address)
        except socket.error, msg:
            raise SSocketError('Bind failed. Error Code : ' + str(msg[0]) + ' Message ' + msg[1])
        logging.debug('Socket bind complete')
        self.socket.listen(self.client_num)
        logging.debug('Socket now listening')

    def start(self, callfunc=None, obj=None):
        """
        Start socket server
        :param callfunc: callback function , default first params is current connect, if return -1 then server closed
        :return:
        """
        if not callfunc:
            logging.error("Should set process function")
            return
        # now keep talking with the client
        while self.not_close:
            # wait to accept a connection - blocking call
            conn, addr = self.socket.accept()
            logging.debug('Connected with %s' % str(addr))
            while True:
                try:
                    data = conn.recv(RECV_MAX)
                except Exception as e:
                    logging.error("Error %s" % e)
                    break
                if not data:
                    logging.debug('Connected close %s' % str(addr))
                    break
                else:
                    ret = 0
                    send_data = "{'hello'}"
                    s_data = None
                    if callfunc:
                        ret, s_data = callfunc(str(data))
                    if ret == -1:
                        break
                    elif ret == 0 and s_data:
                        send_data = s_data
                    conn.send(send_data)
            conn.close()
        self.socket.close()
        logging.debug('Server closed')

    def close(self):
        self.not_close = False
        self.socket.close()
        if self.family == socket.AF_UNIX:
            if os.path.exists(self.address):
                os.remove(self.address)


