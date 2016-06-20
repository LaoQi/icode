<?php

namespace F;

use Common\Db;
/**
 * Description of Model
 *
 * @author QQQ
 */
class Model {
    
    public $pkName = null;
    public $tableName = null;
    
    public function __construct($tableName, $pkName = null) {
        $this->tableName = $tableName;
        $this->pkName = $pkName;
    }
    
    /**
     * 根据主键查询
     */
    public function findByPk($pk) {
        if (empty($this->pkName)) {
            throw new \Exception("Not support method, pkname is null!");
        }
        $where = [
            $this->pkName => $pk,
        ];
        $db = Db::getInstance();
        return $db->findOne($this->tableName, $where);
    }
    
    public function findOne($where) {
        $db = Db::getInstance();
        return $db->findOne($this->tableName, $where);
    }
    
    public function updateByPk($pk, $data) {
        if (empty($this->pkName)) {
            throw new \Exception("Not support method, pkname is null!");
        }
        $where = [
            $this->pkName => $pk,
        ];
        $db = Db::getInstance();
        return $db->update($this->tableName, $where, $data);
    }
    
    public function update($data, $where) {
        $db = Db::getInstance();
        return $db->update($this->tableName, $where, $data);
    }
    
    public function deleteByPk($pk) {
        if (empty($this->pkName)) {
            throw new \Exception("Not support method, pkname is null!");
        }
        $where = [
            $this->pkName => $pk,
        ];
        $db = Db::getInstance();
        return $db->delete($this->tableName, $where);
    }
    
    public function getlist($where, $sort = '', $limit = '') {
        $db = Db::getInstance();
        return $db->find($this->tableName, 
            $where, false, '*', $limit, $sort);
    }
    
    public function count($where = []) {
        $db = Db::getInstance();
        $res = $db->findOne($this->tableName, $where, false, 'count(1) AS count');
        if (isset($res['count'])) {
            return $res['count'];
        }
        throw new \Exception("Model error : " . implode(';', $db->error));
    }
    
    public function add($data) {
        $db = Db::getInstance();
        return $db->insert($this->tableName, $data);
    }
}
