<?php
/**
 * 错误页面
 * @author john
 * @mail ucanup@gmail.com
 * 2014-05-17
 */
class errorPage extends page{

    public $_errorData = array(
        'unknow' => '出现了不可预知的错误!',
        '404' => '找不到页面，喝杯红茶冷静下吧 ( ´_ゝ`)',
        '233' => '内部错误',
    );
    public $_errorImg = array(
        'unknow' => 'danding.jpg',
        '404' => 'danding.jpg',
    );
    public $_errorType;
    public $_content = '';
    public $_css = array('error.css');
    public $_type = 'html';

    public function __construct($code = 404, $message = ''){
        $this->_errorType = $code;
        $this->page['title'] = '发生错误啦(つд`ﾟ)';
        $this->page['id'] = $code;
        if (isset($this->_errorData[$code])){
            $this->_content = $this->_errorData[$code];
        } else {
            $this->_content = $this->_errorData['unknow'];
        }
        
        if ($message != '') {
            $this->_content = $message;
        }
    }

    public function _makeContent(){
        switch ($this->_errorType){
        case 404:
            $img = $this->img($this->_errorImg[$this->_errorType]);
            $message = $this->div($this->_content, 'message');
            $out = $this->div($img . $message, 'notice');
            echo $out;
            break;
        case 233 :
            break;
        default:
            $message = $this->div($this->_content, 'message');
            $title = $this->div("System Error", "red font-big");
            $notice = $this->div($title . $message, 'notice');
            echo $notice;
        }
    }
}
   
