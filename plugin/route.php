<?php
/**
 * 简单路由类
 */
class route extends BASE {
    static public $page;
    static public $me;
    public $_requers;

    public function __construct(){
        parent::__construct();
        $this->_getPage();
    }

    private function _getPage(){
        $page = self::$db->query_one("select * from page where id={$this->act};");
        if (empty($page)){
            self::$page = new errorPage();
            return ;
        }
        self::$page = new page($page);
    }

    static public function go(){
        if (self::$me == NULL){
            self::$me = new self();
        }
        self::$page->view();
    }
}
