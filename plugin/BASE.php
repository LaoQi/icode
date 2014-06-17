<?php
/**
 * 鸡肋
 */
class BASE {
    //全部请求
    static public $req;
    //db
    static public $db;
    //act
    public $act;

    public function __construct(){
        if (self::$req == NULL){
            self::$req = array_merge($_GET, $_POST);
        }
        if (self::$db == NULL){
            self::$db = new db(); 
        }
        $url = $_SERVER['QUERY_STRING'];
        $params = explode('&',$url);
        $act = urldecode($params[0]);
        $this->act = trim($act);
    }

}
