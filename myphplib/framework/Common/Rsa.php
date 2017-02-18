<?php

namespace Common;

/**
 * Description of Rsa
 *
 * @author QQQ
 */
class Rsa {

    public $publickey;
    public $privatekey;
    
    private $pubRes = false;
    private $priRes = false;

    /**
     * 
     * @param type $publickey
     * @param type $privatekey
     */
    public function __construct($publickey = false, $privatekey = false) {
        $this->publickey = $publickey;
        $this->privatekey = $privatekey;
        if ($this->publickey) {
            $this->pubRes = openssl_get_publickey($this->publickey);
        } 
        if ($this->privatekey) {
            $this->priRes = openssl_get_privatekey($this->privatekey);
        }
    }
    
    public function __destruct() {
        if ($this->pubRes) {
            openssl_free_key($this->pubRes);
        }
        if ($this->priRes) {
            openssl_free_key($this->priRes);
        }
    }
    
    /**
     * 格式化公钥字符串
     * @param type $pubstr
     */
    public static function TrimPub($pubstr, $width = 64) {
        // 如果起始为 ----- 则删除开头，如果结尾为 -----，则删除结尾
        $pubcontent = preg_replace('/-{5}[\w| ]+-{5}/', '', $pubstr);
        $pubkey = "-----BEGIN PUBLIC KEY-----\n";
        $pub = str_replace(["\r", "\n", "\t", ' '], '', $pubcontent);
        while ($pub) {
            $tmp = substr($pub, 0, $width);
            $pub = substr($pub, $width);
            $pubkey .= $tmp . "\n";
        }
        $pubkey .= "-----END PUBLIC KEY-----";
        return $pubkey;
    }

    /**
     * 公匙加密
     * @param $sourcestr
     * @return Description
     */
    public function publickey_encodeing($sourcestr) {
        $crypttext = '';
        if (openssl_public_encrypt($sourcestr, $crypttext, $this->pubRes, OPENSSL_PKCS1_PADDING)) {
            return $crypttext;
        }
        return false;
    }

    /**
     * 私匙解密
     * @param unknown_type $crypttext
     * @return type Description
     */
    public function privatekey_decodeing($crypttext) {
        $sourcestr = '';
        if (openssl_private_decrypt($crypttext, $sourcestr, $this->priRes, OPENSSL_PKCS1_PADDING)) {
            return $sourcestr;
        }
        return FALSE;
    }

    /**
     * 私匙加密
     * @param unknown_type $sourcestr
     */
    public function privatekey_encodeing($sourcestr) {
        $crypttext = '';
        if (openssl_private_encrypt($sourcestr, $crypttext, $this->priRes, OPENSSL_PKCS1_PADDING)) {
            return $crypttext;
        }
        return FALSE;
    }

    /**
     * 私钥签名
     * @param type $sourcestr
     * @return type
     */
    public function sign($sourcestr) {
        $signature = '';
        openssl_sign($sourcestr, $signature, $this->priRes);
        return $signature;
    }

    /**
     * 公钥验证
     * @param type $sourcestr
     * @param type $signature
     * @return type
     */
    public function verify($sourcestr, $signature) {
        $verify = openssl_verify($sourcestr, $signature, $this->pubRes);
        return $verify === 1;
    }

    /**
     * 公钥分段解密
     * @param string $data
     * @param int $rsa_bit
     * @return string
     */
    public function decrypt($data, $rsa_bit = 128) {
        $output = '';
        while ($data) {
            $out = '';
            $input = substr($data, 0, $rsa_bit);
            $data = substr($data, $rsa_bit);
            openssl_public_decrypt($input, $out, $this->pubRes);
            $output .= $out;
        }
        return $output;
    }

}
