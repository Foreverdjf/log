<?php

namespace Neo;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\RedisHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as Monologger;
use Neo\Exception\ResourceNotFoundException;

/**
 * Class NeoLog
 */
class NeoLog
{
    /**
     * @var array
     */
    private static $fileHandler = null;

    /**
     * @var StreamHandler
     */
    private static $streamHandler = null;

    /**
     * Adds a log record at the DEBUG level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $type    The log type
     * @param string $message The log message
     * @param mixed  $context The log context
     */
    public static function debug($type, $message, $context = null)
    {
        static::logit('debug', $type, $message, $context);
    }

    /**
     * Adds a log record at the INFO level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $type    The log type
     * @param string $message The log message
     * @param mixed  $context The log context
     */
    public static function info($type, $message, $context = null)
    {
        static::logit('info', $type, $message, $context);
    }

    /**
     * Adds a log record at the NOTICE level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $type    The log type
     * @param string $message The log message
     * @param mixed  $context The log context
     */
    public static function notice($type, $message, $context = null)
    {
        static::logit('notice', $type, $message, $context);
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $type    The log type
     * @param string $message The log message
     * @param mixed  $context The log context
     */
    public static function warn($type, $message, $context = null)
    {
        static::logit('warn', $type, $message, $context);
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $type    The log type
     * @param string $message The log message
     * @param mixed  $context The log context
     */
    public static function warning($type, $message, $context = null)
    {
        static::logit('warning', $type, $message, $context);
    }

    /**
     * Adds a log record at the ERROR level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $type    The log type
     * @param string $message The log message
     * @param mixed  $context The log context
     */
    public static function error($type, $message, $context = null)
    {
        static::logit('error', $type, $message, $context);
    }

    /**
     * Adds a log record at the CRITICAL level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $type    The log type
     * @param string $message The log message
     * @param mixed  $context The log context
     */
    public static function crit($type, $message, $context = null)
    {
        static::logit('crit', $type, $message, $context);
    }

    /**
     * Adds a log record at the ALERT level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $type    The log type
     * @param string $message The log message
     * @param mixed  $context The log context
     */
    public static function alert($type, $message, $context = null)
    {
        static::logit('alert', $type, $message, $context);
    }

    /**
     * Adds a log record at the EMERGENCY level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $type    The log type
     * @param string $message The log message
     * @param mixed  $context The log context
     */
    public static function emerg($type, $message, $context = null)
    {
        static::logit('emerg', $type, $message, $context);
    }

    /**
     * Adds a log record at the EMERGENCY level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $type    The log type
     * @param string $message The log message
     * @param mixed  $context The log context
     */
    public static function emergency($type, $message, $context = null)
    {
        static::logit('emergency', $type, $message, $context);
    }

    /**
     * 统一日志记录入口
     *
     * @param string $action
     * @param string $type
     * @param string $message
     * @param mixed  $context
     */
    private static function logit($action, $type, $message, $context)
    {
        static $number = 0;

        try {
            $logger = static::log($type);
            if ($logger == null) {
                return;
            }

            if (! is_array($context)) {
                $context = (array) $context;
            }

            // 按文件分隔日志时的文件名
            $context['type'] = $type ?: 'neo';

            // 获取日志记录在文件中的位置
            $context['traces'] = static::getTraces();
            $context['line'] = 'No.' . ($number++) . $context['traces'][0];

            $logger->{$action}($message, $context);
        } catch (\Exception $ex) {
            $args = processExceptionForNeolog($ex);

            $args['action'] = $action;
            $args['type'] = $type;
            $args['message'] = $message;
            $args['context'] = $context;

            $msg = static::formatDate() . "\t" . static::getLoggerId() . "\t" . json_encode(
                $args,
                JSON_UNESCAPED_UNICODE
            ) . PHP_EOL . PHP_EOL;
            error_log($msg, 3, static::getFileLogDir() . DIRECTORY_SEPARATOR . 'neologerror.log');

            unset($args, $msg);
        }
    }

    /**
     * 日志
     *
     * @param string $type
     *
     * @throws \Exception
     * @return null|Monologger
     */
    private static function log($type = '')
    {
        $type || $type = 'neo';

        $handlers = [];
        // 写到文件
        if (defined('NEO_LOGGER_FILE') && NEO_LOGGER_FILE) {
            $fileHandler = static::log2File($type);
            if ($fileHandler) {
                $handlers[] = $fileHandler;
            }
        }

        // 写到php://stderr
        if (defined('NEO_LOGGER_STDERR') && NEO_LOGGER_STDERR) {
            $streamHandler = static::log2Stream();
            if ($streamHandler) {
                $handlers[] = $streamHandler;
            }
        }

        if ($handlers) {
            $logger = new Monologger('neolog', $handlers);
            Monologger::setTimezone(static::dateTimeZone());
        } else {
            $logger = null;
        }

        return $logger;
    }

    /**
     * 创建一个文件日志处理器
     *
     * @param string $type 文件名
     *
     * @throws \Exception
     * @return \Monolog\Handler\AbstractHandler
     */
    private static function log2File($type)
    {
        // 是否只输出到一个日志文件，PERTYPE：每个type一个日志文件
        if (! (defined('NEOLOG_LOGGER_FILE_PERTYPE') && NEOLOG_LOGGER_FILE_PERTYPE)) {
            // 项目指定文件日志的文件名
            $type = defined('NEOLOG_LOGGER_FILE_TYPENAME') && NEOLOG_LOGGER_FILE_TYPENAME ? NEOLOG_LOGGER_FILE_TYPENAME : 'neo';
        }

        if (static::$fileHandler[$type]) {
            return static::$fileHandler[$type];
        }

        $fmt = defined('NEO_LOGGER_FILE_FORMATTER') && NEO_LOGGER_FILE_FORMATTER ? NEO_LOGGER_FILE_FORMATTER : 'json';

        $stream = new NeoLogRotatingFileHandler(
            static::getFileLogDir() . '/' . $type . '.log',
            10,
            static::getLoggerLevel(),
            true,
            0664
        );
        $stream->setFormatter(static::loggerFormatter($fmt));
        $stream->pushProcessor(new NeoLogFileProcessor());

        static::$fileHandler[$type] = $stream;

        return $stream;
    }

    /**
     * 创建一个流日志处理器
     *
     * @param string $type php://stderr
     *
     * @throws \Exception
     * @return \Monolog\Handler\AbstractHandler
     */
    private static function log2Stream($type = 'stderr')
    {
        if (static::$streamHandler) {
            return static::$streamHandler;
        }

        $stream = new StreamHandler('php://' . $type, static::getLoggerLevel());

        $SIMPLE_FORMAT = '[%loggertime%] %channel%.%level_name% %loggerid% %message% %context% %extra% %line%' . PHP_EOL;
        $stream->setFormatter(new LineFormatter($SIMPLE_FORMAT));

        static::$streamHandler = $stream;

        return $stream;
    }

    /**
     * 文本日志格式
     *
     * @param string $fmt
     *
     * @return JsonFormatter|LineFormatter
     */
    private static function loggerFormatter($fmt = 'line')
    {
        switch ($fmt) {
            case 'line':
                $SIMPLE_FORMAT = '[%loggertime%] %channel%.%level_name% %loggerid% %message% %context% %extra% %line%' . PHP_EOL;
                $formatter = new LineFormatter($SIMPLE_FORMAT);
                break;
            case 'json':
            default:
                $formatter = new JsonFormatter();
                break;
        }

        return $formatter;
    }

    /**
     * 获取日志记录在文件中的位置
     *
     * @return array
     */
    protected static function getTraces()
    {
        $traces = getDebugBacktrace();

        if (count($traces) < 6) {
            return [];
        }

        $traces = array_splice($traces, 3, count($traces) - 6);

        $lines = [];
        foreach ($traces as $trace) {
            $lines[] = str_replace(
                    NeoFrame::getAbsPath(),
                DIRECTORY_SEPARATOR,
                $trace['file']
            ) . ':' . (int) $trace['line'];
        }

        return $lines;
    }

    /**
     * 时区
     *
     * @return \DateTimeZone
     */
    protected static function dateTimeZone()
    {
        return new \DateTimeZone(getDatetimeZone());
    }

    /**
     * 格式化当前时间
     *
     * @param string $format
     *
     * @return false|string
     */
    public static function formatDate($format = 'Y-m-d H:i:s')
    {
        return date($format);
    }

    /**
     * 获取文件日志目录
     *
     * @return string
     */
    public static function getFileLogDir()
    {
        $dir = '/data/weblog/web_pc';
        // 使用文件记录日志
        if (defined('NEO_LOGGER_FILE') && NEO_LOGGER_FILE) {
            // 日志文件存放路径
            if (defined('NEO_LOGGER_DIR') && NEO_LOGGER_DIR) {
                $dir = NEO_LOGGER_DIR;
            }
            if (empty($dir)) {
                throw new ResourceNotFoundException(__('Logger dir cannot be null.'));
            }
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            if (! is_writeable($dir)) {
                throw new ResourceNotFoundException(__f('Logger dir %s cannot be writeable.', $dir));
            }
        }
        return $dir;
    }

    /**
     * 文件日志级别
     *
     * @return int
     */
    public static function getLoggerLevel()
    {
        if (defined('NEO_LOGGER_LEVEL') && NEO_LOGGER_LEVEL) {
            return NEO_LOGGER_LEVEL;
        }

        // DEBUG = 100;
        // INFO = 200;
        // NOTICE = 250;
        // WARNING = 300;
        // ERROR = 400;
        // CRITICAL = 500;
        // ALERT = 550;
        // EMERGENCY = 600;
        return 100;
    }

    /**
     * 生成日志ID
     *
     * @return string
     */
    public static function getLoggerId()
    {
        if (defined('NEO_LOGGER_ID') && NEO_LOGGER_ID) {
            return NEO_LOGGER_ID;
        }

        return sha1(uniqid(
            '',
            true
        ) . str_shuffle(str_repeat(
            '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            16
        )));
    }
}

/**
 * Class NeoLogProcessor
 */
class NeoLogProcessor
{
    /**
     * 添加更多内容
     *
     * @param array $record
     *
     * @return array
     */
    public function more(array $record)
    {
        $record['loggerid'] = NeoLog::getLoggerId();
        $record['loggertime'] = NeoLog::formatDate();
        $record['line'] = $record['context']['line'];
        $record['type'] = $record['context']['type'];
        unset($record['context']['line'], $record['context']['type']);
        return $record;
    }
}

/**
 * Class NeoLogFileProcessor
 */
class NeoLogFileProcessor extends NeoLogProcessor
{
    /**
     * @param array $record
     *
     * @return array
     */
    public function __invoke(array $record)
    {
        return $this->more($record);
    }
}

/**
 * RotatingFileHandler 不会rotate文件，故重写
 *
 * Class NeoLogRotatingFileHandler
 */
class NeoLogRotatingFileHandler extends RotatingFileHandler
{
    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        // on the first record written, if the log is new, we should rotate (once per day)
        if ($this->mustRotate === null) {
            $this->mustRotate = ! file_exists($this->url);
        }

        if ($this->mustRotate === true) {
            $this->rotate();
        }

        parent::write($record);
    }
}
