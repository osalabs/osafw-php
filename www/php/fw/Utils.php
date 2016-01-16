<?php
/*
Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2015 Oleg Savchuk www.osalabs.com
*/

class Utils {

    //just return logged user id
    public static function me() {
        return $_SESSION['user']['id']+0;
    }

    public static function kill_magic_quotes($value){
        $value = is_array($value) ?
                  array_map( array('Utils','kill_magic_quotes'), $value) :
                  stripslashes($value);
        return $value;
    }

    //split string by "whitespace characters" and return array
    public static function qw($str) {
        if ($str>""){
            return preg_split("/\s+/", $str);
        }else{
            return array();
        }
    }

    /*
    convert string like "AAA|1 BBB|2 CCC|3 DDD" to hash
    (or just "AAA BBB CCC DDD")
    AAA => 1
    BBB => 2
    CCC => 3
    DDD => 1 (default value 1)

    WARN! replaces all "&nbsp;" to spaces (after convert)
    */
    public static function qh($str) {
        $result=array();
        foreach (static::qw($str) as $value) {
            $kv = explode('|', $value, 2);
            $val = 1;
            if (count($kv)==2) $val = str_replace('&nbsp;', ' ', $kv[1]);

            $result[ $kv[0] ] = $val;
        }
        return $result;
    }

    //get string with random chars A-Fa-f0-9
    public static function get_rand_str($len){
        $result='';
        $chars=array("A","B","C","D","E","F","a","b","c","d","e","f",0,1,2,3,4,5,6,7,8,9);
        for($i=0;$i<$len;$i++) $result.=$chars[mt_rand(0,count($chars))];
        return $result;
    }

    //get icode with a given length based on a full set A-Za-z0-9
    //default length is 4
    public static function get_icode($len=4){
        $result='';
        $chars=array(0,1,2,3,4,5,6,7,8,9);
        for($i=ord('A');$i<=ord('Z');$i++) $chars[]=chr($i);
        for($i=ord('a');$i<=ord('z');$i++) $chars[]=chr($i);

        for($i=0;$i<$len;$i++) $result.=$chars[mt_rand(0,count($chars))];
        return $result;
    }

    public static function escape_str($str){
        return urlencode($str);
    }

    public static function n2br($str, $is_compress=''){
        $res=preg_replace("/\r/","",$str);
        $regexp="/\n/";
        if ($is_compress) $regexp="/\n+/";
        return preg_replace($regexp,"<br>",$res);
    }
    public static function br2n($str){
        return preg_replace("/<br>/i","\n",$str);
    }
    public static function dehtml($str){
        $aaa=preg_replace("/<[^>]*>/","",$str);
        return preg_replace("/%%[^%]*%%/","",$aaa);  //remove special tags too
    }

    public static function quotestr($str){
        $str=n2br($str);
        $str=str_replace('"','""',$str);

        return '"'.$str.'"';
    }


    /**
     * array of values to csv-formatted string for one line, order defiled by $fields
     * @param  array $row    hash values for csv line
     * @param  array $fields plain array - field names
     * @return string one csv line with properly quoted values and "\n" at the end
     */
    public static function to_csv_row($row, $fields){
        $result='';

        foreach ($fields as $key => $fld) {
            $str = $row[$fld];
            if ( preg_match('/[",]/', $str) ){
                $str = quotestr($str);
            }
            $result.=(($result)?",":"").$str;
        }

        return $result."\n";
    }

    public static function get_csv_export($rows, $fields=''){
        $result='';
        if (!is_array($fields)) $fields = static::qh($fields);

        #headers - if no fields set - read first row and get header names
        $headers_str='';
        if (!count($fields)){
            if (!count($rows)) return "";

            $fields = array_keys($rows[0]);
            $fields_header = $fields;
        }else{
            $fields_header = array_values($fields);
            $fields = array_keys($fields);
        }
        $headers_str=implode(',', $fields_header);

        $result=$headers_str."\n";
        foreach ($rows as $key => $row) {
            $result.=static::to_csv_row($row, $fields);
        }

        return $result;
    }

    /**
     * write reponse as csv
     * @var array   $rows   array of hashes from db_array
     * @var string|array $fields fields to export - string for qh or hash - (fieldname => field name in header), default - all export fields
     * @var string $filename human name of the file for browser, default "export.csv"
     */
    public static function response_csv($rows, $fields='', $filename='export.csv'){
        $filename = str_replace('"', "'", $filename); #quote filename

        header('Content-type: text/csv');
        header('Content-Disposition: attachment; filename="'.$filename.'"');

        echo static::get_csv_export($rows, $fields);
    }


    /**
     * bytes 2 human readable string
     * @param  int  $b   bytes
     * @return string    string with KiB, MiB, GiB like:
     *
     * 123 - 123 b
     * 1234 - 1.24 KiB
     * 12345 - 12.35 KiB
     * 1234567 - 1.23 MiB
     * 1234567890 - 1.23 GiB
     */
    public static function bytes2str($b){
        $result=$b;

        if ($b<1024){
            $result.=" B";
        }elseif ($b<1048576){
            $result=(ceil($b/1024*100)/100)." KiB";
        }elseif ($b<1073741824){
            $result=(ceil($b/1048576*100)/100)." MiB";
        }else{
            $result=(ceil($b/1073741824*100)/100)." GiB";
        }

        return $result;
    }
}

?>