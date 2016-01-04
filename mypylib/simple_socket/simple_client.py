# -*- coding:utf-8 -*-
"""
Simple Socket client
"""

import socket
import logging
import os
from simple_server import SSocketError, RECV_MAX


class SSClient:

    AF_INET = socket.AF_INET
    AF_UNIX = socket.AF_UNIX

    def __init__(self, family=socket.AF_INET, type=socket.SOCK_STREAM, address="0.0.0.0", port=9016):
        """
        Simple client
        :param family:
        :param type:
        :param address:
        :param port:
        :param client_num:
        :return:
        """
        self.family = family
        self.type = type

        # FILE
        if family == socket.AF_UNIX:
            self.address = address

        # NET
        elif family == socket.AF_INET:
            self.port = port
            self.address = (address, port)

        logging.debug('Socket created')
        self.socket = socket.socket(family, type)

    def connect(self):
        try:
            self.socket.connect(self.address)
        except Exception as e:
            raise SSocketError("Socket connect error : %s" % str(e))

    def request(self, data):
        try:
            self.socket.send(data)
            ret = self.socket.recv(RECV_MAX)
        except Exception as e:
            raise SSocketError("Socket send data error : %s" % str(e))

        return ret

    def close(self):
        try:
            self.socket.close()
        except Exception as e:
            raise SSocketError("Socket close error : %s" % e)
