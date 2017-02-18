<?php

namespace Common;

use Common\RedisProxy;
/**
 * 流水日志管线
 * （存入Redis,交给其他进程处理）
 *
 * @author QQQ
 */
class LogPipe {
    
    const DbIndex = 2;
    static private $rdb = null;
    // 限制3000条，防止处理未完成仍继续写入
    const Limit = 3000;
    
    const Key = 'LOG::PIPE';
    const File = 'logpipe';
    
    /**
     * 
     * @return \Redis
     */
    static private function _GetRdb() {
        if (self::$rdb === null) {
            $rdb = RedisProxy::getInstance();
            $rdb->select(self::DbIndex);
            self::$rdb = $rdb;
        }
        return self::$rdb;
    }

    static public function Send($msg) {
        $rdb = self::_GetRdb();
        $len = $rdb->lLen(self::Key);
        if (!is_string($msg)) {
            $msg = json_encode($msg);
        }
        // 超出时写入文件
        if ($len >= self::Limit) {
            file_put_contents(RUNTIME_DIR . 'Backup/' . self::File, $msg . PHP_EOL);
            return;
        }
        $rdb->lpush(self::Key, $msg);
    }
    
}
