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
    if (is_dir(PLUGIN_PATH . $classname)){
        require PLUGIN_PATH . $classname . DS . "main.php";
    } elseif (is_file(PLUGIN_PATH . $classname . ".php")){
        require PLUGIN_PATH . $classname . ".php";
    } else {
        throw new Exception("cann`t find plugin {$classname} !", 233);
    }
}

try {
    Route::go();
} catch (Exception $e){
    echo $e->getMessage();
}

?>
