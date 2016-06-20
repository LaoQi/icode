<?php

namespace F;

use F\URL;
use Common\CacheProxy;
/**
 * 权限管理
 *
 * @author QQQ
 */
class ACL {
    
    private static $instance = null;
    private $data = null;
    
    private function __construct() {
        if ($this->data === null) {
            $this->data = CacheProxy::getInstance()->get(ACLCacheKey);
        }
        if ($this->data === null) {
            $this->scanAcl();
        }
    }
    
    /**
     * 根据Controller重新生成权限列表
     */
    private function scanAcl() {
        $raw_list = scandir(CONTROLLER_DIR);
        $class_acls = [];
        foreach ($raw_list as $value) {
//            if (in_array($value, ['.', '..', 'SystemController.php'])) {
            if (in_array($value, ['.', '..'])) {
                continue;
            }
            $class = str_replace('.php', '', $value);
            $class_acl = get_class_vars('\\Controller\\' . $class);
            if (!empty($class_acl['acl']) && !empty($class_acl['desc'])) {
                $class_acls[$class] = ['desc' => $class_acl['desc'], 'acl' => $class_acl['acl']];
            }
        }
        $this->data = $class_acls;
        CacheProxy::getInstance()->set(ACLCacheKey, $class_acls);
    }

    /**
     * 
     * @return \F\ACL
     */
    public static function Init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function getCache() {
        return CacheProxy::getInstance()->get(ACLCacheKey);
    }
    
    public static function cleanCache() {
        return CacheProxy::getInstance()->del(ACLCacheKey);
    }
    
    /**
     * @return \F\ACL 
     */
    public static function GetInstance() {
        return self::$instance;
    }
    
    public function refresh() {
        $this->scanAcl();
    }
    
    public function toList() {
        if (empty($this->data)) {
            $this->scanAcl();
        }
        return $this->data;
    }
    
    /**
     * 低等级权限
     */
    public function lowLevelList() {
        $data = $this->toList();
        foreach ($data as $k => &$v) {
            // 删除所有admin权限
            if (!empty($v['acl'])) {
                if (isset($v['acl']['admin'])) {
                    unset($v['acl']['admin']);
                }
                if (empty($v['acl'])) {
                    unset($data[$k]);
                }
            }
        } 
        return $data;
    }
    
    /**
     * 校验 用户访问权限
     * @param User $user
     * @param type $action
     * @param type $controller
     * @return boolean
     */
    public function checkAction($user, $action, $controller) {
        $auth = $user->getAuth();
        // 根用户略过权限
        if (in_array('root', $auth)) {
            return true;
        }
        $auth_name = null;
        $controller_vars = get_class_vars('\\Controller\\'.$controller);
        if ($controller_vars && !empty($controller_vars['acl'])) {
            foreach ($controller_vars['acl'] as $k => $v) {
                if (in_array($action, $v['method'])) {
                    $auth_name = $k;
                    break;
                }
            }
        }

        if ($auth_name === null) {
            $auth_name = 'public';
        }
        
        if (in_array($auth_name, $auth)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 快速检测权限
     * @param F\User $user
     * @param string $url controller/action
     */
    public function quickCheck($user, $url) {
        $auth = $user->getAuth();
        // 根用户略过权限
        if (in_array('root', $auth)) {
            return true;
        }
        list($controller, $action) = URL::ToCA($url);
        $auth_name = null;
        if (isset($this->data[$controller]) && !empty($this->data[$controller]['acl'])) {
            foreach ($this->data[$controller]['acl'] as $k => $v) {
                if (in_array($action, $v['method'])) {
                    $auth_name = $k;
                    break;
                }
            }
        }
        
        if ($auth_name === null) {
            $auth_name = 'public';
        }
        
        if ($auth_name === 'noAuthention') {
            return true;
        }
        
        if (in_array($auth_name, $auth)) {
            return true;
        }
        
        return false;
    }
}
