/**********************************************************************************
 *
 *  Tccgi  
 *  Auth : MADAO
 *  License : GPL
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
#define MAX_HEADER 16
#define HEADER_LENGTH 1024
#define MAX_BODY 524288

#define INDEX_PAGE "index.html"
#define CGI_EXTNAME "cgi"
#define CGI_TIMEOUT 3000

#define ERR_TEMPLATE "HTTP/1.1 %s\r\nContent-Type: text/html\r\nServer: Tccgi 0.1.0\r\nConnection: Close\r\n\r\n<!DOCTYPE html>\n<center><h1>%s</h1><hr>Powered By Tccgi</center>"

#define SOCPERROR printf("Socket Error : %d\n", WSAGetLastError());//perror(errstr)
#define Logln(...) {printf(__VA_ARGS__);printf("\n");}

typedef struct _Request {
    enum { get = 1, post = 2, put = 3, delete = 4} method;
    char path[PATH_LENGTH];
    char query_string[QUERY_STR_LEN];
    char params[PATH_LENGTH + QUERY_STR_LEN];
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
    "200", "Ok",
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
char DEFAULT_TYPE[] = "application/octet-stream";

char www_root[2048];
char resbuff[RESPONSE_LENGTH];
char cgi_ext[10];
char cgi_buff[MAX_BODY];
char header_buff[1024];
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
    memset(req->query_string, '\0', QUERY_STR_LEN);

    char method[7];
    while (i < 6 && data[i] != ' ') {
        method[i] = data[i];
        ++i;
    }
    method[i] = '\0';
    if (0 == strcmp(method, "GET")) {
        req->method = get;
    } else if (0 == strcmp(method, "POST")) {
        req->method = post;
    } else if (0 == strcmp(method, "PUT")) {
        req->method = put;
    } else if (0 == strcmp(method, "DELETE")) {
        req->method = delete;
    } else {
        return 2;  // unknow method
    }

    ++i;
    while (pi < PATH_MAX && data[i] != '?' && data[i] != ' ') {
        req->path[pi++] = data[i++];
    }
    req->path[pi] = '\0';

    pi = 0;
    while(data[i] != ' ' && pi < QUERY_STR_LEN) {
        req->query_string[pi++] = data[i++];
    }
    req->query_string[pi] = '\0';
    return 0;
}

void build_cgi_req(Request *req, const char* path) {
    memset(req->params, '\0', PATH_LENGTH + QUERY_STR_LEN);
    if (strlen(req->query_string) > 1) {
        sprintf(req->params, "%s %s", path, req->query_string + 1);
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

void reset_response(Response* res) {
    res->code = res->header_num = res->body_length = 0;
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

int send_response(SOCKET conn, char* buff, Response* res) {
    sprintf(buff, "HTTP/1.1 %d %s\r\n", res->code, res->phrase);
    memset(header_buff, '\0', HEADER_LENGTH);
    for(int i = 0; i < res->header_num; i++) {
        sprintf(header_buff, "%s: %s\r\n", res->header[i*2], res->header[i*2+1]);
        strcat(buff, header_buff);
    }
    // add length server
    sprintf(header_buff, 
        "Content-Length: %d\r\nServer: %s\r\nConnection: Close\r\n\r\n", 
        res->body_length, __NAME__);
    strcat(buff, header_buff);
    if (res->body_length > 0) {
        strcat(buff, res->body);
    } 
    send(conn, buff, strlen(buff), 0);
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

    sprintf(res, ERR_TEMPLATE, info, info);
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
    sprintf(head, "HTTP/1.1 200 Ok\r\nContent-Type: %s\r\nContent-Length:%d\r\nServer: Tccgi\r\nConnection: Close\r\n\r\n", type, length);
    send(conn, head, strlen(head), 0);
    send(conn, resbuff, length, 0);
    closesocket(conn);
    return 0;
}

int cgi_parse(HANDLE hProcess, HANDLE hReadPipe, SOCKET conn) {
    int dwRet;
    DWORD bytesInPipe;
    LPDWORD bytesRead;
    
    dwRet = WaitForSingleObject(hProcess, CGI_TIMEOUT);
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
    memset(cgi_buff, '\0', MAX_BODY);
    if (!PeekNamedPipe(hReadPipe, cgi_buff, RESPONSE_LENGTH, &bytesRead, &bytesInPipe, NULL)) {
        return 2;
    }
    // check CGI-field
    response->code = 200;
    strcpy(response->phrase, "Ok");

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
    if (i >= MAX_HEADER || strlen(line_buff) != 1) {
        return 10;
    }
    int body_length = 0;
    if (cdr != NULL) {
        strcpy(response->body, cdr);
        body_length = strlen(cdr);
    }
    response->body_length = body_length;
    send_response(conn, resbuff, response);
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
    if (0 == CreateProcess(cmd, req->params, NULL, NULL, TRUE, NULL, NULL, NULL,&si,&pi)) {
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

    // static file
    if (0 == _access(path, 0)) {
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

void main_loop(int port) {

    // get root path
    getcwd(www_root, MAX_PATH);
    Logln("www_root : %s", www_root);

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
    server_sockaddr.sin_port = htons(port);
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

    Logln("Server start at %d;", port);
    //client
    char buffer[BUFFER_SIZE];
    Request *req = (Request*)malloc(sizeof(Request));
    response = (Response*)malloc(sizeof(Response));

    while(1) {
        struct sockaddr_in client_addr;
        int length = sizeof(client_addr);

        SOCKET conn = accept(sock_fd, (SOCKADDR *)&client_addr, &length);
        if(conn<0) {
            SOCPERROR
            continue;
        }

        size_t len;

        *buffer = '\0';
        len = recv(conn, buffer, sizeof(buffer),0);

        char address[50];
        DWORD address_len = 50;
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

        Logln("ACCEPT: %s %s", address, req->path);

        // just support get
        if (req->method != get) {
            http_response_code(405, conn);
            continue;
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

    int port = DEFAULT_PORT;
    sprintf(cgi_ext,".%s", CGI_EXTNAME);
    if (argc >= 2) {
        if (strcmp(argv[1], "-p") != 0 || argc < 3) {
            printf("Usage:\n\
 -p port\tdefault port is %d\n\
 -t cgi timeout\tdefault is 3 seconds\n\
 -e extname\tdefault is cgi\n", DEFAULT_PORT);
            exit(1);
        } else {
            port = atoi(argv[2]);
        }
    }

    main_loop(port);
    return 0;
}
