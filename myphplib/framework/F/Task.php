<?php

namespace F;

/**
 * Description of Task
 *
 * @author QQQ
 */
abstract class Task {
    
    public $argv = [];
    public $config = [];
    
    /**
     * 主要的执行部分
     */
    abstract function run();
    
    public function __construct($config, $argv) {
        $this->config = $config;
        $this->argv = $argv;
    }
    
}
