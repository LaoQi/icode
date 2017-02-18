<?php

namespace Common;

/**
 * Description of RegisterCache
 * @author QQQ
 */

use Common\RedisProxy;

class RegisterCounter {
    
    const USER_KEY = 'Register:Counter:%d';
    const DEVICE_KEY = 'Device:Counter:%d';
    
    // channel_id app_id time
    const KEY_FORMAT = '%d_%d';
    
    public function userInc($channel_id, $app_id, $time = false) {
        return $this->countInc(self::USER_KEY, $channel_id, $app_id, $time);
    }
    
    public function deviceInc($channel_id, $app_id, $time = false) {
        return $this->countInc(self::DEVICE_KEY, $channel_id, $app_id, $time);
    }
    
    private function countInc($keyformat, $channel_id, $app_id, $time) {
        if ($time === false) {
            $time = strtotime(date('Y-m-d H:00:00'));
        }
        $key = sprintf($keyformat, $time);
        $hkey = sprintf(self::KEY_FORMAT, $channel_id, $app_id);
        $rdb = RedisProxy::getInstance();
        $count = $rdb->hIncrBy($key, $hkey, 1);
        // 限制超时 48小时
        if ($count && $count < 2) {
            $rdb->expireAt($key, $time + 3600*48);
        }
        
        return $count;
    }
    
    /**
     * 获取实时新用户注册数
     * @param type $hour
     * @param array $channel_id
     * @param array $app_id
     * @return type
     */
    public function getLastUserCounter($hour = 24, $channel_id = false, $app_id = false, $sortByTime = true) {
        return $this->getLastCounter(self::USER_KEY, $channel_id, $app_id, $hour, $sortByTime);
    }
    
    /**
     * 获取实时设备新注册数
     * @param type $hour
     * @param array $channel_id
     * @param array $app_id
     * @return type
     */
    public function getLastDeviceCounter($hour = 24, $channel_id = false, $app_id = false, $sortByTime = true) {
        return $this->getLastCounter(self::DEVICE_KEY, $channel_id, $app_id, $hour, $sortByTime);
    }
    
    /**
     * 
     * @param array $channel_id
     * @param array $app_id
     * @param type $hour
     */
    private function getLastCounter($keyformat, $channel_id, $app_id = false, $hour = 24, $sortByTime = true) {
        $data = $this->getLast($keyformat, $hour);
        $rtn = [];
        // 如果不存在channel_id，则返回合计数
        if ($channel_id === false) {
            foreach ($data as $k => $v) {
                $rtn[$k] = array_sum($v);
            }
            return $rtn;
        }
        foreach ($data as $k => &$v) {
            foreach ($channel_id as $cid) {
                if ($sortByTime) {
                    isset($rtn[$k]) or $rtn[$k] = [];
                    isset($rtn[$k][$cid]) or $rtn[$k][$cid] = 0;
                } else {
                    isset($rtn[$cid]) or $rtn[$cid] = [];
                    isset($rtn[$cid][$k]) or $rtn[$cid][$k] = 0;
                }
                // 如果存在app_id则以app_id数组遍历
                if (is_array($app_id)) {
                    $tmp = [];
                    foreach ($app_id as $aid) {
                        $key = sprintf(self::KEY_FORMAT, $cid, $aid);
                        $tmp[$aid] = 0;
                        if (isset($v[$key])) {
                            $tmp[$aid] = $v[$key];
                        }
                    }
                    if ($sortByTime) {
                        $rtn[$k][$cid] = array_sum($tmp);
                    } else {
                        $rtn[$cid][$k] = array_sum($tmp);
                    }
                }
            }
            
            // 不存在app_id则以$v遍历
            if (!is_array($app_id)) {
                $tmp = [];
                foreach ($v as $ca => $c) {
                    list($tcid, $taid) = explode('_', $ca);
                    if (in_array(intval($tcid), $channel_id)) {
                        
                        if ($sortByTime) {
                            $rtn[$k][$tcid] += $c;
                        } else {
                            $rtn[$tcid][$k] += $c;
                        }
                    }
                }
            }
        }
        return $rtn;
    }
    
    /**
     * 
     * @param format $keyformat
     * @param number $hour
     * @return array
     */
    private function getLast($keyformat, $hour = 24) {
        $rdb = RedisProxy::getInstance();
        $now = strtotime(date('Y-m-d H:00:00'));
        $rtn = [];
        // 最多提供48小时
        for ($i = 0; $i < $hour && $i < 48; $i++) {
            $time = $now - $i * 3600;
            $key = sprintf($keyformat, $time);
//            $hkey = sprintf(self::KEY_FORMAT, self::KEY_FORMAT, $channel_id, $app_id);
            $d = $rdb->hGetAll($key);
            $rtn[$time] = $d;
        }
        ksort($rtn);
        return $rtn;
    }
    
    /**
     * 整理缓存，将超过48小时的数据清理掉
     * 交由redis自行清理
     */
//    public function trim() {
//        $rdb = RedisProxy::getInstance();
//        $keys = $rdb->hKeys(self::KEY);
//        $now = time();
//        if (!empty($keys)) {
//            foreach ($keys as $k) {
//                list($channel_id, $app_id, $timestr) = explode('_', $k);
//                $time = intval($timestr);
//                if ($now - $time > 172800) {
//                    $rdb->hDel(self::KEY, $k);
//                }
//            }
//        }
//    }
    
}
