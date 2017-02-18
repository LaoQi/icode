<?php

namespace Common;

use Common\Db;
/**
 * Description of StatData
 * 统计类
 * @author QQQ
 */
class StatData {
    
    protected $dock = [];

    public function __construct() {
        $db = Db::getInstance();
        // 对接参数
        $dockraw = $db->find('apps_channels', []);
        foreach ($dockraw as $d) {
            $key = $d['channel_id'] . '_' . $d['app_id'];
            $this->dock[$key] = $d;
        }
        unset($dockraw, $d);
    }
    
    public function findDock($channel_id, $app_id) {
        $key = $channel_id . '_' . $app_id;
        if (isset($this->dock[$key])) {
            return $this->dock[$key];
        }
        return false;
    }


    /**
     * 默认获取前一天
     * @param type $curtime
     * @return type
     */
    public function lastday($curtime = false) {
        if ($curtime === false) {
            $curtime = time() - 86400;
        }
        $start = date('Y-m-d', $curtime) . ' 00:00:00';
        $end = date('Y-m-d', $curtime) . ' 24:00:00';
        return array($start, $end);
    }
    
    /**
     * 登录统计
     * 新用户，新设备
     * @param String $start
     * @param String $end
     * @return Mixed 
     */
    public function register($start, $end) {
        $db = Db::getInstance();
        $sql = "
SELECT CONCAT(channel_id, '_', app_id) as ca, `channel_id`, `app_id`, 
    count(IF(`is_new`=1,1,NULL)) as new_user_count,
    count(IF(`is_newdev`=1,1,NULL)) as new_dev_count
FROM login_log FORCE INDEX (channel_app, time)
WHERE `time` > \"{$start}\" AND `time` < \"{$end}\" GROUP BY `channel_id`, `app_id` ORDER BY NULL";
        $data = $db->query($sql);
        if (!empty($data)) {
            $rtn = [];
            foreach ($data as &$d) {
                $rtn[$d['ca']] = $d;
            }
            return $rtn;
        }
        return [];
    }
    
    /**
     * 登录
     * DAU数据，（登录超过两次的用户）， 老用户，付费老用户
     * @param String $start
     * @param String $end
     */
    public function login($start, $end) {
        $db = Db::getInstance();
        $sql = "
SELECT CONCAT(channel_id, '_', app_id) as ca, channel_id, app_id, platform,
    count(1) AS user_count,
    count(IF(t.login_times > 1,1,NULL)) AS DAU,
    count(IF(`register_time`<\"{$start}\",1,NULL)) as olduser_count,
    count(IF(`register_time`<\"{$start}\" AND `is_pay`=1,1,NULL)) as oldpayuser_count
FROM (
    SELECT channel_id, app_id, count(1) as login_times, register_time, is_pay, platform
    FROM login_log  FORCE INDEX (channel_app, time, uid)
	WHERE `time` > \"{$start}\" AND `time` < \"{$end}\" GROUP BY uid ORDER BY NULL) 
as t GROUP BY channel_id, app_id ORDER BY NULL";
        $data = $db->query($sql);
        if (!empty($data)) {
            $rtn = [];
            foreach ($data as &$d) {
                $rtn[$d['ca']] = $d;
            }
            return $rtn;
        }
        return [];
    }
    
    /**
     * 付费统计  付费用户数（去重） 付费次数 总金额 老用户付费数
     * @param String $start
     * @param String $end
     * @return Mixed 
     */
    public function pay($start, $end) {
        $db = Db::getInstance();
        $sql = "
SELECT 
	CONCAT(channel_id, '_', app_id) as ca, `channel_id`, `app_id`,
	count(1) as pay_user_count, 
	sum(t.times) as pay_times , 
	sum(t.sum_money) as pay_sum,
	count(IF(`user_reg_time` < \"{$start}\", 1, NULL)) as olduser_pay_count,
	sum(IF(`user_reg_time` < \"{$start}\", t.sum_money, 0)) as olduser_pay_sum,
	sum(IF(`user_reg_time` < \"{$start}\", t.times, 0)) as olduser_pay_times
FROM
	(SELECT count(1) as times, uid, user_reg_time, sum(money) as sum_money, channel_id, app_id
	from channel_order FORCE INDEX(success_time,uid)
	where create_time > \"{$start}\" and create_time < \"{$end}\" GROUP BY uid ORDER BY NULL) as t
GROUP BY `channel_id`, `app_id` ORDER BY NULL";
        $data = $db->query($sql);
        if (!empty($data)) {
            $rtn = [];
            foreach ($data as &$d) {
                $rtn[$d['ca']] = $d;
            }
            return $rtn;
        }
        return [];
    }
    
    /**
     * 留存数据
     * @param String $date strtotime 'YYYY-MM-DD'
     * @return Mixed
     */
    public function remain($date = false) {
        $db = Db::getInstance();
        $days = [];
        if ($date === false) {
            $curtime = time() - 86400;
        } else {
            $curtime = strtotime($date);
        }
        foreach ([1,2,3,4,5,6,14,29] as $d) {
            $days[] = date('Y-m-d', $curtime - $d * 86400);
        }
        $curdate = date('Y-m-d', $curtime);
        
        $sql = "
SELECT CONCAT(channel_id, '_', app_id) as ca, t.channel_id, t.app_id, 
    count(IF(t.register_time > \"{$days[0]} 00:00:00\" AND t.register_time < \"{$days[0]} 24:00:00\", 1, NULL)) as r2,
    count(IF(t.register_time > \"{$days[1]} 00:00:00\" AND t.register_time < \"{$days[1]} 24:00:00\", 1, NULL)) as r3,
    count(IF(t.register_time > \"{$days[2]} 00:00:00\" AND t.register_time < \"{$days[2]} 24:00:00\", 1, NULL)) as r4,
    count(IF(t.register_time > \"{$days[3]} 00:00:00\" AND t.register_time < \"{$days[3]} 24:00:00\", 1, NULL)) as r5,
    count(IF(t.register_time > \"{$days[4]} 00:00:00\" AND t.register_time < \"{$days[4]} 24:00:00\", 1, NULL)) as r6,
    count(IF(t.register_time > \"{$days[5]} 00:00:00\" AND t.register_time < \"{$days[5]} 24:00:00\", 1, NULL)) as r7,
    count(IF(t.register_time > \"{$days[6]} 00:00:00\" AND t.register_time < \"{$days[6]} 24:00:00\", 1, NULL)) as r15,
    count(IF(t.register_time > \"{$days[7]} 00:00:00\" AND t.register_time < \"{$days[7]} 24:00:00\", 1, NULL)) as r30
FROM
	(SELECT 
		channel_id, app_id, uid, register_time, time
	FROM login_log FORCE INDEX (channel_app, time)
    WHERE time > \"{$curdate} 00:00:00\" AND time < \"{$curdate} 24:00:00\" GROUP BY uid ORDER BY NULL) as t
GROUP BY t.channel_id, t.app_id ORDER BY NULL";
        $data = $db->query($sql);
        if (!empty($data)) {
            $rtn = [];
            foreach ($data as &$d) {
                $rtn[$d['ca']] = $d;
            }
            return $rtn;
        }
        return [];
    }

    /**
     * 统计数据，不包括留存
     * @param int $date 日期 YYYYMMDD
     * @return type
     */
    public function makedata($date = false) {
        // 默认统计当日数据(昨日）
        if ($date === false) {
            list($start, $end) = $this->lastday();
        } else {
            // 统计所选择的日期数据
            list($start, $end) = $this->lastday(strtotime($date));
        }
        // 生成新的统计数据
        $login = $this->login($start, $end);
        $reg = $this->register($start, $end);
        $pay = $this->pay($start, $end);
        
        $data = [];
        foreach ($login as $ca => &$l) {
            $dock = $this->findDock($l['channel_id'], $l['app_id']);
            $tmp = [
                'date' => date('Ymd', strtotime($start)),
                'channel_id' => $l['channel_id'],
                'app_id' => $l['app_id'],
                'user_count' => $l['user_count'],
                'user_active_count' => $l['user_count'],
                'platform' => $l['platform'],
                'DAU' => $l['DAU'],
                'olduser_count' => $l['olduser_count'],
                'oldpayuser_count' => $l['oldpayuser_count'],
                // reg
                'new_user_count' => 0,
                'new_dev_count' => 0,
                // pay
                'pay_user_count' => 0,
                'pay_times' => 0,
                'pay_sum' => 0,
                'newuser_pay_count' => 0,
                'newuser_pay_sum' => 0,
                'olduser_pay_count' => 0,
                'olduser_pay_sum' => 0,
                'olduser_pay_times' => 0,
                // business
                'business' => isset($dock['business']) ? $dock['business'] : 0,
            ];
            if (isset($reg[$ca])) {
                $tmp['new_user_count'] = $reg[$ca]['new_user_count'];
                $tmp['new_dev_count'] = $reg[$ca]['new_dev_count'];
            }
            if (isset($pay[$ca])) {
                $p = $pay[$ca];
                $tmp['pay_user_count'] = $p['pay_user_count'];
                $tmp['pay_times'] = $p['pay_times'];
                $tmp['pay_sum'] = $p['pay_sum'];
                $tmp['olduser_pay_count'] = $p['olduser_pay_count'];
                $tmp['olduser_pay_sum'] = $p['olduser_pay_sum'];
                $tmp['olduser_pay_times'] = $p['olduser_pay_times'];
                $tmp['newuser_pay_count'] = $p['pay_user_count'] - $p['olduser_pay_count'];
                $tmp['newuser_pay_sum'] = $p['pay_sum'] - $p['olduser_pay_sum'];
            }
            $data[] = $tmp;
        }
        unset($tmp, $ca, $l);
        return $data;
    }
    
    /**
     * 根据统计数据得到结算报表
     * @param array $data
     */
    public function settlement($data) {
        if (empty($data)) {
            return [];
        }
        $rtn = [];
        foreach ($data as $d) {
            $dock = $this->findDock($d['channel_id'], $d['app_id']);
            if (!$dock) {
                continue;
            }
            $rtn[] = [
                'date' => $d['date'],
                'channel_id' => $d['channel_id'],
                'app_id' => $d['app_id'],
                'pay_sum' => $d['pay_sum'],
                // business
                'business' => $dock['business'],
                'own_fee' => $dock['own_fee'],
                'channel_fee' => $dock['channel_fee'],
                'channel_cost' => $dock['channel_cost'],
                // 
                'own_amount' => round(($d['pay_sum'] * $dock['own_fee'])/100, 2),
                'channel_amount' => round(($d['pay_sum'] * $dock['channel_fee'])/100, 2),
                'channel_cost_amount' => round(($d['pay_sum'] * $dock['channel_cost'])/100, 2),
            ];
        }
        return $rtn;
    }
}
