<?php

/**
 * URI Class
 *
 * Parses URIs and determines routing
 *
 * @category       URI
 * @see           http://codeigniter.com/user_guide/libraries/uri.html
 */
class CI_URI
{
    /**
     * List of cached URI segments
     *
     * @var array
     */
    public $keyval = [];

    /**
     * Current URI string
     *
     * @var string
     */
    public $uri_string;

    /**
     * List of URI segments
     *
     * @var array
     */
    public $segments = [];

    /**
     * Re-indexed list of URI segments
     *
     * Starts at 1 instead of 0.
     *
     * @var array
     */
    public $rsegments = [];

    const PERMITTED_URI_CHARS = 'a-z 0-9~%.:_\-';

    const URL_SUFFIX = '.html';

    /**
     * Class constructor
     *
     * Simply globalizes the $RTR object. The front
     * loads the Router class early on so it's not available
     * normally as other classes are.
     */
    public function __construct()
    {
    }

    // --------------------------------------------------------------------

    /**
     * Fetch URI String
     *
     * @used-by    CI_Router
     */
    public function _fetch_uri_string()
    {
        // Is the request coming from the command line?
        if ($this->_is_cli_request()) {
            $this->_set_uri_string($this->_parse_argv());

            return;
        }

        // Is there a PATH_INFO variable? This should be the easiest solution.
        if (isset($_SERVER['PATH_INFO'])) {
            $this->_set_uri_string($_SERVER['PATH_INFO']);

            return;
        }

        // Let's try REQUEST_URI then, this will work in most situations
        if (($uri = $this->_parse_request_uri()) !== '') {
            $this->_set_uri_string($uri);

            return;
        }

        // No REQUEST_URI either?... What about QUERY_STRING?
        if (($uri = $this->_parse_query_string()) !== '') {
            $this->_set_uri_string($uri);

            return;
        }

        // As a last ditch effort let's try using the $_GET array
        if (is_array($_GET) && count($_GET) === 1 && trim(key($_GET), '/') !== '') {
            $this->_set_uri_string(key($_GET));

            return;
        }

        // We've exhausted all our options...
        $this->uri_string = '';
    }

    // --------------------------------------------------------------------

    /**
     * Set URI String
     *
     * @param string $str
     */
    protected function _set_uri_string($str)
    {
        // Filter out control characters and trim slashes
        $this->uri_string = trim(static::remove_invisible_characters($str, false), '/');
    }

    // --------------------------------------------------------------------

    /**
     * Parse REQUEST_URI
     *
     * Will parse REQUEST_URI and automatically detect the URI from it,
     * while fixing the query string if necessary.
     *
     * @used-by    CI_URI::_fetch_uri_string()
     * @return string
     */
    protected function _parse_request_uri()
    {
        if (! isset($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME'])) {
            return '';
        }

        $uri = parse_url($_SERVER['REQUEST_URI']);
        $query = isset($uri['query']) ? $uri['query'] : '';
        $uri = isset($uri['path']) ? rawurldecode($uri['path']) : '';

        if (strpos($uri, $_SERVER['SCRIPT_NAME']) === 0) {
            $uri = (string) substr($uri, strlen($_SERVER['SCRIPT_NAME']));
        } elseif (strpos($uri, dirname($_SERVER['SCRIPT_NAME'])) === 0) {
            $uri = (string) substr($uri, strlen(dirname($_SERVER['SCRIPT_NAME'])));
        }

        // This section ensures that even on servers that require the URI to be in the query string (Nginx) a correct
        // URI is found, and also fixes the QUERY_STRING server var and $_GET array.
        if (trim($uri, '/') === '' && strncmp($query, '/', 1) === 0) {
            $query = explode('?', $query, 2);
            $uri = rawurldecode($query[0]);
            $_SERVER['QUERY_STRING'] = isset($query[1]) ? $query[1] : '';
        } else {
            $_SERVER['QUERY_STRING'] = $query;
        }

        parse_str($_SERVER['QUERY_STRING'], $_GET);

        if ($uri === '/' or $uri === '') {
            return '/';
        }

        // Do some final cleaning of the URI and return it
        return $this->_remove_relative_directory($uri);
    }

    // --------------------------------------------------------------------

    /**
     * Remove relative directory (../) and multi slashes (///)
     *
     * Do some final cleaning of the URI and return it, currently only used in self::_parse_request_uri()
     *
     * @param string $uri
     *
     * @return string
     */
    protected function _remove_relative_directory($uri)
    {
        $uris = [];
        $tok = strtok($uri, '/');
        while ($tok !== false) {
            if ((! empty($tok) or $tok === '0') && $tok !== '..') {
                $uris[] = $tok;
            }
            $tok = strtok('/');
        }

        return implode('/', $uris);
    }

    // --------------------------------------------------------------------

    /**
     * Parse QUERY_STRING
     *
     * Will parse QUERY_STRING and automatically detect the URI from it.
     *
     * @used-by    CI_URI::_fetch_uri_string()
     * @return string
     */
    protected function _parse_query_string()
    {
        $uri = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : @getenv('QUERY_STRING');

        if (trim($uri, '/') === '') {
            return '';
        }
        if (strncmp($uri, '/', 1) === 0) {
            $uri = explode('?', $uri, 2);
            $_SERVER['QUERY_STRING'] = isset($uri[1]) ? $uri[1] : '';
            $uri = rawurldecode($uri[0]);
        }

        parse_str($_SERVER['QUERY_STRING'], $_GET);

        return $this->_remove_relative_directory($uri);
    }

    // --------------------------------------------------------------------

    /**
     * Is CLI Request?
     *
     * Duplicate of method from the Input class to test to see if
     * a request was made from the command line.
     *
     * @see        CI_Input::is_cli_request()
     * @used-by    CI_URI::_fetch_uri_string()
     * @return bool
     */
    protected function _is_cli_request()
    {
        return (PHP_SAPI === 'cli') or defined('STDIN');
    }

    // --------------------------------------------------------------------

    /**
     * Parse CLI arguments
     *
     * Take each command line argument and assume it is a URI segment.
     *
     * @return string
     */
    protected function _parse_argv()
    {
        $args = array_slice($_SERVER['argv'], 1);

        return $args ? implode('/', $args) : '';
    }

    // --------------------------------------------------------------------

    /**
     * Filter URI
     *
     * Filters segments for malicious characters.
     *
     * @used-by    CI_Router
     *
     * @param string $str
     *
     * @return string
     */
    public function _filter_uri($str)
    {
        if ($str !== '' && self::PERMITTED_URI_CHARS != '') {
            // preg_quote() in PHP 5.3 escapes -, so the str_replace() and addition of - to preg_quote() is to maintain backwards
            // compatibility as many are unaware of how characters in the permitted_uri_chars will be parsed as a regex pattern
            if (! preg_match(
                '|^[' . str_replace(
                    ['\\-', '\-'],
                    '-',
                    preg_quote(self::PERMITTED_URI_CHARS, '-')
                ) . ']+$|i',
                $str
            )) {
                CodeIgniter::show_error('The URI you submitted has disallowed characters.', 404);
            }
        }

        // Convert programatic characters to entities and return
        return str_replace(['$', '(', ')', '%28', '%29'], // Bad
                           ['&#36;', '&#40;', '&#41;', '&#40;', '&#41;'], // Good
                           $str);
    }

    // --------------------------------------------------------------------

    /**
     * Remove URL suffix
     *
     * Removes the suffix from the URL if needed.
     *
     * @used-by    CI_Router
     */
    public function _remove_url_suffix()
    {
        $suffix = self::URL_SUFFIX;

        if ($suffix === '') {
            return;
        }

        $slen = strlen($suffix);

        if (substr($this->uri_string, -$slen) === $suffix) {
            $this->uri_string = substr($this->uri_string, 0, -$slen);
        }
    }

    // --------------------------------------------------------------------

    /**
     * Explode URI segments
     *
     * The individual segments will be stored in the $this->segments array.
     *
     * @see        CI_URI::$segments
     * @used-by    CI_Router
     */
    public function _explode_segments()
    {
        foreach (explode('/', preg_replace('|/*(.+?)/*$|', '\\1', $this->uri_string)) as $val) {
            // Filter segments for security
            $val = trim($this->_filter_uri($val));

            if ($val !== '') {
                $this->segments[] = $val;
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Re-index Segments
     *
     * Re-indexes the CI_URI::$segment array so that it starts at 1 rather
     * than 0. Doing so makes it simpler to use methods like
     * CI_URI::segment(n) since there is a 1:1 relationship between the
     * segment array and the actual segments.
     *
     * @used-by    CI_Router
     */
    public function _reindex_segments()
    {
        array_unshift($this->segments, null);
        array_unshift($this->rsegments, null);
        unset($this->segments[0], $this->rsegments[0]);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch URI Segment
     *
     * @see        CI_URI::$segments
     *
     * @param int   $n         Index
     * @param mixed $no_result What to return if the segment index is not found
     *
     * @return mixed
     */
    public function segment($n, $no_result = null)
    {
        return isset($this->segments[$n]) ? $this->segments[$n] : $no_result;
    }

    // --------------------------------------------------------------------

    /**
     * Fetch URI "routed" Segment
     *
     * Returns the re-routed URI segment (assuming routing rules are used)
     * based on the index provided. If there is no routing, will return
     * the same result as CI_URI::segment().
     *
     * @see        CI_URI::$rsegments
     * @see        CI_URI::segment()
     *
     * @param int   $n         Index
     * @param mixed $no_result What to return if the segment index is not found
     *
     * @return mixed
     */
    public function rsegment($n, $no_result = null)
    {
        return isset($this->rsegments[$n]) ? $this->rsegments[$n] : $no_result;
    }

    // --------------------------------------------------------------------

    /**
     * URI to assoc
     *
     * Generates an associative array of URI data starting at the supplied
     * segment index. For example, if this is your URI:
     *
     *    example.com/user/search/name/joe/location/UK/gender/male
     *
     * You can use this method to generate an array with this prototype:
     *
     *    array (
     *        name => joe
     *        location => UK
     *        gender => male
     *     )
     *
     * @param int   $n       Index (default: 3)
     * @param array $default Default values
     *
     * @return array
     */
    public function uri_to_assoc($n = 3, $default = [])
    {
        return $this->_uri_to_assoc($n, $default, 'segment');
    }

    // --------------------------------------------------------------------

    /**
     * Routed URI to assoc
     *
     * Identical to CI_URI::uri_to_assoc(), only it uses the re-routed
     * segment array.
     *
     * @see        CI_URI::uri_to_assoc()
     *
     * @param int   $n       Index (default: 3)
     * @param array $default Default values
     *
     * @return array
     */
    public function ruri_to_assoc($n = 3, $default = [])
    {
        return $this->_uri_to_assoc($n, $default, 'rsegment');
    }

    // --------------------------------------------------------------------

    /**
     * Internal URI-to-assoc
     *
     * Generates a key/value pair from the URI string or re-routed URI string.
     *
     * @used-by    CI_URI::uri_to_assoc()
     * @used-by    CI_URI::ruri_to_assoc()
     *
     * @param int    $n       Index (default: 3)
     * @param array  $default Default values
     * @param string $which   Array name ('segment' or 'rsegment')
     *
     * @return array
     */
    protected function _uri_to_assoc($n = 3, $default = [], $which = 'segment')
    {
        if (! is_numeric($n)) {
            return $default;
        }

        if (isset($this->keyval[$which], $this->keyval[$which][$n])) {
            return $this->keyval[$which][$n];
        }

        $total_segments = "total_{$which}s";
        $segment_array = "{$which}_array";

        if ($this->{$total_segments}() < $n) {
            return (count($default) === 0) ? [] : array_fill_keys($default, null);
        }

        $segments = array_slice($this->{$segment_array}(), ($n - 1));
        $i = 0;
        $lastval = '';
        $retval = [];
        foreach ($segments as $seg) {
            if ($i % 2) {
                $retval[$lastval] = $seg;
            } else {
                $retval[$seg] = null;
                $lastval = $seg;
            }

            ++$i;
        }

        if (count($default) > 0) {
            foreach ($default as $val) {
                if (! array_key_exists($val, $retval)) {
                    $retval[$val] = null;
                }
            }
        }

        // Cache the array for reuse
        isset($this->keyval[$which]) or $this->keyval[$which] = [];
        $this->keyval[$which][$n] = $retval;

        return $retval;
    }

    // --------------------------------------------------------------------

    /**
     * Assoc to URI
     *
     * Generates a URI string from an associative array.
     *
     * @param array $array Input array of key/value pairs
     *
     * @return string URI string
     */
    public function assoc_to_uri($array)
    {
        $temp = [];
        foreach ((array) $array as $key => $val) {
            $temp[] = $key;
            $temp[] = $val;
        }

        return implode('/', $temp);
    }

    // --------------------------------------------------------------------

    /**
     * Slash segment
     *
     * Fetches an URI segment with a slash.
     *
     * @param int    $n     Index
     * @param string $where Where to add the slash ('trailing' or 'leading')
     *
     * @return string
     */
    public function slash_segment($n, $where = 'trailing')
    {
        return $this->_slash_segment($n, $where, 'segment');
    }

    // --------------------------------------------------------------------

    /**
     * Slash routed segment
     *
     * Fetches an URI routed segment with a slash.
     *
     * @param int    $n     Index
     * @param string $where Where to add the slash ('trailing' or 'leading')
     *
     * @return string
     */
    public function slash_rsegment($n, $where = 'trailing')
    {
        return $this->_slash_segment($n, $where, 'rsegment');
    }

    // --------------------------------------------------------------------

    /**
     * Internal Slash segment
     *
     * Fetches an URI Segment and adds a slash to it.
     *
     * @used-by    CI_URI::slash_segment()
     * @used-by    CI_URI::slash_rsegment()
     *
     * @param int    $n     Index
     * @param string $where Where to add the slash ('trailing' or 'leading')
     * @param string $which Array name ('segment' or 'rsegment')
     *
     * @return string
     */
    protected function _slash_segment($n, $where = 'trailing', $which = 'segment')
    {
        $leading = $trailing = '/';

        if ($where === 'trailing') {
            $leading = '';
        } elseif ($where === 'leading') {
            $trailing = '';
        }

        return $leading . $this->{$which}($n) . $trailing;
    }

    // --------------------------------------------------------------------

    /**
     * Segment Array
     *
     * @return array CI_URI::$segments
     */
    public function segment_array()
    {
        return $this->segments;
    }

    // --------------------------------------------------------------------

    /**
     * Routed Segment Array
     *
     * @return array CI_URI::$rsegments
     */
    public function rsegment_array()
    {
        return $this->rsegments;
    }

    // --------------------------------------------------------------------

    /**
     * Total number of segments
     *
     * @return int
     */
    public function total_segments()
    {
        return count($this->segments);
    }

    // --------------------------------------------------------------------

    /**
     * Total number of routed segments
     *
     * @return int
     */
    public function total_rsegments()
    {
        return count($this->rsegments);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch URI string
     *
     * @return string CI_URI::$uri_string
     */
    public function uri_string()
    {
        return $this->uri_string;
    }

    /**
     * Remove Invisible Characters
     *
     * This prevents sandwiching null characters
     * between ascii characters, like Java\0script.
     *
     * @param    string
     * @param    bool
     * @param mixed $str
     * @param mixed $url_encoded
     *
     * @return string
     */
    public static function remove_invisible_characters($str, $url_encoded = true)
    {
        $non_displayables = [];

        // every control character except newline (dec 10),
        // carriage return (dec 13) and horizontal tab (dec 09)
        if ($url_encoded) {
            $non_displayables[] = '/%0[0-8bcef]/';    // url encoded 00-08, 11, 12, 14, 15
            $non_displayables[] = '/%1[0-9a-f]/';    // url encoded 16-31
        }

        $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';    // 00-08, 11, 12, 14-31, 127

        do {
            $str = preg_replace($non_displayables, '', $str, -1, $count);
        } while ($count);

        return $str;
    }
}
