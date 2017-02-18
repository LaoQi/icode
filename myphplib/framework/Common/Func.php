<?php

namespace Common;

/**
 * 常用方法
 *
 * @author QQQ
 */
class Func {
    
    /**
     * json输出
     * @param array $data
     */
    public static function ReturnJson($data) {
        header('Content-Type:application/json; charset=utf-8');
        if (is_array($data)) {
            echo json_encode($data);
        } elseif (is_string($data)) {
            echo $data;
        }
        exit();
    }
    
    /**
     * 对数据进行URL解码，先`=` 补位， 替换 '-_', '+/' 再做base64解码
     * @param string $data 要解码的数据
     * @return mixed 成功返回数据，失败返回false
     */
    public static function Base64url($data) {
        $raw_data = self::Base64urlDecode($data);
        // 如果不存在=，一定为非键值对
        if (strpos($raw_data, '=') === false) {
            return false;
        }
        $k_arr = explode('&', $raw_data);
        $rtn = [];
        foreach ($k_arr as $v) {
            $unit = explode('=', $v);
            $rtn[$unit[0]] = $unit[1];
        }
        return $rtn;
    }
    
    /**
     * 将键值对数据做url safe的编码操作
     * @param string $data 要解码的数据
     * @return str 成功返回数据，失败返回false
     */
    public static function Base64KVEncode($data) {
        $tmp = [];
        foreach ($data as $k => $v) {
            $tmp[] = $k . '=' . $v;
        }
        $raw_data = implode('&', $tmp);
        return self::Base64urlEncode($raw_data);
    }
    
    /**
     * 将键值对数据做url safe的解码操作
     * Base64KVDecode 别名
     * @param string $data 要解码的数据
     * @return Mixed 成功返回数据，失败返回false
     */
    public static function Base64KVDecode($data) {
        return self::Base64url($data);
    }
    
    /**
     * 做url safe的base64解码
     * @param string $data
     * @return string
     */
    public static function Base64urlDecode($data) {
        // 一定是4的倍数进行`=`补位
        $pad_data = str_pad(
            strtr($data, '-_', '+/'), 
            strlen($data) % 4, '=', STR_PAD_RIGHT);
        $raw_data = base64_decode($pad_data);
        return $raw_data;
    }
    
    /**
     * 做url safe的base64编码
     * @param string $data
     * @return string
     */
    public static function Base64urlEncode($data) {
        $str = base64_encode($data);
        $base = trim(strtr($str, '+/', '-_'), '=');
        return $base;
    }
    
    /**
     * Get 方式请求
     * @param string $url
     * @param array $data
     * @return string
     */
    public static function HttpGet($url, $data) {
        $params = strpos($url, '?') !== false ? '&' : '?';
        $params .= http_build_query($data);
        return file_get_contents($url . $params);
    }
    
    /**
     * Post 方式请求
     * @param string $url
     * @param array $data
     * @return string
     */
    public static function HttpPost($url, $data) {
        $ch = curl_init();//初始化curl
        curl_setopt($ch,CURLOPT_URL, $url);
        if (substr($url, 0, 5) === 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);	// 不对证书检查
//            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);	// 从证书中检查SSL加密算法是否存在 
        } 
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);		// 设置超时限制防止死循环
        $out = curl_exec($ch);
        curl_close($ch);
        return $out;
    }
    
    /**
     * 进一步的http请求
     * @param string $url
     * @param array $post
     * @return array [ 'code', 'header', 'body' ]
     */
    public static function HttpRequest($url, $post = [], $timeout = 10) {
        $ch = curl_init();//初始化curl
        curl_setopt($ch,CURLOPT_URL, $url);
        // 出现请求失败的bug，增加http头。
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);	// 不对证书检查
        if (substr($url, 0, 5) === 'https') {
//            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);	// 从证书中检查SSL加密算法是否存在 
        } 
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);		// 设置超时限制防止死循环
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        return ['code' => $code, 
            'info' => $info, 
            'body' => $body, 
            'error' => $error, 
            'errno' => $errno];
    }
    
    /**
     * 获取请求来源IP
     * @return string
     */
    public static function GetIP() {
        $realip = '127.0.0.1';
        if (isset($_SERVER)){
            if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])){
                $realip = $_SERVER["HTTP_X_FORWARDED_FOR"];
            } else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
                $realip = $_SERVER["HTTP_CLIENT_IP"];
            } else {
                $realip = $_SERVER["REMOTE_ADDR"];
            }
        }
        return $realip;
    }
    
    /**
     * 生成16位uid
     * @param int $channel_id
     * @param string $app_id
     * @param string $cuid
     * @return string
     */    
    public static function Uid($channel_id, $app_id, $cuid) {
        $str = sprintf("%s_%s_%s", $channel_id, $app_id, $cuid);
        return strtolower(substr(md5($str), 8, 16));
    }
    
    /**
     * 参数校验
     * @param array $args
     * @param array $argName
     * @return boolean
     */
    public static function CheckParams($args, $argName) {
        foreach($argName as $n) {
            if (!isset($args[$n])) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * 严格校验参数
     * @param array $args
     * @param array $argName
     * @return boolean
     */
    public static function StrictCheckParams($args, $argName) {
        foreach ($argName as $n) {
            if (!isset($args[$n]) || $args[$n] == '' ) {
                return false;
            }
        }
        return true;
    }
    
    const KEY = 'e10adc3949ba59abbe56e057f20f883e';
    
    /**
     * 简单的token加密编码方法
     * 对字符串data按位与$key异或，进行base64编码方便传输。反转base64顺序
     * @param string $data
     */
    public static function TokenEncode($data) {
        // 循环key得到可以异或的字符串
        $datalen = strlen($data);
        $repeat = ceil($datalen/32);
        $key = str_repeat(self::KEY, $repeat);
        $bin = $key ^ $data;
        $str = self::Base64urlEncode($bin);
        $arr = array_reverse(str_split($str, 4));
        return implode('', $arr);
    }
    
    /**
     * 简单的token解码方法
     * 对字符串data补位反转后，base64解码，按位异或key得到原始数据
     * @param string $data
     * @return string
     */
    public static function TokenDecode($data) {
        $datalen = strlen($data);
        $end = '';
        $pad = $datalen%4;
        if ($pad !== 0) {
            $end = substr($data, 0, $pad);
            $data = substr($data, $pad);
        }
        $arr = array_reverse(str_split($data, 4));
        $arr[] = $end;
        $base = implode('', $arr);
        $raw_data = self::Base64urlDecode($base);
        
        if (!$raw_data) {
            return FALSE;
        }
        $rawlen = strlen($raw_data);
        $repeat = ceil($rawlen/32);
        $key = str_repeat(self::KEY, $repeat);
        $out = $key ^ $raw_data;
        
        return $out;
    }
    
    /**
    * 字符串首判断
    * @param string $haystack
    * @param string $needle
    * @return string
    */
   public static function startsWith($haystack, $needle) {
       $length = strlen($needle);
       return (substr($haystack, 0, $length) === $needle);
   }
   
   /**
    * 整理从数据库取出的原始json字符串
    * 去除转义字符
    * @param string $jsonString
    * @return string
    */
   public static function trimDbJSON($jsonString) {
       $str = str_replace(
           ['\\t', '\\n', '\\r', '\r\n', '\t', '\n', '\r', '\\/'], 
           ["\t", "\n", "\r", "\n", "\t", "\n", "\r", '/'], 
           $jsonString);
       $json = stripslashes($str);
       return $json;
   }
   
   /**
    * 解码从数据库取出的json字符串
    * @param string $jsonString
    * @return array
    */
   public static function decodeDbJson($jsonString) {
       $json = stripslashes($jsonString);
//       $str = str_replace(
//           ['\t', '\n', '\r', '\/'], 
//           ["\t", "\n", "\r", '/'], 
//           $jsonString);
       return json_decode($json, true);
   }
   
   /**
    * 从给定数组筛选
    * @param Array $input
    * @param Array $keys
    * @param boolean $ignoreEmpty
    * @return Array
    */
   public static function filterArray($input, $keys, $ignoreEmpty = false) {
       $rtn = [];
       foreach ($keys as $k) {
           if (isset($input[$k])) {
               if ($ignoreEmpty && empty($input[$k])) {
                   continue;
               }
               $rtn[$k] = $input[$k];
           }
       }
       return $rtn;
   }
   
   /**
    * 返回键值对字符串
    * @param Array $input
    * @return string
    */
   public static function buildKVString($input) {
       $tmp = [];
       foreach ($input as $k => $v) {
           $tmp[] = $k . '=' . $v;
       }
       return implode($tmp, '&');
   }

   /**
    * 整理textarea的返回值
    * @param string $string
    * @return string 
    */
//   public static function trimTextarea($string) {
//       return $string;
//   }
}
