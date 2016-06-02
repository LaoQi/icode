#ifndef __SERVER_H__
#define __SERVER_H__

#ifdef WIN32
#define PLATFORM_WIN 1
#else
#define PLATFORM_LINUX 1
#endif

#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#ifdef PLATFORM_LINUX
/////////////////////////////////////////
//PLATFORM_LINUX
/////////////////////////////////////////
#include <netinet/in.h>
#include <unistd.h>
typedef int HSocket;

#define SOCPERROR(errstr) perror(errstr)
#define SOC_PROTOCOL 0
#define ISSOCKHANDLE(x) (x>0)
#define CLOSE_SOCKET(x) close(x)
#define INITIALIZE_SOCKET_ENV do {}while(0)

#define LOG(...) do{ char log_buff[1024]; sprintf(log_buff, __VA_ARGS__); fputs(log_buff, stdout); }while(0)
#endif
/////////////////////////////////////////

////////////////////////////////////////
//WIN32
////////////////////////////////////////
#ifdef PLATFORM_WIN
#pragma comment(lib,"ws2_32.lib")
#include <WinSock2.h>
#include <Windows.h>
typedef SOCKET HSocket;
typedef int socklen_t;

#define SOCPERROR(errstr) printf("Socket Error : %d\n", WSAGetLastError());//perror(errstr)
#define SOC_PROTOCOL IPPROTO_TCP
#define ISSOCKHANDLE(x) (x!=INVALID_SOCKET)
#define CLOSE_SOCKET(x) closesocket(x)
#define INITIALIZE_SOCKET_ENV do { \
    WSADATA  Ws; \
    if ( WSAStartup(MAKEWORD(2,2), &Ws) != 0 ) { SOCPERROR("startup"); exit(4); } }while(0)

#define LOG(...) do{ char log_buff[1024]; sprintf_s(log_buff, 1024, __VA_ARGS__); fputs(log_buff, stdout); }while(0)
#endif
//////////////////////////////////////////

#define DEFAULT_PORT 10000
#define SOCKET_BACKLOG 24
#define BUFFER_SIZE 1024
#define TRUE 1
#define FALSE 0


int main(int, char** );
void main_loop(HSocket);
int bind_socket(u_short);

#define HTML "HTTP/1.1 200 OK \n\n\
<!DOCTYPE html>\n\
<html>HelloWorld!</html> "

#endif //__SERVER_H__
