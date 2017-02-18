/**********************************************************************************
 *
 *  Tccgi  
 *  Auth : MADAO
 *  License : WTFPL
 *  compiler by tcc link:http://bellard.org/tcc/
 *  cgi :  https://www.ietf.org/rfc/rfc3875
 *
 **********************************************************************************/

#define __NAME__ "Tccgi"
#define __VERSION__ "0.1.1"

#include <windows.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include "winsock2.h"

#define DEFAULT_PORT 9527
#define SOCKET_BACKLOG 24
#define BUFFER_SIZE 8192
#define RESPONSE_LENGTH 655360

#define PATH_LENGTH 1024
#define QUERY_STR_LEN 1024
#define MAX_PARAMS 16
#define PARAM_LENGTH 512
#define ENV_LENGTH 8192
#define MAX_HEADER 16
#define HEADER_LENGTH 1024
#define MAX_BODY 524288

#define INDEX_PAGE "index.html"
#define CGI_EXTNAME "cgi"
#define CGI_TIMEOUT 3

#define PUSH_ENV(x, y, z) {x += strlen(x) + 1; sprintf(x, "%s=%s", y, z);}
#define PUSH_ENV_D(x, y, z) {x += strlen(x) + 1; sprintf(x, "%s=%d", y, z);}
#define Logln(...) do{char _logbuff[1024]; sprintf(_logbuff, __VA_ARGS__); write(STDOUT_FILENO,_logbuff,strlen(_logbuff)); write(STDOUT_FILENO,"\n",1);} while(0);
#define SOCPERROR Logln("Socket Error : %d\n", WSAGetLastError());//perror(errstr)

typedef struct _Request {
    char method[7];
    char path[PATH_LENGTH];
    char query_string[QUERY_STR_LEN];
    char params[PATH_LENGTH + QUERY_STR_LEN];
    char request_uri[PATH_LENGTH + QUERY_STR_LEN];
    char remote_addr[60];
    char script_name[PATH_LENGTH];
    int remote_port;
} Request;

typedef struct _Response {
    int code;
    int header_num;
    int body_length;
    char phrase[25];
    char header[MAX_HEADER*2][PARAM_LENGTH];
    char body[MAX_BODY];
} Response;

#define HTTP_CODE_NUM 18
char HTTP_CODE[HTTP_CODE_NUM][50] = {
    "200", "OK",
    "400", "Bad Request",
    "403", "Forbidden",
    "404", "Not Found",
    "405", "Method Not Allowed",
    "406", "Not Acceptable",
    "408", "Request Timeout",
    "414", "Request-URI Too Long",
    "500", "Internal Server Error"
};

#define MIME_TYPE_NUM 12
char MIME_TYPE[MIME_TYPE_NUM][50] = {
    "html", "text/html",
    "js", "application/x-javascript",
    "json", "application/json; charset=utf-8",
    "css", "text/css",
    "png", "image/png",
    "jpg", "image/jpeg"
};

// Global
char DEFAULT_TYPE[] = "application/octet-stream";
int bind_port = 9527;
int cgi_timeout = 3;
char cgi_ext[10];

char www_root[2048];
char resbuff[RESPONSE_LENGTH];
size_t cgi_ext_len;
char cgi_buff[MAX_BODY];
char header_buff[1024];
char lpEnv[ENV_LENGTH];
Response *response;

char* strsep_s(char *buff, char* cdr, char delim, size_t len) {
    size_t i = 0;
    while (i < len && *cdr != '\0' && *cdr != delim) {
        *buff = *cdr;
        ++buff; ++cdr; ++i;
    }
    *buff = '\0'; 
    if (*cdr == '\0') {
        return NULL;
    }
    if (i < len) {
        ++cdr;
    }
    return cdr;
}

int parse_head(const char *data, size_t len, Request *req) {
    size_t i = 0, pi = 0;
    BOOL has_query = FALSE;
    while (i < 6 && data[i] != ' ') {
        req->method[i] = data[i];
        ++i;
    }
    req->method[i] = '\0';
    ++i;
    while (pi < PATH_MAX && data[i] != '?' && data[i] != ' ') {
        req->path[pi++] = data[i++];
    }
    req->path[pi] = '\0';

    if (data[i] == '?') has_query = TRUE;
    ++i; pi = 0;
    while(has_query && data[i] != ' ' && pi < QUERY_STR_LEN) {
        req->query_string[pi++] = data[i++];
    }
    req->query_string[pi] = '\0';
    return 0;
}

int clear_buffer(char *buffer, size_t buffsize) {
    int i = 0, del = 0;
    while (i + del < buffsize) {
        buffer[i] = buffer[i + del];
        if (buffer[i] == 13 && i + del + 1 < buffsize && buffer[i + del + 1] == 10) {
            buffer[i] = 10;
            ++del;
        }
        ++i;
    }
    return buffsize - del;
}

void build_cgi_req(Request *req, const char* path) {
    sprintf(req->script_name, "%s%s", req->path, cgi_ext);
    if (NULL == strchr(req->query_string, '=') && strlen(req->query_string) > 1) {
        sprintf(req->params, "%s %s", path, req->query_string);
        char* p = strchr(req->params, ' ');
        while('\0' != *p) {
            if ('+' == *p) {
                *p = ' ';
            }
            p++;
        }
    } else {
        strcpy(req->params, path);
    }
}

void build_cgi_env(char* env, Request* req) {
    memset(env, 0, ENV_LENGTH);
    sprintf(env, "%s=%s", "SERVER_NAME", "Boom shaka Laka");
    // env += strlen(env) + 1;
    // sprintf(env, "%s=%s", "PATH", req->path);
    PUSH_ENV(env, "QUERY_STRING", req->query_string);
    PUSH_ENV(env, "SERVER_SOFTWARE", __NAME__);
    PUSH_ENV(env, "GATEWAY_INTERFACE", "CGI/1.1");
    PUSH_ENV(env, "SERVER_PROTOCOL", "HTTP/1.1");
    PUSH_ENV_D(env, "SERVER_PORT", bind_port);
    PUSH_ENV(env, "REQUEST_METHOD", req->method);
    PUSH_ENV(env, "PATH_INFO", req->path);
    PUSH_ENV(env, "SCRIPT_NAME", req->script_name);
    PUSH_ENV(env, "REMOTE_ADDR", req->remote_addr);
    PUSH_ENV_D(env, "REMOTE_PORT", req->remote_port);
    // PUSH_ENV(env, "REQUEST_URI", req->request_uri)
    env = env + strlen(env);
    sprintf(env, "%c%c", 0, 0);
}

void reset_response(Response* res) {
    res->code = res->header_num = res->body_length = 0;
}

void reset_request(Request* req) {
    memset(req->method, 0, 7);
    memset(req->path, 0, PATH_LENGTH);
    memset(req->query_string, 0, QUERY_STR_LEN);
    memset(req->params, 0, PATH_LENGTH + QUERY_STR_LEN);
    memset(req->remote_addr, 0, 60);
    memset(req->script_name, 0, PATH_LENGTH);
}

int add_header(Response* res, const char* name, const char* value) {
    if (res->header_num < MAX_HEADER) {
        int i = res->header_num * 2;
        strcpy(res->header[i], name);
        strcpy(res->header[i+1], value);
        res->header_num += 1;
        return 0;
    }
    return -1;
}

int send_response(SOCKET conn, Response* res) {
    sprintf(resbuff, "HTTP/1.1 %d %s\r\n", res->code, res->phrase);
    memset(header_buff, '\0', HEADER_LENGTH);
    for(int i = 0; i < res->header_num; i++) {
        sprintf(header_buff, "%s: %s\r\n", res->header[i*2], res->header[i*2+1]);
        strcat(resbuff, header_buff);
    }
    // add length server
    sprintf(header_buff, 
        "Content-Length: %d\r\nServer: %s\r\nConnection: Close\r\n\r\n", 
        res->body_length, __NAME__);
        // "Server: %s\r\nConnection: Close\r\n\r\n", __NAME__);
    strcat(resbuff, header_buff);
    send(conn, resbuff, strlen(resbuff), 0);
    if (res->body_length > 0) {
        send(conn, res->body, res->body_length, 0);
    } 
    closesocket(conn);
    return 0;
}

void http_response_code(int code, SOCKET conn) {
    char res[255], info[50];
    int i = 0;
    do{
        if (atoi(HTTP_CODE[i]) == code) {
            sprintf(info, "%s %s", HTTP_CODE[i], HTTP_CODE[i+1]);
            break;
        }
        i += 2;
        if (i > HTTP_CODE_NUM) {
            sprintf(info, "%s %s", "500", "Internal Server Error");
        }
    } while(i < HTTP_CODE_NUM);

    sprintf(res, "HTTP/1.1 %s\r\nContent-Type: text/html\r\nServer: %s\r\nConnection: Close\r\n\r\n<!DOCTYPE html>\n<center><h1>%s</h1><hr>Powered By Tccgi</center>", info, __NAME__, info);
    send(conn, res, strlen(res), 0);
    closesocket(conn);
}

char* mime_type(char *type, const char* path) {
    char* ext = strchr(path, '.');
    if (ext == NULL) {
        strcpy(type, DEFAULT_TYPE);
        return type;
    }
    ext++;
    for (int i = 0; i < MIME_TYPE_NUM; i+=2) {
        if (0 == stricmp(MIME_TYPE[i], ext)) {
            strcpy(type, MIME_TYPE[i+1]);
            return type;
        }
    }
    strcpy(type, DEFAULT_TYPE);
    return type;
}

int static_file(const char *path, SOCKET conn) {
    FILE * fp;
    errno_t err;
    if ((err = fopen_s(&fp, path, "r")) != 0) {
        Logln("error!");
        return 1;
    }
    fseek(fp, 0, SEEK_END);
    int length = ftell(fp);
    if (length >= RESPONSE_LENGTH) {
        fclose(fp);
        Logln("File too large!");
        return 2;
    }
    rewind(fp);
    length = fread(resbuff, 1, length, fp);  
    resbuff[length] = '\0';
    fclose(fp);
    char head[160];
    char type[50];
    mime_type(type, path);
    sprintf(head, "HTTP/1.1 200 Ok\r\nContent-Type: %s\r\nContent-Length: %d\r\nServer: Tccgi\r\nConnection: Close\r\n\r\n", type, length);
    send(conn, head, strlen(head), 0);
    send(conn, resbuff, length, 0);
    closesocket(conn);
    return 0;
}

int cgi_parse(HANDLE hProcess, HANDLE hReadPipe, SOCKET conn) {
    int dwRet;
    DWORD bytesInPipe, bytesRead;
    
    dwRet = WaitForSingleObject(hProcess, cgi_timeout*1000);
    if (dwRet == WAIT_TIMEOUT) {
        Logln("Process timeout");
        // test kill 通过杀死子进程释放socket
        TerminateProcess(hProcess, 0);
        http_response_code(408, conn);
        return 0;
    }
    if (dwRet == WAIT_FAILED) {
        Logln("Process error : %d", GetLastError());
        return 1;
    }
    // reset cgi_buff
    memset(cgi_buff, 0, MAX_BODY);
    if ( !PeekNamedPipe(hReadPipe, cgi_buff, MAX_BODY, &bytesRead, &bytesInPipe, NULL) ) {
        return 2;
    }
    // conver \r\n to \n
    clear_buffer(cgi_buff, bytesRead + 1);
    // check CGI-field
    response->code = 200;
    strcpy(response->phrase, "OK");

    char line_buff[1024];
    char *cdr = cgi_buff;
    cdr = strsep_s(line_buff, cdr, '\n', 1024);
    if (strncmp("Content-Type", line_buff, 12) == 0) {
        char* ct = strchr(line_buff, ':');
        if (ct == NULL || strlen(ct) < 3) {
            return 3;
        }
        ++ct;
        while(*ct == ' ') {++ct;}
        add_header(response, "Content-Type", ct);
    } else if (strncmp("Status", line_buff, 6) == 0) {
        char code[4];
        char *p = line_buff;
        int cur = 0;
        while(strlen(p) > 2 && cur < 3) {
            if (*p >= '0' && *p <= '9') {
                code[cur] = *p;
                ++cur;
            } else if (cur > 0) {
                return 4;
            }
            ++p;
        }
        ++p;
        code[cur] = '\0';
        response->code = atoi(code);
        strcpy(response->phrase, p);
    } else if (strncmp("Location", line_buff, 8) == 0) {
        // @todo
        add_header(response, "Location", "");
    } else {
        Logln("Error cgi content");
        return 5;
    }
    // check "\n\n"
    int i = 0;
    memset(header_buff, '\0', HEADER_LENGTH);
    cdr = strsep_s(line_buff, cdr, '\n', 1024);
    while (cdr != NULL && strlen(line_buff) > 1 && i++ < MAX_HEADER) {
        char* p = line_buff;
        p = strsep_s(header_buff, p, ':', 1024);
        if (p == NULL || strlen(p) == 1) {
            return 6;
        }
        while(' ' == *p) {++p;}
        add_header(response, header_buff, p);
        cdr = strsep_s(line_buff, cdr, '\n', 1024);
    }
    if (i >= MAX_HEADER || strlen(line_buff) > 0) {
        return 10;
    }
    size_t body_length = 0;
    if (cdr != NULL) {
        body_length = strlen(cdr);
        strncpy(response->body, cdr, body_length);
    }
    response->body_length = body_length;
    send_response(conn, response);
    return  0;
}

int cgi_process(const char* cmd, Request *req, SOCKET conn) {
    HANDLE hReadPipe, hWritePipe, hProcess;
    SECURITY_ATTRIBUTES sa;

    sa.nLength = sizeof(SECURITY_ATTRIBUTES);
    sa.lpSecurityDescriptor = NULL; //使用系统默认的安全描述符
    sa.bInheritHandle = TRUE; //一定要为TRUE，不然句柄不能被继承。  bug: socket 也被继承，无法关闭

    CreatePipe(&hReadPipe,&hWritePipe,&sa,0); //创建pipe内核对象,设置好hReadPipe,hWritePipe.
    STARTUPINFO si;
    PROCESS_INFORMATION pi; 
    si.cb = sizeof(STARTUPINFO);
    GetStartupInfo(&si); 
    si.hStdError = hWritePipe; //设定其标准错误输出为hWritePipe
    si.hStdOutput = hWritePipe; //设定其标准输出为hWritePipe
    si.wShowWindow = SW_HIDE;
    si.dwFlags = STARTF_USESHOWWINDOW | STARTF_USESTDHANDLES;
    Logln(req->params);
    build_cgi_env(lpEnv, req);
    if (0 == CreateProcess(cmd, req->params, NULL, NULL, TRUE, 0, lpEnv, NULL, &si, &pi)) {
        Logln("Error create %d", GetLastError());
        return -1;
    }
    int ret;
    if ((ret = cgi_parse(pi.hProcess, hReadPipe, conn)) != 0) {
        Logln("Cgi error %d", ret);
        http_response_code(500, conn);
    }
    
    CloseHandle(hProcess);
    CloseHandle(hReadPipe);
    CloseHandle(hWritePipe);
    return 0;
}

int dispatch(Request *req, SOCKET conn) {
    char path[PATH_LENGTH];
    strcpy(path, www_root);
    strcat(path, req->path);
    if (0 == strcmp(req->path, "/")) {
        strcat(path, INDEX_PAGE);
    }
    // reset response
    reset_response(response);
    // @todo config route

    if (0 == _access(path, 0)) {
        if (strlen(req->path) > cgi_ext_len && 0 == strcmp((path + strlen(path) - cgi_ext_len), cgi_ext) ) {
            build_cgi_req(req, path);
            return cgi_process(path, req, conn);
        }
        // static file
        return static_file(path, conn);
    }
    // cgi
    strcat(path, cgi_ext);
    if (0 == _access(path, 0)) {
        build_cgi_req(req, path);
        return cgi_process(path, req, conn);
    }
    
    http_response_code(404, conn);
    return 0;
}

void main_loop() {

    WSADATA Ws;
    if ( WSAStartup(MAKEWORD(2,2), &Ws) != 0 ) { 
        SOCPERROR
        exit(4); 
    };
    //sockfd
    SOCKET sock_fd = socket(AF_INET,SOCK_STREAM, IPPROTO_TCP);

    //sockaddr_in
    struct sockaddr_in server_sockaddr;
    server_sockaddr.sin_family = AF_INET;
    server_sockaddr.sin_port = htons(bind_port);
    server_sockaddr.sin_addr.s_addr = htonl(INADDR_ANY);

    int reuse0 = 1;
    if (setsockopt(sock_fd, SOL_SOCKET, SO_REUSEADDR, (char *)&reuse0, sizeof(reuse0))==-1) {
        SOCPERROR
        exit(errno);
    } 

    ///bind，，success return 0，error return -1
    if(bind(sock_fd,(struct sockaddr *)&server_sockaddr,sizeof(server_sockaddr)) == -1) {
        SOCPERROR
        exit(1);
    }

    //listen，success return 0，error return -1
    if(listen(sock_fd, SOCKET_BACKLOG) == -1) {
        SOCPERROR
        exit(2);
    }

    if (sock_fd < 0) {
        SOCPERROR
        exit(3);
    }

    Logln("Server start...");
    //client
    char buffer[BUFFER_SIZE];
    Request *req = (Request*)malloc(sizeof(Request));
    response = (Response*)malloc(sizeof(Response));
    DWORD address_len = 60;
    char address[60];

    while(1) {
        struct sockaddr_in client_addr;
        int length = sizeof(client_addr);

        reset_request(req);

        SOCKET conn = accept(sock_fd, (SOCKADDR *)&client_addr, &length);
        if(conn<0) {
            SOCPERROR
            continue;
        }

        size_t len;

        *buffer = 0;
        len = recv(conn, buffer, sizeof(buffer),0);
        memset(address, 0, 60);
        WSAAddressToString((LPSOCKADDR)&client_addr, sizeof(SOCKADDR), NULL, address, &address_len);

        if (len < 0) {
            Logln("%s recv error", address);
            http_response_code(400, conn);
            continue;
        }

        if (parse_head(buffer, len, req) != 0) {
            Logln("Bad request from %s", address);
            Logln("Recv : %s", buffer);
            http_response_code(400, conn);
            continue;
        }

        Logln("%s: %s %s", req->method, address, req->path);

        // just support get
        if (0 != stricmp(req->method, "GET")) {
            http_response_code(405, conn);
            continue;
        }

        char* sep = strchr(address, ':');
        if (sep != NULL) {
            *sep = 0;
            strcpy(req->remote_addr, address);
            req->remote_port = atoi(sep+1);
        }

        if (dispatch(req, conn) == 0) {
            continue;
        }
        http_response_code(500, conn);
    }
    closesocket(sock_fd);
    free(req);
    free(response);
}

int main(int argc, char* argv[]) {

    bind_port = DEFAULT_PORT;
    cgi_timeout = CGI_TIMEOUT;
    sprintf(cgi_ext,".%s", CGI_EXTNAME);
    cgi_ext_len = strlen(cgi_ext);
    // get root path
    getcwd(www_root, MAX_PATH);
    if (argc > 1 && 0 == (argc%2)) {
        printf("Usage:
 -d root directory
 -p port\tdefault port is %d
 -t cgi timeout\tdefault is %d seconds
 -e extname\tdefault is %s\n", DEFAULT_PORT, CGI_TIMEOUT, CGI_EXTNAME);
            exit(1);
    }
    while(argc > 2) {
        if (0 == strcmp(argv[argc - 2], "-p") && atoi(argv[argc-1]) > 0) {
            bind_port = atoi(argv[argc - 1]);
        } else if (0 == strcmp(argv[argc - 2], "-t") && atoi(argv[argc - 1]) > 0) {
            cgi_timeout = atoi(argv[argc - 1]);
        } else if (0 == strcmp(argv[argc - 2], "-e") && strlen(argv[argc - 1]) < 10) {
            sprintf(cgi_ext,".%s", argv[argc - 1]);
            cgi_ext_len = strlen(argv[argc - 1]);
        } else if (0 == strcmp(argv[argc - 2], "-d") && strlen(argv[argc - 1]) < MAX_PATH) {
            strcpy(www_root, argv[argc - 1]);
        }
        argc -= 2;
    }
    Logln("www_root : %s\nCGI extname : %s\nBind port : %d\nCGI timeout : %d seconds",
        www_root, cgi_ext + 1, bind_port, cgi_timeout);
    main_loop();
    return 0;
}
