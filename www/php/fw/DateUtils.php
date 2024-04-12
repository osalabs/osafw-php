<?php
/*
Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
 */

class DateUtils {
    public const MINUTE_SECONDS   = 60; #seconds in one minute
    public const HALFHOUR_SECONDS = 1800; #seconds in 30 minutes
    public const HOUR_SECONDS     = 3600; #seconds in one hour
    public const DAY_SECONDS      = 86400; #seconds in one day
    public const WEEK_SECONDS     = 604800; #seconds in one day

    public static $DATE_FORMAT = 1; //0 - DD/MM/YYYY (Europe), 1-MM/DD/YYYY (USA)
    public static $DATE_FORMAT_STR = 'MM/DD/YY'; #or DD/MM/YY
    public static $TIME_FORMAT = 1; #0 - HH:MM (Europe), 1-HH:MM AM/PM (USA)
    public static $TIME_FORMAT_STR = 'HH:MM'; #or HH:MM AM/PM

    ##### from UNIX time (epoch seconds) to YYYY-MM-DD HH:MM:SS
    # if $is_date_only - return only year-mon-day (no hh mm ss)
    public static function Unix2SQL($unixtime = null, $is_date_only = false) {
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
    public static function now() {
        return self::Unix2SQL();
    }

    ##### from YYYY-MM-DD to UNIX time (epoch seconds)
    public static function SQL2Unix($s) {
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
    public static function SQL2Str($s, $human_format = 0, $add_hms = 0) {
        if (!strlen($s) || $s == '0000-00-00' || $s == '0000-00-00 00:00:00') {
            return '';
        }

        $unixtime = self::SQLDate2Unix($s);

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

    public static function Date2Str(DateTime $dt): string {
        return $dt->format(self::$DATE_FORMAT_STR);
    }

    // from date string to YYYY-MM-DD
    public static function Str2SQL($s) {
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

    // anything to date, if not valid - return null
    public static function f2date($s): ?DateTime {
        if (empty($s)) {
            return null;
        }
        if ($s instanceof DateTime) {
            return $s;
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
    public static function ParseStrToDate($inDate, $date_format = '') {

        if (!strlen($date_format)) {
            $date_format = self::$DATE_FORMAT; //set global format if date_format not provided
        }


        preg_match("/^\s*(\d+)\/(\d+)\/(\d+)\s*$/", $inDate, $matches);

        if ($date_format) {
            // MM/DD/YYYY
            return array($matches[2] + 0, $matches[1] + 0, $matches[3] + 0);

        } else {
            // DD/MM/YYYY
            return array($matches[1] + 0, $matches[2] + 0, $matches[3] + 0);
        }
    }

    #input: 0-86400 (daily time in seconds)
    #output: HH:MM or empty string if 0
    public static function int2timestr($i) {
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
    public static function timestr2int($hhmm) {
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
     * @param string $unixtime epoch seconds
     * @param int $seconds
     * @return boolean
     */
    public static function isExpired($unixtime, $seconds) {
        return $unixtime < (time() - $seconds);
    }

    /**
     * return true if datetime(in sql format) earlier than now-$seconds
     * @param string $sql_datetime YYYY-MM-DD HH:MM:SS
     * @param int $seconds
     * @return boolean
     */
    public static function isSQLExpired($sql_datetime, $seconds) {
        return self::isExpired(self::SQL2Unix($sql_datetime), $seconds);
    }

    // return time difference between two second values
    public static function difference($from, $to) {
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
    public static function differenceShort($from, $to) {
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
    public static function differenceDays($from, $to) {
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
