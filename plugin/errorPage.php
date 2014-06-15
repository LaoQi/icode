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
    public $_content;
    public $_css = array('error.css');

    public function __construct($type = 404, $message = ''){
        $this->_type = 'html';
        $this->_errorType = $type;
        $this->page['title'] = '发生错误啦';
        $this->page['id'] = $type;
        if (isset($this->_errorData[$type])){
            $this->_content = $this->_errorData[$type];
        } elseif ($message != '') {
            $this->_content = $message;
        } else {
            $this->_content = $this->_errorData['unknow'];
        }
    }

    public function _makeContent(){
        switch ($this->_type){
        case '404':
            $this->_img($this->_errorImg[$this->_errorType], 'errorImg');
            $this->_div($this->_content, 'font-big');
            break;
        case '233' :
            break;
        default:
            $this->_img($this->_errorImg[$this->_errorType], 'errorImg');
            $this->_div($this->_content, 'font-big');
        }
    }
}
   
