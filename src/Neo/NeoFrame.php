<?php

namespace Neo;

use Neo\Exception\ResourceNotFoundException;
use Neo\Http\Request;

/**
 * NeoFrame Container
 */
class NeoFrame implements \ArrayAccess
{
    /**
     * The base path for the system
     *
     * @var string
     */
    protected static $ABSPATH = '';

    /**
     * The container's bindings.
     *
     * @var array
     */
    protected $bindings = [];

    /**
     * Request object.
     *
     * @var \Neo\Http\Request
     */
    public $request;

    /**
     * User Information
     *
     * @var array
     */
    public $user = [];

    /**
     * User ID
     *
     * @var int
     */
    public $userid = 0;

    /**
     * Array of data that has been cleaned by the input cleaner.
     *
     * @var array
     */
    public $input = [];

    /**
     * NeoFrame
     *
     * @var \Neo\NeoFrame
     */
    private static $instance = null;

    /**
     * NeoFrame constructor.
     *
     * @param null|string $absPath
     */
    public function __construct(string $absPath = null)
    {
        timerStart();

        // Error Handler
        set_error_handler('neoErrorHandler', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

        // Exception Handler
        set_exception_handler('neoExceptionHandler');

        // 如果定义了常量
        if (empty($absPath) && defined('ABSPATH') && ABSPATH) {
            $absPath = ABSPATH;
        }

        if ($absPath) {
            $this->setAbsPath($absPath);
        }

        static::setInstance($this);

        // 检查日志文件存放目录
        $this->checkLoggerDir();

        // HTTP Request
        $this->request = new Request();
    }

    /**
     * Set the base path for the application.
     *
     * @param string $absPath
     */
    public function setAbsPath($absPath)
    {
        static::$ABSPATH = rtrim($absPath, '\/');

        $this->setPaths();
    }

    /**
     * Get the base path for the application.
     *
     * @return string
     */
    public static function getAbsPath()
    {
        return static::$ABSPATH;
    }

    /**
     * 设置多个目录
     */
    public function setPaths()
    {
        // 日志文件存放路径
        if (defined('NEO_LOGGER_DIR') && NEO_LOGGER_DIR) {
            $this->bindings['logger_dir'] = NEO_LOGGER_DIR;
        }
    }

    /**
     * 检查日志文件存放目录
     */
    private function checkLoggerDir()
    {
        $dir = $this->bindings['logger_dir'];

        // 使用文件记录日志
        if (defined('NEO_LOGGER_FILE') && NEO_LOGGER_FILE) {
            if (empty($dir)) {
                throw new ResourceNotFoundException(__('Logger dir cannot be null.'));
            }

            if (! is_dir($dir) || ! is_writeable($dir)) {
                throw new ResourceNotFoundException(__f('Logger dir %s cannot be writeable.', $dir));
            }
        }
    }

    /**
     * 获取系统缺省的Charset，默认是utf-8
     *
     * @param  string $charset
     * @return string Charset
     */
    public static function charset($charset = 'utf-8')
    {
        return (defined('NEO_CHARSET') && NEO_CHARSET) ? NEO_CHARSET : $charset;
    }

    /**
     * Get instance
     *
     * @return static
     */
    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Set instance
     *
     * @param null|NeoFrame $container
     *
     * @return static
     */
    public static function setInstance(NeoFrame $container = null)
    {
        return static::$instance = $container;
    }

    /**
     * Determine if a given offset exists.
     *
     * @param string $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->bindings);
    }

    /**
     * Get the value at a given offset.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->bindings[$key];
    }

    /**
     * Set the value at a given offset.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function offsetSet($key, $value)
    {
        $this->bindings[$key] = $value;
    }

    /**
     * Unset the value at a given offset.
     *
     * @param string $key
     */
    public function offsetUnset($key)
    {
        unset($this->bindings[$key]);
    }

    /**
     * Dynamically access container services.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this[$key];
    }

    /**
     * Dynamically set container services.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function __set($key, $value)
    {
        $this[$key] = $value;
    }
}
