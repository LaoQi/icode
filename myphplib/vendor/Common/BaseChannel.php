<?php

namespace Common;

use api\Launch;
use Common\Log;
use Common\Func;
use Common\Db;
use Common\ChannelData;
use Common\LogPipe;

/**
 * Description of BaseChannel
 *
 * @author QQQ
 */
abstract class BaseChannel {
    
    const NotifyTaskList = "Notify::Tasks::List";
    
    /*************************
     * 通知状态
     ************************/
    /**
     * 已付款
     */
    const STATUS_PAY = 1;
    /**
     * 通知中（多次通知失败等待轮询通知）
     */
    const STATUS_NOTIFY = 2;
    /**
     * 通知成功
     */
    const STATUS_SUCCESS = 3;
    /**
     * 通知失败进入手动补单流程
     */
    const STATUS_FAILED = 4;
    
    protected $channel_id;
    
    public function __construct($channel_id) {
        $this->channel_id = $channel_id;
    }
    public function getChannelId() {
        return $this->channel_id;
    }

    // 重载方法，防止调用不存在的方法报错
    function __call($name, $arguments) {
        // 记录错误日志
        return false;
    }

    /**
     * 检查token，获得渠道用户标识
     * @param array $args 登录验证的请求参数
     * @return Mixed  [ 'code' ,'cuid' , 'username' ]  or false
     * @link http://183.60.108.95/admin/document/sdkinterface
     */
    public abstract function login($args);

    /**
     * 支付回调
     * @param array $args 渠道请求的数据集
     */
    public abstract function payCallback($args);

    /**
     * 获取产品信息
     * @param array $args
     */
    public function getItem($args) {
        if (!Func::CheckParams($args, ['app_id', 'item_id'])) {
            Launch::returnCode(2);
        }
        $cd = ChannelData::InitData($this->channel_id, $args['app_id']);
        if ($args['item_id'] !== '-1') {
            $item = $cd->getItem($args['item_id']);
            if (!empty($item)) {
                Launch::returnCode(1, [
                    "data" => [$item],
                ]);
            }
        }
        // 获取全部
        else {
            $items = $cd->getAllItems();
            if (!empty($items)) {
                Launch::returnCode(1, [
                    "data" => array_values($items),
                ]);
            }
        }

        Launch::returnCode(3);
    }
    
    /**
     * payCallback之前的装饰
     * @param type $args
     */
    public function doPay($args) {
        // 测试
        if (ChannelData::GetDebugMode($this->channel_id, ChannelData::DebugPay)) {
            $datalog = RUNTIME_DIR . 'Debug/pay/' . $this->channel_id . '_' . date("Y_m_d");
            file_put_contents($datalog, var_export($args, true) . PHP_EOL, FILE_APPEND);
        }
        $this->payCallback($args);
    }
    
    /**
     * 订单成功处理逻辑
     *      更新cp订单表，添加渠道订单，通知cp方，更新渠道订单状态
     * @param array $order cp订单表订单信息
     * @param string $chl_order_id  渠道订单号
     * @return bool 是否成功处理
     */
    protected function order_success($order, $chl_order_id) {
        $date = date('Y-m-d H:i:s');
        $db = Db::getInstance();

        // 查找该用户
        $user = $db->findOne('users', ['uid' => $order['uid']]);
        if (empty($user)) {
            Log::write('Order user error, uid ['. $order['uid'].']', '[Db]');
        }
        
        // 更新用户数据
        $check = $db->query('UPDATE `users` SET `pay_count` = `pay_count` + ' 
            . $order['money'] . ', `pay_times` = `pay_times` + 1 WHERE `uid` = "' 
            . $order['uid'] .'"');
        
        if (!$check) {
            Log::write('Update users error, uid ['. $order['uid'].']', '[Db]');
        }
        
        $order['pay_status'] = 1;
        $check_result = $db->update('cp_order', 
            ['id' => $order['id']], 
            ['pay_status' => 1]);
        if (! $check_result) {
            Log::write('Update cp_order error, id ['. $order['id'] .'] ', '[Db]');
        }
        
        // 记录渠道订单
        $chl_order = array(
            'order_id' => $order['order_id'],
            'app_id' => $order['app_id'],
            'channel_id' => $order['channel_id'],
            'cp_id' => $order['cp_id'],
            'channel_order_id' => $chl_order_id,
            'cp_order_id' => $order['cp_order_id'],
            'uid' => $order['uid'],
            'money' => $order['money'],
            'status' => $order['pay_status'],
            'order_title' => $order['order_title'],
            'create_time' => $date,
            'user_reg_time' => $user['register_time']
        );
        
        $chl_oid = $db->insert('channel_order', $chl_order);
        if ($chl_oid < 0) {
            Log::write('Insert channel_order error, chl_order data :'. json_encode($chl_order), '[Db]');
            return false;
        }
        // 回调处理
        $log = static::NotifyCpServer($order);
        
        $chl_order_update = array(
            'status' => $log['result'] === 1 ? self::STATUS_SUCCESS : self::STATUS_NOTIFY,
            'notify_times' => 1,
            'notify_success_time' => $date,
        );
        // 成功加入流水
        if ($chl_order_update['status'] == self::STATUS_SUCCESS) {
            $chl_log = array_merge($chl_order, $chl_order_update);
            LogPipe::Send(['act' => 'channel_order', 'data' => $chl_log]);
        }
        
        $checkchl = $db->update('channel_order', array('id' => $chl_oid), $chl_order_update);
        if (!$checkchl) {
            Log::write('Update channel_order error, id ['. $chl_oid .']', '[Db]');
        }
        return $log['result'] === 1;
    }
    
    /**
     * 通知cp方
     * @param array $order   cp订单表订单详情
     * @param int $retry  重试次数，默认为5
     * @return int
     */
    public static function NotifyCpServer($order, $retry = 5) {
        $time = time();
//        $apps = M('apps')->where('id=' . $order['app_id'])->field('cp_key, notify_url')->find();
        $CD = ChannelData::InitData($order['channel_id'], $order['app_id']);
        $appkey = $CD->get(ChannelData::AppKey);
        $cp_notify_url = $CD->get(ChannelData::NotifyUrl);
        // 生成规则，访问回调地址
        $data = [
            'order' => $order['order_id'],
            'time' => $time,
            'money' => $order['money'],
            'uid' => $order['uid'],
            'cp_order' => $order['cp_order_id'],
            'cp_info' => $order['cp_info'],
            'order_title' => $order['order_title'],
        ];
        $data['sign'] = strtolower(
            md5($data['order'] . 
                $time . 
                $data['money'] . 
                $data['uid'] . 
                $data['cp_order']. 
                $appkey ));
        
        $notify_res = Func::HttpRequest($cp_notify_url, $data);

        // 组成回调结果
        $log = array(
            'order_id' => $order['order_id'],
            'notify_url' => $cp_notify_url,
            'notify_data' => json_encode($data),
            'time' => date('Y-m-d H:i:s'),
            'cp_id' => $order['cp_id'],
            'channel_id' => $order['channel_id'],
            'app_id' => $order['app_id'],
        );
        if ($notify_res['errno'] === 0) {
            $log['http_code'] = $notify_res['code'];
            $log['response_data'] = $notify_res['body'];
        } else {
            $log['http_code'] = '0';
            $log['response_data'] = $notify_res['errno'] . ' ' . $notify_res['error'] . ' ' . $notify_res['body'];
        }

        // 判断回调地址返回参数是否正确
        if (!empty($notify_res['body']) && intval($notify_res['body']) === 1) {
            $log['result'] = 1;
        } else {
            $log['result'] = 0;
            // 根据重试次数加入轮询队列
            if ($retry > 0) {
                $task = [
                    'retry' => $retry,
                    'order' => $order
                ];
                // 加入轮询通知队列
                $rdb = RedisProxy::getInstance();
                $rdb->lPush(static::NotifyTaskList, serialize($task));
            }
        }
        $db = Db::getInstance();
        // 加入记录统计日志
        $db->insert('notify_log', $log);
        // 加入统计流水日志
        LogPipe::Send(['act' => 'notify', 'data' => $log]);
        return $log;
    }
    
    /**
     * 检查订单
     * @param string $order_id 订单id
     * @param mixed $empty_out 订单为空时输出
     * @param mixed $complete_out 订单已完成输出
     * @return mixed 异常退出，正常返回 $order_data
     */
    protected function checkOrder($order_id, $empty_out = false, $complete_out = false) {
        $db = Db::getInstance();
        // 查询订单号是否存在
        $order_data = $db->findOne('cp_order',
            array('order_id' => $order_id));
        //查询不到对应订单ID
        if (empty($order_data)) {
            if ($empty_out === false) {
                return false;
            }
            echo $empty_out;
            die();
        }
        if ($order_data['pay_status'] == 1) {
            if ($complete_out === false) {
                return false;
            }
            echo $complete_out; // 订单已完成
            die();
        }
        return $order_data;
    }
    
    
    /**
     * 创建订单
     */
    public function createOrder($args) {
        // 验证post
        $ckparams = Func::StrictCheckParams($args, [
            'uid', 'cp_order_id', 'cp_id', 'app_id', 'channel_id', 'money', 'sign', 'platform',
        ]);
        if (!$ckparams) {
            Launch::returnCode(2);
        }

        if (strlen($args['uid']) !== 16) {
            // 非法的uid
            Launch::returnCode(401);
        }
        // 验证sign
        if (md5($args['cp_order_id'] . $args['cp_id'] . $args['app_id'] . $args['money'] . '93pk') !== $args['sign']) {
            Launch::returnCode(3);
        }
        $db = Db::getInstance();
        $res = $db->findOne('cp_order', array(
            'cp_order_id' => $args['cp_order_id'],
            'cp_id' => $args['cp_id']));
        
        // 检查订单是否已存在
        if (!empty($res)) {
            Launch::returnCode(402, [
                'order_id' => $res['order_id'],
            ]);
        }
        $money = round(floatval($args['money']), 2);
        $cp_info = empty($args['cp_info']) ? '' : $args['cp_info'];
        $order_title = empty($args['order_title']) ? '' : $args['order_title'];

        // 创建订单
        $order = array(
            'uid' => $args['uid'],
            'channel_id' => $args['channel_id'],
            'app_id' => $args['app_id'],
            'cp_order_id' => $args['cp_order_id'],
            'cp_id' => $args['cp_id'],
            'money' => $money,
            'create_time' => date('Y-m-d H:i:s'),
            'platform' => intval($args['platform']),
            'cp_info' => $cp_info,
            'order_title' => $order_title,
        );
        $md5 = md5(uniqid($args['cp_order_id'], true));
        $order['order_id'] = substr($md5, 0, 8) .
            '-' . substr($md5, 8, 4) .
            '-' . substr($md5, 12, 6) .
            '-' . substr($md5, 18, 8) .
            '-' . substr($md5, 26, 6);
        
        $res_id = $db->insert('cp_order', $order);
        if ($res_id > 0) {
            Launch::returnCode(1, [
                'order_id' => $order['order_id'],
                'msg' => '创建订单成功',
            ]);
        }
        // 订单创建失败
        Launch::returnCode(403);
    }
}
