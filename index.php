<?php
/**
 * 类似bootstrap，做一些地址解析，引导用
 * @author john
 * @mail ucanup@gmail.com
 * 2014-05-17
 */

define('DS', DIRECTORY_SEPARATOR);
define('IMGPATH', DS . "data" . DS . "image" . DS);
define('CSSPATH', DS . "data" . DS . "css" . DS);
define('JSPATH', DS . "data" . DS . "js" . DS);
define('TEMP', dirname(__FILE__) . DS . "data" . DS . "temp" . DS);
define('PLUGIN_PATH', dirname(__FILE__) . DS . "plugin" . DS); 

$GLOBALS['DB'] = 'sqlite';

function __autoload($classname){
    require_once PLUGIN_PATH . $classname . ".php";
}

route::go();

?>
