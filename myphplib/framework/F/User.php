<?php

namespace F;

use Model\AdminModel;
use Model\CpModel;
use Common\Db;
use Common\Func;
/**
 * Description of User
 *
 * @author QQQ
 */
class User {
    
    // 空用户
    const EmptyUser = 1;
    // 新用户
    const NewUser = 2;
    // 老用户
    const OldUser = 3;
    // 停用
    const DeadUser = 4;
    
    public $logined = false;
    private $userinfo = null;

    public function getAuth() {
        return $this->userinfo['auth'];
    }
    
    public function logout() {
        $this->logined = false;
        $this->userinfo = null;
        unset($_SESSION['userinfo']);
    }
    
    /**
     * 管理员用户登录
     */
    public function adminLogin($device_id, $flag) {
        $db = Db::getInstance();
        $umodel = new AdminModel();
        $userdata = $umodel->findByDevice($device_id);
        if (empty($userdata)) {
            return static::EmptyUser;
        }
        // 未初始化用户
        if ($userdata['is_active'] == 1) {
            return static::NewUser;
        } 
        if ($userdata['is_active'] == 3) {
            return static::DeadUser;
        }
        $update = [
            'last_login' => date('Y-m-d H:i:s'),
            'last_ip' => Func::GetIP(),
        ];
        $umodel->updateByPk($userdata['id'], $update);
        
        $groups = $userdata['group'];
        $auth_data = $db->find('group', ['`id` IN' => $groups]);
        // 默认具有公共权限
        $auth = ['public'];
        if (!empty($auth_data)) {
            foreach ($auth_data as $v) {
                $auth = array_merge($auth, explode(',', $v['auth']));
            }
            $auth = array_unique($auth);
        }
        $this->userinfo = [
            'auth' => $auth,
            'uid' => $userdata['id'],
            'username' => $userdata['username'],
            'account' => $userdata['account'],
            'device_id' => $userdata['device_id'],
            'flag' => $flag,
        ];
        
        $this->logined = true;
        $_SESSION['userinfo'] = $this->userinfo;
        return static::OldUser;
    }
    
    public function initNewUser($deviceId) {
        $umodel = new AdminModel();
        $umodel->initByDevice($deviceId);
    }
    
    /**
     * cp 用户登录
     */
    public function cpLogin($username, $passwd) {
        $umodel = new CpModel();
        $userdata = $umodel->findByKey($username, 'account');
        if (empty($userdata) || $userdata['passwd'] != md5($passwd)) {
            return false;
        }
        if ($userdata['status'] != 1) {
            return false;
        }
        
        $update = [
            'last_login' => date('Y-m-d H:i:s'),
            'last_ip' => Func::GetIP(),
        ];
        $umodel->updateByPk($userdata['id'], $update);
        
        // cp默认权限 cp, 补单
        $auth = ['cp', 'order_reissue'];
        
        $this->userinfo = [
            'auth' => $auth,
            'uid' => $userdata['id'],
            'username' => $userdata['name'],
            'account' => $userdata['account'],
            'cid' => $userdata['id'],
        ];
        
        $this->logined = true;
        $_SESSION['userinfo'] = $this->userinfo;
        return true;
    }

    
    public function __get($name) {
        if (isset($this->userinfo[$name])) {
            return $this->userinfo[$name];
        }
        throw new \Exception(__CLASS__ . ' property "' . $name . '" not exist!');
    }
    
    /**
     * @return User
     * @throws \Exception
     */
    public static function Init() {
        if (F::GetUser() === null) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $user = new self();
            // 已登录
            if (!empty($_SESSION['userinfo'])) {
                $user->logined = true;
                $user->userinfo = $_SESSION['userinfo'];
            }
            // 未登录
            else {
                $user->logined = false;
            }
            F::SetUser($user);
            return $user;
        }
        throw new \Exception('User Init too many times!');
    }
   
}
