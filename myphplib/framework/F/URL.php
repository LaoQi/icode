<?php

namespace F;

use Common\Func;
/**
 * Description of URL
 *
 * @author QQQ
 */
class URL {
    
    private static $Config;


    public static function Init($config) {
        self::$Config = $config;
    }

    public static function JS($file) {
        return self::$Config['JS'] . $file;
    }
    
    public static function CSS($file) {
        return self::$Config['CSS'] . $file;
    }
    
    public static function FILE($file) {
        return self::$Config['Static'] . $file;
    }

    /**
     * Url别名，可以对a标签使用
     * @param string $url ( controller/action )
     * @param array $data
     * @return string
     */
    public static function TagA($url, $data = null) {
        return self::Url($url, $data);
    }
    
    /**
     * 将 "controller/action" 类型的转化成该站点相对地址
     * @param string $url
     * @param array $data
     * @return string
     */
    public static function Url($url, $data = null) {
        if ($url === '#') {
            return '#';
        }
        if (Func::startsWith($url, 'http')) {
            return $url;
        }
        $d = "";
        if (!empty($data)) {
            $d = '?' . http_build_query($data);
        }
        return self::$Config['UrlPrefix'] . $url . $d;
    }
    
    /**
     * 生成url
     * @param string $controller
     * @param string $action
     * @return string
     */
    public static function ToUrl($controller, $action) {
        $m = lcfirst($controller);
        if (strpos($controller, "Controller") !== false) {
            $m = str_replace("Controller", "", $controller);
        }
        $a = $action;
        if (strpos($action, "Action") !== false) {
            $a = str_replace("Action", "", $action);
        }
        $url = $m . '/' . $a;
        return self::Url($url);
    }
    
    /**
     * 生成controller Action 名
     * @param string $url contronller/action
     */
    public static function ToCA($url) {
        $rp = explode('/', $url);
        if (count($rp) < 1) {
            return false;
        }
        $controller = ucwords(array_shift($rp) . 'Controller');
        $action = array_shift($rp) . 'Action';
        return [$controller, $action];
    }
    
}
