<?php

namespace Common;

use mysqli;
use mysqli_result;

/**
 * Description of Db
 *
 * @author QQQ
 */
class Db {
    
    private static $config = [];
    private static $flag = 0;

    public $last_id = -1;
    public $affected_rows = 0;
    public $sql_record = [];
    public $bind_record = [];
    public $error = [];
    private static $instance = null;
    private $conn = null;

    private function __construct() {
        if (count(self::$config) < 1) {
            new \Exception('Database not init');
        }
        $config = self::$config[self::$flag];
        $this->conn = new mysqli($config['Host'], $config['User'], $config['Passwd'], $config['Database']);
        if (mysqli_connect_errno()) {
            // @TODO 添加数据库报错日志
            new \Exception('Database connect error! erron : ' . mysqli_connect_errno());
        }
        $this->query('SET NAMES `utf8`');
    }

    function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
    
    public static function Init($config) {
        self::$config = $config;
    }

    /**
     * 单例
     * @return Db
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 查找一条
     * @param string $table
     * @param array $where
     * @param string|bool $bind_type
     * @param string $field
     * @return array|null
     */
    public function findOne($table, $where, $bind_type = false, $field = '*') {
        $data = $this->find(
            $table, $where, $bind_type, $field, 1);
        if (!empty($data)) {
            return $data[0];
        }
        return null;
    }

    /**
     * 查找多条数据
     * @param string $table
     * @param array $where example: array('id' => $id, 'time>' => 1)
     * @param string|bool $bind_type fasle 'iiiisss'
     * @param string $field '*'
     * @param string $limit "0,20"
     * @param string $sort "id desc"
     * @return array
     * @throws \Exception
     */
    public function find($table, $where, $bind_type = false, $field = '*', $limit = '', $sort = '') {
        if (!is_array($where)) {
            $this->error[] = '$where is not Array';
            return null;
        }
        $sql = 'SELECT ' . $field . ' FROM `' . $table . '`' . $this->_make_where($where);
        if ($sort) {
            $sql .= ' ORDER BY ' . $sort;
        }
        if ($limit) {
            $sql .= ' LIMIT ' . $limit;
        }
        $bind_var = $this->_get_bind_var($where, $bind_type);
        $stmt = $this->_execute($sql, $bind_var);
        if ($stmt == false) {
            return null;
        }
        $result = $stmt->get_result();

        if ($result === false) {
            return null;
        }
        $data = array();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $result->free();
        return $data;
    }

    /**
     * 修改数据
     * @param string $table
     * @param array $where example: array('id' => $id, 'time>' => 1)
     * @param array $data example: array('id' => $id, 'time' => 2)
     * @param string|bool $bind_type
     * @return int effectRows
     */
    public function update($table, $where, $data, $bind_type = false) {
        if (!is_array($where) || !is_array($data)) {
            $this->error[] = '$where or $data is not Array';
            return false;
        }
        $sql = 'UPDATE `' . $table .
            '` SET ' . $this->_make_data($data) .
            $this->_make_where($where);

        $bind_array = array_merge(array_values($data), array_values($where));
        $bind_var = $this->_get_bind_var($bind_array, $bind_type);
        $stmt = $this->_execute($sql, $bind_var);
        if ($stmt == false) {
            return -1;
        }
        $this->affected_rows = $stmt->affected_rows;
        return $this->affected_rows;
    }

    /**
     * 增加一条数据
     * @param string $table
     * @param array $data example: array('id' => $id, 'time' => 1)
     * @param string|bool $bind_type
     * @return int
     */
    public function insert($table, $data, $bind_type = false) {
        if (!is_array($data)) {
            $this->error[] = '$data is not Array';
            return false;
        }
        $sql = 'INSERT INTO `' . $table .
            '`(`' . implode('`,`', array_keys($data)) .
            '`) VALUES(' . implode(',', array_pad([], count($data), '?')) . ')';

        $bind_var = $this->_get_bind_var($data, $bind_type);
        $stmt = $this->_execute($sql, $bind_var);
        if ($stmt == false) {
            return -1;
        }
        $this->last_id = $stmt->insert_id;
        return $this->last_id;
    }

    /**
     * @todo 增加多条数据
     * @param string $table
     * @param array $data
     * @param string|bool $bind_type
     */
    public function insert_many($table, $data, $bind_type = false) {
        
    }
    
    /**
     * 删除一条数据
     * @param string $table
     * @param array $where
     * @return boolean
     */
    public function delete($table, $where) {
        if (!is_array($where)) {
            $this->error[] = 'where is not Array';
            return false;
        }
        $sql = 'DELETE FROM `' . $table . '` ' .
            $this->_make_where($where);

        $bind_var = $this->_get_bind_var($where);
        $stmt = $this->_execute($sql, $bind_var);
        if ($stmt == false) {
            return -1;
        }
        $this->affected_rows = $stmt->affected_rows;
        return $this->affected_rows;
    }
    
    /**
     * 执行裸sql
     * @param string $sql
     * @TODO boolean $security 检查安全限制 (select 自动加入limit, 禁止 delete, update)
     * @return mixed
     */
    public function query($sql) {
        $this->bind_record[] = ['empty'];
        $this->sql_record[] = $sql;
        $result = $this->conn->query($sql);
        $this->error[] = $this->conn->error;
        $this->affected_rows = $this->conn->affected_rows;
        if ($result instanceof mysqli_result) {
            $rtn = [];
            while ($v = $result->fetch_assoc()) {
                $rtn[] = $v;
            }
            $result->free();
            return $rtn;
        }
        return $result;
    }

    /**
     * @param string $sql
     * @param string $bind_var
     * @return bool|\mysqli_stmt
     */
    public function _execute($sql, $bind_var) {
        $this->sql_record[] = $sql;
        $stmt = $this->conn->prepare($sql);
        if ($stmt == false) {
            $this->error[] = $this->conn->error;
            return false;
        }
        if (count($bind_var) > 1) {
            call_user_func_array(array($stmt, 'bind_param'), 
                $this->_pass_by_reference($bind_var));
        }
        if (!$stmt->execute()) {
            $this->error[] = $stmt->error;
            return false;
        }
        $this->error[] = '';
        return $stmt;
    }

    /**
     * @param array $where
     * @return string
     */
    public function _make_where(&$where) {
        if (empty($where)) {
            return '';
        }
        $temp = [];
        foreach ($where as $k => &$v) {
            if (preg_match('/\bin$/i', $k)) {
                $temp[] = "{$k}(?)";
                continue;
            }
            if (preg_match('/[<|>|=|!]+/', $k)) {
                $temp[] = "{$k}?";
                continue;
            } 
            $opt = [];
            if (preg_match('/^[<|>|=|!]+/', $v, $opt)) {
                $temp[] = "{$k}{$opt[0]}?";
                $v = ltrim($v, $opt[0]);
                continue;
            }
            $temp[] = "`{$k}`=?";
        }
        return ' WHERE ' . implode(' AND ', $temp);
    }

    /**
     * @param array $data
     * @return string
     */
    public function _make_data($data) {
        $temp = array();
        foreach ($data as $k => $v) {
            if ($v === false) {
                $temp[] = $k;
                continue;
            }
            $temp[] = "`{$k}`=?";
        }
        return implode(',', $temp);
    }

    /**
     * @param array $data
     * @param string|bool $bind_type
     * @return array
     */
    public function _get_bind_var($data, $bind_type = false) {
        $types = array();
        $params = array();
        foreach ($data as $arg) {
            if ($arg === false) {
                continue;
            }
            $types[] = is_int($arg) ? 'i' : (is_float($arg) ? 'd' : 's');
//            $params[] = $this->conn->real_escape_string($arg);
            $params[] = $arg;
        }
        # Stick the types at the start of the params
        if ($bind_type !== false) {
            array_unshift($params, $bind_type);
        } else {
            array_unshift($params, implode($types));
        }
        $this->bind_record[] = $params;
        return $params;
    }

    /**
     * @param array $arr
     * @return array
     */
    private function _pass_by_reference(&$arr) {
        $refs = array();
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }

}
