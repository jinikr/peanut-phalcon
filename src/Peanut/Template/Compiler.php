<?php
namespace Peanut\Template;

class Compiler
{
    /**
     * @var array
     */
    private $brace = [];
    /**
     * @var string
     */
    private $loopkey = 'A';
    /**
     * @var int
     */
    private $permission = 0777;
    /**
     * @var bool
     */
    private $phpengine = false;

    public function __construct()
    {
        $functions           = get_defined_functions();
        $this->all_functions = array_merge(
            $functions['internal'],
            $functions['user'],
            ['isset', 'empty', 'eval', 'list', 'array', 'include', 'require', 'include_once', 'require_once']
        );
    }

    /**
     * @param  $tpl
     * @param  $fid
     * @param  $tplPath
     * @param  $cplPath
     * @param  $cplHead
     * @return mixed
     */
    public function execute($tpl, $fid, $tplPath, $cplPath, $cplHead)
    {
        $this->permission = $tpl->permission;
        $this->phpengine  = $tpl->phpengine;

        if (!@is_file($cplPath)) {
            $dirs = explode('/', $cplPath);

            $path         = '';
            $once_checked = false;

            for ($i = 0, $s = count($dirs) - 1; $i < $s; $i++) {
                $path .= $dirs[$i].'/';

                if ($once_checked or !is_dir($path) and $once_checked = true) {
                    if (false === mkdir($path)) {
                        throw new Compiler\Exception('cannot create compile directory <b>'.$path.'</b>');
                    }

                    @chmod($path, $this->permission);
                }
            }
        }

        // get template
        $source = '';

        if ($source_size = filesize($tplPath)) {
            $fpTpl  = fopen($tplPath, 'rb');
            $source = fread($fpTpl, $source_size);
            fclose($fpTpl);
        }

        $gt_than_or_eq_to_5_4 = defined('PHP_MAJOR_VERSION') and 5.4 <= (float) (PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION);
        $php_tag              = '<\?php|(?<!`)\?>';

        if (ini_get('short_open_tag')) {
            $php_tag .= '|<\?(?!`)';
        } elseif ($gt_than_or_eq_to_5_4) {
            $php_tag .= '|<\?=';
        }

        if (ini_get('asp_tags')) {
            $php_tag .= '|<%(?!`)|(?<!`)%>';
        }

        $php_tag .= '|';

        /*
            // {{를 쓰는 다른 템플릿과 구분을 위해서, 대신 {test}와 같은 표현을 하기 위해
            // {{=test}}와 같은 구문을 사용할수 없다. 일단 제외
            //$tokens     = preg_split('/('.$php_tag.'<!--{?{(?!`)|(?<!`)}?}-->|{?{(?!`)|(?<!`)}?})/i', $source, -1, PREG_SPLIT_DELIM_CAPTURE);
        */
        $tokens = preg_split('/('.$php_tag.'<!--{(?!`)|(?<!`)}-->|{(?!`)|(?<!`)})/i', $source, -1, PREG_SPLIT_DELIM_CAPTURE);

        $line       = 0;
        $is_open    = 0;
        $new_tokens = [];

        for ($_index = 0, $s = count($tokens); $_index < $s; $_index++) {
            $line = substr_count(implode('', $new_tokens), chr(10)) + 1;

            $new_tokens[$_index] = $tokens[$_index];

            switch (strtolower($tokens[$_index])) {
                case '<?php':
                case '<?=':
                case '<?':
                case '<%':

                    if (false == $this->phpengine) {
                        $new_tokens[$_index] = str_replace('<', '&lt;', $tokens[$_index]);
                    } else {
                        $new_tokens[$_index] = $tokens[$_index];
                    }

                    break;
                case '?>':
                case '%>':

                    if (false == $this->phpengine) {
                        $new_tokens[$_index] = str_replace('>', '&gt', $tokens[$_index]);
                    } else {
                        $new_tokens[$_index] = $tokens[$_index];
                    }

                    break;
                case '<!--{':
                case '{':
                    $is_open = $_index;
                    break;
                case '}-->':
                case '}':

                    if ($is_open !== $_index - 2) {
                        break; // switch exit
                    }

                    $result = $this->compileStatement($tokens[$_index - 1], $line);

                    if (1 == $result[0] || false === $result[1]) {
                        $new_tokens[$_index - 1] = $tokens[$_index - 1];
                    } elseif (2 == $result[0]) {
                        $new_tokens[$is_open]    = '<?php ';
                        $new_tokens[$_index - 1] = $result[1];
                        $new_tokens[$_index]     = '?>';
                    }

                    $is_open = 0;
                    break;
                default:
            }
        }

        if (count($this->brace)) {
            array_pop($this->brace);
            $c = end($this->brace);
            throw new Compiler\Exception('error line '.$c[1]);
        }

        $source = implode('', $new_tokens);
        $this->saveResult($cplPath, $source, $cplHead, '*/ ?>');
    }

    /**
     * @param $cplPath
     * @param $source
     * @param $cplHead
     * @param $initCode
     */
    private function saveResult($cplPath, $source, $cplHead, $initCode)
    {
        $source_size = strlen($cplHead) + strlen($initCode) + strlen($source) + 9;

        $source = $cplHead.str_pad($source_size, 9, '0', STR_PAD_LEFT).$initCode.$source;

        $fpCpl = fopen($cplPath, 'wb');

        if (false === $fpCpl) {
            throw new Compiler\Exception('cannot write compiled file "<b>'.$cplPath.'</b>"');
        }

        fwrite($fpCpl, $source);
        fclose($fpCpl);

        if (filesize($cplPath) != strlen($source)) {
            @unlink($cplPath);
            throw new Compiler\Exception('Problem by concurrent access. Just retry after some seconds. "<b>'.$cplPath.'</b>"');
        }
    }

    /**
     * @param  $statement
     * @param  $line
     * @return mixed
     */
    public function compileStatement($statement, $line)
    {
        $org       = $statement;
        $statement = trim($statement);

        $match = [];
        preg_match('/^(\\\\*)\s*(:\?|\/@|\/\?|[=#@?:\/+])?(.*)$/s', $statement, $match);

        if ($match[1]) {
            // escape
            $result = [1, substr($org, 1)];
        } else {
            switch ($match[2]) {
                case '@':
                    $this->brace[] = ['if', $line];
                    $this->brace[] = ['loop', $line];
                    $result        = [2, $this->compileLoop($statement, $line)];
                    break;
                case '#':
                    if (1 === preg_match('`^#([\s+])?([a-zA-Z0-9\-_\.]+)$`', $statement)) {
                        $result = [2, $this->compileDefine($statement, $line)];
                    } else {
                        $result = [1, $statement];
                    }

                    break;
                case ':':
                    if (!count($this->brace)) {
                        throw new Compiler\Exception('error line '.$line);
                    }

                    $result = [2, $this->compileElse($statement, $line)];
                    break;

                case '/':
                    if (!count($this->brace)) {
                        throw new Compiler\Exception('not if/loop error line '.$line);
                    }

                    array_pop($this->brace);
                    array_pop($this->brace);

                    $result = [2, $this->compileClose($statement, $line)];
                    break;
                case '=':
                    $result = [2, $this->compileEcho($statement, $line)];
                    break;
                case '?':
                    $this->brace[] = ['if', $line];
                    $this->brace[] = ['if', $line];
                    $result        = [2, $this->compileIf($statement, $line)];
                    break;
                case ':?':
                    if (!count($this->brace)) {
                        throw new Compiler\Exception('error line '.$line);
                    }

//    $this->brace[] = ['elseif', $line];
                    //    $this->brace[] = ['if', $line];
                    $result = [2, $this->compileElseif($statement, $line)];
                    break;
                default:

                    $compileString = $this->compileDefault($statement, $line);

                    if (false === $compileString) {
                        $result = [1, $org];
                    } else {
                        $result = [2, $compileString.';'];
                    }

                    break;
            }
        }

        return $result;
    }

    /**
     * @param $statement
     * @param $line
     */
    public function compileDefine($statement, $line)
    {
        return "echo self::show('".trim(substr($statement, 1))."')";
    }

    /**
     * @param  $statement
     * @param  $line
     * @return mixed
     */
    public function compileDefault($statement, $line)
    {
        return $this->tokenizer($statement, $line);
    }

    /**
     * @param  $statement
     * @param  $line
     * @return null
     */
    public function compileLoop($statement, $line)
    {
        $tokenizer = explode('=', $this->tokenizer(substr($statement, 1), $line), 2);

        if (isset($tokenizer[0]) == false || isset($tokenizer[1]) == false) {
            throw new Compiler\Exception('Parse error: syntax error, loop는 {@row = array}...{/} 로 사용해주세요. line '.$line);
        }

        list($loop, $array) = $tokenizer;

        $loopValueName  = trim($loop);
        $loopKey        = $this->loopkey++;
        $loopArrayName  = '$_a'.$loopKey;
        $loopIndexName  = '$_i'.$loopKey;
        $loopSizeName   = '$_s'.$loopKey;
        $loopKeyName    = '$_k'.$loopKey;
        $loop_ValueName = '$_j'.$loopKey;

        return $loopArrayName.'='.$array.';'
            .$loopIndexName.'=-1;'
            .'if(is_array('.$loopArrayName.')&&('.$loopSizeName.'=count('.$loopArrayName.'))'.'){'
            .'foreach('.$loopArrayName.' as '.$loopKeyName.'=>'.$loopValueName.'){'
            .$loop_ValueName.'='.$loopValueName.';'
            .'if(false == is_array('.$loopValueName.')) {'
            .$loopValueName.'=(array)'.$loopValueName.';'
            .'}'
            .$loopIndexName.'++;'
            .$loopValueName.'[\'index_\']='.$loopIndexName.';'
            .$loopValueName.'[\'size_\']='.$loopSizeName.';'
            .$loopValueName.'[\'key_\']='.$loopKeyName.';'
            .$loopValueName.'[\'value_\']='.$loop_ValueName.';'
            .$loopValueName.'[\'last_\']=('.$loopValueName.'[\'size_\']=='.$loopValueName.'[\'index_\']+1);';
    }

    /**
     * @param $statement
     * @param $line
     */
    public function compileIf($statement, $line)
    {
        return 'if('.$this->tokenizer(substr($statement, 1), $line).'){{';
    }

    /**
     * @param $statement
     * @param $line
     */
    public function compileEcho($statement, $line)
    {
        $result = $this->tokenizer(substr($statement, 1), $line);

        if (false === $result) {
            return false;
        } else {
            return 'echo '.$result.';';
        }
    }

    /**
     * @param $statement
     * @param $line
     */
    public function compileElse($statement, $line)
    {
        return '}}else{{'.$this->tokenizer(substr($statement, 1), $line);
    }

    /**
     * @param $statement
     * @param $line
     */
    public function compileElseif($statement, $line)
    {
        return '}}else if('.$this->tokenizer(substr($statement, 2), $line).'){{';
    }

    /**
     * @param $statement
     * @param $line
     */
    public function compileClose($statement, $line)
    {
        return '}}'.$this->tokenizer(substr($statement, 1), $line);
    }

    /**
     * @param $statement
     * @param $line
     */
    public function compileCloseIf($statement, $line)
    {
        return '}}'.$this->tokenizer(substr($statement, 2), $line);
    }

    /**
     * @param $statement
     * @param $line
     */
    public function compileCloseLoop($statement, $line)
    {
        return '}}'.$this->tokenizer(substr($statement, 2), $line);
    }

    /**
     * @param  $source
     * @param  $line
     * @return mixed
     */
    public function tokenizer($source, $line)
    {
        $expression = $source;
        $token      = [];

        for ($i = 0; strlen($expression); $expression = substr($expression, strlen($m[0])), $i++) {
            //

            preg_match('/^
            (:P<unknown>(?:\.\s*)+)
            |(?P<number>(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+\-]?\d+)?)
            |(?P<array_keyword>array)
            |(?P<assoc_array>=\>)
            |(?P<object_sign>-\>)
            |(?P<namespace_sigh>\\\)
            |(?P<static_object_sign>::)
            |(?P<compare>===|!==|<<|>>|<=|>=|==|!=|&&|\|\||<|>)
            |(?P<assign>\=)
            |(?P<string_concat>\.)
            |(?P<left_parenthesis>\()
            |(?P<right_parenthesis>\))
            |(?P<left_bracket>\[)
            |(?P<right_bracket>\])
            |(?P<comma>,)
            |(?:(?P<string>[A-Z_a-z\x7f-\xff][\w\x7f-\xff]*)\s*)
            |(?<quote>(?:"(?:\\\\.|[^"])*")|(?:\'(?:\\\\.|[^\'])*\'))
            |(?P<double_operator>\+\+|--)
            |(?P<operator>\+|\-|\*|\/|%|&|\^|~|\!|\|)
            |(?P<not_support>\?|:)
            |(?P<whitespace>\s+)
            |(?P<dollar>\$)
            |(?P<semi_colon>;)
            |(?P<not_match>.+)
            /ix', $expression, $m);

            $r = ['org' => '', 'name' => '', 'value' => ''];

            foreach ($m as $key => $value) {
                if (is_numeric($key)) {
                    continue;
                }

                if (strlen($value)) {
                    $v = trim($value);

                    if ('number' == $key && '.' == $v[0]) {
                        $token[] = ['org' => '.', 'name' => 'number_concat', 'value' => '.'];
                        $r       = ['org' => substr($v, 1), 'name' => 'string_number', 'value' => substr($v, 1)];
                    } else {
                        $r = ['org' => $m[0], 'name' => $key, 'value' => $v];
                    }

                    break;
                }
            }

            if ('whitespace' != $r['name'] && 'enter' != $r['name']) {
                $token[] = $r;
            }
        }

        $xpr    = '';
        $stat   = [];
        $assign = 0;
        $org    = '';

        foreach ($token as $key => &$current) {
            if ('semi_colon' == $current['name']) {
                return false;
            }

            $current['key'] = $key;

            if (true === isset($token[$key - 1])) {
                $prev = $token[$key - 1];
            } else {
                $prev = ['org' => '', 'name' => '', 'value' => ''];
            }

            $org .= $current['org'];

            if (true === isset($token[$key + 1])) {
                $next = $token[$key + 1];
            } else {
                $next = ['org' => '', 'name' => '', 'value' => ''];
            }

// 마지막이 종결되지 않음
            if (!$next['name'] && false === in_array($current['name'], ['string', 'number', 'string_number', 'right_bracket', 'right_parenthesis', 'double_operator', 'quote'])) {
                //pr($current);
                return false;
                throw new Compiler\Exception('parse error : line '.$line.' '.$current['org']);
            }

            switch ($current['name']) {
                case 'string':
                    if (false === in_array($prev['name'], ['', 'left_parenthesis', 'left_bracket', 'assign', 'object_sign', 'static_object_sign', 'namespace_sigh', 'double_operator', 'operator', 'assoc_array', 'compare', 'quote_number_concat', 'assign', 'string_concat', 'comma'])) {
                        return false; //
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
// 클로저를 허용하지 않음. 그래서 string_concat 비교 보다 우선순위가 높음
                        if (true === in_array($next['name'], ['left_parenthesis', 'static_object_sign', 'namespace_sigh'])) {
                            if ('string_concat' == $prev['name']) {
                                throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org'].$next['org']);
                            } else {
                                if ('_' == $current['value']) {
                                    //$xpr .= '\\limepie\\'.$current['value'];
                                    $xpr .= $current['value'];
                                } else {
                                    $xpr .= $current['value'];
                                }
                            }
                        } elseif ('object_sign' == $prev['name']) {
                            $xpr .= $current['value'];
                        } elseif ('static_object_sign' == $prev['name']) {
                            $xpr .= '$'.$current['value'];
                        } elseif ('namespace_sigh' == $prev['name']) {
                            $xpr .= $current['value'];
                        } elseif ('string_concat' == $prev['name']) {
                            $xpr .= '[\''.$current['value'].'\']';
                        } else {
                            if (true === in_array(strtolower($current['value']), ['true', 'false', 'null'])) {
                                $xpr .= $current['value'];
                            } elseif (preg_match('#__([a-zA-Z_]+)__#', $current['value'])) {
                                $xpr .= $current['value']; // 처음
                            } else {
                                $xpr .= '$'.$current['value']; // 처음
                            }
                        }
                    }

                    break;
                case 'dollar':
                    throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    break;
                case 'not_support':
                    throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    break;
                case 'not_match':
                    throw new Compiler\Exception('parse error : line '.$line.' '.$current['org']);
                    break;
                case 'assoc_array':
                    $last_stat = array_pop($stat);

                    if ($last_stat
                        && $last_stat['key'] > 0
                        && true === in_array($token[$last_stat['key'] - 1]['name'], ['string'])
                    ) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    }

                    $stat[] = $last_stat;

                    if (false === in_array($prev['name'], ['number', 'string', 'quote', 'right_parenthesis', 'right_bracket'])) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
                        $xpr .= $current['value'];
                    }

                    break;
                case 'quote':
                    if (false === in_array($prev['name'], ['', 'left_parenthesis', 'left_bracket', 'comma', 'compare', 'assoc_array', 'operator', 'quote_number_concat'])) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
                        $xpr .= $current['value'];
                    }

                    break;
                case 'number':
                    $last_stat = array_pop($stat);

                    if ('assoc_array' == $prev['name']) {
                    } elseif ($last_stat
                        && $last_stat['key'] > 1
                        && 'assoc_array' == $prev['name'] && false === in_array($token[$last_stat['key'] - 1]['name'], ['left_bracket'])
                    ) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    }

                    $stat[] = $last_stat;

                    if (false === in_array($prev['name'], ['', 'left_bracket', 'left_parenthesis', 'comma', 'compare', 'operator', 'assign', 'assoc_array', 'string', 'right_bracket', 'number_concat', 'string_concat', 'quote_number_concat'])) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
                        if ('quote_number_concat' == $prev['name']) {
                            $xpr .= "'".$current['value']."'";
                            $current['name'] = 'quote';
                        } elseif (true === in_array($prev['name'], ['string', 'right_bracket', 'number_concat'])) {
                            $xpr .= '['.$current['value'].']';
                        } else {
                            $xpr .= $current['value'];
                        }
                    }

                    break;
                case 'string_number':
                    if (false === in_array($prev['name'], ['right_bracket', 'number_concat'])) {
//'string',
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
                        $xpr .= '['.$current['value'].']';
                    }

                    break;
                case 'number_concat':
                    if (false === in_array($prev['name'], ['string', 'string_number', 'right_bracket'])) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
                    }

                    break;
                case 'double_operator':
                    if (false === in_array($prev['name'], ['string', 'number', 'string_number', 'assign'])) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
                        $xpr .= $current['value'];
                    }

                    break;
                case 'object_sign':
                    if (false === in_array($prev['name'], ['string', 'right_parenthesis'])) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
                        $xpr .= $current['value'];
                    }

                    break;
                case 'namespace_sigh':
                    if (false === in_array($prev['name'], ['string', 'assign', ''])) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
                        $xpr .= $current['value'];
                    }

                    break;
                case 'static_object_sign':
                    if (false === in_array($prev['name'], ['string', ''])) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
                        $xpr .= $current['value'];
                    }

                    break;
                case 'operator':
                    if (false === in_array($prev['name'], ['', 'right_parenthesis', 'right_bracket', 'number', 'string', 'string_number', 'quote', 'assign'])) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
// + 이지만 앞이나 뒤가 quote라면 + -> .으로 바꾼다. 지금의 name또한 변경한다.
                        if ('+' == $current['value'] && ('quote' == $prev['name'] || 'quote' == $next['name'])) {
                            $xpr .= '.';
                            $current['name'] = 'quote_number_concat';
                        } else {
                            $xpr .= $current['value'];
                        }
                    }

                    break;
                case 'compare':
                    if (false === in_array($prev['name'], ['number', 'string', 'string_number', 'assign', 'left_parenthesis', 'left_bracket', 'quote', 'right_parenthesis'])) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
                        $xpr .= $current['value'];
                    }

                    break;
                case 'assign':
                    $assign++;

                    if ($assign > 1) {
                        // $test = $ret = ... 와 같이 여러 변수를 사용하지 못하는 제약 조건
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } elseif (false === in_array($prev['name'], ['right_bracket', 'string', 'operator'])) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
// = 앞에는 일부의 연산자만 허용된다. +=, -=...
                        if ('operator' == $prev['name'] && false === in_array($prev['value'], ['+', '-', '*', '/', '%', '^', '!'])) {
                            throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                        }

                        $xpr .= $current['value'];
                    }

                    break;
                case 'left_bracket':
                    $stat[] = $current;
                    if (false === in_array($prev['name'], ['', 'assign', 'left_bracket', 'right_bracket', 'comma', 'left_parenthesis', 'string', 'string_number'])) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
                        $xpr .= $current['value'];
                    }

                    break;
                case 'right_bracket':
                    $last_stat = array_pop($stat);
                    if ('left_bracket' != $last_stat['name']) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    }

                    if (false === in_array($prev['name'], ['quote', 'left_bracket', 'right_parenthesis', 'string', 'number', 'string_number', 'right_bracket'])) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
                        $xpr .= $current['value'];
                    }

                    break;
                case 'array_keyword':
                    if (false === in_array($prev['name'], ['', 'compare', 'operator', 'left_parenthesis', 'left_bracket', 'comma', 'assign'])) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
                        $xpr .= $current['value'];
                    }

                    break;
                case 'left_parenthesis':
                    $stat[] = $current;
                    if (false === in_array($prev['name'], ['', 'quote_number_concat', 'operator', 'compare', 'assoc_array', 'left_parenthesis', 'left_bracket', 'array_keyword', 'string', 'assign'])) {
//, 'string_number' ->d.3.a() -> ->d[3]['a']() 제외
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
                        $xpr .= $current['value'];
                    }

                    break;
                case 'right_parenthesis':
                    $last_stat = array_pop($stat);

                    if ('left_parenthesis' != $last_stat['name']) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    }

                    if (false === in_array($prev['name'], ['left_parenthesis', 'right_bracket', 'right_parenthesis', 'string', 'number', 'string_number', 'quote'])) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
                        $xpr .= $current['value'];
                    }

                    break;
                case 'comma':
                    $last_stat = array_pop($stat);

                    if ($last_stat['name'] && 'left_bracket' == $last_stat['name'] && $last_stat['key'] > 0) {
// ][ ,] 면 배열키이므로 ,가 있으면 안됨
                        if (in_array($token[$last_stat['key'] - 1]['name'], ['right_bracket', 'string'])) {
                            throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                        }
                    }

// 배열이나 인자 속이 아니면 오류
                    if (false === in_array($last_stat['name'], ['left_parenthesis', 'left_bracket'])) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    }

                    $stat[] = $last_stat;
                    if (false === in_array($prev['name'], ['quote', 'string', 'number', 'string_number', 'right_parenthesis', 'right_bracket'])) {
                        throw new Compiler\Exception('parse error : line '.$line.' '.$prev['org'].$current['org']);
                    } else {
                        $xpr .= $current['value'];
                    }

                    break;
            }
        }

        if (count($stat)) {
            $last_stat = array_pop($stat);
            if ('left_parenthesis' == $last_stat['name']) {
                throw new Compiler\Exception('parse error : line '.$line.' '.$current['org']);
            } elseif ('left_bracket' == $last_stat['name']) {
                throw new Compiler\Exception('parse error : line '.$line.' '.$current['org']);
            }
        }

        return $xpr;
    }
}

namespace Peanut\Template\Compiler;

class Exception extends \Exception
{
    /**
     * @param $error
     */
    public function __construct($e)
    {
        parent::__construct($e);
    }
}
