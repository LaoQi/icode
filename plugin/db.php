<?php
class db {
    private static $DB;
    public $sqls = array();
    public $errors = array();
    
    public function __construct(){
        if (self::$DB == NULL){
            self::$DB = new $GLOBALS['DB']();
        }
    }
    
    public function exec($sql){
        $this->sqls[] = $sql;
        self::$DB->exec($sql);
    }
    
    public function query($sql){
        $this->sqls[] = $sql;
        return self::$DB->query($sql);
    }

    public function query_one($sql){
        $this->sqls[] = $sql;
        return self::$DB->query_one($sql);
    }
}
?>
