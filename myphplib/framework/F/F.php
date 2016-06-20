<?php

namespace F;

use Common\Log;
use Common\CacheProxy;
use Common\Db;
use Common\RedisProxy;

/**
 * Description of Init
 *
 * @author QQQ
 */
class F {

    public static $Config;
    public static $Start;
    public static $LoadHistory = [];
    public static $Debug = [];
    public static $User = null;
    public static $ErrorType = [
        E_ERROR => 'E_ERROR',
        E_WARNING => 'E_WARNING',
        E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_USER_WARNING => 'E_USER_WARNING',
        E_STRICT => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        E_ALL => 'E_ALL',
    ];

    public function __construct($config) {
        static::$Config = $config;
        // 记录所有错误，加入日志
        error_reporting(E_ALL);
        if (file_exists(ROOT_DIR . 'debug.php')) {
            $debug = require ROOT_DIR . 'debug.php';
            defined("F_DEBUG") or define("F_DEBUG", true);
            static::$Config = array_merge($config, $debug);
        } else {
            define("F_DEBUG", false);
        }
        if (F_DEBUG) {
            ini_set('display_errors', 'On');
        }
    }

    /**
     * 获取当前用户
     * @return User|CpUser
     */
    static public function GetUser() {
        return static::$User;
    }

    /**
     * 设置当前用户
     * @param User $user
     */
    static public function SetUser($user) {
        static::$User = $user;
    }

    /**
     * 提供路径解析 支持 index.php?r=controller/action/key1/value1/key2/value2?key3=value3类型的解析
     * @param array $args
     */
    public function route($args) {

        $r = empty($args['r']) ? 'index/index' : $args['r'];
        unset($args['r']);
        // 分离参数
        $ro = explode('?', $r);
        if (count($ro) === 2) {
            $r_params = explode('=', $ro[1]);
            if (count($r_params) === 2) {
                $args[$r_params[0]] = $r_params[1];
            }
        }
        // 抽取键值对
        $rp = explode('/', trim($ro[0], '/'));
        $controller = ucwords(array_shift($rp) . 'Controller');
        $action = array_shift($rp) . 'Action';
        while (count($rp) > 1) {
            $key = array_shift($rp);
            $value = array_shift($rp);
            $args[$key] = $value;
        }

        if (!file_exists(CONTROLLER_DIR . $controller . '.php')) {
            die('Not Found!');
        }
        $cname = '\\Controller\\' . $controller;
        $obj = new $cname(self::$Config, $args);
        if (method_exists($obj, $action)) {
            $obj->doAction($controller, $action);
        }
        http_response_code(404);
        die('Not Found!');
    }

    static public function autoloader($className) {
        $classFile = $className . '.php';
        if (strpos($classFile, '\\') !== false) {
            $classFile = str_replace('\\', '/', $classFile);
        }
        if (is_file(APP_DIR . $classFile)) {
            $classFile = APP_DIR . $classFile;
        } elseif (is_file(VENDOR_DIR . $classFile)) {
            $classFile = VENDOR_DIR . $classFile;
        } else {
            return;
        }
        static::$LoadHistory[] = $classFile;
        include($classFile);
    }

    /**
     * 自定义异常处理
     * @access public
     * @param \Exception $e 异常对象
     */
    static public function appException($e) {
        if (F_DEBUG) {
            echo "<h2>", $e->getMessage(), "</h2><hr /><pre>",
            $e->getTraceAsString(), "</pre>";
        }
        Log::write("{$e->getFile()} line {$e->getLine()} : {$e->getMessage()}", '[Exception]');
    }

    /**
     * 自定义错误处理
     * @access public
     * @param int $type 错误类型
     * @param string $message 错误信息
     * @param string $file 错误文件
     * @param int $line 错误行数
     * @return mixed
     */
    static public function appError($type, $message, $file, $line) {
        $typestr = isset(self::$ErrorType[$type]) ? self::$ErrorType[$type] : $type;
        $log = "[{$typestr}] {$file} line {$line} : {$message}";
        Log::Warning($log);

        if (F_DEBUG) {
            return false;
        }
    }

    /**
     * 捕获致命异常
     */
    static public function fatalError() {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $type = self::$ErrorType[$e['type']];
            $log = "[{$type}] {$e['file']} line {$e['line']} : {$e['message']}";
            Log::Danger($log);
        }
    }

    /**
     * web 服务启动
     */
    public function run() {
        self::$Start = microtime();
        spl_autoload_register(['F\F', 'autoloader']);
        set_error_handler(['F\F', 'appError']);
        set_exception_handler(['F\F', 'appException']);
        register_shutdown_function(['F\F', 'fatalError']);
        // Common
        include VENDOR_DIR . 'Common/function.php';
        self::$LoadHistory[] = VENDOR_DIR . 'Common/function.php';
        // URL
        URL::Init(self::$Config['URL']);
        // 初始化缓存
        CacheProxy::Init(self::$Config['Cache']);
        // 日志
        Log::Init(self::$Config['Log']);
        // 权限管理
        define('ACLCacheKey', self::$Config['ACLCacheKey']);
        // 数据库
        Db::Init(self::$Config['Db']);
        // Redis
        RedisProxy::Init(self::$Config['Redis']);

        //过滤参数
        $get = filter_input_array(INPUT_GET);
        $args = empty($get) ? [] : $get;
        $post = filter_input_array(INPUT_POST);
        $cookies = filter_input_array(INPUT_COOKIE);
        if (!empty($post)) {
            $args = array_merge($args, $post);
        }
        if (!empty($cookies)) {
            $args = array_merge($args, $cookies);
        }
        $this->route($args);
    }

    /**
     * 命令任务
     */
    public function console($argc, $argv) {
        if (PHP_SAPI !== 'cli') {
            exit(1);
        }
        self::$Start = microtime();
        spl_autoload_register(['F\F', 'autoloader']);
        set_error_handler(['F\F', 'appError']);
        set_exception_handler(['F\F', 'appException']);

        // Common
        include VENDOR_DIR . 'Common/function.php';
        self::$LoadHistory[] = VENDOR_DIR . 'Common/function.php';
        // URL
        URL::Init(self::$Config['URL']);
        // 初始化缓存
        CacheProxy::Init(self::$Config['Cache']);
        // 日志
        Log::Init(self::$Config['Log']);
        // 数据库
        Db::Init(self::$Config['Db']);
        // Redis
        RedisProxy::Init(self::$Config['Redis']);

        if ($argc < 2) {
            echo self::$Config['Console']['Usage'];
            exit(0);
        }
        $taskname = '\\Tasks\\' . ucwords($argv[1]) . 'Task';
        if (class_exists($taskname)) {
            (new $taskname(self::$Config, $argv))->run();
        } else {
            echo "Task {$argv[1]} not found!";
            exit(1);
        }
        exit(0);
    }

}
