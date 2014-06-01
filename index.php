<?php
/**
 * 类似bootstrap，做一些地址解析，引导用
 * @author john
 * @mail ucanup@gmail.com
 * 2014-05-17
 */

define('IMGPATH', "/data/image/");
define('CSSPATH', "/data/css/");
define('JSPATH', "/data/js/");
define('PLUGIN_PATH', dirname(__FILE__) . "/plugin/"); 

$GLOBALS['DB'] = 'sqlite';

function __autoload($classname){
    require_once PLUGIN_PATH . $classname . ".php";
}

route::go();

?>
