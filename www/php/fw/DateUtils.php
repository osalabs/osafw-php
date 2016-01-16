<?php
/*
Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2015 Oleg Savchuk www.osalabs.com
*/

class DateUtils {
    public static $DATE_FORMAT=1;  //0 - DD/MM/YYYY (Europe), 1-MM/DD/YYYY (USA)
    public static $DATE_FORMAT_STR='MM/DD/YYYY'; #or DD/MM/YYYY
    public static $TIME_FORMAT=1;  #0 - HH:MM (Europe), 1-HH:MM AM/PM (USA)
    public static $TIME_FORMAT_STR='HH:MM';         #or HH:MM AM/PM

    ##### from UNIX time (epoch seconds) to YYYY-MM-DD HH:MM:SS
    # if $is_date_only - return only year-mon-day (no hh mm ss)
    public static function Unix2SQL($unixtime, $is_date_only=false){
        list ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst)=localtime($unixtime);
        $year+=1900;
        $mon+=1;
        if ($is_date_only){
            return "$year-$mon-$mday";
        }else{
            return "$year-$mon-$mday $hour:$min:$sec";
        }
    }

    ##### from YYYY-MM-DD to UNIX time (epoch seconds)
    public static function SQL2Unix($s){
        preg_match("/^(\d+)-(\d+)-(\d+)/", $s, $matches);
        list($year, $mon, $day)=array($matches[1],$matches[2],$matches[3]);

        //time
        list($hour, $min, $sec)=array(0,0,0);
        if ( preg_match("/(\d+):(\d+):(\d+)$/", $s, $matches) ) {
            list($hour, $min, $sec)=array($matches[1],$matches[2],$matches[3]);
        }

        return @mktime($hour, $min, $sec, $mon, $day, $year);
    }

    // *****************************************
    // from YYYY-MM-DD to date string
    // params: SQL Date, Format (if 1 - better human format), Add HH:MM:SS
    public static function SQL2Str($s, $human_format=0, $add_hms=0){
        global $form_utils_AMONTH;

        if (!strlen($s) || $s=='0000-00-00' || $s=='0000-00-00 00:00:00') return '';

        $unixtime=SQLDate2Unix($s);

        $time_format_str='';
        if ($add_hms){
            if (self::$TIME_FORMAT || $human_format){
               $time_format_str=' h:i a';
            }else{
               $time_format_str=' H:i';
            }
        }

        if ($human_format){
            if (self::$DATE_FORMAT){ // MM/DD/YYYY
               return date("M d Y".self::$TIME_FORMAT_STR, $unixtime);

            }else{                                   // DD/MM/YYYY
                return date("d M Y".self::$TIME_FORMAT_STR, $unixtime);
            }
        }else{
            if (self::$DATE_FORMAT){ // MM/DD/YYYY
               return date("m/d/Y".self::$TIME_FORMAT_STR, $unixtime);

            }else{                                   // DD/MM/YYYY
               return date("d/m/Y".self::$TIME_FORMAT_STR, $unixtime);
            }
        }
    }

    // from date string to YYYY-MM-DD
    public static function Str2SQL($s){
        if (!strlen($s)) return '';

        list($day, $month, $year)=self::ParseStrToDate($s);
        if (strlen($day)<2) $day="0".$day;
        if (strlen($month)<2) $month="0".$month;
        return "$year-$month-$day";
    }

    // from date string to array (day, month, year)
    public static function ParseStrToDate($inDate, $date_format=''){

     if (!strlen($date_format)) $date_format=self::$DATE_FORMAT;  //set global format if date_format not provided

     preg_match("/^\s*(\d+)\/(\d+)\/(\d+)\s*$/", $inDate, $matches);

     if ($date_format){// MM/DD/YYYY
        return array($matches[2]+0,$matches[1]+0,$matches[3]+0);

     }else{                       // DD/MM/YYYY
        return array($matches[1]+0,$matches[2]+0,$matches[3]+0);
     }
    }

    #input: 0-86400 (daily time in seconds)
    #output: HH:MM
    public static function int2timestr($i) {
        $h = floor($i / 3600);
        $m = floor(($i - $h * 3600) / 60);
        return $h.':'.$m;
    }

    #input: HH:MM
    #output: 0-86400 (daily time in seconds)
    public static function timestr2int($hhmm) {
        $result = 0;
        $ahhmm = explode(':', $hhmm);
        if (count($ahhmm)==2) {
            $result = (int)($ahhmm[0]) * 3600 + (int)($ahhmm[1]) * 60;
        }
        
        return $result;
    }

}

?>