#include "server.h"

HSocket bind_socket(u_short port) {

    INITIALIZE_SOCKET_ENV;
    //sockfd
    HSocket server_sockfd = socket(AF_INET,SOCK_STREAM, SOC_PROTOCOL);

    //sockaddr_in
    struct sockaddr_in server_sockaddr;
    server_sockaddr.sin_family = AF_INET;
    server_sockaddr.sin_port = htons(port);
    server_sockaddr.sin_addr.s_addr = htonl(INADDR_ANY);

    ///bind，，success return 0，error return -1
    if(bind(server_sockfd,(struct sockaddr *)&server_sockaddr,sizeof(server_sockaddr)) == -1) {
        SOCPERROR("bind");
        exit(1);
    }

    //listen，success return 0，error return -1
    if(listen(server_sockfd, SOCKET_BACKLOG) == -1) {
        SOCPERROR("listen");
        exit(2);
    }

    return server_sockfd;
}

void main_loop(HSocket server_sockfd) {
    //client
    char buffer[BUFFER_SIZE];

    while(TRUE) {
        struct sockaddr_in client_addr;
        socklen_t length = sizeof(client_addr);

        HSocket conn = accept(server_sockfd, (struct sockaddr*)&client_addr, &length);
        IN_ADDR ip_addr = client_addr.sin_addr;
        LOG("#%d.%d.%d.%d \n", ip_addr.S_un.S_un_b.s_b1, ip_addr.S_un.S_un_b.s_b2, ip_addr.S_un.S_un_b.s_b3, ip_addr.S_un.S_un_b.s_b4);
        if(conn<0) {
            SOCPERROR("connect");
            continue;
        }
        memset(buffer,0,sizeof(buffer));
        int len = recv(conn, buffer, sizeof(buffer),0);

        char html[sizeof(HTML)];
        strcpy(html, HTML);
        send(conn, html, strlen(html), 0);
        CLOSE_SOCKET(conn);
    }
}

int main(int argc, char* argv[]) {

    int port = DEFAULT_PORT;
    if (argc >= 2) {
        if (strcmp(argv[1], "-p") != 0 || argc < 3) {
            fputs("Usage: -p port\n\tdefault port is 8888", stdout);
            exit(1);
        } else {
            port = atoi(argv[2]);
        }
    }

    HSocket server_sockfd = bind_socket(port);
    if (server_sockfd < 0) {
        SOCPERROR("connect error");
        exit(3);
    }
    LOG("Server start at %d\n", port);
    main_loop(server_sockfd);

    CLOSE_SOCKET(server_sockfd);
    return 0;
}
