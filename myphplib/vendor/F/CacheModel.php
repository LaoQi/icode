<?php

namespace F;

use F\Model;
use Common\CacheProxy;
/**
 * Description of CacheModel
 * 自带缓存的model
 * @author QQQ
 */
class CacheModel extends Model {
    
    public $tableName = null;
    public $pkName = null;
    
    static $data = null;
    
    const CacheKey = null;
    
    
    public function __construct($tableName, $pkName) {
        parent::__construct($tableName, $pkName);
        if (static::CacheKey === null) {
            new \Exception('CacheModel must set cachekey!');
        }
    }
    
    public static function getCache() {
        return CacheProxy::getInstance()->get(static::CacheKey);
    }
    
    public static function cleanCache() {
        return CacheProxy::getInstance()->del(static::CacheKey);
    }
    
    public function getAll() {
        if (static::$data != null) {
            return static::$data;
        }
        $c = CacheProxy::getInstance();
        static::$data = $c->get(static::CacheKey);
        if (empty(static::$data)) {
            static::$data = $this->getlist([]);
            $c->set(static::CacheKey, static::$data);
        }
        return static::$data;
    }
    
    public function getAssocList() {
        $data = $this->getAll();
        $rtn = [];
        foreach ($data as &$v) {
            $rtn[$v[$this->pkName]] = $v;
        }
        return $rtn;
    }
    
    public function findByPk($pk) {
        return $this->findByKey($pk, $this->pkName);
    }
    
    
    public function findByKey($value, $key) {
        if (static::$data != null) {
            foreach (static::$data as $v) {
                if ($v[$key] == $value) {
                    return $v;
                }
            }
        }
        $c = CacheProxy::getInstance();
        static::$data = $c->get(static::CacheKey);
        if (empty(static::$data)) {
            $this->getAll();
        }
        foreach (static::$data as $d) {
            if ($d[$key] == $value) {
                return $d;
            }
        }
        return null;
    }
    
    public function count($where = []) {
        if (empty($where)){
            if (empty(static::$data)) {
                $this->getAll();
            }
            return count(static::$data);
        }
        return parent::count($where);
    }
    
    public function add($data) {
        $res = parent::add($data);
        $this->flush();
        return $res;
    }
    
    public function deleteByPk($pk) {
        $res = parent::deleteByPk($pk);
        $this->flush();
        return $res;
    }
    
    public function updateByPk($pk, $data) {
        $res = parent::updateByPk($pk, $data);
        $this->flush();
        return $res;
    }
    
    public function update($data, $where) {
        $res = parent::update($data, $where);
        $this->flush();
        return $res;
    }
    
    public function flush() {
        static::$data = null;
        $c = CacheProxy::getInstance();
        $c->refresh(static::CacheKey);
    }
}
