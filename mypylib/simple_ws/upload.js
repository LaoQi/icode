var error = 0;
var success = 0;
var wsUpload = {
    url : 'ws://127.0.0.1:7000/',
    chunk : 1024*8,
    conn : null,
    file : null,
    startSize : 0, 
    endSize : 0,
    no : -1,
    status : 'wait',
    lastquery : 0,
    timeout : 3000,
    progress : null,
    interval : 0,
    starttime: null,
    endtime: null,
    submit : function (fileElement) {
        if (window.MozWebSocket) {
            window.WebSocket = window.MozWebSocket;
        }
        this.starttime = new Date();
        this.conn = new WebSocket(this.url);
        this.conn.onopen = this.onOpen;
        this.conn.onclose = function (event) {
            console.log("closed");
        };
        this.conn.onmessage = this.onMessage;
        this.status = 'progress';
        this.interval = setInterval('wsUpload.poll()', 1000);
        this.fileInit(fileElement);
    },
    poll : function () {
        if (this.status != 'progress') {
            return ;
        }
        console.log("check");
        var now = (new Date).getTime();
        if ((now - this.lastquery) > this.timeout) {
            this.sendNextChunk();
        }
    },
    fileInit : function (fileElement) {
        this.startSize = 0;
        this.endSize = 0;
        this.no = -1;
        this.file = document.getElementById(fileElement).files[0];
    },
    sendNextChunk : function() {
        this.no++;
        // console.log("send next : " + this.no);
        this.sendFile(this.no);
    },
    sendFile : function (no) {
        var file = this.file;
        this.startSize = no * this.chunk;
        this.endSize = this.startSize + this.chunk;
        if (this.endSize > file.size) {
            this.endSize = file.size;
        }
        if (this.onProgress) {
            this.onProgress(this.endSize/file.size);
        }
        if (this.startSize > this.endSize) {
            // error
            this.sendMessage('over');
            return ;
        }
        var blob = file.slice(this.startSize, this.endSize);
        var reader = new FileReader();
        reader.readAsDataURL(blob);
        reader.no = no;
        reader.startSize = this.startSize;
        reader.endSize = this.endSize;
        reader.onload = function loaded(evt) {
            // var ArrayBuffer = evt.target.result;
            var index = evt.target.result.search("base64,") + 7;
            var data = evt.target.result.slice(index);
            // console.info("No : " + this.no + " s : " + this.startSize + " e : "+this.endSize);
            wsUpload.sendMessage('f|' + this.no + '|'+this.startSize+'|'+this.endSize+'|' + data);
        };
        reader.onerror = function (evt) {
            console.error("read error " + this.no);
        };
    },
    sendMessage : function (msg) {
        this.conn.send(msg);
    },
    sendComplete : function () {
        clearInterval(this.interval);
        console.log("发送文件完毕");
        wsUpload.sendMessage('over');
        this.endtime = new Date();
        if (this.onEnd) {
            this.onEnd();
        }
        return ;
    },
    onProgress : null,
    onEnd : null,
    onOpen : function () {
        console.log("open");
    },
    onMessage : function (event) {
        this.lastquery = (new Date()).getTime();
        // console.log(event.data + ':' + wsUpload.endSize);
        if (event.data == 'init') {
            wsUpload.sendMessage('init|'+wsUpload.file.name);
            return ;
        } else if (event.data == 'complete') {
            
        } else {
            var result = event.data.split(':');
            if (result.length < 1) {
                // console.log(event.data);
                wsUpload.sendMessage('close');
                return ;
            }
            if (result[0] == 'ok') {
                success++;
                if (wsUpload.endSize >= wsUpload.file.size) {
                    wsUpload.sendComplete();
                }
                wsUpload.sendNextChunk();
                return ;
            }
            if (result[0] == 'retry' || result[0] == 'empty' || result[0] == 'error') {
                error++;
                var no = parseInt(result[1]);
                wsUpload.no = no;
                wsUpload.sendFile(no);
                return;
            }
            console.log('never be here!');
        }
    }
};
