<?php

namespace foreverdjf\log\Neo;

/**
 * 国际化
 */
class I18n
{
    /**
     * 获取缺省语言
     *
     * @param string $languages_dir 语言目录
     * @param string $locale        本地化语言
     */
    public static function loadDefaultLanguage(?string $languages_dir = null, $locale = null)
    {
        if (! $languages_dir) {
            return;
        }

        $locale || $locale = NeoFrame::language();

        static::loadLanguage("{$languages_dir}/{$locale}.php");
    }

    /**
     * 加载语言文件
     *
     * @uses $l10n Gets list of domain translated string objects
     *
     * @param string $lanFile 语言文件
     *
     * @return bool True on success, false on failure
     */
    public static function loadLanguage($lanFile)
    {
        if (! is_readable($lanFile)) {
            return false;
        }

        neo()->i18n = include $lanFile;

        return true;
    }

    /**
     * 翻译短语
     *
     * @param string $text 待翻译的短语
     *
     * @return string 已经翻译的短语
     */
    public static function translate($text)
    {
        $i18n = neo()->i18n;

        return $i18n[$text] ?? $text;
    }

    /**
     * 返回翻译后的短语
     *
     * @param string $text 待翻译的短语
     *
     * @return string 已经翻译的短语
     */
    public static function __($text)
    {
        return static::translate($text);
    }

    /**
     * 显示翻译后的短语
     *
     * @param string $text 待翻译的短语
     */
    public static function _e($text)
    {
        echo __($text);
    }
}
