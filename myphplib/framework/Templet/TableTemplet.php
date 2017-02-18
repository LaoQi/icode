<?php

namespace Templet;

use F\Model;
use F\URL;
/**
 * Description of TableTemplet
 *
 * @author QQQ
 */
abstract class TableTemplet {
    /**
     * [
     *     'key' => [
     *                  'title' => '日期',
     *                  'format' => 'date',
     *              ],
     * ]
     */
    abstract function getViewField() ;
    
    abstract function getPicker();
    
    public $tableName;
    public $pkName;
    public $args;
    public $pdata = null;
    
    public $dataCount = 100;

    public $id = "F-Table";
    const PickerKey = "F-Table-Picker";
    const PageinateKey = "F-Table-Page";
    
    public $config = [
        'pageMax' => 20,
        'paginateView' => 10,
    ];

    public $condition = []; //额外的查询条件
    
    public function __construct($args = []) {
        $this->args = $args;
    }
    
    /**
     * 数据准备
     */
    public function dataPrepare() {
        $args = $this->args;
        $picker = $this->getPicker();
        $this->pdata = [];
        $pdata = [];
        if (isset($this->args[static::PickerKey])) {
            $pdata = json_decode($args[static::PickerKey], true);
        }
        // 更新选择器
        $clean = true;
        // 其他选择器跟新重置分页选择器
        $resetPage = false;
        if (!empty($picker)) {
            foreach ($picker as $p) {
                if (isset($args[$p['name']])) {
                    $clean = false;
                    $resetPage = true;
                    $pdata[$p['name']] = trim($args[$p['name']]);
                }
            }
        }
        // 分页选择器
        if (isset($args[static::PageinateKey])) {
            $clean = false;
            $pdata[static::PageinateKey] = $args[static::PageinateKey];
        }
        if ($resetPage) {
            unset($pdata[static::PageinateKey]);
        }

        if (!empty($pdata) && !$clean) {
            $this->pdata = $pdata;
            setcookie(static::PickerKey, json_encode($pdata), 0);
        }
        // 当所有picker都未命中，去除所有选择器
        if ($clean) {
            $this->pdata = [];
            setcookie(static::PickerKey, '', 0);
        }
    }
    
    public function set($config) {
        $this->config = array_merge($this->config, $config);
    }
    
    public function filter($args) {
        $this->args = $args;
    }

    /**
     * 在表格输出之前执行，可以输出其它显示信息
     */
    public function beforeTable() {}

    public function afterTable() {}

    /**
     * 
     * @param type $custom_config  ['title' => 'title', 'No' => true or '#',] 
     */
    public function view($custom_config = []) {
        $this->dataPrepare();
        
        $config = array_merge($this->config, $custom_config);
        
        $viewField = $this->getViewField();

        ob_start();
        echo '<div id="', $this->id , '" class="panel panel-default">';
        if (isset($config['title'])) {
            echo '<div class="panel-heading">' . $config['title'] . '</div>';
        }
        echo '<div class="panel-body"><div class="table-picker-panel">';
        // create
        if (isset($config['create'])) {
            $params = isset($config['create']['data']) ? $config['create']['data'] : null;
            echo ' <div class="btn-group"><a href="', Url::TagA($config['create']['url'], $params),
                '" class="btn btn-success">', $config['create']['text'], '</a></div>';
        }
        // export 
        if (isset($config['export'])) {
            $params = isset($config['export']['data']) ? $config['export']['data'] : null;
            echo ' <div class="btn-group"><a target="_blank" href="', Url::TagA($config['export']['url'], $params),
                '" class="btn btn-danger">', $config['export']['text'], '</a></div>';
        }
        echo '</div>';
        // picker
        if (isset($config['picker'])) {
            echo '<script type="text/javascript" src="' . URL::JS('bootstrap-datepicker.js') . '"></script>';
            echo '<link href="' . URL::CSS('datepicker.css') . '" rel="stylesheet" /></style>';
            $this->makePicker($config);
            echo '<script>$(".table-datepicker").datepicker();</script>';
        }
        // table before
        $this->beforeTable();
        // table body
        echo '<div class="table-responsive">';
        echo '<table class="table table-striped table-bordered table-hover" style="white-space: pre;">';
        //thead
        echo '<thead><tr>';
        $No = false;
        if (isset($config['No'])) {
            $No = true;
            if ($config['No'] !== true) {
                echo '<th>', $config['No'], '</th>';
            } else {
                echo '<th>#</th>';
            }
        }
        foreach($viewField as $v) {
            echo '<th>', $v['title'] , '</th>';
        }
        echo '</tr></thead>';
        //tbody
        $data = $this->dataProvide();
        if (!empty($data)) {
            foreach ($data as $n => $d) {
                echo '<tr>';
                if ($No) {
                    //序号
                    $page = isset($this->pdata[static::PageinateKey]) ? $this->pdata[static::PageinateKey] : 1;
                    $index = ($page - 1) * $config['pageMax'] + $n + 1;
                    echo '<td>', $index, '</td>';
                }
                $this->makeRowData($viewField, $d);
                echo '</tr>';
            }
        }
        echo '</table></div><div class="table-paginate-panel">';
        if (isset($config['paginate']) && $config['paginate']) {
            echo '<div class="col-md-10">';
            $this->makePaginate();
            echo '</div>';
        }
        echo '</div>';
        $this->afterTable();
        echo '</div></div>';
        ob_end_flush();
    }
    
    protected function makePicker($config = []) {
        $pickers = $this->getPicker();
        $url = '';
        if (isset($config['picker']['url'])) {
            $url = URL::Url($config['picker']['url']);
        }
        echo '<div class="table-picker-panel">'
        . '<form class="form-inline" method="get" action="' . $url . '">';
        foreach ($pickers as $p) {
            $this->makeP($p);
        }
        echo ' <div class="form-group" ><button type="submit" class="btn btn-info">查询</button></div></form></div>';
    }

    protected function makeP($p) {
        echo '<div class="form-group">';
        if ($p['type'] == 'date') {
			echo '<div class="input-group">
                <span class="input-group-addon" id="addon-', $p['name'] ,'">', $p['title'], '</span>
                <input type="text" readonly="readonly" ';
            if (isset($this->pdata[$p['name']])) {
                echo 'value="', $this->pdata[$p['name']], '" ';
            }
            echo 'class="form-control table-datepicker" name="', $p['name'], 
                '"aria-describedby="addon-', $p['name'] ,'">
                </div>';
        } elseif ($p['type'] == 'select' || $p['type'] == 'chosen-select') {
            echo '<div class="input-group">';
            $cls = 'form-control';
            if ($p['type'] == 'chosen-select') {
                $cls = 'chosen-select';
            } else {
                echo '<span class="input-group-addon" id="addon-', $p['name'], '">', $p['title'], '</span>';
            }
            echo '<select class="', $cls ,'" id="input', $p['name'],
            '" name="', $p['name'], '" data-placeholder="', $p['title'], '" ';
            if (isset($p['attr'])) {
                $this->makeAttr($p['attr']);
            }
            echo '>';
            if (is_array($p['data'])) {
                $data = $p['data'];
            } else {
                $data = $this->$p['data']();
            }
            if (is_array($data) && $p['type'] == 'chosen-select') {
                //显示一个默认提示，提示信息为$p['title']
                array_unshift($data, ['text' => '', 'value' => '']);
            }
            $default = isset($p['default']) ? $p['default'] : '';
            if (isset($this->pdata[$p['name']])) {
                $default = $this->pdata[$p['name']];
            }
            foreach ($data as $v) {
                echo '<option value="', $v['value'], '" ';
                if ($v['value'] == $default && $default !== '') {
                    echo 'selected="selected"';
                }
                echo '>', $v['text'], '</option>';
            }
            echo '</select></div>';
        } elseif ($p['type'] == 'text') {
            echo '<div class="input-group">',
                 '<span class="input-group-addon" id="addon-',
                 $p['name'],'">',$p['title'],'</span>';
            echo '<input type="text" class="form-control" name="',
                 $p['name'], '" ';
            if (isset($p['placeholder'])) {
                echo 'placeholder="', $p['placeholder'], '"';
            }
            echo ' aria-describedby="addon-',$p['name'],'"></div>';
        }
        echo '</div> ';
    }

    protected function makeRowData($viewField, $data) {
        foreach ($viewField as $k => $f) {
            echo '<td>';
            // 默认输出数据
            if (!isset($f['type'])) {
                if (!isset($data[$k])) {
                    continue;
                } else if (isset($f['format']) && method_exists($this, $f['format'])) {
                    echo $this->$f['format']($data);
                } else {
                    echo $data[$k];
                }
            } 
            // 输出按钮
            else if ($f['type'] == 'buttons') {
                $this->makeButtons($f['buttons'], $data);
            } 
            // 输出整理文本
            else if ($f['type'] == 'text') {
                if (isset($f['format']) && method_exists($this, $f['format'])) {
                    echo $this->$f['format']($data);
                }
            }
            echo '</td>';
        }
    }
    
    /**
     * [ 'text' => '按钮', 'url' => '', 'args' => '' ] 
     * @param type $buttons
     */
    protected function makeButtons($buttons, $data) {
        echo '<div class="btn-group btn-group-xs">';
        if (!is_array($buttons)) {
            if (method_exists($this, $buttons)) {
                $buttons = $this->$buttons($data);
            } else {
                throw new \Exception("Buttons type error!");
            }
        } 
        foreach ($buttons as $button) {
            $args = [];
            if (is_array($button['args'])) {
                foreach($button['args'] as $key => $val) {
                    if (is_numeric($key)) {
                        $args[$val] = $data[$val];
                    } else {
                        // 别名
                        $args[$key] = $data[$val];
                    }
                }
            } else {
                if (isset($button['args'])) {
                    $args[$button['args']] = $data[$button['args']];
                }
            }
            $class = 'btn btn-default';
            if (isset($button['class'])) {
                $class = $button['class'];
            }
            echo '<a class="'. $class . '" href="' . URL::TagA($button['url'], $args) . 
                '" >' . $button['text'] . "</a>";
        }
        echo '</div>';
    }
    
    protected function makePaginate() {
        $end = ceil($this->dataCount / $this->config['pageMax']);
        $cur = $this->getCurPaginate();
        $max = $end > $this->config['paginateView'] ? $this->config['paginateView'] : $end;

        $i = (($cur + $max / 2) > $end) ?
             ($end - $max + 1) :
             (($cur > $max / 2) ? ceil($cur - $max / 2) : 1);

        $start_link = $cur > 1 ? '?' . static::PageinateKey . '=1' : 'javascript:;';
        $end_link = $cur < $end ? '?' . static::PageinateKey . "={$end}" : 'javascript:;';

        echo '<nav>
                <ul class="pagination">
                  <li>
                    <a href="', $start_link , '" aria-label="Previous">
                      <span aria-hidden="true">首页</span>
                    </a>
                  </li>';

        for ($n = 0; $n < $max; $i++, $n++){
            $attr = '';
            $link = '?' . static::PageinateKey . "={$i}";
            if ($i == $cur) {
                $attr = 'class="active"';
                $link = "javascript:;";
            }
            echo "<li {$attr}><a href='{$link}'>{$i}</a></li>";
        }
        
        echo '    <li>
                    <a href="', $end_link,'" aria-label="Next">
                      <span aria-hidden="true">末页</span>
                    </a>
                  </li>';
        echo '<li><span>总计：', $this->dataCount, 
            ' 当前显示：', $this->config['pageMax']*($cur - 1),' - ',
            $this->config['pageMax']*$cur, '</span></li>
                </ul>
              </nav>';
    }
    
    public function getCurPaginate($returnLimit = false) {
        $end = ceil($this->dataCount / $this->config['pageMax']);
        $cur = isset($this->pdata[static::PageinateKey]) ? $this->pdata[static::PageinateKey] : 1;
        if ($cur > $end) {
            $cur = $end;
        }
        if ($cur < 1) {
            $cur = 1;
        }
        if ($returnLimit) {
            return ' ' . ($cur - 1) * $this->config['pageMax'] . ',' . $this->config['pageMax'];
        }
        return $cur;
    }

    public function trimData() {
        $this->dataPrepare();
        
        $data = $this->dataProvide();
        $viewField = $this->getViewField();
        if (empty($data)) {
            return [];
        }
        $rtn = [];
        foreach ($data as $n => $d) {
            $row = [];
            foreach ($viewField as $k => $f) {
                // 默认输出数据
                if (!isset($f['type'])) {
                    if (!isset($d[$k])) {
                        $row[$k] = '';
                    } else if (isset($f['format']) && method_exists($this, $f['format'])) {
                        $row[$k] = strip_tags($this->$f['format']($d));
                    } else {
                        $row[$k] = $d[$k];
                    }
                }
                // 输出整理文本
                else if ($f['type'] == 'text') {
                    if (isset($f['format']) && method_exists($this, $f['format'])) {
                        $row[$k] = strip_tags($this->$f['format']($d));
                    } else {
                        $row[$k] = '';
                    }
                }
            }
            $rtn[] = $row;
        }
        return $rtn;
    }

    public function getHeader() {
        $viewField = $this->getViewField();
        $ret = [];
        foreach ($viewField as $v) {
            if (!isset($v['type']) || $v['type'] == 'text' || isset($v['format'])) {
                $ret[] = $v['title'];
            }
        }
        return $ret;
    }

    public function dataProvide() {
        $model = new Model($this->tableName, $this->pkName);
        $where = $this->getWhere();
        $limit = '';
        if (isset($this->config['paginate']) && $this->config['paginate']) {
            $this->dataCount = $model->count($where);
            $limit = $this->getCurPaginate(TRUE);
        }

        //排序
        $sort = isset($this->config['sort']) ? $this->config['sort'] : '';
        return $model->getlist($where, $sort, $limit);
    }
    
    public function getWhere() {
        $picker = $this->getPicker();
        if (empty($picker)) {
            return [];
        }
        if (!isset($this->config['picker']) || !$this->config['picker']) {
            return [];
        }
        if (empty($this->pdata)) {
            $this->dataPrepare();
        }
        $where = [];
        foreach ($picker as $p) {
            if ($p['name'] != static::PageinateKey && isset($this->pdata[$p['name']]) && $this->pdata[$p['name']] !== '') {
                $where[$p['name']] = $this->pdata[$p['name']];
            }
        }

        //添加上额外的条件
        if (is_array($this->condition) && !empty($this->condition)) {
            $where = array_merge($where, $this->condition);
        }
        return $where;
    }
    
    protected function makeAttr($attr) {
        foreach($attr as $k => $v) {
            echo $k . '="' . $v . '" ';
        }
    }
}
