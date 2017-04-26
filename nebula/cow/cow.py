# -*- coding:utf-8 -*-
"""
cow.py
"""

import codecs
import os
import re
import shutil
import stat
import sys
import json
import timeit

import markdown
from pymdownx import extra

ABSTRACT_LEN = 300
INDEX_LIMIT = 20
TAG_TITLE = '{{TITLE}}'
TAG_CONTENT = '{{CONTENT}}'
RE_TRIP = re.compile('<.*?>|\n')
RE_TITLE = re.compile('<h1>(.+?)</h1>')

class MyPostprocessor(markdown.postprocessors.Postprocessor):
    """ markdown postprocessor """

    def __init__(self, *args, **kwargs):
        super(MyPostprocessor).__init__(*args, **kwargs)
        self.title = 'nebula-m78'
        self.abstract = ''

    def run(self, text):
        abstract_len = ABSTRACT_LEN
        result = RE_TITLE.match(text)
        if result:
            self.title = result.group(1)
            abstract_len = len(self.title) + ABSTRACT_LEN + 9 # abstract + <h1></h1>
            self.abstract = RE_TRIP.sub('', text[len(self.title) + 9:])[:abstract_len]
        else:
            self.abstract = RE_TRIP.sub('', text)[:abstract_len]

        content = TEMPLATE.replace(TAG_TITLE, self.title)

        self.abstract = RE_TRIP.sub('', self.abstract)
        return content.replace(TAG_CONTENT, text)

def generate(src, dst):
    """conver md2html"""
    input_file = codecs.open(src, mode="r", encoding="utf-8")
    text = input_file.read()

    markd = markdown.Markdown(extensions=[extra.makeExtension()])
    processor = MyPostprocessor()
    markd.postprocessors.add('mypreprocessor', processor, '_end')

    output_file = codecs.open(dst, "w", encoding="utf-8")
    output_file.write(markd.convert(text))
    status = os.stat(src)
    return (processor.title, processor.abstract, status[stat.ST_MTIME])

def building(source_root, target_root):
    """doc"""

    count_copy = 0
    count_convert = 0
    start = timeit.default_timer()
    result = []
    struct = {}

    for root, dirs, files in os.walk(source_root):
        for dirname in dirs:
            dir_path = os.path.join(root, dirname)
            target_dir = os.path.join(target_root, os.path.relpath(dir_path, source_root))
            if not os.path.exists(target_dir):
                os.mkdir(target_dir)

        for filename in files:
            file_path = os.path.join(root, filename)
            relpath = os.path.relpath(root, source_root)
            target_file = os.path.join(target_root, relpath, filename)

            if relpath == '.':
                relpath = '/'
            else:
                relpath = '/' + relpath + '/'

            if filename.endswith(".md"):
                title, abstract, timestamp = generate(file_path, target_file[:-3] + ".html")
                if relpath not in struct:
                    struct[relpath] = []
                struct[relpath].append({'title':title, 'path':filename[:-3], 'mtime': timestamp})

                result.append({
                    'title':title, 'abstract':abstract,
                    'mtime':timestamp, 'path':relpath + filename[:-3] + '.html'})
                count_convert += 1
            else:
                shutil.copy(file_path, target_file)
                count_copy += 1

    cost_time = timeit.default_timer() - start

    # sort
    result.sort(key=lambda x: x['mtime'])
    for key in struct:
        struct[str(key)].sort(key=lambda x: x['mtime'])

    # storage
    for i in range(0, len(result), INDEX_LIMIT):
        with open(os.path.join(target_root, 'index.%d.json'%(i/INDEX_LIMIT)), 'w') as output:
            json.dump(result[i:i+INDEX_LIMIT], output)
    with open(os.path.join(target_root, 'struct.json'), 'w') as output:
        json.dump(struct, output)

    print(
        "Copy %d files, Convert %d markdown files, Use time : %ds" %
        (count_copy, count_convert, cost_time))


if __name__ == "__main__":
    if len(sys.argv) < 3:
        print('Error Params')
        exit(1)

    def check(path):
        """check path"""
        return os.path.isdir(os.path.abspath(path))

    TEMPLATE = """<!DOCTYPE html>
<html>
    <head>
        <title>{{TITLE}}</title>
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    </head>
    <body>{{CONTENT}}</body>
</html>"""
    if os.path.exists('template.html'):
        with open('template.html', mode='r') as t:
            TEMPLATE = t.read()

    if not check(sys.argv[1]) or not check(sys.argv[2]):
        print('Error Path')
        exit(2)

    DOCUMENT_ROOT = os.path.abspath(sys.argv[1])
    TARGET_ROOT = os.path.abspath(sys.argv[2])
    building(DOCUMENT_ROOT, TARGET_ROOT)
