# -*- coding:utf-8 -*-

import os
from hosts import Hosts
from tray import SysTrayIcon


def bye(sysTrayIcon):
    print 'Bye, then.'


class Gui:
    hosts = None

    def __init__(self):
        self.hosts = Hosts()

    def build_menu(self):
        hlist = self.hosts.getlist()
        menu = [(u'配置', None, self.hello)]
        for i in hlist:
            menu.append((' '.join(i), None, self.hello))
        if len(menu) > 100:
            menu = menu[:100]
        return tuple(menu)

    def hello(self, sysTrayIcon):
        print "hello"

    def run(self):
        menu_options = self.build_menu()
        SysTrayIcon(r'Setup.ico', hover_text='hoststray', menu_options=menu_options,
                    quit_name=u'退出', on_quit=bye, default_menu_index=1)

Gui().run()
