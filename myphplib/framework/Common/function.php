<?php

/**
 * 常用操作
 */

/**
 * 字符串首判断
 * @param type $haystack
 * @param type $needle
 * @return type
 */
function startsWith($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

/**
 * 字符串尾判断
 * @param type $haystack
 * @param type $needle
 * @return type
 */
function endsWith($haystack, $needle) {
    $length = strlen($needle);
    $start = $length * -1; //negative
    return (substr($haystack, $start, $length) === $needle);
}

/**
 * 参数校验
 * @param type $args
 * @param type $argName
 * @return boolean
 */
function CheckParams($args, $argName) {
    foreach($argName as $n) {
        if (!isset($args[$n])) {
            return false;
        }
    }
    return true;
}
