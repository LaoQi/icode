/**********************************************************************************
 *
 *  Tccgi  
 *  Auth : MADAO
 *  compiler by tcc link:http://bellard.org/tcc/
 *  License : GPL
 *
 **********************************************************************************/

#define __NAME__ "Tccgi"
#define __VERSION__ "0.1.0"

#include <windows.h>
#include <winsock2.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#define DEFAULT_PORT 9527
#define SOCKET_BACKLOG 24
#define BUFFER_SIZE 8192
#define RESPONSE_LENGTH 524288

#define PATH_LENGTH 1024
#define MAX_PARAMS 16
#define PARAM_LENGTH 512

#define INDEX_PAGE "index.html"
#define CGI_EXTNAME "cgi"
#define CGI_TIMEOUT 3000

#define ERR_TEMPLATE "HTTP/1.1 %s\r\nContent-Type: text/html\r\nServer: Tccgi 0.1.0\r\nConnection: Close\r\n\r\n<!DOCTYPE html>\n<center><h1>%s</h1><hr>Powered By Tccgi</center>"

#define SOCPERROR printf("Socket Error : %d\n", WSAGetLastError());//perror(errstr)
#define Logln(...) {printf(__VA_ARGS__);printf("\n");}

typedef struct _ReqHead {
    enum { get = 1, post = 2, put = 3, delete = 4} method;
    char path[PATH_LENGTH];
    char params[MAX_PARAMS][PARAM_LENGTH];
    int params_num;
} ReqHead;

void main_loop(int port);
int parse_head(const char *data, size_t len, ReqHead *req);
void http_response_code(int code, SOCKET conn);
int dispatch(ReqHead *req, SOCKET conn);
int static_file(const char* path, SOCKET conn);
int cgi_process(const char* cmd, ReqHead *req, SOCKET conn);

#define HTTP_CODE_NUM 16
char HTTP_CODE[HTTP_CODE_NUM][50] = {
    "200", "Ok",
    "400", "Bad Request",
    "403", "Forbidden",
    "404", "Not Found",
    "405", "Method Not Allowed",
    "406", "Not Acceptable",
    "408", "Request Timeout",
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

char www_root[2048] = {0};
char response[RESPONSE_LENGTH] = {0};
char cgi_ext[10] = {0};
char cgi_buff[RESPONSE_LENGTH] = {0};


int parse_head(const char *data, size_t len, ReqHead *req) {
    size_t i = 0, pi = 0;
    req->params_num = 0;

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
        return -1;  // unknow method
    }

    ++i;
    while (pi < PATH_MAX && data[i] != '?' && data[i] != ' ') {
        req->path[pi++] = data[i++];
    }
    req->path[pi] = '\0';

    if (data[i] == ' ') {
        return 0;
    }

    ++i;
    int j = 0;
    while (i < len &&  data[i] != ' ')  {
        if (data[i] == '+') {
            req->params[req->params_num][j] = '\0';
            j = 0;
            req->params_num++;
            if (req->params_num >= MAX_PARAMS) {
                return -2;  // too more params
            }
            ++i;
            continue;
        }
        req->params[req->params_num][j++] = data[i++];
    }
    req->params_num++;
    return 0;
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
    length = fread(response, 1, length, fp);  
    response[length] = '\0';
    fclose(fp);
    char head[160];
    char type[50];
    mime_type(type, path);
    sprintf(head, "HTTP/1.1 200 OK\r\nContent-Type: %s\r\nContent-Length:%d\r\nServer: Tccgi\r\nConnection: Close\r\n\r\n", type, length);
    send(conn, head, strlen(head), 0);
    send(conn, response, length, 0);
    closesocket(conn);
    return 0;
}

int cgi_process(const char* cmd, ReqHead *req, SOCKET conn) {
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
	if (0 == CreateProcess(cmd, NULL, NULL, NULL, TRUE, NULL, NULL, NULL,&si,&pi)) {
		Logln("Error create %d", GetLastError());
		return -1;
	}
    hProcess = pi.hProcess;
    int dwRet;
    DWORD bytesInPipe;
    LPDWORD bytesRead;
    do {
        dwRet = WaitForSingleObject(hProcess, CGI_TIMEOUT);
        if (dwRet == WAIT_TIMEOUT) {
            Logln("Process timeout : %s", cmd);
            // test kill 通过杀死子进程释放socket
            TerminateProcess(hProcess, 0);
            http_response_code(408, conn);
            break;
        }
        if (dwRet == WAIT_FAILED) {
            Logln("Process error : %d", GetLastError());
            http_response_code(500, conn);
            break;
        }
        if (!PeekNamedPipe(hReadPipe, cgi_buff, RESPONSE_LENGTH, &bytesRead, &bytesInPipe, NULL)) {
            http_response_code(500, conn);
            break;
        }
        Logln(cgi_buff);
        http_response_code(200, conn);
    } while(0);
    
    CloseHandle(hProcess);
    CloseHandle(hReadPipe);
	CloseHandle(hWritePipe);
	return 0;
}

int dispatch(ReqHead *req, SOCKET conn) {
    char path[2048];
    strcpy(path, www_root);
    strcat(path, req->path);
    if (0 == strcmp(req->path, "/")) {
        strcat(path, INDEX_PAGE);
    }
    // @todo config route

    // static file
    if (0 == _access(path, 0)) {
        return static_file(path, conn);
    }
    // cgi
    strcat(path, cgi_ext);
    if (0 == _access(path, 0)) {
        return cgi_process(path, req, conn);
    }
    
    http_response_code(404, conn);
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
    ReqHead *req = (ReqHead*)malloc(sizeof(ReqHead));

    while(1) {
        struct sockaddr_in client_addr;
        int length = sizeof(client_addr);

        SOCKET conn = accept(sock_fd, (SOCKADDR *)&client_addr, &length);
        if(conn<0) {
            SOCPERROR
            continue;
        }

        size_t len;
        memset(buffer,0,sizeof(buffer));
        len = recv(conn, buffer, sizeof(buffer),0);

        char address[50];
        DWORD address_len = 50;
        WSAAddressToString((LPSOCKADDR)&client_addr, sizeof(SOCKADDR), NULL, address, &address_len);

        if (len < 0) {
            Logln("%s recv error", address);
            http_response_code(400, conn);
            continue;
        }

        if (parse_head(buffer, len, req) < 0) {
            Logln("Bad Request");
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
}

int main(int argc, char* argv[]) {

    int port = DEFAULT_PORT;
    sprintf(cgi_ext,".%s", CGI_EXTNAME);
    if (argc >= 2) {
        if (strcmp(argv[1], "-p") != 0 || argc < 3) {
            printf("Usage:\n\
 -p port\tdefault port is %d\n\
 -e extname\tdefault is cgi\n", DEFAULT_PORT);
            exit(1);
        } else {
            port = atoi(argv[2]);
        }
    }

    main_loop(port);
    return 0;
}
