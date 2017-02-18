<?php

namespace Templet;

use F\URL;
/**
 * 自动生成表单模板
 *
 * @author QQQ
 */
abstract class FormTemplet {
    
    public $args = [];
    public $submit = true;
    public $id = "F-Form";
    public $method = "post";
    public $action = "";
    public $file = false;
    
    public function __construct($args = []) {
        $this->args = $args;
    }
    
    /**
     * @return Array [
     *                  'method' => 'post',
     *                  'action' => 'index/index',
     *                  'fields' => [
     *                      ['name'=>'username', 
     *                      'type'=>'text', 
     *                      'title'=>'用户名', 
     *                      'placeholder' => '输入用户名'],
     *                  ]
     */
    abstract function getForm();
    
    abstract function getFilter();
    
    /**
     * 过滤成可直接使用的数据
     */
    public function filter() {
        if (empty($this->args)) {
            return false;
        }
        $args = $this->args;
        $filter = $this->getFilter();
        $data = [];
        foreach ($filter as $k => $v) {
            if (!$v) {
                if (isset($args[$k])) {
                    $data[$k] = $args[$k];
                    continue;
                } else {
                    return false;
                }
            }
            if (!empty($args[$k])) {
                foreach ($v as $f) {
                    $res = $this->switchFilter($args[$k], $f);
                    if (!$res) {
                        return false;
                    }
                    $data[$k] = $args[$k];
                }
            } else if (in_array('require', $v)) {
                return false;
            }
        }
        return $data;
    }
    
    public function switchFilter($data, $f) {
        switch ($f) {
            case 'require':
                return true;
            case 'notzero':
                return intval($data) != 0;
            default :
                return true;
        }
    }

    /**
     * 渲染表单
     */
    public function view($display = true) {
        $form = $this->getForm();
        ob_start();
        // 表单之前
        $this->beforeForm();
        echo '<div class="row">
                <div class="col-md-10">';
        echo '<form class="form-horizontal"', 
            ' id="', $this->id, '"' ;
        if ($this->file) {
            echo ' enctype="multipart/form-data"';
        }
        echo ' method="' ,$this->method, '" action="', URL::TagA($this->action),'">';
        
        // 表单组之前
        $this->beforeGroup();
        foreach ($form['fields'] as $group) {
            $this->makeGroup($group);
        }
        // 表单组之后
        $this->afterGroup();
        
        // 隐藏提交内容
        if (!empty($form['hide'])){
            foreach ($form['hide'] as $hide) {
                $this->makeHide($hide);
            }
        }
        
        // 提交按钮
        $this->makeSubmit();
        
        echo '</form></div></div>';
        // 表单之后
        $this->afterForm();
        if ($display) {
            ob_end_flush();
            return ;
        }
        return ob_get_clean();
    }
    
    
    public function beforeForm() { }
    
    public function afterForm() { }

    public function beforeGroup() { }
    
    public function afterGroup() { }
    
    public function makeSubmit() {
        if ($this->submit) {
            echo '<div class="form-group">
        <div class="col-sm-offset-2 col-sm-10">
            <button type="submit" class="btn btn-lg btn-primary">提交</button>
        </div>
    </div>';
        }
    }
    
    public function makeHide($hide) {
        echo '<input class="hide" name="' . $hide['name'] . 
            '" value="' . $hide['value'] . '" />';
    }
    
    public function makeGroup($group) {
        $type = 'text';
        if (isset($group['type'])) {
            $type = $group['type'];
        }
        //分隔线
        if ($type == 'hr') {
            echo '<hr/>';
            return null;
        }

        $rowClass = "form-group";
        if (!empty($group['rowClass'])) {
            $rowClass = $group['rowClass'];
        }
        if (isset($group['hide']) && $group['hide']) {
            $rowClass = 'hide';
        }
        echo '<div class="', $rowClass, '"><label for="input', $group['name'],
                '" class="col-sm-2 control-label">',$group['title'],
                '</label><div class="col-sm-10">';
        $class = 'form-control';
        $attr = [];
        if (!empty($group['class'])) {
            $class = $group['class'];
        }
        if (!empty($group['attr'])) {
            $attr = $group['attr'];
        }
        switch ($type) {
            case 'file':
                //TODO 文件上传功能
            case 'text':
            case 'password':
            case 'number':
            case 'email':
                $this->makeInput($group, $type, $class, $attr);
                break;
            case 'textarea':
                $this->makeTextarea($group, $class, $attr);
                break;
            case 'select':
                $this->makeSelected($group, $class, $attr);
                break;
            case 'checkbox':
                $this->makeCheckbox($group, $class, $attr);
                break;
            default :
                break;
        }
        echo '</div></div>';
    }
    
    public function makeTextarea($group, $class, $attr) {
        echo '<textarea class="',$class,'" id="input', $group['name'], 
            '" name="', $group['name'], '" ';
        $this->makeAttr($attr);
        if (isset($group['placeholder'])){
            echo 'placeholder="',$group['placeholder'],'" ';
        }
        echo '>';
        if (isset($group['default'])) {
            echo $group['default'];
        }
        echo '</textarea>';
    }
    
    public function makeSelected($group, $class, $attr) {
        echo '<select class="',$class,'" id="input', $group['name'],
            '" name="', $group['name'], '" ';
        $this->makeAttr($attr);
        echo '>';
        if (is_array($group['data'])) {
            $data = $group['data'];
        } else {
            $data = $this->$group['data']();
        }
        $default = isset($group['default']) ? $group['default'] : '';
        foreach ($data as $v) {
            echo '<option value="', $v['value'], '" ';
            if ($v['value'] == $default) {
                echo 'selected="selected"';
            }
            echo '>', $v['text'], '</option>';
        }
        echo '</select>';
    }
    
    public function makeInput($group, $type, $class, $attr) {
        $has_wrap = isset($group['prefix']) || isset($group['suffix']);
        if ($has_wrap) {
            echo '<div class="input-group">';
        }

        //前缀
        if (isset($group['prefix'])) {
            echo "<span class='input-group-addon'>{$group['prefix']}</span>";
        }

        //input
        echo '<input class="' . $class .'" id="input' . $group['name'] .
            '" name="' . $group['name'] . '" ';
        if (isset($group['default'])) {
            echo 'value="' . $group['default'] . '" ';
        }
        $this->makeAttr($attr);
        echo 'type="' . $type . '" ';
        if (isset($group['placeholder'])){
            echo 'placeholder="' . $group['placeholder'] . '" ';
        }
        echo '/>';

        //后缀
        if (isset($group['suffix'])) {
            echo "<span class='input-group-addon'>{$group['suffix']}</span>";
        }

        if ($has_wrap) {
            echo '</div>';
        }
    }
    
    public function makeCheckbox($group, $class, $attr) {
        echo '<input class="',$class,'" id="input', 
            $group['name'], '" type="checkbox" value="',
            $group['value'], '"';
        if (!empty($group['default'])) {
            echo ' checked="checked"';
        }
        $this->makeAttr($attr);
        echo ' />';
    }
    
    public function makeAttr($attr) {
        foreach($attr as $k => $v) {
            echo $k . '="' . $v . '" ';
        }
    }
    
}
