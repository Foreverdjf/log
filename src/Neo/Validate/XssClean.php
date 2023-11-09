<?php

namespace foreverdjf\log\Neo\Validate;

use Neo\NeoFrame;
use Neo\NeoLog;

/**
 * Xss 清洗
 * ported from "CodeIgniter"
 * Class XssClean
 */
class XssClean
{
    /**
     * xss 安全标记, 用户模板渲染跳过检测
     */
    const XSS_SAFE_FLAG = '_xss_safe_flag';

    /**
     * @var self
     */
    private static $instance;

    /**
     * List of sanitize filename strings
     *
     * @var array
     */
    public $filename_bad_chars = [
        '../', '<!--', '-->', '<', '>',
        "'", '"', '&', '$', '#',
        '{', '}', '[', ']', '=',
        ';', '?', '%20', '%22',
        '%3c',		// <
        '%253c',	// <
        '%3e',		// >
        '%0e',		// >
        '%28',		// (
        '%29',		// )
        '%2528',	// (
        '%26',		// &
        '%24',		// $
        '%3f',		// ?
        '%3b',		// ;
        '%3d',		// =
    ];

    /**
     * Character set
     *
     * Will be overridden by the constructor.
     *
     * @var string
     */
    public $charset = 'utf-8';

    /**
     * List of never allowed strings
     *
     * @var array
     */
    protected $_never_allowed_str = [
        'document.cookie' => '',
        '(document).cookie' => '',
        'document.write' => '',
        '(document).write' => '',
        '.parentNode' => '',
        '.innerHTML' => '',
        '-moz-binding' => '',
        '<!--' => '&lt;!--',
        '-->' => '--&gt;',
        '<![CDATA[' => '&lt;![CDATA[',
        '<comment>' => '&lt;comment&gt;',
        '<%' => '&lt;&#37;',
    ];

    /**
     * List of never allowed regex replacements
     *
     * @var array
     */
    protected $_never_allowed_regex = [
        'javascript\s*:',
        '(\(?document\)?|\(?window\)?(\.document)?)\.(location|on\w*)',
        'expression\s*(\(|&\#40;)', // CSS and IE
        'vbscript\s*:', // IE, surprise!
        'wscript\s*:', // IE
        'jscript\s*:', // IE
        'vbs\s*:', // IE
        'Redirect\s+30\d',
        "([\"'])?data\\s*:[^\\1]*?base64[^\\1]*?,[^\\1]*?\\1?",
    ];

    /**
     * XssClean constructor.
     */
    private function __construct()
    {
        $this->charset = NeoFrame::charset();
    }

    /**
     * 单例
     * @return XssClean
     */
    public static function getInstance()
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 是否后台输出的安全html标签
     * @param $html
     * @return bool
     */
    protected static function _is_safe_html_tag($html)
    {
        return stripos($html, self::XSS_SAFE_FLAG) !== false;
    }

    /**
     * @param $str
     * @param  bool                            $is_image
     * @return null|array|bool|string|string[]
     */
    public function xss_clean($str)
    {
        // Is the string an array?
        if (is_array($str)) {
            foreach ($str as $key => $value) {
                //校验数组key是否存在Xss异常
                self::check_array_key_xss($key);

                $str[$key] = $this->xss_clean($value);
            }

            return $str;
        }

        if (! is_string($str)) {
            return $str;
        }

        if (self::_is_safe_html_tag($str)) {
            return str_replace(self::XSS_SAFE_FLAG, '', $str);
        }

        //将html实体转化为正常字符串
        $str = strip_tags(htmlspecialchars_decode($str));

        return $this->_do_clean($str);
    }

    /**
     * 对str 执行 xss 清洗
     * @param  string                    $str
     * @param  bool                      $is_image
     * @return null|bool|string|string[]
     */
    protected function _do_clean(string $str, $is_image = false)
    {
        // Remove Invisible Characters
        $str = \CI_URI::remove_invisible_characters($str);

        /*
         * URL Decode
         *
         * Just in case stuff like this is submitted:
         *
         * <a href="http://%77%77%77%2E%67%6F%6F%67%6C%65%2E%63%6F%6D">Google</a>
         *
         * Note: Use rawurldecode() so it does not remove plus signs
         */
        if (stripos($str, '%') !== false) {
            do {
                $oldstr = $str;
                $str = rawurldecode($str);
                $str = preg_replace_callback('#%(?:\s*[0-9a-f]){2,}#i', [$this, '_urldecodespaces'], $str);
            } while ($oldstr !== $str);
            unset($oldstr);
        }

        /*
         * Convert character entities to ASCII
         *
         * This permits our tests below to work reliably.
         * We only convert entities that are within tags since
         * these are the ones that will pose security problems.
         */
        $str = preg_replace_callback("/[^a-z0-9>]+[a-z0-9]+=([\\'\"]).*?\\1/si", [$this, '_convert_attribute'], $str);
        $str = preg_replace_callback('/<\w+.*/si', [$this, '_decode_entity'], $str);

        // Remove Invisible Characters Again!
        $str = \CI_URI::remove_invisible_characters($str);

        /*
         * Convert all tabs to spaces
         *
         * This prevents strings like this: ja	vascript
         * NOTE: we deal with spaces between characters later.
         * NOTE: preg_replace was found to be amazingly slow here on
         * large blocks of data, so we use str_replace.
         */
        $str = str_replace("\t", ' ', $str);

        // Capture converted string for later comparison
        $converted_string = $str;

        // Remove Strings that are never allowed
        $str = $this->_do_never_allowed($str, '');

        /*
         * Makes PHP tags safe
         *
         * Note: XML tags are inadvertently replaced too:
         *
         * <?xml
         *
         * But it doesn't seem to pose a problem.
         */
        $str = str_replace(['<?', '?>'], ['&lt;?', '?&gt;'], $str);

        /*
         * Compact any exploded words
         *
         * This corrects words like:  j a v a s c r i p t
         * These words are compacted back to their correct state.
         */
        $words = [
            'javascript', 'expression', 'vbscript', 'jscript', 'wscript',
            'vbs', 'script', 'base64', 'applet', 'alert', 'document',
            'write', 'cookie', 'window', 'confirm', 'prompt', 'eval',
        ];

        foreach ($words as $word) {
            $word = implode('\s*', str_split($word));
            // We only want to do this when it is followed by a non-word character
            // That way valid stuff like "dealer to" does not become "dealerto"
            $str = preg_replace_callback('#(' . $word . ')(\W)#is', [$this, '_compact_exploded_words'], $str);
        }

        /*
         * Remove disallowed Javascript in links or img tags
         * We used to do some version comparisons and use of stripos(),
         * but it is dog slow compared to these simplified non-capturing
         * preg_match(), especially if the pattern exists in the string
         *
         * Note: It was reported that not only space characters, but all in
         * the following pattern can be parsed as separators between a tag name
         * and its attributes: [\d\s"\'`;,\/\=\(\x00\x0B\x09\x0C]
         * ... however, remove_invisible_characters() above already strips the
         * hex-encoded ones, so we'll skip them below.
         */
        do {
            $original = $str;

            if (preg_match('/<a/i', $str)) {
                $str = preg_replace_callback('#<a(?:rea)?[^a-z0-9>]+([^>]*?)(?:>|$)#si', [$this, '_js_link_removal'], $str);
            }

            if (preg_match('/<img/i', $str)) {
                $str = preg_replace_callback('#<img[^a-z0-9]+([^>]*?)(?:\s?/?>|$)#si', [$this, '_js_img_removal'], $str);
            }

            if (preg_match('/script|xss/i', $str)) {
                $str = preg_replace('#</*(?:script|xss).*?>#si', '', $str);
            }
        } while ($original !== $str);
        unset($original);

        //移除事件属性
        $str = $this->_remove_evil_attributes($str, $is_image);

        $naughty = 'alert|applet|audio|basefont|base|behavior|bgsound|blink|body|embed|expression|form|frameset|frame|head|html|ilayer|iframe|input|isindex|layer|link|meta|object|plaintext|style|script|textarea|title|video|xml|xss';

        //处理html标签  -对捕获的开括号或闭括号进行编码，以防止向量递归
        $str = preg_replace_callback('#<(/*\s*)(' . $naughty . ')([^><]*)([><]*)#is', [$this, '_sanitize_naughty_html'], $str);

        /*
         * Sanitize naughty scripting elements
         *
         * Similar to above, only instead of looking for
         * tags it looks for PHP and JavaScript commands
         * that are disallowed. Rather than removing the
         * code, it simply converts the parenthesis to entities
         * rendering the code un-executable.
         *
         * For example:	eval('some code')
         * Becomes:	eval&#40;'some code'&#41;
         */
        $str = preg_replace(
            '#(alert|prompt|confirm|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)\((.*?)\)#si',
            '\\1\\2&#40;\\3&#41;',
            $str
        );

        // Same thing, but for "tag functions" (e.g. eval`some code`)
        // See https://github.com/bcit-ci/CodeIgniter/issues/5420
        $str = preg_replace(
            '#(alert|prompt|confirm|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)`(.*?)`#si',
            '\\1\\2&#96;\\3&#96;',
            $str
        );

        // Final clean up
        // This adds a bit of extra precaution in case
        // something got through the above filters
        $str = $this->_do_never_allowed($str, '');

        /*
         * Images are Handled in a Special Way
         * - Essentially, we want to know that after all of the character
         * conversion is done whether any unwanted, likely XSS, code was found.
         * If not, we return TRUE, as the image is clean.
         * However, if the string post-conversion does not matched the
         * string post-removal of XSS, then it fails, as there was unwanted XSS
         * code found and removed/changed during processing.
         */
        if ($is_image === true) {
            return $str === $converted_string;
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * HTML Entities Decode
     *
     * A replacement for html_entity_decode()
     *
     * The reason we are not using html_entity_decode() by itself is because
     * while it is not technically correct to leave out the semicolon
     * at the end of an entity most browsers will still interpret the entity
     * correctly. html_entity_decode() does not convert entities without
     * semicolons, so we are left with our own little solution here. Bummer.
     *
     * @see	https://secure.php.net/html-entity-decode
     *
     * @param  string $str     Input
     * @param  string $charset Character set
     * @return string
     */
    public function entity_decode($str, $charset = null)
    {
        if (strpos($str, '&') === false) {
            return $str;
        }

        static $_entities;

        isset($charset) or $charset = $this->charset;
        isset($_entities) or $_entities = array_map('strtolower', get_html_translation_table(HTML_ENTITIES, ENT_COMPAT | ENT_HTML5, $charset));

        do {
            $str_compare = $str;

            // Decode standard entities, avoiding false positives
            if (preg_match_all('/&[a-z]{2,}(?![a-z;])/i', $str, $matches)) {
                $replace = [];
                $matches = array_unique(array_map('strtolower', $matches[0]));
                foreach ($matches as &$match) {
                    if (($char = array_search($match . ';', $_entities, true)) !== false) {
                        $replace[$match] = $char;
                    }
                }

                $str = str_replace(array_keys($replace), array_values($replace), $str);
            }

            // Decode numeric & UTF16 two byte entities
            $str = html_entity_decode(
                preg_replace('/(&#(?:x0*[0-9a-f]{2,5}(?![0-9a-f;])|(?:0*\d{2,4}(?![0-9;]))))/iS', '$1;', $str),
                ENT_COMPAT | ENT_HTML5,
                $charset
            );
        } while ($str_compare !== $str);
        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Sanitize Filename
     *
     * @param  string $str           Input file name
     * @param  bool   $relative_path Whether to preserve paths
     * @return string
     */
    public function sanitize_filename($str, $relative_path = false)
    {
        $bad = $this->filename_bad_chars;

        if (! $relative_path) {
            $bad[] = './';
            $bad[] = '/';
        }

        $str = \CI_URI::remove_invisible_characters($str, false);

        do {
            $old = $str;
            $str = str_replace($bad, '', $str);
        } while ($old !== $str);

        return stripslashes($str);
    }

    // ----------------------------------------------------------------

    /**
     * Strip Image Tags
     *
     * @param  string $str
     * @return string
     */
    public function strip_image_tags($str)
    {
        return preg_replace(
            [
                '#<img[\s/]+.*?src\s*=\s*(["\'])([^\\1]+?)\\1.*?\>#i',
                '#<img[\s/]+.*?src\s*=\s*?(([^\s"\'=<>`]+)).*?\>#i',
            ],
            '\\2',
            $str
        );
    }

    // ----------------------------------------------------------------

    /**
     * URL-decode taking spaces into account
     *
     * @see		https://github.com/bcit-ci/CodeIgniter/issues/4877
     * @param  array  $matches
     * @return string
     */
    protected function _urldecodespaces($matches)
    {
        $input = $matches[0];
        $nospaces = preg_replace('#\s+#', '', $input);
        return ($nospaces === $input)
            ? $input
            : rawurldecode($nospaces);
    }

    // ----------------------------------------------------------------

    /**
     * Compact Exploded Words
     *
     * Callback method for xss_clean() to remove whitespace from
     * things like 'j a v a s c r i p t'.
     *
     * @used-by	CI_Security::xss_clean()
     * @param  array  $matches
     * @return string
     */
    protected function _compact_exploded_words($matches)
    {
        return preg_replace('/\s+/s', '', $matches[1]) . $matches[2];
    }

    // --------------------------------------------------------------------

    /**
     * Sanitize Naughty HTML
     *
     * Callback method for xss_clean() to remove naughty HTML elements.
     *
     * @param $matches
     *
     * @return string
     */
    protected function _sanitize_naughty_html($matches)
    {
        // encode opening brace
        $str = '<' . $matches[1] . $matches[2] . $matches[3];

        // encode captured opening or closing brace to prevent recursive vectors
        $str .= str_replace(['>', '<'], ['>', '<'], $matches[4]);
        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * JS Link Removal
     *
     * Callback method for xss_clean() to sanitize links.
     *
     * This limits the PCRE backtracks, making it more performance friendly
     * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
     * PHP 5.2+ on link-heavy strings.
     *
     * @used-by	CI_Security::xss_clean()
     * @param  array  $match
     * @return string
     */
    protected function _js_link_removal($match)
    {
        return str_replace(
            $match[1],
            preg_replace(
                '#href=.*?(?:(?:alert|prompt|confirm)(?:\(|&\#40;|`|&\#96;)|javascript:|livescript:|mocha:|charset=|window\.|\(?document\)?\.|\.cookie|<script|<xss|d\s*a\s*t\s*a\s*:)#si',
                '',
                $this->_filter_attributes($match[1])
            ),
            $match[0]
        );
    }

    // --------------------------------------------------------------------

    /**
     * JS Image Removal
     *
     * Callback method for xss_clean() to sanitize image tags.
     *
     * This limits the PCRE backtracks, making it more performance friendly
     * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
     * PHP 5.2+ on image tag heavy strings.
     *
     * @used-by	CI_Security::xss_clean()
     * @param  array  $match
     * @return string
     */
    protected function _js_img_removal($match)
    {
        return str_replace(
            $match[1],
            preg_replace(
                '#src=.*?(?:(?:alert|prompt|confirm|eval)(?:\(|&\#40;|`|&\#96;)|javascript:|livescript:|mocha:|charset=|window\.|\(?document\)?\.|\.cookie|<script|<xss|base64\s*,)#si',
                '',
                $this->_filter_attributes($match[1])
            ),
            $match[0]
        );
    }

    // --------------------------------------------------------------------

    /**
     * Attribute Conversion
     *
     * @used-by	CI_Security::xss_clean()
     * @param  array  $match
     * @return string
     */
    protected function _convert_attribute($match)
    {
        return str_replace(['>', '<', '\\'], ['&gt;', '&lt;', '\\\\'], $match[0]);
    }

    // --------------------------------------------------------------------

    /**
     * Filter Attributes
     *
     * Filters tag attributes for consistency and safety.
     *
     * @used-by	CI_Security::_js_img_removal()
     * @used-by	CI_Security::_js_link_removal()
     * @param  string $str
     * @return string
     */
    protected function _filter_attributes($str)
    {
        $out = '';
        if (preg_match_all('#\s*[a-z\-]+\s*=\s*(\042|\047)([^\\1]*?)\\1#is', $str, $matches)) {
            foreach ($matches[0] as $match) {
                $out .= preg_replace('#/\*.*?\*/#s', '', $match);
            }
        }

        return $out;
    }

    // --------------------------------------------------------------------

    /**
     * HTML Entity Decode Callback
     *
     * @used-by	CI_Security::xss_clean()
     * @param  array  $match
     * @return string
     */
    protected function _decode_entity($match)
    {
        $xss_hash = md5(uniqid(mt_rand(), true));
        // Protect GET variables in URLs
        // 901119URL5918AMP18930PROTECT8198
        $match = preg_replace('|\&([a-z\_0-9\-]+)\=([a-z\_0-9\-/]+)|i', $xss_hash . '\\1=\\2', $match[0]);

        // Decode, then un-protect URL GET vars
        return str_replace(
            $xss_hash,
            '&',
            $this->entity_decode($match, $this->charset)
        );
    }

    // --------------------------------------------------------------------

    /**
     * Do Never Allowed
     *
     * @used-by	CI_Security::xss_clean()
     * @param 	string
     * @param  mixed  $str
     * @param  mixed  $replace
     * @return string
     */
    protected function _do_never_allowed($str, $replace = '[removed]')
    {
        $str = str_replace(array_keys($this->_never_allowed_str), $this->_never_allowed_str, $str);

        foreach ($this->_never_allowed_regex as $regex) {
            $str = preg_replace('#' . $regex . '#is', $replace, $str);
        }

        return $str;
    }

    /**
     * 判断数组的key是否存在Xss 存在抛异常
     * @param $key
     */
    public static function check_array_key_xss($key)
    {
        $newKey = XssClean::getInstance()->xss_clean($key);
        if ($newKey != rawurldecode($key)) {
            NeoLog::error('check_array_key_xss', 'check_array_key_xss', ['newKey' => $newKey, 'key' => $key]);
            displayError('Fatal Error: THIS IS TYPE_ARRAY_CLEAN_XSS XSS .');
        }
    }

    /**
     * Xss针对事件处理
     *
     * @param $str
     * @param $is_image
     *
     * @return null|string|string[]
     */
    protected function _remove_evil_attributes($str, $is_image)
    {
        // 所有有on开头的属性&Other
        $evil_attributes = ['onafterprint', 'onbeforeprint', 'onbeforeunload', 'onerror', 'onhaschange', 'onload', 'onmessage', 'onoffline', 'ononline', 'onpagehide', 'onpageshow', 'onpopstate', 'onredo', 'onresize', 'onstorage', 'onundo', 'onunload', 'onblur', 'onchange', 'oncontextmenu', 'onfocus', 'onformchange', 'onforminput', 'oninput', 'oninvalid', 'onreset', 'onselect', 'onsubmit', 'onkeydown', 'onkeypress', 'onkeyup', 'onclick', 'ondblclick', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onmousedown', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel', 'onscroll', 'onabort', 'oncanplay', 'oncanplaythrough', 'ondurationchange', 'onemptied', 'onended', 'onerror', 'onloadeddata', 'onloadedmetadata', 'onloadstart', 'onpause', 'onplay', 'onplaying', 'onprogress', 'onratechange', 'onreadystatechange', 'onseeked', 'onseeking', 'onstalled', 'onsuspend', 'ontimeupdate', 'onvolumechange', 'onwaiting', 'style', 'xmlns', 'formaction', 'form', 'xlink:href', 'FSCommand', 'seekSegmentTime'];
        if ($is_image === true) {
            /*
             * Adobe Photoshop puts XML metadata into JFIF images,
             * including namespacing, so we have to allow this for images.
             */
            unset($evil_attributes[array_search('xmlns', $evil_attributes)]);
        }

        do {
            $count = 0;
            $attribs = [];

            // find attribute strings with quotes (042 and 047 are octal quotes 八进制引号)
            // 匹配带引号的属性 042 047 八进制引号 e.g. attr='value'
            preg_match_all(
                '/(' . implode('|', $evil_attributes) . ')\s*=\s*(\042|\047)([^\\2]*?)(\\2)/is',
                $str,
                $matches,
                PREG_SET_ORDER
            );
            foreach ($matches as $attr) {
                // 对匹配到的内容进行转义
                $attribs[] = preg_quote($attr[0], '/');
            }
            // find attribute strings without quotes
            // 匹配 不 带引号的属性 e.g. attr=value
            preg_match_all(
                '/(' . implode('|', $evil_attributes) . ')\s*=\s*([^\s>]*)/is',
                $str,
                $matches,
                PREG_SET_ORDER
            );
            foreach ($matches as $attr) {
                $attribs[] = preg_quote($attr[0], '/');
            }
            // 去除字符串中HTML标签里面的 这种 onclick = 'javascript:func();'
            if (count($attribs) > 0) {
                //             $1     $2            $3        $4                       $5                $6    $7       $8
                $str = preg_replace(
                    '/(<?)(\/?[^><]+?)([^A-Za-z<>\-])(.*?)(' . implode('|', $attribs) . ')(.*?)([\s><]?)([><]*)/i',
                    '$1$2$3$4$6$7$8',
                    $str,
                    -1,
                    $count
                );
            }
        } while ($count);

        return $str;
    }
}
