<?php

use Neo\NeoFrame;

/**
 * 返回NeoFrame实例
 *
 * @return NeoFrame
 */
function neo()
{
    return NeoFrame::getInstance();
}

/**
 * Microtime start capture.
 */
function timerStart()
{
    // start the page generation timer
    define('TIMESTART', microtime(true));

    // set the current unix timestamp
    define('TIMENOW', time());
}

/**
 * 获取某个断点的堆栈
 *
 * @param int $index
 * @param int $limit
 *
 * @return array
 */
function getDebugBacktrace($index = 0,$limit = 0)
{
    $backtrace = debug_backtrace((PHP_VERSION_ID < 50306) ? 2 : DEBUG_BACKTRACE_IGNORE_ARGS, $limit);

    return $index ? $backtrace[$index] : $backtrace;
}

/**
 * 返回翻译后的短语
 *
 * @param string $text 待翻译的短语
 *
 * @return string 已经翻译的短语
 */
function __(string $text)
{
    return \Neo\I18n::__($text);
}

/**
 * 显示翻译后的短语
 *
 * @param string $text 待翻译的短语
 */
function _e(string $text)
{
    echo __($text);
}

/**
 * 返回格式化后的已经翻译的短语
 *
 * @return string 已经翻译的短语
 */
function __f()
{
    $args = func_get_args();

    if (empty($args)) {
        return '';
    }
    if (count($args) == 1) {
        return __($args[0]);
    }

    $str = $args[0];
    unset($args[0]);

    return vsprintf(__($str), $args);
}

/**
 * @param $ex
 *
 * @return array
 */
function processExceptionForNeolog($ex)
{
    if (! $ex || ! $ex->getFile()) {
        return [];
    }

    $traces[] = str_ireplace(\Neo\NeoFrame::getAbsPath(), '', "{$ex->getFile()}({$ex->getLine()})");

    foreach ($ex->getTrace() as $trace) {
        if (isset($trace['line'])) {
            // 忽略composer组件
            if (stripos($trace['file'], 'vendor/') !== false) {
                break;
            }
            $traces[] = str_ireplace(
                \Neo\NeoFrame::getAbsPath(),
                '',
                "{$trace['file']}({$trace['line']}): {$trace['function']}()"
            );
        }
    }

    return [
        'exception_message' => $ex->getMessage(),
        'exception_code' => $ex->getCode(),
        'exception_traces' => $traces,
    ];
}


/**
 * 获取时区
 *
 * @return string
 */
function getDatetimeZone()
{
    if (defined('DATETIME_ZONE') && DATETIME_ZONE) {
        return DATETIME_ZONE;
    }
    return date_default_timezone_get() ?: 'UTC';
}

