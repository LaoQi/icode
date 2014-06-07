<?php

define('DBPATH', dirname(__FILE__) . '/../data/pages.data');

class sqlite {

    static public $db;

    public function __construct() {
        if (self::$db == NULL) {
            try {
                $database = 'sqlite:' . realpath(DBPATH);
                self::$db = new PDO($database);
                self::$db->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);
            } catch (PDOException $e) {
                echo 'PDO Connection Failed ' . $e->getMessage();
            }
        }
    }

    public function exec($sql) {
        try {
            self::$db->exec($sql);
        } catch (PDOException $e) {
            echo 'PDO Excute Failed ' . $e->getMessage();
        }
    }

    public function query_one($sql) {
        try {
            $rs = self::$db->query($sql);
            if (!$rs) {
                return false;
            }
            $rs->setFetchMode(PDO::FETCH_ASSOC);
            $rtn = $rs->fetch();
            return $rtn;
        } catch (PDOException $e) {
            echo 'PDO Query Failed' . $e->getMessage();
        }
    }

    public function query($sql) {
        try {
            $rs = self::$db->query($sql);
            if (!$rs) {
                return false;
            }
            $rs->setFetchMode(PDO::FETCH_ASSOC);
            $rtn = $rs->fetchAll();
            return $rtn;
        } catch (PDOException $e) {
            echo 'PDO Query Failed' . $e->getMessage();
        }
    }

}
