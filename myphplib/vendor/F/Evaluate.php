<?php

namespace F;

/**
 * Description of Evaluate
 *
 * @author QQQ
 */

class Evaluate {

	public $keys = [
		'如果' => '#COND#', 
		'其余' => '#DEFAULT#',
		'月流水' => '#MONTH#', 
		'日流水' => '#DAY#', 
		'周流水' => '#WEEK#',
		'大于' => '#>#', 
		'小于' => '#<#', 
		'等于' => '#=#', 
		'为' => '#THEN#',
	];

	public $var = ['MONTH', 'DAY'];

	public $segment = [];
    
    public $value = null;

	public function register($var) {
        if ($var == 'MONTH') {
            
        }
	}

	public function get($var) {
		return 6000;
	}

	public function exec() {
		foreach ($this->segment as $key => $value) {
			$value = $this->process($value);
			if ($value !== false) {
				return $value;
			}
		}
		return 0;
	}

	public function prepare($input) {
        $value = intval($input);
        if ($value > 0 && $value == $input) {
            $this->value = $value;
            return;
        }

		$s = preg_replace("/[a-z]/i", '', $input);
		$s = strtr($s, $this->keys);

		$s = preg_replace("/\p{Han}/u", '', $s);

		$s = strtr($s, [',' => '，']);
		$this->segment = explode('，', $s);
	}

	public function process($segment) {
        if ($this->value !== null) {
            return $this->value;
        }
		$a = explode('#', $segment);
		$a = array_filter($a);
		$cond = false;
		$then = false;
		$exp = [];
		while (!empty($a)) {
			$ch = array_shift($a);
			switch ($ch) {
				case 'COND':
					$cond = true;
					break;
				case 'THEN':
					$cond = false;
					$then = true;
					break;
				default:
					if ($cond) {
						$exp[] = $ch;
						if (in_array($ch, $this->var)) {
							$this->register($ch);
						}
					}
					if ($then) {
						$value = intval($ch);
					}
					break;
			}

		}
		// var_dump($exp, $value);

		if (empty($exp) || $this->express($exp)) {
			return $value;
		}
		return false;
	}

	 public function express($exp) {
		// 简单实现,单算符,需要扩展再重写
		if (count($exp) != 3) {
			return false;
		}
		if ($exp[1] == '>') {
			return $this->get($exp[0]) > intval($exp[2]);
		} else if ($exp[1] == '<') {
			return $this->get($exp[0]) < intval($exp[2]);
		} 
		return $this->get($exp[0]) == intval($exp[2]);
	}
}
