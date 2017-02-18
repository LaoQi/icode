<?php
/**
 * 管理后台引导页
 */

define('DS', DIRECTORY_SEPARATOR) ;
define('ROOT_DIR', dirname(__DIR__) . DS);
define('APP_DIR', ROOT_DIR . 'manager/');
define('VENDOR_DIR', ROOT_DIR . 'vendor/');
define('RUNTIME_DIR', ROOT_DIR . 'Runtime/');
define('VIEW_DIR', APP_DIR . 'View/');
define('CONTROLLER_DIR', APP_DIR . 'Controller/');
require ROOT_DIR . 'vendor/F/F.php';
$config = require ROOT_DIR . 'config.php';

// 时区
ini_set('date.timezone','Asia/Shanghai');
// 编码
header("Content-Type:text/html;charset=utf-8");

(new F\F($config))->run();
