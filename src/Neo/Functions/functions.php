<?php

use Neo\NeoFrame;

/**
 * 返回NeoFrame实例
 *
 * @return \Neo\NeoFrame
 */
function neo()
{
    return \Neo\NeoFrame::getInstance();
}

/**
 * 获取基于某个时区的Carbon对象
 *
 * @param null|string $tz
 *
 * @return \Carbon\Carbon|\Carbon\CarbonInterface
 */
function carbon($tz = null)
{
    $tz || $tz = getDatetimeZone();

    return \Carbon\Carbon::now($tz);
}

/**
 * 格式化 Request Parameters
 *
 * @param string $source
 * @param array  $variables
 *
 * @return array
 */
function input( $source,  $variables)
{
    $neo = neo();

    $params = $neo->request->cleanArrayGPC($source, $variables);
    $neo->input = array_merge($neo->input, $params);

    return $neo->input;
}

/**
 * 格式化 Request Parameters
 *
 * @param string $source
 * @param string $varname
 * @param int    $vartype
 *
 * @return mixed
 */
function inputOne( $source,  $varname,  $vartype = TYPE_NOCLEAN)
{
    $neo = neo();

    $neo->input[$varname] = $neo->request->cleanGPC($source, $varname, $vartype);

    return $neo->input[$varname];
}

/**
 * 格式化一个数组
 *
 * @param array $source
 * @param array $variables
 *
 * @return array
 */
function inputArray(array $source, array $variables)
{
    $neo = neo();

    return $neo->request->cleanArray($source, $variables);
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
        if ($trace['line']) {
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

/**
 * 按照预订格式显示时间
 *
 * @param string $format    格式
 * @param int    $timestamp 时间
 * @param int    $yestoday  时间显示模式：0：标准的年月日模式，1：今天/昨天模式，2：1分钟，1小时，1天等更具体的模式
 *
 * @return string 格式化后的时间串
 */
function formatDate($format = 'Ymd', $timestamp = 0, $yestoday = 0)
{
    $carbon = carbon();

    if ($timestamp) {
        $microsecond = $carbon->microsecond;
        $carbon->setTimestamp($timestamp)
            ->setMicrosecond($microsecond);
    } else {
        $timestamp = $carbon->timestamp;
    }

    $timenow = time();

    if ($yestoday == 0) {
        $returndate = $carbon->format($format);
    } elseif ($yestoday == 1) {
        if (date('Y-m-d', $timestamp) == date('Y-m-d', $timenow)) {
            $returndate = __('Today');
        } elseif (date('Y-m-d', $timestamp) == date('Y-m-d', $timenow - 86400)) {
            $returndate = __('Yesterday');
        } else {
            $returndate = $carbon->format($format);
        }
    } else {
        $timediff = $timenow - $timestamp;

        if ($timediff < 0) {
            $returndate = $carbon->format($format);
        } elseif ($timediff < 60) {
            $returndate = __('1 minute before');
        } elseif ($timediff < 3600) {
            $returndate = sprintf(__('%d minutes before'), intval($timediff / 60));
        } elseif ($timediff < 7200) {
            $returndate = __('1 hour before');
        } elseif ($timediff < 86400) {
            $returndate = sprintf(__('%d hours before'), intval($timediff / 3600));
        } elseif ($timediff < 172800) {
            $returndate = __('1 day before');
        } elseif ($timediff < 604800) {
            $returndate = sprintf(__('%d days before'), intval($timediff / 86400));
        } elseif ($timediff < 1209600) {
            $returndate = __('1 week before');
        } elseif ($timediff < 3024000) {
            $returndate = sprintf(__('%d weeks before'), intval($timediff / 604900));
        } elseif ($timediff < 15552000) {
            $returndate = sprintf(__('%d months before'), intval($timediff / 2592000));
        } else {
            $returndate = $carbon->format($format);
        }
    }

    return $returndate;
}



