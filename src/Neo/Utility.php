<?php

namespace Neo;

/**
 * Class Utility
 */
class Utility
{
    /**
     * Returns whether we run on CLI or browser.
     *
     * @return bool
     */
    public static function isCli()
    {
        return PHP_SAPI === 'cli';
    }
    /**
     * AJAX Request
     *
     * @return bool
     */
    public static function isAjax()
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || $_REQUEST['ajax'] || $_REQUEST['AJAX'];
    }

    /**
     * Converts value to nonnegative integer.
     *
     * @param int $maybeint Data you wish to have converted to a nonnegative integer
     *
     * @return int An nonnegative integer
     */
    public static function absint($maybeint)
    {
        return abs(intval($maybeint));
    }

    /**
     * 格式化输出存储大小
     *
     * @param int $bytes    文件大小
     * @param int $decimals 精度
     *
     * @return string
     */
    public static function byteFormat( $bytes,  $decimals = 2)
    {
        $units = [
            'B' => 0,
            'KB' => 1,
            'MB' => 2,
            'GB' => 3,
            'TB' => 4,
            'PB' => 5,
            'EB' => 6,
            'ZB' => 7,
            'YB' => 8,
        ];

        $value = 0;
        $unit = '';
        if ($bytes > 0) {
            $pow = floor(log($bytes) / log(1024));
            $unit = array_search($pow, $units);

            // Calculate byte value by prefix
            $value = ($bytes / pow(1024, floor($units[$unit])));
        }

        // If decimals is not numeric or decimals is less than 0
        // then set default value
        if (! is_numeric($decimals) || $decimals < 0) {
            $decimals = 2;
        }

        // Format output
        return number_format($value, $decimals) . $unit;
    }

    /**
     * Verifies that an email is valid.
     *
     * Does not grok i18n domains. Not RFC compliant.
     *
     * @param string $email email address to verify
     *
     * @return bool
     */
    public static function isEmail( $email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? true : false;
    }

    /**
     * Verifies that a Chinese mobile is valid.
     * 手机号码只有11位，且以1开头
     *
     * @param string $mobile mobile to verify
     *
     * @return bool either false or true
     */
    public static function isChineseMobile($mobile)
    {
        return preg_match('/^1\d{10}$/', $mobile) ? true : false;
    }

    /**
     * Verifies that a hyperlink is valid
     *
     * @param string $link Hyperlink URL
     *
     * @return bool
     */
    public static function isLink( $link)
    {
        return filter_var($link, FILTER_VALIDATE_URL) ? true : false;
    }

    /**
     * Verifies that a IP address is valid
     *
     * @param string $ip IP address
     *
     * @return bool
     */
    public static function isIpAddress( $ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP) ? true : false;
    }

    /**
     * 多行文字转换为数组
     *
     * @param string $str
     *
     * @return array
     */
    public static function linesToArray($str)
    {
        $str = preg_replace(['/\r\n|\r/', '/\n+/'], PHP_EOL, trim($str));

        return array_map('trim', explode(PHP_EOL, $str));
    }

    /**
     * Get the class "basename" of the given object / class.
     *
     * @param mixed $class
     *
     * @return string
     */
    public static function getClassBasename($class)
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }

    /**
     * 通过管道异步打开进程
     *
     * @param string $cmd
     * @param string $exec
     */
    public static function toPipe($cmd, $exec = '')
    {
        pclose(popen($exec . $cmd . ' & ', 'r'));
    }


    /**
     * 获取服务器名称
     *
     * @return string
     */
    public static function gethostname()
    {
        return $_SERVER['NEO_HOST'] ? : gethostname();
    }
}
