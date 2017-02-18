var error = 0;
var success = 0;
var wsUpload = {
    url : 'ws://127.0.0.1:7000/',
    chunk : 1024*8,
    conn : null,
    file : null,
    filehash : '',
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
        // 先校验文件md5
        this.fileInit(fileElement);
    },
    fileInit : function (fileElement) {
        this.startSize = 0;
        this.endSize = 0;
        this.no = -1;
        this.file = document.getElementById(fileElement).files[0];
        var blobSlice = File.prototype.slice || File.prototype.mozSlice || File.prototype.webkitSlice,
            file = this.file,
            chunkSize = 2097152,                           // read in chunks of 2MB
            chunks = Math.ceil(file.size / chunkSize),
            currentChunk = 0,
            spark = new SparkMD5(),
            fileReader = new FileReader();

        fileReader.onload = function (e) {
            if (wsUpload.checkFileProcess) {
                wsUpload.checkFileProcess(currentChunk + 1)
            }

            spark.appendBinary(e.target.result);                 // append array buffer
            currentChunk += 1;

            if (currentChunk < chunks) {
                console.log("check file chunk " + currentChunk);
                loadNext();
            } else {
                wsUpload.filehash = spark.end()
                console.log(wsUpload.filehash)
                // 开始建立连接
                wsUpload.connect();
            }
        };

        fileReader.onerror = function () {
            console.log("error");
        };

        function loadNext() {
            var start = currentChunk * chunkSize,
                end = start + chunkSize >= file.size ? file.size : start + chunkSize;

            fileReader.readAsBinaryString(blobSlice.call(file, start, end));
        }
        loadNext();
    },
    connect : function () {
        if (window.MozWebSocket) {
            window.WebSocket = window.MozWebSocket;
        }
        this.starttime = new Date();
        this.conn = new WebSocket(this.url);
        this.conn.onopen = this.onOpen;
        this.conn.onclose = this.onClose;
        this.conn.onmessage = this.onMessage;
        this.status = 'progress';
    },
    poll : function () {
        if (wsUpload.status != 'progress') {
            return ;
        }
        var now = (new Date).getTime();
        console.log((now - this.lastquery));
        if ((now - this.lastquery) > this.timeout) {
            this.ping();
        }
    },
    ping : function () {
        this.conn.send('ping');
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
        console.log("发送文件完毕");
        this.conn.send('over|ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890abcdefghijklmnopqrstuvwxyz');
        this.endtime = new Date();
        wsUpload.status = 'end';
        clearInterval(wsUpload.interval);
        if (this.onEnd) {
            this.onEnd();
        }
        return ;
    },
    onProgress : null,
    onEnd : null,
    onClose : function() {
        clearInterval(wsUpload.interval);
        console.log("连接关闭");
    },
    onOpen : function () {
        console.log("open");
        wsUpload.interval = setInterval('wsUpload.poll()', 3000);
        wsUpload.sendNextChunk();
    },
    onMessage : function (event) {
        wsUpload.lastquery = (new Date()).getTime();
        // console.log(event.data + ':' + wsUpload.endSize);
        if (event.data == 'init') {
            wsUpload.sendMessage('init|'+wsUpload.file.name);
            return ;
        } else if (event.data == 'over') {
            wsUpload.sendMessage('check|'+wsUpload.filehash);
        } else {
            // console.log(event.data);
            var result = event.data.split(':');
            if (result.length < 1) {
                wsUpload.sendMessage('closed|ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890abcdefghijklmnopqrstuvwxyz');
                return ;
            }
            if (result[0] == 'ok') {
                success++;
                if (wsUpload.endSize >= wsUpload.file.size) {
                    wsUpload.sendComplete();
                    return ;
                }
                wsUpload.sendNextChunk();
                return ;
            }
            else if (result[0] == 'already') {
                error++;
                if (wsUpload.endSize >= wsUpload.file.size) {
                    wsUpload.sendComplete();
                    return ;
                }
                this.no++;
                wsUpload.sendNextChunk();
                return ;
            }
            else if (result[0] == 'retry' || result[0] == 'empty' || result[0] == 'error') {
                error++;
                var no = parseInt(result[1]);
                wsUpload.no = no;
                wsUpload.sendFile(no);
                return;
            }
            else if (result[0] == 'check') {
                console.log(result);
                wsUpload.sendMessage('closed|ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890abcdefghijklmnopqrstuvwxyz');
                return;
            }
            console.log('never be here!', event.data);
        }
    }
};
