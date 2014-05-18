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
    );
    public $_errorImg = array(
        'unknow' => 'danding.jpg',
        '404' => 'danding.jpg',
    );
    public $_errorType;
    public $_content;
    public $_css = array('error.css');
    public $_headers = array('<title>发生错误啦</title>');

    public function __construct($type = 404){
        $this->_type = 'html';
        $this->_errorType = $type;
        if (isset($this->_errorData[$type])){
            $this->_content = $this->_errorData[$type];
        } else {
            $this->_content = $this->_errorData['unknow'];
        }
    }

    public function _makeContent(){
        $this->_img($this->_errorImg[$this->_errorType], 'errorImg');
        $this->_div($this->_content, 'font-big');
    }
}
   
