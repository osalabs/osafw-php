<?php
/*
Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
 */

class DateUtils {
    public const int MINUTE_SECONDS   = 60; #seconds in one minute
    public const int HALFHOUR_SECONDS = 1800; #seconds in 30 minutes
    public const int HOUR_SECONDS     = 3600; #seconds in one hour
    public const int DAY_SECONDS      = 86400; #seconds in one day
    public const int WEEK_SECONDS     = 604800; #seconds in one day

    public static int $DATE_FORMAT = 1; //0 - DD/MM/YYYY (Europe), 1-MM/DD/YYYY (USA)
    public static string $DATE_FORMAT_STR = 'MM/DD/YY'; #or DD/MM/YY
    public static int $TIME_FORMAT = 1; #0 - HH:MM (Europe), 1-HH:MM AM/PM (USA)
    public static string $TIME_FORMAT_STR = 'HH:MM'; #or HH:MM AM/PM

    ##### from UNIX time (epoch seconds) to YYYY-MM-DD HH:MM:SS
    # if $is_date_only - return only year-mon-day (no hh mm ss)
    public static function Unix2SQL($unixtime = null, $is_date_only = false): string {
        if (is_null($unixtime)) {
            $unixtime = time();
        }

        list($sec, $min, $hour, $mday, $mon, $year, $wday, $yday, $isdst) = localtime($unixtime);
        $year += 1900;
        $mon  += 1;

        if (strlen($mday) < 2) {
            $mday = "0" . $mday;
        }

        if (strlen($mon) < 2) {
            $mon = "0" . $mon;
        }

        if (strlen($hour) < 2) {
            $hour = "0" . $hour;
        }

        if (strlen($min) < 2) {
            $min = "0" . $min;
        }

        if (strlen($sec) < 2) {
            $sec = "0" . $sec;
        }

        if ($is_date_only) {
            return "$year-$mon-$mday";
        } else {
            return "$year-$mon-$mday $hour:$min:$sec";
        }
    }

    # return current datetime in SQL format YYYY-MM-DD HH:MM:SS
    # convenience alias of Unix2SQL()
    public static function now(): string {
        return self::Unix2SQL();
    }

    ##### from YYYY-MM-DD to UNIX time (epoch seconds)
    public static function SQL2Unix($s): false|int {
        if (empty($s)) {
            return 0;
        }
        if (preg_match("/^\d+$/", $s)) {
            #all numbers - already in unix seconds
            return (int)$s;
        }

        preg_match("/^(\d+)-(\d+)-(\d+)/", $s, $matches);
        list($year, $mon, $day) = array($matches[1], $matches[2], $matches[3]);

        //time
        list($hour, $min, $sec) = array(0, 0, 0);
        if (preg_match("/(\d+):(\d+):(\d+)$/", $s, $matches)) {
            list($hour, $min, $sec) = array($matches[1], $matches[2], $matches[3]);
        }

        return @mktime($hour, $min, $sec, $mon, $day, $year);
    }

    // *****************************************
    // from YYYY-MM-DD to date string
    // params: SQL Date, Format (if 1 - better human format), Add HH:MM:SS
    public static function SQL2Str($s, $human_format = 0, $add_hms = 0): string {
        if (!strlen($s) || $s == '0000-00-00' || $s == '0000-00-00 00:00:00') {
            return '';
        }

        $unixtime = self::SQL2Unix($s);

        $time_format_str = '';
        if ($add_hms) {
            if (self::$TIME_FORMAT || $human_format) {
                $time_format_str = ' h:i a';
            } else {
                $time_format_str = ' H:i';
            }
        }

        if ($human_format) {
            if (self::$DATE_FORMAT) {
                // MM/DD/YYYY
                return date("M d Y" . self::$TIME_FORMAT_STR, $unixtime);

            } else {
                // DD/MM/YYYY
                return date("d M Y" . self::$TIME_FORMAT_STR, $unixtime);
            }
        } else {
            if (self::$DATE_FORMAT) {
                // MM/DD/YYYY
                return date("m/d/Y" . self::$TIME_FORMAT_STR, $unixtime);

            } else {
                // DD/MM/YYYY
                return date("d/m/Y" . self::$TIME_FORMAT_STR, $unixtime);
            }
        }
    }

    public static function Date2SQL(DateTime $dt): string {
        return $dt->format('Y-m-d');
    }

    /**
     * return true if string is date in format MM/DD/YYYY or DD/MM/YYYY
     * @param string $str date string
     * @return bool
     */
    public static function isDateStr(string $str): bool {
        return (bool)preg_match("/^\d{1,2}\/\d{1,2}\/\d{4}$/", $str);
    }

    public static function Date2Str(DateTime $dt): string {
        return $dt->format(self::$DATE_FORMAT_STR);
    }

    /**
     * return datetime in ISO 8601 format YYYY-MM-DDTHH:MM:SSZ (UTC timezone) from any format
     * example: 2024-09-26T15:32:58Z
     * @param mixed $date DateTime OR string in SQL format OR unix timestamp
     * @return string|null return null if date is not valid
     */
    public static function date2iso(mixed $date): ?string {
        $dt = self::f2date($date);
        return $dt?->format('c');
    }

    // from date string to YYYY-MM-DD
    public static function Str2SQL($s): ?string {
        if (is_null($s)) {
            return null; #keep passed null as is, so it will go to db as NULL
        }
        if (!strlen($s)) {
            return '';
        }

        list($day, $month, $year) = self::ParseStrToDate($s);
        if (strlen($day) < 2) {
            $day = "0" . $day;
        }

        if (strlen($month) < 2) {
            $month = "0" . $month;
        }

        return "$year-$month-$day";
    }

    public static function Str2Unix($str): int {
        $dt = new \DateTime(strval($str));
        return $dt->getTimestamp();
    }

    /**
     * convert datetime string to date only string
     * Example: 1/17/2023 12:00:00 AM => 1/17/2023
     * @param string $str datetime string
     * @return string date only string
     */
    public static function Str2DateOnly(string $str): string {
        $result = $str;
        $dt     = self::f2date($str);
        if ($dt) {
            $result = $dt->format('Y-m-d');
        }
        return $result;
    }

    // anything to date, if not valid - return null
    public static function f2date($s): ?DateTime {
        if ($s instanceof DateTime) {
            return $s;
        }
        if (empty($s) || !is_string($s)) {
            return null;
        }
        if (is_numeric($s) || preg_match('/^\d+$/', $s)) {
            return new DateTime('@' . $s); //unix timestamp
        }
        try {
            return new DateTime($s);
        } catch (Exception $e) {
            return null;
        }
    }

    // from date string to array (day, month, year)
    public static function ParseStrToDate($inDate, $date_format = ''): array {

        if (!strlen($date_format)) {
            $date_format = self::$DATE_FORMAT; //set global format if date_format not provided
        }


        preg_match("/^\s*(\d+)\/(\d+)\/(\d+)\s*$/", $inDate, $matches);

        if ($date_format) {
            // MM/DD/YYYY
            return array(intval($matches[2]), intval($matches[1]), intval($matches[3]));

        } else {
            // DD/MM/YYYY
            return array(intval($matches[1]), intval($matches[2]), intval($matches[3]));
        }
    }

    #input: 0-86400 (daily time in seconds)
    #output: HH:MM or empty string if 0
    public static function int2timestr(int $i): string {
        if (!$i) {
            return '';
        }

        $h = floor($i / self::HOUR_SECONDS);
        $m = floor(($i - $h * self::HOUR_SECONDS) / self::MINUTE_SECONDS);
        if ($m < 10) {
            $m = '0' . $m;
        }

        return $h . ':' . $m;
    }

    #input: HH:MM or HH
    #output: 0-86400 (daily time in seconds)
    public static function timestr2int(string $hhmm): float|int {
        $result = 0;
        $ahhmm  = explode(':', $hhmm);
        if (count($ahhmm) == 2) {
            $result = (int)($ahhmm[0]) * self::HOUR_SECONDS + (int)($ahhmm[1]) * self::MINUTE_SECONDS;
        } elseif (count($ahhmm) == 1) {
            $result = (int)($ahhmm[0]) * self::HOUR_SECONDS;
        }

        return $result;
    }

    /**
     * return true if datetime(in unix format) earlier than now-$seconds
     * @param int $unixtime epoch seconds
     * @param int $seconds
     * @return boolean
     */
    public static function isExpired(int $unixtime, int $seconds): bool {
        return $unixtime < (time() - $seconds);
    }

    /**
     * return true if datetime(in sql format) earlier than now-$seconds
     * @param string|null $sql_datetime YYYY-MM-DD HH:MM:SS
     * @param int $seconds
     * @return boolean
     */
    public static function isSQLExpired(?string $sql_datetime, int $seconds): bool {
        return self::isExpired(self::SQL2Unix($sql_datetime), $seconds);
    }

    // return time difference between two second values
    public static function difference($from, $to): string {
        $from       = new DateTime($from);
        $to         = new DateTime($to);
        $difference = $from->diff($to);

        $string = '';
        if ($difference->h) {
            $string .= $difference->h . " h ";
        }
        if ($difference->i) {
            $string .= $difference->i . " m ";
        }
        if ($difference->s) {
            $string .= $difference->s . " s";
        }

        return trim($string);
    }

    // return time diffference between two date values in shorter format:
    // if less than 60s - XXs
    // if less than 60m - XXm
    // if more than 1h - XXh
    public static function differenceShort($from, $to): string {
        $from       = new DateTime($from);
        $to         = new DateTime($to);
        $difference = $from->diff($to);

        $string = '';
        if ($difference->h) {
            $min_dec = ($difference->i / self::MINUTE_SECONDS);
            $string  .= round($difference->h + $min_dec, 1) . "h";
        } elseif ($difference->i) {
            $sec_dec = ($difference->s / self::MINUTE_SECONDS);
            $string  .= round($difference->i + $sec_dec, 1) . "m";
        } else {
            $string .= $difference->s . "s";
        }

        return trim($string);
    }

    # return time difference in days between two datetimes (negative if $to is earlier than $from)
    # to count diff for timestamps:
    #    DateUtils::differenceDays(DateUtils::Unix2SQL($from_seconds), DateUtils::Unix2SQL($to_seconds))
    # to count calendar difference convert to dates without time (returns 1 if dates are diff even time diff 1 sec like 23:59:59 and 00:00:00):
    #    DateUtils::differenceDays(DateUtils::Unix2SQL($from_seconds, true), DateUtils::Unix2SQL($to_seconds, true))
    public static function differenceDays($from, $to): false|int {
        $from     = new DateTime($from);
        $to       = new DateTime($to);
        $interval = $from->diff($to);
        $result   = $interval->days;
        if ($result && $interval->invert) {
            $result = -$result;
        }
        return $result;
    }

}
