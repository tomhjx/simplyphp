<?php

namespace Core\Support;

use Moment\Moment;

class Time
{
    private static $_instance = null;

    const DEFAULT_TIMEZONE = 'Asia/Shanghai';

    const FORMAT_ATOM = 'Y-m-d\TH:i:sP'; // 2005-08-15T15:52:01+00:00
    const FORMAT_COOKIE = 'l, d-M-y H:i:s T'; // Monday, 15-Aug-2005 15:52:01 UTC
    const FORMAT_ISO8601 = 'Y-m-d\TH:i:sO'; // 2005-08-15T15:52:01+0000
    const FORMAT_RFC822 = 'D, d M y H:i:s O'; // Mon, 15 Aug 05 15:52:01 +0000
    const FORMAT_RFC850 = 'l, d-M-y H:i:s T'; // Monday, 15-Aug-05 15:52:01 UTC
    const FORMAT_RFC1036 = 'D, d M y H:i:s O'; // Mon, 15 Aug 05 15:52:01 +0000
    const FORMAT_RFC1123 = 'D, d M Y H:i:s O'; // Mon, 15 Aug 2005 15:52:01 +0000
    const FORMAT_RFC2822 = 'D, d M Y H:i:s O'; // Mon, 15 Aug 2005 15:52:01 +0000
    const FORMAT_RSS = 'D, d M Y H:i:s O'; // Mon, 15 Aug 2005 15:52:01 +0000
    const FORMAT_W3C = 'Y-m-d\TH:i:sP'; // 2005-08-15T15:52:01+00:00

    const FORMAT_NO_TZ_MYSQL = 'Y-m-d H:i:s'; // 2005-08-15 15:52:01
    const FORMAT_NO_TZ_NO_SECS = 'Y-m-d H:i'; // 2005-08-15 15:52
    const FORMAT_NO_TIME = 'Y-m-d'; // 2005-08-15

    public function __construct()
    {

    }

    public static function getInstance()
    {
        if (self::$_instance) {
            return self::$_instance;
        }
        self::$_instance = new self();
        return self::$_instance;
    }

    /**
     * 格式化时间
     *
     * @param string $date 日期字符串
     * @param string $format 日期格式
     * @param string $toTimezone 返回结果时区
     * @param string $timezone $date所属时区, 无时区日期字符串必填
     * @return string
     */
    public function format(string $date = 'now', string $format = self::FORMAT_ATOM, string $toTimezone = self::DEFAULT_TIMEZONE, string $timezone = self::DEFAULT_TIMEZONE): string
    {
        $m = new Moment($date, $timezone);
        return $m->setTimezone($toTimezone)->format($format);
    }

    /**
     * 获取unix 时间戳
     *
     * @param string $date 日期字符串
     * @param string $toTimezone 返回结果时区
     * @param string $timezone $date所属时区, 无时区日期字符串必填
     * @return string
     */
    public function unix(string $date = 'now', string $timezone = self::DEFAULT_TIMEZONE): string
    {
        return $this->format($date, 'U', self::DEFAULT_TIMEZONE, $timezone);
    }

}
