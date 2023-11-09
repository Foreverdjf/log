<?php

namespace forever\log\Neo\Http;

/*
 * Ways of cleaning input. Should be mostly self-explanatory.
 */
use Neo\NeoString;
use Neo\Validate\XssClean;

// no change
define('TYPE_NOCLEAN', 0);
// force boolean
define('TYPE_BOOL', 1);
// force integer
define('TYPE_INT', 2);
// force unsigned integer
define('TYPE_UINT', 3);
// force number
define('TYPE_NUM', 4);
// force unsigned number
define('TYPE_UNUM', 5);
// force unix datestamp (unsigned integer)
define('TYPE_UNIXTIME', 6);
// force trimmed string
define('TYPE_STR', 7);
// force string - no trim
define('TYPE_NOTRIM', 8);
// force trimmed string with HTML made safe
define('TYPE_NOHTML', 9);
// force array
define('TYPE_ARRAY', 10);
// force file
define('TYPE_FILE', 11);
// force binary string
define('TYPE_BINARY', 12);
// force trimmed string with HTML made safe if determined to be unsafe
define('TYPE_NOHTMLCOND', 13);
// xss safe filter, remove some dangerous script and html tags
define('TYPE_CLEAN_XSS', 14);

define('TYPE_ARRAY_BOOL', 101);
define('TYPE_ARRAY_INT', 102);
define('TYPE_ARRAY_UINT', 103);
define('TYPE_ARRAY_NUM', 104);
define('TYPE_ARRAY_UNUM', 105);
define('TYPE_ARRAY_UNIXTIME', 106);
define('TYPE_ARRAY_STR', 107);
define('TYPE_ARRAY_NOTRIM', 108);
define('TYPE_ARRAY_NOHTML', 109);
define('TYPE_ARRAY_ARRAY', 110);
// An array of "Files" behaves differently than other <input> arrays. TYPE_FILE handles both types.
define('TYPE_ARRAY_FILE', 11);
define('TYPE_ARRAY_BINARY', 112);
define('TYPE_ARRAY_NOHTMLCOND', 113);
define('TYPE_ARRAY_CLEAN_XSS', 114);

define('TYPE_ARRAY_KEYS_INT', 202);
define('TYPE_ARRAY_KEYS_STR', 207);

// value to subtract from array types to convert to single types
define('TYPE_CONVERT_SINGLE', 100);
// value to subtract from array => keys types to convert to single types
define('TYPE_CONVERT_KEYS', 200);

/**
 * Class to handle and sanitize variables from GET, POST and COOKIE etc
 */
class Request
{
    /**
     * Translation table for short superglobal name to long superglobal name
     *
     * @var array
     */
    public $superglobal_lookup = [
        'g' => '_GET',
        'p' => '_POST',
        'r' => '_REQUEST',
        'c' => '_COOKIE',
        's' => '_SERVER',
        'e' => '_ENV',
        'f' => '_FILES',
    ];

    /**
     * 有些变量可能没有传递
     *
     * @var array
     */
    private $param_exists = [];

    /**
     * @var string
     */
    protected $method;

    /**
     * List of allowed HTTP methods
     *
     * @var array
     */
    protected $allowedHttpMethods = ['get', 'delete', 'post', 'put', 'options', 'patch', 'head'];

    /**
     * Constructor
     *
     * First, reverses the effects of magic quotes on GPC
     * Second, deals with $_COOKIE[userId] conflicts
     */
    public function __construct()
    {
        $this->initGLOBALS();

        $this->initHttpConstant();
    }

    /**
     * 初始化系统全局常量
     */
    public function initGLOBALS()
    {
        if (! is_array($GLOBALS)) {
            displayError('Fatal Error: no $GLOBALS.');
        }

        // overwrite GET[x] and REQUEST[x] with POST[x] if it exists (overrides server's GPC order preference)
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            foreach (array_keys($_POST) as $key) {
                if (isset($_GET["{$key}"])) {
                    $_GET["{$key}"] = $_REQUEST["{$key}"] = $_POST["{$key}"];
                }
            }
        }

        // deal with cookies that may conflict with _GET and _POST data, and create our own _REQUEST with no _COOKIE input
        foreach (array_keys($_COOKIE) as $varname) {
            unset($_REQUEST["{$varname}"]);
            if (isset($_POST["{$varname}"])) {
                $_REQUEST["{$varname}"] = &$_POST["{$varname}"];
            } elseif (isset($_GET["{$varname}"])) {
                $_REQUEST["{$varname}"] = &$_GET["{$varname}"];
            }
        }
    }

    /**
     * 初始化一些HTTP常量
     */
    private function initHttpConstant()
    {
        // fetch client IP address
        define('IPADDRESS', static::fetchIp());

        // attempt to fetch IP address from behind proxies - useful, but don't rely on it...
        define('ALTIP', static::fetchAltIp());

        // fetch complete url of current page
        define('SCRIPTPATH', static::fetchScriptpath());

        // fetch url of current page without the variable string
        $quest_pos = strpos(SCRIPTPATH, '?');
        $script = $quest_pos !== false ? substr(SCRIPTPATH, 0, $quest_pos) : SCRIPTPATH;
        define('SCRIPT', $script);

        define('HTTP_REFERER', static::fetchHttpReferer());

        $default_server_values = [
            'SERVER_SOFTWARE' => '',
            'REQUEST_URI' => '',
        ];

        $_SERVER = array_merge($default_server_values, $_SERVER);

        // Fix for PHP as CGI hosts that set SCRIPT_FILENAME to something ending in php.cgi for all requests
        if (isset($_SERVER['SCRIPT_FILENAME']) && (strpos(
            $_SERVER['SCRIPT_FILENAME'],
            'php.cgi'
        ) == strlen($_SERVER['SCRIPT_FILENAME']) - 7)) {
            $_SERVER['SCRIPT_FILENAME'] = $_SERVER['PATH_TRANSLATED'];
        }

        // Fix for Dreamhost and other PHP as CGI hosts
        if (strpos($_SERVER['SCRIPT_NAME'], 'php.cgi') !== false) {
            unset($_SERVER['PATH_INFO']);
        }

        $httphost = $_SERVER['HTTP_HOST'] ?: $_SERVER['SERVER_NAME'];
        // 修正HTTP_HOST带端口的BUG
        [$httphost, $port] = explode(':', $httphost);

        // 添加端口
        $port = $port ?: $_SERVER['SERVER_PORT'];

        // 443，HTTPS负载均衡用
        if ($port && $port != 80 && $port != 443) {
            $httphost .= ':' . $port;
        }

        define('HTTPHOST', $httphost);

        // defines if the current page was visited via SSL or not
        $scheme = $port == 443 || (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == '1')) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ? 'https' : 'http';

        define('SCHEME', $scheme);

        define('USERAGENT', $_SERVER['HTTP_USER_AGENT']);
    }

    /**
     * Detect which HTTP method is being used
     *
     * @return string
     */
    public function getRequestMethod()
    {
        if ($this->method === null) {
            $this->method = strtolower($_SERVER['REQUEST_METHOD']);
        }

        if (in_array($this->method, $this->allowedHttpMethods)) {
            return $this->method;
        }

        return null;
    }

    /**
     * Makes data in an array safe to use
     *
     * @param array $source    The source array containing the data to be cleaned
     * @param array $variables Array of variable names and types we want to extract from the source array
     *
     * @return array
     */
    public function cleanArray($source, $variables)
    {
        $return = [];

        foreach ($variables as $varname => $vartype) {
            $return["{$varname}"] = $this->clean($source["{$varname}"], $vartype, isset($source["{$varname}"]));
        }

        return $return;
    }

    /**
     * Makes GPC variables safe to use
     *
     * @param string $source    Either, g, p, c, r or f (corresponding to get, post, cookie, request and files)
     * @param array  $variables Array of variable names and types we want to extract from the source array
     *
     * @return array
     */
    public function cleanArrayGPC($source, $variables)
    {
        $sg = &$GLOBALS[$this->superglobal_lookup["{$source}"]];
        $input = [];
        foreach ($variables as $varname => $vartype) {
            $this->param_exists["{$varname}"] = isset($sg["{$varname}"]);
            $input["{$varname}"] = &$this->clean($sg["{$varname}"], $vartype, isset($sg["{$varname}"]));
        }

        return $input;
    }

    /**
     * Makes a single GPC variable safe to use and returns it
     *
     * @param string $source  Either, g, p, c, r or f (corresponding to get, post, cookie, request and files)
     * @param string $varname The name of the variable in which we are interested
     * @param int    $vartype The type of the variable in which we are interested
     *
     * @return mixed
     */
    public function cleanGPC($source, $varname, $vartype = TYPE_NOCLEAN)
    {
        $sg = &$GLOBALS[$this->superglobal_lookup["{$source}"]];

        $this->param_exists["{$varname}"] = isset($sg["{$varname}"]);

        return $this->clean($sg["{$varname}"], $vartype, isset($sg["{$varname}"]));
    }

    /**
     * Makes a single variable safe to use and returns it
     *
     * @param mixed $var     The variable to be cleaned
     * @param int   $vartype The type of the variable in which we are interested
     * @param bool  $exists  Whether or not the variable to be cleaned actually is set
     *
     * @return mixed The cleaned value
     */
    private function clean(&$var, $vartype = TYPE_NOCLEAN, $exists = true)
    {
        if ($exists) {
            if ($vartype < TYPE_CONVERT_SINGLE) {
                $this->doClean($var, $vartype);
            } elseif (is_array($var)) {
                $vartype -= TYPE_CONVERT_SINGLE;

                foreach (array_keys($var) as $key) {
                    //校验数组key是否存在Xss异常
                    XssClean::check_array_key_xss($key);

                    $this->doClean($var["{$key}"], $vartype);
                }
            } else {
                $var = [];
            }

            return $var;
        }

        if ($vartype < TYPE_CONVERT_SINGLE) {
            switch ($vartype) {
                    case TYPE_INT:
                    case TYPE_UINT:
                    case TYPE_NUM:
                    case TYPE_UNUM:
                    case TYPE_UNIXTIME:

                            $var = 0;
                            break;
                    case TYPE_STR:
                    case TYPE_NOHTML:
                    case TYPE_CLEAN_XSS:
                    case TYPE_NOTRIM:
                    case TYPE_NOHTMLCOND:

                            $var = '';
                            break;
                    case TYPE_BOOL:

                            $var = 0;
                            break;
                    case TYPE_ARRAY:
                    case TYPE_FILE:

                            $var = [];
                            break;
                    case TYPE_NOCLEAN:

                            $var = null;
                            break;
                    default:

                            $var = null;
                }
        } else {
            $var = [];
        }

        return $var;
    }

    /**
     * Does the actual work to make a variable safe
     *
     * @param mixed $data The data we want to make safe
     * @param int   $type The type of the data
     *
     * @return mixed
     */
    private function doClean(&$data, $type)
    {
        static $booltypes = ['1', 'yes', 'y', 'true'];

        switch ($type) {
            case TYPE_INT:
                $data = intval($data);
                break;
            case TYPE_UINT:
                $data = ($data = intval($data)) < 0 ? 0 : $data;
                break;
            case TYPE_NUM:
                $data = strval($data) + 0;
                break;
            case TYPE_UNUM:
                $data = strval($data) + 0;
                $data = ($data < 0) ? 0 : $data;
                break;
            case TYPE_BINARY:
                $data = strval($data);
                break;
            case TYPE_STR:
                $data = trim(strval($data));
                break;
            case TYPE_NOTRIM:
                $data = strval($data);
                break;
            case TYPE_NOHTML:
                $data = NeoString::htmlSpecialCharsUni(trim(strval($data)));
                break;
            case TYPE_CLEAN_XSS:
                // 支持多维数组
                $data = static::cleanXss($data);
                break;
            case TYPE_BOOL:
                $data = in_array(strtolower($data), $booltypes) ? 1 : 0;
                break;
            case TYPE_ARRAY:
                $data = (is_array($data)) ? $data : [];
                break;
            case TYPE_NOHTMLCOND:

                    $data = trim(strval($data));
                    if (strcspn($data, '<>"') < strlen($data) or (strpos(
                        $data,
                        '&'
                    ) !== false and ! preg_match(
                        '/&(#[0-9]+|amp|lt|gt|quot);/si',
                        $data
                    ))) {
                        // data is not htmlspecialchars because it still has characters or entities it shouldn't
                        $data = NeoString::htmlSpecialCharsUni($data);
                    }
                    break;
            case TYPE_FILE:

                    // perhaps redundant :p
                    if (is_array($data)) {
                        if (is_array($data['name'])) {
                            $files = count($data['name']);
                            for ($index = 0; $index < $files; ++$index) {
                                $data['name']["{$index}"] = trim(strval($data['name']["{$index}"]));
                                $data['type']["{$index}"] = trim(strval($data['type']["{$index}"]));
                                $data['tmp_name']["{$index}"] = trim(strval($data['tmp_name']["{$index}"]));
                                $data['error']["{$index}"] = intval($data['error']["{$index}"]);
                                $data['size']["{$index}"] = intval($data['size']["{$index}"]);
                            }
                        } else {
                            $data['name'] = trim(strval($data['name']));
                            $data['type'] = trim(strval($data['type']));
                            $data['tmp_name'] = trim(strval($data['tmp_name']));
                            $data['error'] = intval($data['error']);
                            $data['size'] = intval($data['size']);
                        }
                    } else {
                        $data = [
                            'name' => '',
                            'type' => '',
                            'tmp_name' => '',
                            'error' => 0,
                            'size' => 4, // UPLOAD_ERR_NO_FILE
                        ];
                    }
                    break;
            case TYPE_UNIXTIME:

                    if (is_array($data)) {
                        $data = $this->clean($data, TYPE_ARRAY_UINT);
                        if ($data['month'] and $data['day'] and $data['year']) {
                            $data = mktime(
                                $data['hour'],
                                $data['minute'],
                                $data['second'],
                                $data['month'],
                                $data['day'],
                                $data['year']
                            );
                        } else {
                            $data = 0;
                        }
                    } else {
                        $data = ($data = intval($data)) < 0 ? 0 : $data;
                    }
                    break;
        }

        // strip out characters that really have no business being in non-binary data
        switch ($type) {
            case TYPE_STR:
            case TYPE_NOTRIM:
            case TYPE_CLEAN_XSS:
            case TYPE_NOHTML:
            case TYPE_NOHTMLCOND:
                $data = str_replace(chr(0), '', $data);
        }

        return $data;
    }

    /**
     * Removes HTML characters and potentially unsafe scripting words from a string
     *
     * @param string $var The variable we want to make safe
     *
     * @return string
     */
    private static function cleanXss($var)
    {
        return XssClean::getInstance()->xss_clean($var);
    }

    /**
     * Reverses the effects of magic_quotes on an entire array of variables
     *
     * @param array $value The array on which we want to work
     * @param int   $depth The depth of procession
     */
    private function stripslashesDeep(&$value, $depth = 0)
    {
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                if (is_string($val)) {
                    $value["{$key}"] = stripslashes($val);
                } elseif (is_array($val) and $depth < 10) {
                    $this->stripslashesDeep($value["{$key}"], $depth + 1);
                }
            }
        }
    }

    /**
     * Fetches the 'scriptpath' variable - ie: the URI of the current page
     *
     * @return string
     */
    public static function fetchScriptpath()
    {
        if ($_SERVER['REQUEST_URI']) {
            $scriptpath = $_SERVER['REQUEST_URI'];
        } else {
            $scriptpath = $_SERVER['PATH_INFO'] ?: $_SERVER['REDIRECT_URL'] ?: $_SERVER['PHP_SELF'];

            if ($_SERVER['QUERY_STRING']) {
                $scriptpath .= '?' . $_SERVER['QUERY_STRING'];
            }
        }

        $quest_pos = strpos($scriptpath, '?');
        if ($quest_pos !== false) {
            $script = urldecode(substr($scriptpath, 0, $quest_pos));
            $scriptpath = $script . substr($scriptpath, $quest_pos);
        } else {
            $scriptpath = urldecode($scriptpath);
        }

        return static::cleanXss($scriptpath);
    }

    /**
     * Fetches the 'url' variable - usually the URL of the previous page in the history
     *
     * @return string
     */
    public static function fetchHttpReferer()
    {
        $scriptpath = static::fetchScriptpath();
        $url = $_SERVER['HTTP_REFERER'];

        if ($url == $scriptpath || empty($url)) {
            $url = '';
        } else {
            $url = static::cleanXss($url);
        }

        return $url;
    }

    /**
     * Fetches an alternate IP address of the current visitor
     *
     * @return string
     */
    public static function fetchAltIp()
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Fetches the IP address of the current visitor, attempting to detect proxies etc.
     *
     * @return string
     */
    public static function fetchIp()
    {
        $_ip = $_SERVER['REMOTE_ADDR'];

        if (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $_ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match_all(
            '#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s',
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $matches
        )) {
            // ignore an internal IP defined by RFC1918
            foreach ($matches[0] as $ip) {
                if (! preg_match('#^(10|172\.16|192\.168)\.#', $ip)) {
                    $_ip = $ip;
                    break;
                }
            }
        } elseif (isset($_SERVER['HTTP_FROM'])) {
            $_ip = $_SERVER['HTTP_FROM'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $_ip = $_SERVER['HTTP_CLIENT_IP'];
        }

        return $_ip;
    }

    /**
     * 检查某个参数值是否通过URL或者form传递
     *
     * @param string $key
     *
     * @return bool
     */
    public function isParamSet($key)
    {
        if (! key_exists($key, $this->param_exists)) {
            return false;
        }

        return $this->param_exists[$key];
    }
}
