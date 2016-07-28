<?php

namespace Common;

use Common\RedisProxy;
/**
 * 
 * 缓存代理类， 暂时使用文件存储
 * Description of Cache
 *
 * @author QQQ
 */

interface CacheInterface {
    public function set($name, $data);
    
    public function get($name);
    
    public function del($name);
        
    public function refresh($name);
}

/**
 * 文件缓存
 */
class FileCache implements CacheInterface {
    
    private $filename = null;
    private $data = null;
    public $cache_dir = null;
    
    public function __construct($cache_dir) {
        $this->cache_dir = $cache_dir;
        if (!is_writable($this->cache_dir)) {
            throw new \Exception("Data Directory Error!");
        }
    }

    public function set($name, $data) {
        $filename = md5($name);
        $this->filename = $filename;
        $this->data = $data;
        file_put_contents($this->cache_dir . $filename, json_encode($data));
    }
    
    public function get($name) {
        $filename = md5($name);
        if ($this->data !== null && $this->filename === $filename) {
            return $this->data;
        }

        if (!file_exists($this->cache_dir . $filename)) {
            return null;
        }
        $this->filename = $filename;
        $data = json_decode(file_get_contents($this->cache_dir . $filename), true);
        $this->data = $data;
        return $data;
    }

    public function del($name) {
        $filename = md5($name);
        $this->filename = null;
        $this->data = null;
        if (file_exists($this->cache_dir . $filename)) {
            unlink($this->cache_dir . $filename);
        }
    }

    public function refresh($name) {
        $this->del($name);
    }
}

/**
 * memcache 缓存
 */
class MemCache implements CacheInterface {
    
    public function __construct() {
        ;
    }
    
    public function del($name) {
        
    }

    public function get($name) {
        
    }

    public function refresh($name) {
        
    }

    public function set($name, $data) {
        
    }

}

/**
 * Redis缓存，基于hash结构
 */
class RedisCache implements CacheInterface {
    
    const HashKey = 'MANAGER:REDIS:HASH:CACHE';
    public $rdb;
    
    public function __construct() {
        $this->rdb = RedisProxy::getInstance();
    }
    
    public function del($name) {
        return $this->rdb->hDel(self::HashKey, $name);
    }

    public function get($name) {
        $str = $this->rdb->hGet(self::HashKey, $name);
        if ($str) {
            return unserialize($str);
        }
        return $str;
    }

    public function refresh($name) {
        return $this->del($name);
    }

    public function set($name, $data) {
        $str = serialize($data);
        return $this->rdb->hSet(self::HashKey, $name, $str);
    }

}

/**
 * 缓存代理，实现 add del update 
 */
class CacheProxy implements CacheInterface {
    
    private static $instance = null;
    private $client = null;
    private $config = null;
    // 延迟加载
    private function getClient() {
        if ($this->client === null) { 
            switch ($this->config['Type']) {
                case 'file':
                    $this->client = new FileCache($this->config['Dir']);
                    break;
                case 'memcache':
                    $this->client = new MemCache();
                    break;
                case 'redis':
                    $this->client = new RedisCache();
                    break;
                default:
                    $this->client = new FileCache($this->config['Dir']);
                    break;
            }
        }
        return $this->client;
    }
    
    private function __construct($config) {
        $this->config = $config;
    }

    public static function Init($config) {
        if (self::$instance !== null) {
            throw new \Exception('Cache already init!');
        }
        self::$instance = new self($config);
    }
    
    /**
     * 
     * @return CacheProxy
     */
    public static function getInstance() {
        return self::$instance;
    }

    public function del($name) {
        return $this->getClient()->del($name);
    }

    public function get($name) {
        return $this->getClient()->get($name);
    }

    public function refresh($name) {
        return $this->getClient()->refresh($name);
    }

    public function set($name, $data) {
        return $this->getClient()->set($name, $data);
    }

}
