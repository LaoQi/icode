<?php

namespace Common;

/**
 * Description of Log
 *
 * @author QQQ
 */
class Log {
    
    private $config = null;
    /**
     * @var \Common\Log
     */
    private static $instance = null;
    // 时间 等级 消息
    private static $format = "%s %s %s\n";
    public $logfile ;
    private $maxlevel;
    
    static $levelTag = [
        'debug' => 10,
        'info' => 20,
        'warning' => 30,
        'danger' => 40,
    ];
    
    private function __construct($config) {
        $this->config = $config;
        $this->logfile = $config['Dir'] . date('Ymd') . '.log';
        $maxlevel = isset($config['Level']) ? $config['Level'] : 'debug';
        $this->maxlevel = isset(self::$levelTag[$maxlevel]) ? self::$levelTag[$maxlevel] : 1;
        if (!is_writable($config['Dir'])) {
            throw new \Exception('Log file not writable');
        }
    }
    
    public static function Init($config) {
        if (self::$instance !== null) {
            throw new \Exception('Log already init!');
        }
        self::$instance = new self($config);
    }
    
    public static function write($log, $levelOrTag = 'debug') {
        if (self::$instance == null) {
            return false;
        }
        $level = 99;
        $tag = $levelOrTag;
        if (isset(self::$levelTag[$levelOrTag])) {
            $level = self::$levelTag[$levelOrTag];
            $tag = '['.ucwords($tag).']';
        }
        if ($level >= self::$instance->maxlevel) {
            $str = sprintf(self::$format, date('m-d H:i:s'), $tag, $log);
            file_put_contents(self::$instance->logfile, $str, FILE_APPEND);
        }
    }
    
    public static function Debug($log) {
        self::write($log, 'debug');
    }
    
    public static function Info($log) {
        self::write($log, 'info');
    }
    
    public static function Warning($log) {
        self::write($log, 'warning');
    }
    
    public static function Danger($log) {
        self::write($log, 'danger');
    }
}
