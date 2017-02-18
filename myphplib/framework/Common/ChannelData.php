<?php

namespace Common;

use Common\Db;
use Common\Func;
use Common\RedisProxy;

/**
 * Description of ChannelData
 *  渠道请求参数来源
 *  先使用数据库存车，后期考虑迁移到文件存储与memcache
 *  2016/04/26 迁移 redis
 * @author QQQ
 */
class ChannelData {
    
    public $data = null;
    public static $channel_id = null;
    public static $app_id = null;
    
    private static $instance = null;
    
    /**
     * 渠道应用对接数据
     */
    const HashKey = 'ChannelData:REDIS:HASHKEY';
    const KeyFormat = 'D_%d_%d';
    const AppKey = '93pk_appkey';
    const NotifyUrl = '93pk_notify_url';
    const TokenUrl = '93pk_token_url';
    
    /**
     * 渠道数据
     */
    const AppStatus = '93pk_app_status';
    const DebugMode = '93pk_debug_mode';
    const DebugToken = '93pk_debug_token';
    const DebugPay = '93pk_debug_pay';
    
    /**
     * 应用状态
     */
    const STATUS_DEBUG = 0;
    const STATUS_NORMAL = 1;
    const STATUS_CLOSE = 2;
    
    /**
     * 获取单一渠道数据
     * @param String $channel_id
     * @param String $name
     * @return type
     */
    public static function GetDebugMode($channel_id, $name) {
        // 使用app 1来存储渠道数据
        $debugMode = self::InitData($channel_id, 1)->get(self::DebugMode);
        $n = base_convert($debugMode, 10, 2);
        $bin = str_pad($n, 2, '0', STR_PAD_LEFT);
        if ($name == self::DebugPay) {
            return $bin[0] == 1;
        } elseif ($name == self::DebugToken) {
            return $bin[1] == 1;
        }
    }
    
    public static function getCache() {
        $rdb = RedisProxy::getInstance();
        return $rdb->hGetAll(self::HashKey);
    }
    
    public static function cleanCache() {
        $rdb = RedisProxy::getInstance();
        return $rdb->del(self::HashKey);
    }
    
    private function __construct($channel_id, $app_id){
        self::$channel_id = $channel_id;
        self::$app_id = $app_id;
        $this->data = null;
    }
    
    /**
     * 
     * @param type $channel_id
     * @param type $app_id
     * @return \Common\ChannelData
     */
    public static function InitData($channel_id, $app_id) {
        if (self::$instance === null ||
            $channel_id !== self::$channel_id || $app_id !== self::$app_id) {
            self::$instance = new self($channel_id, $app_id);
        }
        return self::$instance;
    }
    
    public function get($name) {
        if (empty($this->data)) {
            $this->init();
        }
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }
        return null;
    }
    
    public function getCustom($name) {
        $custom = $this->get('custom');
        if (!empty($custom) && isset($custom[$name])) {
            return $custom[$name];
        }
        return null;
    }
    
    public function getItem($id) {
        $items = $this->get('items');
        if (!empty($items) && isset($items[$id])) {
            return $items[$id];
        }
        return null;
    }
    
    public function getAllItems() {
        return $this->get('items');
    }
    
    public function getAll() {
        if (empty($this->data)) {
            $this->init();
        }
        return $this->data;
    }
    
    // 延迟请求
    private function init() {
        // 先从缓存读，读不到从数据库取，再存入缓存。
        $rdb = RedisProxy::getInstance();
        $key = sprintf(self::KeyFormat, self::$channel_id, self::$app_id);
        $data = $rdb->hGet(self::HashKey, $key);
        if ($data) {
            $this->data = unserialize($data);
            return $this;
        }
        // 连接数据库查询
        $db = Db::getInstance();
        $sql = 'SELECT ac.data as data, a.appkey as ' . self::AppKey
            . ', a.status as '. self::AppStatus
            . ', a.notify_url as '. self::NotifyUrl 
            . ', c.token_url as ' . self::TokenUrl
            . ', c.debug_mode as ' . self::DebugMode
            . ' FROM apps_channels as ac, apps as a, channels as c '
            . 'WHERE ac.app_id = a.id AND ac.channel_id = c.id '
            . 'AND ac.app_id = ' . self::$app_id 
            . ' AND ac.channel_id = '. self::$channel_id;
        $dbdata = $db->query($sql);
        if (!empty($dbdata)){
            $dbdata = $dbdata[0];
            if (!empty($dbdata["data"])){
                $jsondata = unserialize($dbdata['data']);
                unset($dbdata['data']);
                $this->data = array_merge($dbdata, $jsondata);
                $rdb->hSet(self::HashKey, $key, serialize($this->data));
            }
        }
        return $this;
    }
    
    /**
     * 清理缓存
     * @param type $channel_id
     * @param type $app_id
     */
    public static function Del($channel_id, $app_id) {
        $rdb = RedisProxy::getInstance();
        $key = sprintf(self::KeyFormat, $channel_id, $app_id);
        return $rdb->hDel(self::HashKey, $key);
    }
    
    /**
     * 清理所有
     * @return type
     */
    public static function Clear() {
        $rdb = RedisProxy::getInstance();
        return $rdb->delete(self::HashKey);
    }
    
    /**
     * 更新某渠道所有缓存
     * @param type $channel_id
     */
    public static function UpdateChannel($channel_id) {
        $db = Db::getInstance();
        $res = $db->find('apps_channels', ['channel_id' => $channel_id]);
        if (!empty($res)) {
            foreach ($res as $v) {
                self::Del($channel_id, $v['app_id']);
            }
        }
    }
    
    /**
     * 更新某应用所有缓存
     * @param type $app_id
     */
    public static function UpdateApp($app_id) {
        $db = Db::getInstance();
        $res = $db->find('apps_channels', ['app_id' => $app_id]);
        if (!empty($res)) {
            foreach ($res as $v) {
                self::Del($v['channel_id'], $app_id);
            }
        }
    }
}
