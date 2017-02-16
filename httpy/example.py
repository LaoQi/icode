# -*- coding:utf-8 -*-

import logging
import os
from httpy import Httpy, app, static_file

logging.basicConfig(level=logging.DEBUG)


@app.route('/')
def index(args):
    return static_file('example.html', True)


@app.ws('message')
def on_message(ctx, msg):
    logging.info(msg)
    ctx.send('yes')


@app.ws('close')
def on_close(ctx):
    logging.info('close')


@app.ws('open')
def on_open(ctx):
    logging.info('open')

Httpy().run()

