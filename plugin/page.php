<?php

/**
 * 一个页面类，组装页面元素
 * @author john
 * @mail ucanup@gmail.com
 * 2014-05-17
 */
class page extends BASE {

    //页面信息
    public $page = array();

    /**
     * 网页元素
     */
    public $_type       = 'html';
    public $_coding     = 'utf-8';
    public $_headers    = array();
    public $_css        = array();
    public $_javascript = array();
    public $_top        = false;
    public $_bottom     = false;
    public $_content    = array();
    public $_sidebar    = false;
    public $_isTemp     = 0;

    public function __construct($page) {
        parent::__construct();
        $this->page    = $page;
        $this->_isTemp = isset($page['istemp']) ? (int) $page['istemp'] : 0;
        $this->_content = self::$db->query("select * from content where pageid=" . $page['id']);
        if (empty($this->_content)){
            throw new Exception("页面内容已经消失!", 333);
        }
    }

    public function _set() {
        
    }

    public function setTitle($title) {
        $this->_headers[] = "<title>" . $title . "</title>";
    }

    public function view() {
        ob_start();
        if ($this->_type == 'html') {
            echo '<!DOCTYPE html>';
            $this->setTitle($this->page['title']);
            $this->_html();
        } elseif ($this->_type == 'xml') {
            echo '<?xml version="1.0" encoding="' . $this->_coding . '"?>';
            $this->_xml();
        } else {
            $this->_other();
        }
        if ($this->_isTemp) {
            $page = ob_get_contents();
            $this->writeFile($page);
        }
        ob_end_flush();
        exit;
    }

    public function writeFile($page){
        if (!is_writable(TEMP)){
            chmod(TEMP, 0770);
        }
        file_put_contents(TEMP . $this->page['id'] . '.tpl', $page);
    }


    /**
     * html页面
     */
    public function _html() {
        echo '<html><head>';
        echo '<meta http-equiv="Content-Type" content="text/html; charset=' . $this->_coding . '" />';
        if (!empty($this->_headers)) {
            foreach ($this->_headers as $h) {
                echo $h;
            }
        }
        if (!empty($this->_css)) {
            foreach ($this->_css as $c) {
                echo '<link href="' . CSSPATH . $c . '" rel="stylesheet" type="text/css" />';
            }
        }
        if (!empty($this->_javascript)) {
            foreach ($this->_javascript as $j) {
                echo '<script type="text/javascript" src="' . JSPATH . $j . '"></script>';
            }
        }
        echo '</head><body>';
        $this->_makeContent();
        echo '</body></html>';
    }

    /**
     * 拼接内容
     */
    public function _makeContent() {
        foreach($this->_content as $element){
            $tmp = new $element['type']();
            $tmp->view();
        }
    }

    public function img($img, $css = false) {
        $rtn = '<img ';
        if ($css) {
            $rtn .= 'class="' . $css . '" ';
        }
        $rtn .= 'src="' . IMGPATH . $img . '" />';
        return $rtn;
    }

    public function div($content, $css = false, $id = false) {
        $rtn = '<div ';
        if ($css) {
            $rtn .= 'class="' . $css . '" ';
        }
        if ($id) {
            $rtn .= 'id="' . $id . '"';
        }
        $rtn .= '/>';
        $rtn .= $content;
        $rtn .= '</div>';
        return $rtn;
    }

}
