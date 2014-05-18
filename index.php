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

function __autoload($classname){
    require_once PLUGIN_PATH . $classname . ".php";
}

/**
 * 进行地址解析,显示页面
 */

$url = $_SERVER['QUERY_STRING'];
$params = explode('&',$url);
$pageCur = $params[0];
$cool = new page($pageCur);
$cool->view();

?>
