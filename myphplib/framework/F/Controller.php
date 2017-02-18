<?php

namespace F;

use F\ACL;
use F\User;
use Common\Db;

/**
 * Description of Controller
 *
 * @author QQQ
 */
class Controller {

    protected $config = null;
    protected $args = null;

    /**
     *  public $acl = [
     *     'test' => ['desc' => '测试', 'method' => ['testAction', 'testFormAction']],
     *  ];
     */
    public $acl = [];

    public function __construct($config, $args) {
        $this->config = $config;
        $this->args = $args;
    }

    /**
     * 做权限检查
     * @param string $controller 控制器名称
     * @param string $action
     */
    public function doAction($controller, $action) {
        $check = true;
        $url = false;
        foreach ($this->acl as $key => $value) {
            if (in_array($action, $value['method'])) {
                if ($key === 'noAuthention') {
                    $check = false;
                } else if (isset($value['redirect'])) {
                    $url = $value['redirect'];
                }
                break;
            }
        }
        if ($check) {
            session_start();
            $ACL = ACL::Init();
            $user = User::Init();
            if (!$user->logined) {
                $this->notLogin();
            }
            if (!$ACL->checkAction($user, $action, $controller)) {
                $this->noPermission($url);
            }
        }
        $this->$action();
    }

    protected function notLogin() {
        http_response_code(403);
        $this->render('public/message', [
                'success' => false, 
                'message' => '未登录', 
                'url' => false,
            ]);
    }

    protected function noPermission($url = false) {
        if ($url) {
            $this->failed('无访问权限！', $url);
        }
        http_response_code(403);
        $this->render('public/message', [
            'success' => false,
            'message' => '无访问权限！',
            'url' => false]);
    }

    /**
     * 消息方法
     * @param array $message
     * @param Mixed $url
     * @param Int $timeout
     * @param Boolean $success
     */
    protected function message($message, $url = false, $timeout = 3, $success = true) {
        http_response_code(200);
        $this->render('public/message', [
            'success' => $success,
            'message' => $message,
            'url' => $url,
            'timeout' => $timeout]);
    }

    protected function success($message, $url = false, $timeout = 3) {
        http_response_code(200);
        $this->render('public/message', [
            'success' => true,
            'message' => $message,
            'url' => $url,
            'timeout' => $timeout]);
    }

    protected function failed($message, $url = false, $timeout = 3) {
        http_response_code(200);
        $this->render('public/message', [
            'success' => false,
            'message' => $message,
            'url' => $url,
            'timeout' => $timeout]);
    }

    protected function debug() {
        http_response_code(200);
        $arg_array = func_get_args();
        echo "<h1>调试信息</h1><hr /><pre>";
        var_dump($arg_array);
        echo "<h1>请求参数</h1><hr /><pre>";
        var_dump($this->args);
        echo "</pre><hr /><h1>数据库请求</h1><pre>";
        var_dump(Db::getInstance()->sql_record, Db::getInstance()->bind_record, Db::getInstance()->error);
        echo "</pre><hr /><h1>文件加载</h1><pre>";
        var_dump(\F\F::$LoadHistory);
        echo "</pre><hr />";
        echo "<h4>总用时：" . (microtime() - \F\F::$Start) . " ms</h4>";
        echo "<h4>内存用量：" . (memory_get_usage() / 1024) . " kb</h4>";
        die();
    }

    /**
     * 跳转
     * @param string $url
     * @param string|bool $msg
     */
    protected function redirect($url, $msg = false, $data = null) {
        if ($msg) {
            http_response_code(200);
            echo '<script>alert("' . $msg . '");window.location.href="' . URL::Url($url, $data) . '";</script>';
            die();
        }
        header('Location: ' . URL::Url($url, $data));
        die();
    }

    /**
     * 返回JSON
     * @param array $data
     */
    public function ajax($data) {
        $out = '{}';
        if (!empty($data) && is_array($data)) {
            $out = json_encode($data);
        }
        http_response_code(200);
        header("Content-type: application/json");
        die($out);
    }

    /**
     * 直接输出
     * @param string $content
     */
    public function out($content) {
        http_response_code(200);
        die($content);
    }

    /**
     * 输出CSV表格文件
     * @param string $filename
     * @param array $head
     * @param array $data
     */
    public function csv($filename, $head, $data) {
        ob_end_clean();
        //header('Content-Encoding: UTF-8');
        header('Content-Type: application/octet-steam; charset=UTF-8');
        header('Content-Disposition: attachment;filename="' . $filename . '.csv"');
        header('Cache-Control: max-age=0');
        echo "\xEF\xBB\xBF";

        $csv = fopen('php://output', 'w');
        fputcsv($csv, $head);
        foreach ($data as $row) {
            fputcsv($csv, $row);
        }
        fputcsv($csv, []);
        fputcsv($csv, ['Create Time:', date('Y-m-d H:i:s')]);
        fclose($csv);
        die();
    }

    /**
     * 展示页面
     * @param string $view
     * @param array $__data
     * @throws \Exception
     */
    public function render($view, $__data = null) {
        ob_start();
        if (!empty($__data) && is_array($__data)) {
            foreach ($__data as $key => $value) {
                $$key = $value;
            }
            unset($__data);
        }
        http_response_code(200);
        $cname = str_replace('Controller', '', get_class($this));
        $dname = strtolower(trim($cname, '\\'));
        if (file_exists(VIEW_DIR . $dname . '/' . $view . '.php')) {
            require VIEW_DIR . $dname . '/' . $view . '.php';
        } else if (file_exists(VIEW_DIR . $view . '.php')) {
            require VIEW_DIR . $view . '.php';
        } else {
            throw new \Exception('templete ' . $view . ' not found');
        }
//        $contents = ob_get_contents();
//        ob_end_clean();
        ob_end_flush();
        die();
    }

}
