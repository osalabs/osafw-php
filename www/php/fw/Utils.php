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
     * for each row in $rows add array keys/values to this row
     * @param  dbarray $rows  array of hashes
     * @param  array $toadd keys/values to add
     * @return none $rows changed by ref
     */
    public static function dbarray_inject(&$rows, $toadd){
        foreach ($rows as $k => $row) {
            #array merge
            foreach ($toadd as $key => $value) {
                $rows[$k][$key] = $value;
            }
        }
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

    //return UUID v4, ex: 67700f72-57a4-4bc6-9c69-836e980390ce
    //WARNING: tries to use random_bytes or openssl_random_pseudo_bytes. If not present - pseudo-random data used
    public static function uuid(){
        if (function_exists('random_bytes')){ //PHP 7 only
            $data=random_bytes(16);
        }elseif (function_exists('openssl_random_pseudo_bytes')){
            $data=openssl_random_pseudo_bytes(16);
        }else{
            $data='';
            for($i=0;$i<16;$i++){
               $data.=chr(mt_rand(0,255));
            }
        }

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * load content from url
     * @param string $url url to get info from
     * @param array $params optional, if set - post will be used, instead of get. Can be string or array
     * @param array $headers optional, add headers
     * @param array $to_file optional, save response to file (for file downloads)
     * @return string content received. FALSE if error
     */
    public static function load_url($url, $params=null, $headers=null, $to_file=''){
        #logger("CURL TO: [$url]", $params);
        $cu = curl_init();

        curl_setopt($cu, CURLOPT_URL,$url);
        curl_setopt($cu, CURLOPT_TIMEOUT, 60);
        curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cu, CURLOPT_FAILONERROR, true); #cause fail on >=400 errors
        if (is_array($headers)) curl_setopt($cu, CURLOPT_HTTPHEADER, $headers);

        if (!is_null($params)){
            curl_setopt($cu, CURLOPT_POST, 1);
            curl_setopt($cu, CURLOPT_POSTFIELDS, $params);
        }
        if ($to_file>''){
            #downloading to tmp file first
            $tmp_file = $to_file.'.download';
            $fh_to_file=fopen($tmp_file, 'wb');
            curl_setopt($cu, CURLOPT_FILE, $fh_to_file);
            curl_setopt($cu, CURLOPT_TIMEOUT, 3600); #1h timeout
        }
        #curl_setopt($cu, CURLOPT_VERBOSE,true);
        ##curl_setopt($cu, CURLINFO_HEADER_OUT, 1);

        $result = curl_exec($cu);
        #logger(curl_getinfo($cu));
        if(curl_error($cu)){
            logger('CURL ERROR: '.curl_error($cu));
            $result=FALSE;
        }
        curl_close($cu);
        #logger("CURL RESULT:", $result);

        if ($to_file>''){
            fclose($fh_to_file);
            #if file download successfull - rename to destination
            #if failed - just remove tmp file
            if ($result!==FALSE){
                rename($tmp_file, $to_file);
            }else{
                unlink($tmp_file);
            }
        }

        return $result;
    }

    #send file to URL with optional params using curl
    public static function send_file_to_url($url, $from_file, $params=null, $headers=null){
        #logger("CURL FILE [$from_file] TO: [$url]", $params);
        $cu = curl_init();

        curl_setopt($cu, CURLOPT_URL,$url);
        curl_setopt($cu, CURLOPT_POST, 1);
        curl_setopt($cu, CURLOPT_TIMEOUT, 3600); #1h timeout
        curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cu, CURLOPT_FAILONERROR, true); #cause fail on >=400 errors

        $headers1=array(
            'Content-Type: multipart/form-data'
        );
        if (is_array($headers)) $headers1 += $headers;
        curl_setopt($cu, CURLOPT_HTTPHEADER, $headers1);

        $params1=array(
            'file'  => new CURLFile($from_file)
        );
        if (is_array($params)) $params1 += $params;
        curl_setopt($cu, CURLOPT_POSTFIELDS, $params1);

        #curl_setopt($cu, CURLOPT_VERBOSE,true);
        ##curl_setopt($cu, CURLINFO_HEADER_OUT, 1);

        $result = curl_exec($cu);
        #logger(curl_getinfo($cu));
        if(curl_error($cu)){
            logger('CURL ERROR: '.curl_error($cu));
            $result=FALSE;
        }
        curl_close($cu);
        #logger("CURL RESULT:", $result);

        return $result;
    }

    /**
     * post json to the url
     * @param string $url url to get info from
     * @param array $json
     * @param array $to_file optional, save response to file (for file downloads)
     * @return array json data received. FALSE if error
     */
    public static function post_json($url, $json, $to_file=''){
        $jsonstr = json_encode($json);

        $headers=array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonstr),
        );
        $result=static::load_url($url, $jsonstr, $headers, $to_file);
        if ($result!==FALSE) {
            if ($to_file>''){
                #if it was file transfer, just construct successful response
                $result = array(
                    'success'   => true,
                    'fsize'     => filesize($to_file)
                );
            }else{
                $result = json_decode($result, true);
            }
        }
        return $result;
    }

    /**
     * GET json from the url
     * @param string $url url to get info from
     * @param string $to_file optional, save response to file (for file downloads)
     * @param array $headers optional, additional headers
     * @return array json data received. FALSE if error
     */
    public static function get_json($url, $to_file='',$headers=null){

        $headers2=array(
            'Accept: application/json'
        );
        if (is_array($headers)) $headers2 = array_merge($headers2, $headers);

        $result=static::load_url($url, null, $headers2, $to_file);
        if ($result!==FALSE) {
            if ($to_file>''){
                #if it was file transfer, just construct successful response
                $result = array(
                    'success'   => true,
                    'fsize'     => filesize($to_file)
                );
            }else{
                $result = json_decode($result, true);
            }
        }
        return $result;
    }

    /**
     * return parsed json from the POST request
     * @return array json or FALSE
     */
    public static function get_posted_json(){
        $raw = file_get_contents("php://input");
        return json_decode($raw, true);
    }

}

?>