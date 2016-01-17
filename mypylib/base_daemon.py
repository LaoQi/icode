# -*- coding:utf-8 -*-

"""
    base_daemon
"""

import time
import os
import logging
import sys
import threading
from .simple_socket import SSServer


class BaseDaemon(object):
    def __init__(self, looptime=2, socket_address=None):
        self.looptime = looptime
        self.socket_address = socket_address
        self.pid = os.getpid()
        self.server = None
        self.thread = None

    def start(self):
        logging.info("Start")
        while True:
            # 启动通信socket
            if not self.thread or not self.thread.is_alive():
                self.thread = threading.Thread(target=self.daemon_run, args=())
                self.thread.setDaemon(True)
                self.thread.start()
            time.sleep(self.looptime)

            try:
                self.do_work()
            except Exception as e:
                logging.exception(e)

    def close(self):
        self.server.close()
        sys.exit(0)

    def daemon_run(self):
        self.server = SSServer(address=self.socket_address)
        try:
            self.server.start(self.server_callback)
        except Exception as e:
            logging.error("Server start failed at %s" % e)
            self.close()

    def server_callback(self, data):
        """
        example:
            ret = 0
            jdata = {}
            try:
                jdata = json.loads(data)
            except ValueError as e:
                logging.error("Server data error %s" % e)
            if "request" in jdata:
                if not self.status:
                    return 1, None
                if jdata["request"] == "status":
                    return ret, self.status
                elif jdata["request"] == "osd_tree":
                    return ret, self.osd_tree
                elif jdata["request"] == "osd_perf":
                    return ret, self.osd_perf
        """
        pass

    def do_work(self):
        pass
