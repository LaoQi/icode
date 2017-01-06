## Tccgi

简单的玩具型http服务器，支持基础的cgi请求。通过[tcc](http://bellard.org/tcc/)编译。  

### 构建：
  
`winsock2.h` 来自MinGW项目的win32API。
依赖`ws2_32.dll`,使用：
```  
tiny_imdef.exe ws2_32.dll
```
生成`ws2_32.def` 执行:
```  
tcc.exe server.c ws2_32.def
```
构建完成:)

### 支持程度：
  
普通静态文件(限制大小512kB)，cgi输出(512kB)。  
仅支持`GET`请求  
支持的环境变量：
```  
SERVER_NAME
QUERY_STRING
SERVER_SOFTWARE
GATEWAY_INTERFACE
SERVER_PROTOCOL
REQUEST_METHOD
PATH_INFO
SCRIPT_NAME
REMOTE_ADDR
REMOTE_PORT
```

cgi支持`Content-Type`与`Status`头部，不支持`Location`头  
  
### 执行：
```  
server.exe [-d www_root | -p port | -t cgi_timeout | -e cgi_extname]
Usage:
-d root directory
-p port        default port is 9527
-t cgi timeout default is 3 seconds
-e extname     default is cgi
```  

### License : WTFPL 

```
            DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
                   Version 2, December 2004

Copyright (C) 2004 Sam Hocevar <sam@hocevar.net>

Everyone is permitted to copy and distribute verbatim or modified
copies of this license document, and changing it is allowed as long
as the name is changed.

           DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
  TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION

 0. You just DO WHAT THE FUCK YOU WANT TO.

```

