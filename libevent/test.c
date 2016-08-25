#include <sys/types.h>

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>

#include <event.h>
#include <evhttp.h>

void generic_request_handler(struct evhttp_request *req, void *arg)
{
    evhttp_add_header(req->output_headers,
            "Server", "yaoniming4000");
    evhttp_add_header(req->output_headers,
            "Content-Type", "text/html; charset=utf-8");
    struct evbuffer *returnbuffer = evbuffer_new();

    evbuffer_add_printf(returnbuffer, "Hello World!");
    evhttp_send_reply(req, HTTP_OK, "Client", returnbuffer);
    evbuffer_free(returnbuffer);
    return;
}

int main(int argc, char **argv)
{
    short http_port = 10000;
    char *http_addr = "0.0.0.0";
    struct evhttp *http_server = NULL;

    event_init();
    http_server = evhttp_start(http_addr, http_port);
    evhttp_set_gencb(http_server, generic_request_handler, NULL);

    fprintf(stderr, "Server started on port %d\n", http_port);
    event_dispatch();

    return(0);
}
