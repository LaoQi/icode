<?php

/**
 * 简单路由类
 */
class Route extends BASE {

    static public $page;
    static public $me;
    static public $temp;
    public $_requers;

    public function __construct() {
        parent::__construct();
        $this->_getPage();
    }

    private function _getPage() {
        if ($this->act == NULL) {
            self::$page = new page(array('title' => 'index'));
            return;
        }
        if (is_numeric($this->act)) {
            $page = self::$db->query_one("select * from page where id={$this->act};");
            if (empty($page)) {
                self::$page = new errorPage();
                return;
            }
            $page['istemp'] == 1 && $this->useTemp($page['id'], $page['changetime']);
            if (empty($this->temp)) {
                self::$page = new page($page);
            }
            return;
        }
        if (is_string($this->act) and $this->act != '') {
            $page = self::$db->query_one("select * from page where title='{$this->act}';");
            if (empty($page)) {
                self::$page = new errorPage();
                return;
            }
            $page['istemp'] == 1 && $this->useTemp($page['id'], $page['changetime']);
            if (empty($this->temp)) {
                self::$page = new page($page);
            }
            self::$page = new page($page);
            return;
        }
        self::$page = new errorPage();
    }

    static public function go() {
        if (self::$me == NULL) {
            self::$me = new self();
        }
        if (self::$temp != NULL) {
            echo self::$temp;
            echo "useTemp";
            exit;
        }
        self::$page->view();
        exit;
    }

    private function useTemp($id, $ctime) {
        $filePath = TEMP . $id . '.tpl';
        if (is_file($filePath) && filectime($filePath) > $ctime) {
            self::$temp = file_get_contents($filePath);
        }
    }

}
