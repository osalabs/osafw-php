<?php
/*
Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
 */

class Utils {
    public const string TMP_PREFIX = 'osafw_'; // prefix for temp directory where framework stores temporary files

    /**
     * split string by "whitespace characters" and return array
     * Example: $array = qw('one two three'); => array('one', 'two', 'three');
     * @param array|string $str space-char separated words
     * @return array      array of words or empty array
     */
    public static function qw(array|string $str): array {
        if (is_array($str)) {
            return $str; #if array passed - don't chagne it
        }

        $str = trim($str);
        if ($str > "") {
            $arr = preg_split("/\s+/", $str);
            foreach ($arr as $key => $value) {
                $arr[$key] = str_replace('&nbsp;', ' ', $value);
            }
            return $arr;
        } else {
            return array();
        }
    }

    //convert from array back to qw-string
    //spaces converted to '&nbsp;'
    public static function qwRevert(array $arr): string {
        $result = '';
        foreach ($arr as $value) {
            $result .= str_replace(' ', '&nbsp;', $value) . ' ';
        }
        return $result;
    }

    /**
     * convert string like "AAA|1 BBB|2 CCC|3 DDD" to hash
     * (or just "AAA BBB CCC DDD")
     * AAA => 1
     * BBB => 2
     * CCC => 3
     * DDD => 1 (default value 1)
     * WARN! replaces all "&nbsp;" to spaces (after convert)
     * @param array|string $str
     * @param mixed $default_value
     * @return array
     */
    public static function qh(array|string $str, mixed $default_value = 1): array {
        if (is_array($str)) {
            return $str; #if array passed - don't chagne it
        }

        $result = array();
        foreach (self::qw($str) as $value) {
            $kv  = explode('|', $value, 2);
            $val = $default_value;
            if (count($kv) == 2) {
                $val = str_replace('&nbsp;', ' ', $kv[1]);
            }

            $result[$kv[0]] = $val;
        }
        return $result;
    }

    /**
     * convert from array back to qh-string
     * spaces converted to '&nbsp;'
     * @param array $sh
     * @return string
     */
    public static function qhRevert(array $sh): string {
        $result = array();
        foreach ($sh as $key => $value) {
            $result[] = str_replace(' ', '&nbsp;', $key) . '|' . $value;
        }
        return implode(' ', $result);
    }

    /**
     * remove elements from hash, leave only those which keys passed
     * @param array $hash
     * @param array $keys
     * @return array
     */
    public function filterKeys(array $hash, array $keys): array {
        $result = array();
        foreach ($keys as $key) {
            if (array_key_exists($key, $hash)) {
                $result[$key] = $hash[$key];
            }
        }
        return $result;
    }

    /**
     * leave just allowed chars in string - for routers: controller, action
     * @param string $str raw name of the controller or action
     * @return string normalized name with only allowed chars
     */
    public static function routeFixChars(string $str): string {
        return preg_replace("/[^A-Za-z0-9_-]+/", "", $str);
    }

    /**
     * trim string to a given length and add "..." at the end if it was trimmed
     * @param string $str
     * @param int $size
     * @return string example: "1234567890" => "12345..."
     */
    public static function sTrim(string $str, int $size): string {
        if (strlen($str) > $size) {
            $str = substr($str, 0, $size) . "...";
        }
        return $str;
    }

    /**
     * get random string with hex chars
     * @param int $len
     * @return string example: "A1B2C3D4"
     * @throws \Random\RandomException
     */
    public static function getRandStr(int $len): string {
        return bin2hex(random_bytes($len));
    }

    //get icode with a given length based on a full set A-Za-z0-9
    //default length is 4
    public static function getIcode(int $len = 4): string {
        $result = '';
        $chars  = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9);
        for ($i = ord('A'); $i <= ord('Z'); $i++) {
            $chars[] = chr($i);
        }

        for ($i = ord('a'); $i <= ord('z'); $i++) {
            $chars[] = chr($i);
        }

        for ($i = 0; $i < $len; $i++) {
            $result .= $chars[mt_rand(0, count($chars) - 1)];
        }

        return $result;
    }

    /**
     * escapes/encodes string so it can be passed as part of the url
     * @param string $str
     * @return string
     */
    public static function urlescape(string $str): string {
        return urlencode($str);
    }

    /**
     * unescapes/decodes escaped/encoded string back
     * @param string $str
     * @return string
     */
    public static function urlunescape(string $str): string {
        return urldecode($str);
    }

    public static function n2br(string $str, bool $is_compress = false): string {
        $res    = preg_replace("/\r/", "", $str);
        $regexp = "/\n/";
        if ($is_compress) {
            $regexp = "/\n+/";
        }

        return preg_replace($regexp, "<br>", $res);
    }

    public static function br2n(string $str): string {
        return preg_replace("/<br>/i", "\n", $str);
    }

    /**
     * convert html to text
     * @param string $str
     * @return string
     */
    public static function html2text(string $str): string {
        $str = preg_replace("/\n+/", " ", $str);
        $str = preg_replace("/<br\s*\/?>/", "\n", $str);
        $str = preg_replace("/(?:<[^>]*>)+/", " ", $str);
        return $str;
    }

    public static function dehtml(string $str): string {
        $aaa = preg_replace("/<[^>]*>/", "", $str);
        return preg_replace("/%%[^%]*%%/", "", $aaa); //remove special tags too
    }

    /**
     * convert comma-delimited str to associative array
     * @param string $sel_ids comma-separated ids
     * @param string|null $value value to use for all keys
     *   null - use id value from input
     *   "123..."  - use index (by order)
     *   "other value" - use this value
     * @return array
     */
    public static function commanstr2hash(string $sel_ids, string $value = null): array {
        $result = array();
        $ids    = explode(",", $sel_ids);
        foreach ($ids as $i => $v) {
            if (is_null($value)) {
                $result[$v] = $v;
            } elseif ($value == "123...") {
                $result[$v] = $i;
            } else {
                $result[$v] = $value;
            }
        }
        return $result;
    }

    /**
     * convert comma-delimited str to newline-delimited str
     * @param string $str
     * @return string
     */
    public static function commastr2nlstr(string $str): string {
        return str_replace(",", "\r\n", $str);
    }

    /**
     * convert newline-delimited str to comma-delimited str
     * @param string $str
     * @return string
     */
    public static function nlstr2commastr(string $str): string {
        return preg_replace("/[\n\r]+/", ",", $str);
    }

    /**
     * for each row in $rows add array keys/values to this row
     * usage: Utils::arrayInject($this->list_rows, array('related_id' => $this->related_id));
     * @param array $rows array of assoc arrays
     * @param array $toadd keys/values to add
     * @return void, $rows changed by ref
     */
    public static function arrayInject(array &$rows, array $toadd): void {
        foreach ($rows as &$row) {
            #array merge
            foreach ($toadd as $key => $value) {
                $row[$key] = $value;
            }
        }
        unset($row);
    }

    /**
     * array of values to csv-formatted string for one line, order defiled by $fields
     * @param array $row hash values for csv line
     * @param array $fields plain array - field names
     * @return string one csv line with properly quoted values and "\n" at the end
     */
    public static function toCSVRow(array $row, array $fields): string {
        $result = '';

        foreach ($fields as $fld) {
            $str = $row[$fld];
            if (preg_match('/[^\x20-\x7f]/', $str)) {
                //non-ascii data - convert to hex
                $str = bin2hex($str);
            }
            if (preg_match('/[",]/', $str)) {
                //quote string
                $str = '"' . str_replace('"', '""', nl2br($str)) . '"';
            }
            $result .= (($result) ? "," : "") . $str;
        }

        return $result . "\n";
    }

    /**
     * export array of assoc arrays to csv format and echo to output
     * @param array $rows array of assoc arrays from db_array
     * @param array|string $fields fields to export - string for qh or array (fieldname => field name in header), default - all export fields
     * @return string|void
     */
    public static function exportCSV(array $rows, array|string $fields = '') {
        if (!is_array($fields)) {
            $fields = self::qh($fields);
        }

        #headers - if no fields set - read first row and get header names
        $headers_str = '';
        if (!count($fields)) {
            if (!count($rows)) {
                return "";
            }

            $fields        = array_keys($rows[0]);
            $fields_header = $fields;
        } else {
            $fields_header = array_values($fields);
            $fields        = array_keys($fields);
        }
        $headers_str = implode(',', $fields_header);

        echo $headers_str . "\n";
        foreach ($rows as $key => $row) {
            echo self::toCSVRow($row, $fields);
        }
    }

    /**
     * output response as csv
     * @var array $rows db array of rows
     * @var string|array $fields fields to export - string for qh or hash - (fieldname => field name in header), default - all export fields
     * @var string $filename human name of the file for browser, default "export.csv"
     */
    public static function responseCSV(array $rows, array|string $fields = '', string $filename = 'export.csv'): void {
        $filename = str_replace('"', "'", $filename); #quote filename

        header('Content-type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        self::exportCSV($rows, $fields);
    }

    public static function responseXLS(FW $fw, array $rows, array|string $fields = '', string $filename = 'export.xls'): void {
        $filename = str_replace('"', "'", $filename); #quote filename

        header('Content-type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        #TODO writeXLSExport
        self::writeXLSExport($fw, $rows, $fields);
    }

    /**
     * export to XLS based on /common/list/export templates
     * @param FW $fw
     * @param array $rows db array of rows
     * @param array|string $fields fields to export - string for qh or hash - (fieldname => field name in header), default - all export fields
     * @param string $tpl_dir template directory to use
     * @return void
     */
    public static function writeXLSExport(FW $fw, array $rows, array|string $fields, string $tpl_dir = "/common/list/export"): void {
        $ps = array();

        if (!is_array($fields)) {
            $fields = self::qh($fields);
        }

        $headers = array();
        foreach ($fields as $iname) {
            $h          = array();
            $h["iname"] = $iname;
            $headers[]  = $h;
        }
        $ps["headers"] = $headers;

        //output headers
        $filedata = parse_page($tpl_dir, "xls_head.html", $ps, 'v');
        print $filedata;

        //output rows in chunks to save memory and keep connection alive
        //ps["rows"] = rows;
        $buffer   = array();
        $psbuffer = array("rows" => $buffer);
        foreach ($rows as $row) {
            $cell = array();
            foreach ($fields as $fld => $iname) {
                $cell[] = [
                    "value" => $row[$fld],
                ];
            }
            $row["cell"] = $cell;
            $buffer[]    = $row;

            //write to output every 10000 rows
            if (count($buffer) >= 10000) {
                $filedata = parse_page($tpl_dir, "xls_rows.html", $psbuffer);
                print $filedata;
                $buffer = array();
            }
        }

        //output if something left
        if (count($buffer) > 0) {
            $filedata = parse_page($tpl_dir, "xls_rows.html", $psbuffer);
            print $filedata;
        }

        //output footer
        $filedata = parse_page($tpl_dir, "xls_foot.html", $ps);
        print $filedata;
    }


    /**
     * zip multiple files into one
     * @param array $files {filename => filepath}
     * @param string $zip_file optional, filepath for zip archive, if empty - new created
     * @return string zip archive filepath
     * @throws ApplicationException
     */
    public static function zipFiles(array $files, string $zip_file = ''): string {
        if (!$zip_file) {
            $zip_file = tempnam(sys_get_temp_dir(), 'osafw_');
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZIPARCHIVE::OVERWRITE) !== TRUE) {
            throw new ApplicationException("Could not open zip archive [$zip_file]");
        }

        foreach ($files as $filename => $filepath) {
            $zip->addFile($filepath, $filename);
        }

        #close and save
        $zip->close();

        return $zip_file;
    }

    /**
     * bytes 2 human readable string
     * @param int $b bytes
     * @return string    string with KiB, MiB, GiB like:
     *
     * 123 - 123 b
     * 1234 - 1.24 KiB
     * 12345 - 12.35 KiB
     * 1234567 - 1.23 MiB
     * 1234567890 - 1.23 GiB
     */
    public static function bytes2str(int $b): string {
        $result = $b;

        if ($b < 1024) {
            $result .= " B";
        } elseif ($b < 1048576) {
            $result = (ceil($b / 1024 * 100) / 100) . " KiB";
        } elseif ($b < 1073741824) {
            $result = (ceil($b / 1048576 * 100) / 100) . " MiB";
        } else {
            $result = (ceil($b / 1073741824 * 100) / 100) . " GiB";
        }

        return $result;
    }

    /**
     * return UUID v4, ex: 67700f72-57a4-4bc6-9c69-836e980390ce  (3.4 x 10^38 unique IDs)
     * WARNING: tries to use random_bytes or openssl_random_pseudo_bytes. If not present - pseudo-random data used
     * @return string
     * @throws \Random\RandomException
     */
    public static function uuid(): string {
        if (function_exists('random_bytes')) {
            //PHP 7 only
            $data = random_bytes(16);
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $data = openssl_random_pseudo_bytes(16);
        } else {
            $data = '';
            for ($i = 0; $i < 16; $i++) {
                $data .= chr(mt_rand(0, 255));
            }
        }

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * return nanoID (2.1 trillion unique IDs)
     * @return string
     * @throws \Random\RandomException
     */
    public static function nanoID(): string {
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz-';
        $size     = 21;
        $id       = '';
        for ($i = 0; $i < $size; $i++) {
            $id .= $alphabet[random_int(0, 63)];
        }
        return $id;
    }

    /**
     * return path to tmp directory with prefix
     * @param string $prefix
     * @return string
     */
    public static function getTmpDir(string $prefix = self::TMP_PREFIX): string {
        $systemTmp = sys_get_temp_dir();
        $appTmp    = $systemTmp . DIRECTORY_SEPARATOR . $prefix;
        if (!is_dir($appTmp)) {
            mkdir($appTmp, 0777, true); // create if not exists
        }
        return $appTmp;
    }

    /**
     * return path to tmp filename WITHOUT extension
     * @param string $prefix optional, default TMP_PREFIX
     * @return string         path
     * @throws \Random\RandomException
     */
    public static function getTmpFilename(string $prefix = self::TMP_PREFIX): string {
        return self::getTmpDir($prefix) . '\\' . self::uuid();
    }

    /**
     * scan tmp directory, find all tmp files created by website and delete older than 1 hour
     * @param string $prefix
     * @return void
     */
    public static function cleanupTmpFiles(string $prefix = self::TMP_PREFIX): void {
        $files = glob(self::getTmpDir($prefix) . '/*');
        foreach ($files as $file) {
            $fi = new SplFileInfo($file);
            $ts = time() - $fi->getCTime();
            if ($ts > 3600) {
                try {
                    unlink($file);
                } catch (Exception $e) {
                    //ignore errors as it just cleanup, should not affect main logic, could be access denied
                }
            }
        }
    }

    /**
     * convert string to 2-char string, add "0" if less than 2 chars
     * @param string $str
     * @return string example: "1" => "01", "10" => "10"
     */
    public static function toXX(string $str): string {
        if (strlen($str) < 2) {
            $str = "0" . $str;
        }
        return $str;
    }

    /**
     * convert number to ordinal string
     * @param int $num
     * @return string example: 1 => "1st", 2 => "2nd", 3 => "3rd", 4 => "4th", 11 => "11th", 12 => "12th", 13 => "13th"
     */
    public static function num2ordinal(int $num): string {
        if ($num <= 0) {
            return (string)$num;
        }

        return match ($num % 100) {
            11, 12, 13 => $num . "th",
            default => match ($num % 10) {
                1 => $num . "st",
                2 => $num . "nd",
                3 => $num . "rd",
                default => $num . "th",
            },
        };
    }


    /**
     * for num (within total) and total - return string "+XXX%" or "-XXX%" depends if num is bigger or smaller than previous period (num-total)
     * @param float $num
     * @param float $total
     * @return string
     */
    public static function percentChange(float $num, float $total): string {
        $result = "";

        $prev_num = $total - $num;
        if ($prev_num == 0) {
            return ($num == 0) ? "0%" : "+100%";
        }

        $percent = (($num - $prev_num) / $prev_num) * 100;
        if ($percent >= 0) {
            $result = "+";
        }

        return $result . round($percent, 2) . "%";
    }

    /**
     * This truncates a variable to a character length, the default is 80.
     * @param string $str
     * @param array $attrs array of attributes
     *   trchar    - string of text to display at the end if the variable was truncated
     *   trword    - 0/1. By default, truncate will attempt to cut off at a word boundary =1.
     *   trend     - 0/1. If you want to cut off at the exact character length, pass the optional third parameter of 1.
     * <~tag truncate="80" trchar="..." trword="1" trend="1">
     * @return string
     */
    public static function str2truncate(string $str, array $attrs): string {
        $len    = 80;
        $trchar = '...';
        $trword = 1;
        $trend  = 1; #if trend=0 trword - ignored
        if ($attrs['truncate'] > 0) {
            $len = $attrs['truncate'];
        }

        if (array_key_exists('trchar', $attrs)) {
            $trchar = $attrs['trchar'];
        }

        if (array_key_exists('trend', $attrs)) {
            $trend = $attrs['trend'];
        }

        if (array_key_exists('trword', $attrs)) {
            $trword = $attrs['trword'];
        }

        $orig_len = strlen($str);

        if ($orig_len <= $len) {
            return $str;
        }

        if ($trend) {
            if ($trword) {
                $str = preg_replace("/^(.{" . $len . ",}?)[\n \t.,!?]+(.*)$/s", "$1", $str);
                if (strlen($str) < $orig_len) {
                    $str .= $trchar;
                }
            } else {
                $str = mb_substr($str, 0, $len) . $trchar;
            }
        } else {
            $str = mb_substr($str, 0, $len / 2) . $trchar . mb_substr($str, -$len / 2);
        }

        return $str;
    }

    /**
     * apply sortdir to orderby string
     * @param string $orderby string for default asc sorting, ex: "id", "id desc", "prio desc, id"
     * @param string $sortdir orderby or inversed orderby (if sortdir="desc"), ex: "id desc", "id asc", "prio asc, id desc"
     * @return string
     */
    public static function orderbyApplySortdir(string $orderby, string $sortdir): string {
        $result = $orderby;

        if ($sortdir == "desc") {
            $order_fields = array();
            foreach (self::qw($orderby) as $fld) {
                $_fld = $fld;
                // if fld contains asc or desc - change to opposite
                if (str_contains($_fld, " asc")) {
                    $_fld = str_replace(" asc", " desc", $_fld);
                } elseif (str_contains($_fld, " desc")) {
                    $_fld = str_replace(" desc", " asc", $_fld);
                } else {
                    // if no asc/desc - just add desc at the end
                    $_fld .= " desc";
                }
                $order_fields[] = $_fld;
            }
            $result = implode(", ", $order_fields);
        }

        return $result;
    }


    /**
     * simple encrypt or decrypt a string with vector/key
     * @param string $action 'encrypt' or 'decrypt'
     * @param string $string string to encrypt or decrypt (base64 encoded)
     * @param string $v vector string
     * @param string $k key string
     * @return false|string encrypted (base64 encoded) or decrypted string or FALSE if wrong action
     * TODO: use https://github.com/defuse/php-encryption instead
     */
    public static function crypt(string $action, string $string, string $v, string $k): false|string {
        $output         = false;
        $encrypt_method = "AES-256-CBC";

        // hash
        $key = hash('sha256', $k);

        // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(hash('sha256', $v), 0, 16);

        if ($action == 'encrypt') {
            $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
            $output = base64_encode($output);
        } elseif ($action == 'decrypt') {
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        }

        return $output;
    }

    #simple encrypt/decrypt pwd based on config keys
    public static function encrypt(string $value): false|string {
        return Utils::crypt('encrypt', $value, fw::i()->config->CRYPT_V, fw::i()->config->CRYPT_KEY);
    }

    public static function decrypt(string $value): false|string {
        return Utils::crypt('decrypt', $value, fw::i()->config->CRYPT_V, fw::i()->config->CRYPT_KEY);
    }

    public static function jsonEncode(mixed $data): false|string {
        return json_encode($data);
    }

    public static function jsonDecode(string|null $str) {
        if (is_null($str)) {
            return null; #Deprecated: json_decode(): Passing null to parameter #1 ($json) of type string is deprecated
        } else {
            return json_decode($str, true);
        }
    }

    /**
     * load content from url
     * @param string $url url to get info from
     * @param string|array|null $params optional, if set - post will be used, instead of get. Can be string or array
     * @param array|null $headers optional, add headers
     * @param string $to_file optional, save response to file (for file downloads)
     * @param array $curlinfo optional, return misc curl info by ref
     * @param bool $report_errors
     * @return false|string content received. FALSE if error
     */
    public static function loadUrl(string $url, string|array $params = null, array $headers = null, string $to_file = '', array &$curlinfo = array(), bool $report_errors = true): false|string {
        logger("CURL load from: [$url]", $params, $headers, $to_file);
        $cu = curl_init();

        curl_setopt($cu, CURLOPT_URL, $url);
        curl_setopt($cu, CURLOPT_TIMEOUT, 60);
        curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cu, CURLOPT_FAILONERROR, true); #cause fail on >=400 errors
        curl_setopt($cu, CURLOPT_FOLLOWLOCATION, true); #follow redirects
        curl_setopt($cu, CURLOPT_MAXREDIRS, 8); #max redirects
        if (is_array($headers)) {
            curl_setopt($cu, CURLOPT_HTTPHEADER, $headers);
        }

        if (!is_null($params)) {
            curl_setopt($cu, CURLOPT_POST, 1);
            curl_setopt($cu, CURLOPT_POSTFIELDS, $params);
        }
        if ($to_file > '') {
            #downloading to tmp file first
            $tmp_file   = $to_file . '.download';
            $fh_to_file = fopen($tmp_file, 'wb');
            curl_setopt($cu, CURLOPT_FILE, $fh_to_file);
            curl_setopt($cu, CURLOPT_TIMEOUT, 3600); #1h timeout
        }
        #curl_setopt($cu, CURLOPT_VERBOSE,true);
        ##curl_setopt($cu, CURLINFO_HEADER_OUT, 1);

        $result = curl_exec($cu);
        logger('TRACE', 'RESULT:', $result);
        $curlinfo = curl_getinfo($cu);
        #logger('TRACE', 'CURL INFO:', $curlinfo);
        if (curl_error($cu)) {
            $curlinfo['error'] = curl_error($cu);
            if ($report_errors) {
                logger('ERROR', 'CURL error: ' . curl_error($cu));
            }
            $result = false;
        }
        curl_close($cu);
        #logger("CURL RESULT:", $result);

        if ($to_file > '') {
            fclose($fh_to_file);
            #if file download successfull - rename to destination
            #if failed - just remove tmp file
            if ($result !== false) {
                rename($tmp_file, $to_file);
            } else {
                unlink($tmp_file);
            }
        }

        return $result;
    }

    /**
     * send file to URL with optional params using curl
     * @param string $url url to post file to
     * @param string $from_file file to send
     * @param array|null $params optional, post params
     * @param array|null $headers optional, additional headers
     * @return bool|string content received. FALSE if error
     */
    public static function sendFileToUrl(string $url, string $from_file, array $params = null, array $headers = null): bool|string {
        logger('TRACE', "CURL post file [$from_file] to: [$url]", $params, $headers);
        $cu = curl_init();

        curl_setopt($cu, CURLOPT_URL, $url);
        curl_setopt($cu, CURLOPT_POST, 1);
        curl_setopt($cu, CURLOPT_TIMEOUT, 3600); #1h timeout
        curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cu, CURLOPT_FAILONERROR, true); #cause fail on >=400 errors

        $headers1 = array(
            'Content-Type: multipart/form-data',
        );
        if (is_array($headers)) {
            $headers1 += $headers;
        }

        curl_setopt($cu, CURLOPT_HTTPHEADER, $headers1);

        $params1 = array(
            'file' => new CURLFile($from_file),
        );
        if (is_array($params)) {
            $params1 += $params;
        }

        curl_setopt($cu, CURLOPT_POSTFIELDS, $params1);

        #curl_setopt($cu, CURLOPT_VERBOSE,true);
        ##curl_setopt($cu, CURLINFO_HEADER_OUT, 1);

        $result = curl_exec($cu);
        #logger(curl_getinfo($cu));
        if (curl_error($cu)) {
            logger('ERORR', 'CURL error: ' . curl_error($cu));
            $result = false;
        }
        curl_close($cu);
        #logger("CURL RESULT:", $result);

        return $result;
    }

    /**
     * post json to the url
     * @param string $url url to get info from
     * @param array $json
     * @param array $headers
     * @param string $to_file optional, save response to file (for file downloads)
     * @return false|array json data received. FALSE if error
     */
    public static function postJson(string $url, array $json, array $headers = [], string $to_file = ''): false|array {
        $jsonstr = json_encode($json);

        $headers = array_merge(array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonstr),
        ), $headers);

        $result = self::loadUrl($url, $jsonstr, $headers, $to_file);
        if ($result !== false) {
            if ($to_file > '') {
                #if it was file transfer, just construct successful response
                $result = array(
                    'success' => true,
                    'fsize'   => filesize($to_file),
                );
            } else {
                $result = json_decode($result, true);
            }
        }
        return $result;
    }

    /**
     * GET json from the url
     * @param string $url url to get info from
     * @param string $to_file optional, save response to file (for file downloads)
     * @param array|null $headers optional, additional headers
     * @return false|array json data received. FALSE if error
     */
    public static function getJson(string $url, string $to_file = '', array $headers = null): false|array {
        $headers2 = array(
            'Accept: application/json',
        );
        if (is_array($headers)) {
            $headers2 = array_merge($headers2, $headers);
        }

        $result = self::loadUrl($url, null, $headers2, $to_file);
        if ($result !== false) {
            if ($to_file > '') {
                #if it was file transfer, just construct successful response
                $result = array(
                    'success' => true,
                    'fsize'   => filesize($to_file),
                );
            } else {
                $result = json_decode($result, true);
            }
        }
        return $result;
    }

    /**
     * return parsed json from the POST request
     * @return array json or FALSE
     */
    public static function getPostedJson(): array {
        $raw = file_get_contents("php://input");
        return json_decode($raw, true);
    }

    /**
     * split string by separator and returns exactly 2 values (if not enough values - empty strings added)
     * usage: list($path1, $path2) = Utils::split2('/', $path)
     * @param string $separator
     * @param string $str
     * @return array
     */
    public static function split2(string $separator, string $str): array {
        return array_pad(explode($separator, $str, 2), 2, '');
    }

    /**
     * split string by "; \n\r" and return array of emails
     * @param string $emails email addresses delimited with ; space or newline
     * @return array of email addresses
     */
    public static function splitEmails(string $emails): array {
        $result = array();
        $arr    = preg_split('/[; \n\r]+/', $emails);
        foreach ($arr as $email) {
            $email = trim($email);
            if ($email > "") {
                $result[] = $email;
            }
        }
        return $result;
    }

    /**
     * escape string for html output
     * @param string $str
     * @return string
     */
    public static function htmlescape(string $str): string {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * prefix string with http:// if not already
     * @param string $str url like "google.com" or "http://google.com"
     * @return string url with http:// prefix
     */
    public static function str2url(string $str): string {
        if (!preg_match('!^\w+://!', $str)) {
            $str = "http://" . $str;
        }
        return $str;
    }

    /**
     * capitalize string:
     *  - if mode='all' - capitalize all words
     *  - otherwise - just a first word
     * EXAMPLE: mode="" : sample string => Sample string
     *          mode="all" : sample STRING => Sample String
     * @param string $str
     * @param string $mode
     * @return string
     */
    public static function capitalize(string $str, string $mode = ""): string {
        if ($mode == "all") {
            return ucwords(strtolower($str));
        } else {
            return ucfirst(strtolower($str));
        }
    }

    /**
     * convert/normalize external table/field name to fw standard name
     * "SomeCrazy/Name" => "some_crazy_name"
     * @param string $str
     * @return string
     */
    public static function name2fw(string $str): string {
        $result = $str;
        $result = preg_replace('/^tbl|dbo/i', '', $result); // remove tbl,dbo prefixes if any
        $result = preg_replace('/([A-Z]+)/', '_$1', $result); // split CamelCase to underscore, but keep abbrs together ZIP/Code -> zip_code
        $result = preg_replace('/\W+/', '_', $result); // replace all non-alphanum to underscore
        $result = preg_replace('/_+/', '_', $result); // deduplicate underscore
        $result = trim($result, " _"); // remove first and last _ if any
        $result = strtolower($result); // and finally to lowercase
        return $result;
    }

    /**
     * convert some system name to human-friendly name'
     * "system_name_id" => "System Name ID"
     * @param string $str
     * @return string
     */
    public static function name2human(string $str): string {
        $str_lc = strtolower($str);
        if ($str_lc == "icode") {
            return "Code";
        }
        if ($str_lc == "iname") {
            return "Name";
        }
        if ($str_lc == "idesc") {
            return "Description";
        }
        if ($str_lc == "idate") {
            return "Date";
        }
        if ($str_lc == "itype") {
            return "Type";
        }
        if ($str_lc == "iyear") {
            return "Year";
        }
        if ($str_lc == "id") {
            return "ID";
        }
        if ($str_lc == "fname") {
            return "First Name";
        }
        if ($str_lc == "lname") {
            return "Last Name";
        }
        if ($str_lc == "midname") {
            return "Middle Name";
        }

        $result = $str;
        $result = preg_replace('/^tbl|dbo/i', '', $result); // remove tbl prefix if any
        $result = preg_replace('/_+/', ' ', $result); // underscores to spaces
        $result = preg_replace('/([a-z ])([A-Z]+)/', '$1 $2', $result); // split CamelCase words
        $result = preg_replace('/ +/', ' ', $result); // deduplicate spaces
        $result = self::capitalize($result, 'all'); // Title Case
        $result = trim($result);

        if (preg_match('/\bid\b/i', $result)) {
            // if contains id/ID - remove it and make singular
            $result = preg_replace('/\s*\bid\b/i', '', $result);
            // singularize TODO use external lib to handle all cases
            $result = preg_replace('/(\S)ies\s*$/', '$1y', $result); // -ies -> -y
            $result = preg_replace('/(\S)es\s*$/', '$1e', $result); // -es -> -e
            $result = preg_replace('/(\S)s\s*$/', '$1', $result); // remove -s at the end
        }

        $result = trim($result);
        return $result;
    }

    /**
     * convert c/snake style name to CamelCase
     * system_name => SystemName
     * @param string $str
     * @return string
     */
    public static function nameCamelCase(string $str): string {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $str)));
    }

    /**
     * copy directory with all files and subdirectories
     * @param string $source source directory
     * @param string $dest destination directory
     * @param bool $is_recursive if true - copy all subdirectories
     * @return void
     */
    public static function copyDirectory(string $source, string $dest, bool $is_recursive = true): void {
        if (!is_dir($dest)) {
            mkdir($dest, 0777, true);
        }

        $dir = opendir($source);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($source . '/' . $file) && $is_recursive) {
                    self::copyDirectory($source . '/' . $file, $dest . '/' . $file);
                } else {
                    copy($source . '/' . $file, $dest . '/' . $file);
                }
            }
        }
        closedir($dir);
    }


    /**
     * create cookie
     * @param string $name cookie name
     * @param string $value cookie value
     * @param int $exp_sec expiration in seconds
     * @return void
     */
    public static function createCookie(string $name, string $value, int $exp_sec): void {
        setcookie($name, $value, time() + $exp_sec, '/');
    }

    /**
     * get cookie value
     * @param string $name
     * @return string cookie value or empty string
     */
    public static function getCookie(string $name): string {
        return $_COOKIE[$name] ?? '';
    }

    /**
     * delete cookie
     * @param string $name
     * @return void
     */
    public static function deleteCookie(string $name): void {
        setcookie($name, '', time() - 3600, '/');
    }

}
