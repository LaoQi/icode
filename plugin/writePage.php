<?php

/**
 * 添加页
 */
class writePage extends BASE {

    public $contents = array();
    
    public function __construct(){
    }
    
    public function set($content){
        $this->contents[] = $content;
    }
    
    public function __destruct(){
        if (!empty($this->contents)){
            self::$db->query();
        }
    }
    
}