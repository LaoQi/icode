#include "server.h"
#include "http_parser.h"

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

int my_url_callback(http_parser* parser, const char *at, size_t length) {
    /* access to thread local custom_data_t struct.
    Use this access save parsed data for later use into thread local
    buffer, or communicate over socket
    */
    LOG("%d\n", parser->method);
    return 0;
}

void main_loop(HSocket server_sockfd) {
    //client
    char buffer[BUFFER_SIZE];

    http_parser_settings settings =
    { .on_message_begin = 0
        ,.on_header_field = 0
        ,.on_header_value = 0
        ,.on_url = my_url_callback
        ,.on_status = 0
        ,.on_body = 0
        ,.on_headers_complete = 0
        ,.on_message_complete = 0
        ,.on_chunk_header = 0
        ,.on_chunk_complete = 0
    };

    while(TRUE) {
        struct sockaddr_in client_addr;
        socklen_t length = sizeof(client_addr);

        HSocket conn = accept(server_sockfd, (struct sockaddr*)&client_addr, &length);
        if(conn<0) {
            SOCPERROR("connect");
            continue;
        }

        size_t len, nparsed;
        // http parser
        http_parser *parser = malloc(sizeof(http_parser));
        http_parser_init(parser, HTTP_REQUEST);
        parser->data = conn;

        memset(buffer,0,sizeof(buffer));
        len = recv(conn, buffer, sizeof(buffer),0);
        nparsed = http_parser_execute(parser, &settings, buffer, len);
        if (len < 0) {
            LOG("%s recv error\n", inet_ntoa(client_addr.sin_addr));
        }

        LOG("%s\n", inet_ntoa(client_addr.sin_addr));

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
            LOG("Usage: -p port\n\tdefault port is %d", DEFAULT_PORT);
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
