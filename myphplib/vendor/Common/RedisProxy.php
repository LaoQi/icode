<?php

namespace Common;

/**
 * Description of RedisProxy
 *
 * @author QQQ
 */
class RedisProxy {
    
    private static $host = null;
    private static $port;
    private static $auth;
    private static $timeout;
    
    /**
     * Redis实例
     * @var \Redis
     */
    private static $rdb = null;
    
    private function __construct() {
        ;
    }
    
    public function __destruct() {
        if (self::$rdb) {
            self::$rdb->close();
        }
    }

    public static function Init($config) {
        self::$host = isset($config['Host']) ? $config['Host'] : '127.0.0.1';
        self::$port = isset($config['Port']) ? $config['Port'] : 6379;
        self::$auth = isset($config['Auth']) ? $config['Auth'] : '';
        self::$timeout = isset($config['Timeout']) ? $config['Timeout'] : 2.5;
    }

    private static function connect() {
        if (self::$host == null) {
            throw new \Exception("Redis not init");
        }
        self::$rdb = new \Redis();
        $res = self::$rdb->connect(self::$host, self::$port, self::$timeout);
        if (!$res) {
            throw new \Exception("Redis connect error");
        }
        if (!empty(self::$auth)) {
            self::$rdb->auth(self::$auth);
        }
    }
    
    public static function isLoaded() {
        return self::$host !== null;
    }
    
    /**
     * 
     * @return \Redis
     */
    public static function getInstance() {
        if (self::$rdb == null) {
            self::connect();
        }
        return self::$rdb;
    }
    
}
