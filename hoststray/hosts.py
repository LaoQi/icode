# -*- coding:utf-8 -*-

import os


class Hosts:

    annotation = []
    ip_disable = []
    ip_enable = []

    def __init__(self):
        with open(r'hosts', 'r') as f:
            for i in f:
                if i.startswith('#'):
                    q = i.split()
                    if len(q) == 3:
                        self.ip_disable.append(q)
                    else:
                        self.annotation.append(i)
                else:
                    q = i.split()
                    if len(q) == 2:
                        self.ip_enable.append(q)

    def getlist(self):
        return self.ip_enable + self.ip_disable

    def export(self, path=r'hosts'):
        with open(path, 'w') as f:
            f.writelines(self.annotation)
            for i in self.ip_disable + self.ip_enable:
                f.write(' '.join(i) + '\n')

    def refresh(self):
        pass

    def enable(self, ele):
        if ele in self.ip_disable:
            self.ip_disable.remove(ele)
        if ele not in self.ip_enable:
            self.ip_enable.append(ele)
        self.refresh()

    def disable(self, ele):
        if ele not in self.ip_disable:
            self.ip_disable.append(ele)
        if ele in self.ip_enable:
            self.ip_enable.remove(ele)
        self.refresh()


if __name__ == '__main__':
    Hosts().export(r'hosts.test')



