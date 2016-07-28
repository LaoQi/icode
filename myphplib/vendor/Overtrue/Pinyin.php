<?php

namespace Overtrue;

/**
 * Description of Pinyin
 *
 * @author QQQ
 */

use InvalidArgumentException;

define('PINYIN_NONE', 'none');
define('PINYIN_ASCII', 'ascii');
define('PINYIN_UNICODE', 'unicode');

/**
 * Chinese to pinyin translator.
 *
 * @author    overtrue <i@overtrue.me>
 * @copyright 2015 overtrue <i@overtrue.me>
 *
 * @link      https://github.com/overtrue/pinyin
 * @link      http://overtrue.me
 */
class Pinyin
{
    const NONE = 'none';
    const ASCII = 'ascii';
    const UNICODE = 'unicode';
    
    /**
     * Words segment name.
     *
     * @var string
     */
    protected $segmentName = 'words_%s';

    protected static $surnames = null;
    protected static $dictionary = null;

    /**
     * Punctuations map.
     *
     * @var array
     */
    protected $punctuations = array(
        '，' => ',',
        '。' => '.',
        '！' => '!',
        '？' => '?',
        '：' => ':',
        '“' => '"',
        '”' => '"',
        '‘' => "'",
        '’' => "'",
    );

    /**
     * Constructor.
     */
    public function __construct()
    {
    }
    
    private function initSurnames() {
        if (self::$surnames !== NULL){
            return ;
        }
        $surnames = __DIR__ .'/data/surnames';

        if (file_exists($surnames)) {
            self::$surnames = (array) include $surnames;
        }
    }
    
    private function initWords() {
        if (self::$dictionary) {
            return ;
        }
        self::$dictionary = [];
        for ($i = 0; $i < 6; ++$i) {
            $segment =  __DIR__ .'/data/'.sprintf($this->segmentName, $i);

            if (file_exists($segment)) {
                self::$dictionary[] = (array) include $segment;
            }
        }
    }

    /**
     * Convert string to pinyin.
     *
     * @param string $string
     * @param string $option
     *
     * @return array
     */
    public function convert($string, $option = self::NONE)
    {
        $pinyin = $this->romanize($string);

        return $this->splitWords($pinyin, $option);
    }

    /**
     * Convert string (person name) to pinyin.
     *
     * @param string $stringName
     * @param string $option
     *
     * @return array
     */
    public function name($stringName, $option = self::NONE)
    {
        $pinyin = $this->romanize($stringName, true);

        return $this->splitWords($pinyin, $option);
    }

    /**
     * Return a pinyin permalink from string.
     *
     * @param string $string
     * @param string $delimiter
     *
     * @return string
     */
    public function permalink($string, $delimiter = '-')
    {
        if (!in_array($delimiter, array('_', '-', '.', ''), true)) {
            throw new InvalidArgumentException("Delimiter must be one of: '_', '-', '', '.'.");
        }

        return implode($delimiter, $this->convert($string, false));
    }
    
    /**
     * Return first letters.
     *
     * @param string $string
     * @param string $delimiter
     *
     * @return string
     */
    public function abbr($string, $delimiter = '')
    {
        $data = $this->convert($string, false);
        $ret = [];
        foreach ($data as $v) {
            $ret[] = $v[0];
        }
        unset($data, $v);
        return implode($delimiter, $ret);
    }

    /**
     * Chinese to pinyin sentense.
     *
     * @param string $sentence
     * @param string $withTone
     *
     * @return string
     */
    public function sentence($sentence, $withTone = false)
    {
        $marks = array_keys($this->punctuations);
        $punctuationsRegex = preg_quote(implode(array_merge($marks, $this->punctuations)), '/');
        $regex = '/[^üāēīōūǖáéíóúǘǎěǐǒǔǚàèìòùǜa-z0-9'.$punctuationsRegex.'\s_]+/iu';

        $pinyin = preg_replace($regex, '', $this->romanize($sentence));

        $punctuations = array_merge($this->punctuations, array("\t" => ' ', '  ' => ' '));
        $pinyin = trim(str_replace(array_keys($punctuations), $punctuations, $pinyin));

        return $withTone ? $pinyin : $this->format($pinyin, false);
    }

    /**
     * Preprocess.
     *
     * @param string $string
     *
     * @return string
     */
    protected function prepare($string)
    {
        $string = preg_replace_callback('/[a-z0-9_-]+/i', ['Overtrue\Pinyin', '_replace'], $string);
        return preg_replace("/[^\p{Han}\p{P}\p{Z}\p{M}\p{N}\p{L}\t]/u", '', $string);
    }
    
    static function _replace($matches) {
        return "\t".$matches[0];
    }

    /**
     * Convert Chinese to pinyin.
     *
     * @param string $str
     * @param bool   $isName
     *
     * @return string
     */
    protected function romanize($str, $isName = false)
    {
        $string = $this->prepare($str);
        if ($isName) {
            $string = $this->convertSurname($string);
        }
        $this->initWords();
        foreach (self::$dictionary as &$dictionary){
            $string = strtr($string, $dictionary);
        }

        return $string;
    }

    /**
     * Convert Chinese Surname to pinyin.
     *
     * @param string $string
     * @return string
     */
    protected function convertSurname($string) {
        $this->initSurnames();
        foreach (self::$surnames as $surname => &$pinyin) {
            if (strpos($string, $surname) === 0) {
                $string = $pinyin . mb_substr($string, mb_strlen($surname, 'UTF-8'), mb_strlen($string, 'UTF-8') - 1, 'UTF-8');
                break;
            }
        }
        return $string;
    }

    /**
     * Split pinyin string to words.
     *
     * @param string $pinyin
     * @param string $option
     *
     * @return array
     */
    public function splitWords($pinyin, $option)
    {
        $split = array_filter(preg_split('/[^üāēīōūǖáéíóúǘǎěǐǒǔǚàèìòùǜa-z\d]+/iu', $pinyin));

        if ($option !== self::UNICODE) {
            foreach ($split as $index => $pinyin) {
                $split[$index] = $this->format($pinyin, $option === self::ASCII);
            }
        }

        return array_values($split);
    }

    /**
     * Format.
     *
     * @param string $pinyin
     * @param bool   $tone
     *
     * @return string
     */
    protected function format($pinyin, $tone = false)
    {
        $replacements = array(
            'üē' => array('ue', 1), 'üé' => array('ue', 2), 'üě' => array('ue', 3), 'üè' => array('ue', 4),
            'ā' => array('a', 1), 'ē' => array('e', 1), 'ī' => array('i', 1), 'ō' => array('o', 1), 'ū' => array('u', 1), 'ǖ' => array('v', 1),
            'á' => array('a', 2), 'é' => array('e', 2), 'í' => array('i', 2), 'ó' => array('o', 2), 'ú' => array('u', 2), 'ǘ' => array('v', 2),
            'ǎ' => array('a', 3), 'ě' => array('e', 3), 'ǐ' => array('i', 3), 'ǒ' => array('o', 3), 'ǔ' => array('u', 3), 'ǚ' => array('v', 3),
            'à' => array('a', 4), 'è' => array('e', 4), 'ì' => array('i', 4), 'ò' => array('o', 4), 'ù' => array('u', 4), 'ǜ' => array('v', 4),
        );

        foreach ($replacements as $unicde => $replacements) {
            if (false !== strpos($pinyin, $unicde)) {
                $pinyin = str_replace($unicde, $replacements[0], $pinyin).($tone ? $replacements[1] : '');
            }
        }

        return $pinyin;
    }
    
    
}
