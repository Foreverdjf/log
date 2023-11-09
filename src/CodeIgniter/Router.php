<?php

/**
 * Router Class
 *
 * Parses URIs and determines routing
 *
 * @category       Libraries
 * @see           http://codeigniter.com/user_guide/general/routing.html
 */
class CI_Router
{
    /**
     * @var CI_URI
     */
    private $uri;

    /**
     * CI_Config class object
     *
     * @var object
     */
    public $config;

    /**
     * List of routes
     *
     * @var array
     */
    public $routes = [];

    /**
     * Current class name
     *
     * @var string
     */
    public $class = '';

    /**
     * Current method name
     *
     * @var string
     */
    public $method = 'index';

    /**
     * Sub-directory that contains the requested controller class
     *
     * @var string
     */
    public $directory = '';

    /**
     * Default controller (and method if specific)
     *
     * @var string
     */
    public $default_controller;

    /**
     * Controller directory
     *
     * @var string
     */
    public $controllers_dir;

    /**
     * Class constructor
     *
     * Runs the route mapping function.
     *
     * @param CI_URI $URI
     * @param string $controllers_dir
     * @param array  $routes
     */
    public function __construct($URI, $controllers_dir, $routes)
    {
        $this->uri = $URI;

        $this->controllers_dir = $controllers_dir;

        $this->_set_routing($routes);
    }

    // --------------------------------------------------------------------

    /**
     * Set route mapping
     *
     * Determines what should be served based on the URI request,
     * as well as any "routes" that have been set in the routing config file.
     *
     * @param array $routes
     */
    protected function _set_routing($routes)
    {
        $this->routes = $routes;

        $this->routes['default_controller'] = 'Index';
        $this->routes['404_override'] = '';

        // Set the default controller so we can display it in the event
        // the URI doesn't correlated to a valid controller.
        $this->default_controller = $this->routes['default_controller'];

        // Fetch the complete URI string
        $this->uri->_fetch_uri_string();

        // Is there a URI string? If not, the default controller specified in the "routes" file will be shown.
        if ($this->uri->uri_string == '') {
            $this->_set_default_controller();

            return;
        }

        $this->uri->_remove_url_suffix(); // Remove the URL suffix
        $this->uri->_explode_segments(); // Compile the segments into an array
        $this->_parse_routes(); // Parse any custom routing that may exist
        $this->uri->_reindex_segments(); // Re-index the segment array so that it starts with 1 rather than 0
    }

    // --------------------------------------------------------------------

    /**
     * Set default controller
     */
    protected function _set_default_controller()
    {
        if (empty($this->default_controller)) {
            CodeIgniter::show_error('Unable to determine what should be displayed. A default route has not been specified in the routing file.');
        }

        // Is the method being specified?
        $class = '';
        $method = '';
        if (sscanf($this->default_controller, '%[^/]/%s', $class, $method) !== 2) {
            $method = 'index';
        }

        $this->set_class($class);
        $this->set_method($method);

        // Assign routed segments, index starting from 1
        $this->uri->rsegments = [
            1 => $class,
            2 => $method,
        ];
    }

    // --------------------------------------------------------------------

    /**
     * Set request route
     *
     * Takes an array of URI segments as input and sets the class/method
     * to be called.
     *
     * @param array $segments URI segments
     */
    protected function _set_request($segments = [])
    {
        $segments = $this->_validate_request($segments);

        if (empty($segments)) {
            $this->_set_default_controller();

            return;
        }

        $this->set_class($segments[0]);

        isset($segments[1]) or $segments[1] = 'index';
        $this->set_method($segments[1]);

        // Update our "routed" segment array to contain the segments.
        // Note: If there is no custom routing, this array will be
        // identical to $this->uri->segments
        $this->uri->rsegments = $segments;
    }

    // --------------------------------------------------------------------

    /**
     * Validate request
     *
     * Attempts validate the URI request and determine the controller path.
     *
     * @param array $segments URI segments
     *
     * @return array URI segments
     */
    protected function _validate_request($segments)
    {
        $c = count($segments);
        // Loop through our segments and return as soon as a controller
        // is found or when such a directory doesn't exist
        while ($c-- > 0) {
            $temp = $this->directory . ucfirst($segments[0]);

            if (! file_exists($this->controllers_dir . '/' . $temp . '.php') && is_dir($this->controllers_dir . '/' . $temp)) {
                $this->set_directory(ucfirst(array_shift($segments)), true);
                continue;
            }

            return $segments;
        }

        // This means that all segments were actually directories
        return $segments;
    }

    // --------------------------------------------------------------------

    /**
     * Parse Routes
     *
     * Matches any routes that may exist in the config/routes.php file
     * against the URI to determine if the class/method need to be remapped.
     */
    protected function _parse_routes()
    {
        // Turn the segment array into a URI string
        $uri = implode('/', $this->uri->segments);

        // Is there a literal match?  If so we're done
        if (isset($this->routes[$uri]) && is_string($this->routes[$uri])) {
            $this->_set_request(explode('/', $this->routes[$uri]));

            return;
        }

        // Loop through the route array looking for wild-cards
        foreach ($this->routes as $key => $val) {
            // Convert wild-cards to RegEx
            $key = str_replace([':any', ':num'], ['.*', '[0-9]+'], $key);
            // Does the RegEx match?
            if (preg_match('#^' . $key . '$#', $uri, $matches)) {
                // Are we using callbacks to process back-references?
                if (! is_string($val) && is_callable($val)) {
                    // Remove the original string from the matches array.
                    array_shift($matches);

                    // Execute the callback using the values in matches as its parameters.
                    $val = call_user_func_array($val, $matches);
                }
                // Are we using the default routing method for back-references?
                elseif (strpos($val, '$') !== false && strpos($key, '(') !== false) {
                    $val = preg_replace('#^' . $key . '$#', $val, $uri);
                }

                $this->_set_request(explode('/', $val));

                return;
            }
        }

        // If we got this far it means we didn't encounter a
        // matching route so we'll set the site default route
        $this->_set_request(array_values($this->uri->segments));
    }

    // --------------------------------------------------------------------

    /**
     * Set class name
     *
     * @param string $class Class name
     */
    public function set_class($class)
    {
        $this->class = str_replace(['/', '.'], '', $class);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch the current class
     *
     * @return string
     */
    public function fetch_class()
    {
        return $this->class;
    }

    // --------------------------------------------------------------------

    /**
     * Set method name
     *
     * @param string $method Method name
     */
    public function set_method($method)
    {
        $this->method = $method;
    }

    // --------------------------------------------------------------------

    /**
     * Fetch the current method
     *
     * @return string
     */
    public function fetch_method()
    {
        return ($this->method === $this->fetch_class()) ? 'index' : $this->method;
    }

    // --------------------------------------------------------------------

    /**
     * Set directory name
     *
     * @param string $dir    Directory name
     * @param bool   $append Whether we're appending rather then setting the full value
     */
    public function set_directory($dir, $append = false)
    {
        if ($append !== true || empty($this->directory)) {
            $this->directory = str_replace('.', '', trim($dir, '/')) . '/';
        } else {
            $this->directory .= str_replace('.', '', trim($dir, '/')) . '/';
        }
    }

    // --------------------------------------------------------------------

    /**
     * Fetch directory
     *
     * Feches the sub-directory (if any) that contains the requested
     * controller class.
     *
     * @return string
     */
    public function fetch_directory()
    {
        return $this->directory;
    }
}
