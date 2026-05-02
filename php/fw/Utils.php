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
        return rtrim($result);
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
     * @param bool $is_strict if true only A-Za-z0-9_ chars allowed, if false - also allows "-"
     * @return string normalized name with only allowed chars
     */
    public static function routeFixChars(string $str, bool $is_strict = false): string {
        if ($is_strict) {
            return preg_replace("/[^A-Za-z0-9_]+/", "", $str);
        } else {
            return preg_replace("/[^A-Za-z0-9_-]+/", "", $str);
        }
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

    /**
     * generate standard icode code - basically UUID without dashes
     * @return string
     * @throws \Random\RandomException
     */
    public static function icode(): string {
        return str_replace("-", "", self::uuid());
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
        $str = preg_replace('/<br\s*\/?>/i', "\n", $str);
        $str = preg_replace('#</(p|div|h[1-6]|li)\s*>#i', "\n", $str);
        $str = preg_replace('#<[^>]+>#', ' ', $str);
        $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $str = str_replace("\xc2\xa0", ' ', $str);
        $str = preg_replace('/[ \t]+/', ' ', $str);
        $str = preg_replace("/\r?\n[\r\n]+/", "\n\n", $str);
        return trim($str);
    }

    public static function dehtml(mixed $str): string {
        $str = strval($str);
        $str = str_replace(["\r\n", "\r"], "\n", $str);
        $str = preg_replace('/<br\s*\/?>/i', "\n", $str);
        $str = preg_replace('#</(p|div|h[1-6]|li)\s*>#i', "\n", $str);
        $str = preg_replace('#<[^>]+>#', ' ', $str);
        $str = preg_replace("/%%[^%]*%%/", "", $str); //remove special tags too
        $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $str = str_replace("\xc2\xa0", ' ', $str);
        $str = preg_replace('/[ \t]+/', ' ', $str);
        $str = preg_replace("/\n{3,}/", "\n\n", $str);
        return trim($str);
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
    public static function commastr2hash(string $sel_ids, ?string $value = null): array {
        $result = array();
        $ids    = explode(",", $sel_ids);
        foreach ($ids as $i => $v) {
            //skip empty values
            if ($v == "") {
                continue;
            }

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
     * get random key from weighted array
     * @param $arr array assoc array : 'some key' => int weight
     * @return int|string
     */
    public static function getRandomFromWeightedArray(array $arr): int|string {
        $result = '';
        $rand   = mt_rand(1, (int)array_sum($arr));

        foreach ($arr as $key => $value) {
            $rand -= $value;
            if ($rand <= 0) {
                $result = $key;
                break;
            }
        }

        return $result;
    }

    /**
     * array of values to csv-formatted string for one line, order defiled by $fields
     * @param array $row [ field => value, ...]
     * @param array $fields plain array - field names
     * @return string one csv line with properly quoted values with quotes and newlines and "\n" at the end
     */
    public static function toCSVRow(array $row, array $fields): string {
        $result = '';
        foreach ($fields as $field) {
            $value = $row[$field] ?? '';
            // check if value needs to be quoted
            if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
                $value = '"' . str_replace('"', '""', $value) . '"';
            }
            $result .= $value . ',';
        }
        return rtrim($result, ',') . "\n";
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
        foreach ($rows as $row) {
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
        $filedata = $fw->parsePage($tpl_dir, "xls_head.html", $ps);
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
                $filedata = $fw->parsePage($tpl_dir, "xls_rows.html", $psbuffer);
                print $filedata;
                $buffer = array();
            }
        }

        //output if something left
        if (count($buffer) > 0) {
            $filedata = $fw->parsePage($tpl_dir, "xls_rows.html", $psbuffer);
            print $filedata;
        }

        //output footer
        $filedata = $fw->parsePage($tpl_dir, "xls_foot.html", $ps);
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
     * Return nanoID (2.1 trillion unique IDs with the default length/alphabet).
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
     * return random ID (2.18 x 10^14 = 218.3 trillion unique IDs for 8 chars, useful up to 15 million generations because of birthday paradox)
     * @param int $length
     * @return string
     * @throws \Random\RandomException
     */
    public static function randomID(int $length = 8): string {
        $chars    = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $maxIndex = strlen($chars) - 1;
        $bytes    = random_bytes($length);
        $result   = '';

        for ($i = 0; $i < $length; $i++) {
            $idx    = ord($bytes[$i]) % ($maxIndex + 1);
            $result .= $chars[$idx];
        }

        return $result;
    }

    // implementation for javascript frontend:
    //    function randomID(length = 8) {
    //        const chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    //        let result = '';
    //        const randomValues = new Uint8Array(length);
    //        crypto.getRandomValues(randomValues);
    //
    //        for (let i = 0; i < length; i++) {
    //            result += chars[randomValues[i] % chars.length];
    //        }
    //        return result;
    //    }

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
                } catch (Exception) {
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
     * calculate sha256 hash
     * @param string $str
     * @return string
     */
    public static function sha256(string $str): string {
        return hash('sha256', $str);
    }

    /**
     * calculate sha256 hash and return binary string
     * @param string $str
     * @return string
     */
    public static function sha256bin(string $str): string {
        return hash('sha256', $str, true);
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
        $key = self::sha256($k);

        // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(self::sha256($v), 0, 16);

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
     * Create and configure a cURL handle with shared defaults used by single and batch requests.
     */
    private static function createCurlHandle(string $url, string|array|null $params = null, ?array $headers = null, string $to_file = '', ?int $timeout_seconds = null): array|false {
        $cu = curl_init();
        if ($cu === false) {
            return false;
        }

        curl_setopt($cu, CURLOPT_URL, $url);
        $default_timeout = $to_file > '' ? 3600 : 60;
        $timeout_to_set  = is_null($timeout_seconds) ? $default_timeout : max(1, $timeout_seconds);
        curl_setopt($cu, CURLOPT_TIMEOUT, $timeout_to_set);
        curl_setopt($cu, CURLOPT_CONNECTTIMEOUT, min(10, $timeout_to_set));
        curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cu, CURLOPT_FAILONERROR, false);
        curl_setopt($cu, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($cu, CURLOPT_MAXREDIRS, 8);
        if (is_array($headers)) {
            curl_setopt($cu, CURLOPT_HTTPHEADER, $headers);
        }

        if (!is_null($params)) {
            curl_setopt($cu, CURLOPT_POST, 1);
            curl_setopt($cu, CURLOPT_POSTFIELDS, $params);
        }

        $tmp_file   = '';
        $fh_to_file = null;
        if ($to_file > '') {
            $tmp_file   = $to_file . '.download';
            $fh_to_file = fopen($tmp_file, 'wb');
            if ($fh_to_file === false) {
                curl_close($cu);
                return false;
            }
            curl_setopt($cu, CURLOPT_FILE, $fh_to_file);
        }

        return [
            'handle'      => $cu,
            'file_handle' => $fh_to_file,
            'tmp_file'    => $tmp_file,
        ];
    }

    /**
     * Build a stable array key for curl handles across PHP versions.
     */
    private static function curlHandleKey($handle): string {
        if (is_object($handle)) {
            return 'o:' . spl_object_id($handle);
        }

        return 'r:' . intval($handle);
    }

    /**
     * load content from url
     * @param string $url url to get info from
     * @param string|array|null $params optional, if set - post will be used, instead of get. Can be string or array
     * @param array|null $headers optional, add headers
     * @param string $to_file optional, save response to file (for file downloads)
     * @param array $curlinfo optional, return misc curl info by ref
     * @param bool $report_errors
     * @param int|null $timeout_seconds optional timeout in seconds. If null - default used.
     * @return false|string content received. FALSE if error
     */
    public static function loadUrl(string $url, string|array|null $params = null, ?array $headers = null, string $to_file = '', array &$curlinfo = array(), bool $report_errors = true, ?int $timeout_seconds = null): false|string {
        if (!is_null($params)) {
            logger("NOTICE", "CURL POST [$url]");
        } else {
            logger("NOTICE", "CURL GET [$url]", $params);
        }

        $handle_data = self::createCurlHandle($url, $params, $headers, $to_file, $timeout_seconds);
        if ($handle_data === false) {
            $curlinfo = [
                'error' => 'Failed to initialize curl handle',
            ];
            if ($report_errors) {
                logger('ERROR', 'CURL error: Failed to initialize curl handle');
            }
            return false;
        }

        $cu         = $handle_data['handle'];
        $fh_to_file = $handle_data['file_handle'];
        $tmp_file   = $handle_data['tmp_file'];

        #curl_setopt($cu, CURLOPT_VERBOSE,true);
        ##curl_setopt($cu, CURLINFO_HEADER_OUT, 1);

        $result = curl_exec($cu);
        logger('TRACE', 'RESULT:', $result);
        $curlinfo = curl_getinfo($cu);
        #logger('TRACE', 'CURL INFO:', $curlinfo);
        $curl_error = trim((string)curl_error($cu));
        $http_code  = intval($curlinfo['http_code'] ?? 0);
        if ($curl_error !== '' || $http_code >= 400) {
            $curlinfo['error'] = $curl_error !== '' ? $curl_error : 'HTTP error ' . $http_code;
            if ($report_errors) {
                logger('ERROR', 'CURL error: ' . $curlinfo['error']);
            }
            $result = false;
        }
        curl_close($cu);
        #logger("CURL RESULT:", $result);

        if ($to_file > '') {
            if (is_resource($fh_to_file)) {
                fclose($fh_to_file);
            }
            #if file download successfull - rename to destination
            #if failed - just remove tmp file
            if ($tmp_file > '' && file_exists($tmp_file)) {
                if ($result !== false) {
                    rename($tmp_file, $to_file);
                    $result = '';
                } else {
                    unlink($tmp_file);
                }
            }
        }

        return $result;
    }

    /**
     * Load multiple URLs in parallel with bounded in-process curl_multi concurrency.
     */
    public static function loadUrlBatch(array $requests, int $parallelMax = 4, bool $report_errors = true): false|array {
        if (empty($requests)) {
            return [];
        }

        $parallelMax = max(1, $parallelMax);
        $results     = array_fill(0, count($requests), ['result' => false, 'curlinfo' => []]);

        $multiHandle = curl_multi_init();
        if ($multiHandle === false) {
            return false;
        }

        $activeHandles = [];
        $pendingIndex  = 0;

        $queueRequest = static function (int $request_index) use (&$requests, &$results, &$activeHandles, $multiHandle, $report_errors): void {
            $request = is_array($requests[$request_index] ?? null) ? $requests[$request_index] : [];
            $url     = trim((string)($request['url'] ?? ''));

            if ($url === '') {
                $results[$request_index] = [
                    'result'   => false,
                    'curlinfo' => ['error' => 'CURL batch request URL is required.'],
                ];
                if ($report_errors) {
                    logger('ERROR', 'CURL error: CURL batch request URL is required.');
                }
                return;
            }

            $params = $request['params'] ?? null;
            if (!is_null($params) && !is_string($params) && !is_array($params)) {
                $results[$request_index] = [
                    'result'   => false,
                    'curlinfo' => ['error' => 'CURL batch request params must be string|array|null.'],
                ];
                if ($report_errors) {
                    logger('ERROR', 'CURL error: CURL batch request params must be string|array|null.');
                }
                return;
            }

            $headers = is_array($request['headers'] ?? null) ? $request['headers'] : null;
            $timeout = null;
            if (array_key_exists('timeout_seconds', $request) && $request['timeout_seconds'] !== null) {
                $timeout = max(1, intval($request['timeout_seconds']));
            }

            $handle_data = self::createCurlHandle($url, $params, $headers, '', $timeout);
            if ($handle_data === false) {
                $results[$request_index] = [
                    'result'   => false,
                    'curlinfo' => ['error' => 'Failed to initialize curl handle'],
                ];
                if ($report_errors) {
                    logger('ERROR', 'CURL error: Failed to initialize curl handle');
                }
                return;
            }

            $handle     = $handle_data['handle'];
            $add_result = curl_multi_add_handle($multiHandle, $handle);
            if ($add_result !== CURLM_OK) {
                $error                   = function_exists('curl_multi_strerror') ? curl_multi_strerror($add_result) : 'Failed to queue CURL batch request.';
                $results[$request_index] = [
                    'result'   => false,
                    'curlinfo' => ['error' => $error],
                ];
                if ($report_errors) {
                    logger('ERROR', 'CURL error: ' . $error);
                }
                curl_close($handle);
                return;
            }

            $activeHandles[self::curlHandleKey($handle)] = [
                'handle'       => $handle,
                'result_index' => $request_index,
            ];
        };

        try {
            while (!empty($activeHandles) || $pendingIndex < count($requests)) {
                while (count($activeHandles) < $parallelMax && $pendingIndex < count($requests)) {
                    $queueRequest($pendingIndex);
                    $pendingIndex++;
                }

                if (empty($activeHandles)) {
                    continue;
                }

                do {
                    $status = curl_multi_exec($multiHandle, $running);
                } while ($status === CURLM_CALL_MULTI_PERFORM);

                if ($status !== CURLM_OK) {
                    $error = function_exists('curl_multi_strerror') ? curl_multi_strerror($status) : 'cURL multi execution failed.';
                    foreach ($activeHandles as $meta) {
                        $results[$meta['result_index']] = [
                            'result'   => false,
                            'curlinfo' => ['error' => $error],
                        ];
                        if ($report_errors) {
                            logger('ERROR', 'CURL error: ' . $error);
                        }
                        $handle = $meta['handle'];
                        curl_multi_remove_handle($multiHandle, $handle);
                        curl_close($handle);
                    }
                    $activeHandles = [];
                    break;
                }

                while ($info = curl_multi_info_read($multiHandle)) {
                    $doneHandle = $info['handle'];
                    $handleId   = self::curlHandleKey($doneHandle);
                    $meta       = $activeHandles[$handleId] ?? null;

                    if ($meta) {
                        $result    = curl_multi_getcontent($doneHandle);
                        $curlinfo  = curl_getinfo($doneHandle);
                        $curlError = trim((string)curl_error($doneHandle));
                        $infoCode  = intval($info['result'] ?? CURLE_OK);
                        $httpCode  = intval($curlinfo['http_code'] ?? 0);
                        if ($curlError === '' && $infoCode !== CURLE_OK) {
                            $curlError = function_exists('curl_strerror') ? curl_strerror($infoCode) : 'cURL request failed.';
                        }
                        if ($curlError === '' && $httpCode >= 400) {
                            $curlError = 'HTTP error ' . $httpCode;
                        }

                        if ($curlError !== '') {
                            $curlinfo['error'] = $curlError;
                            if ($report_errors) {
                                logger('ERROR', 'CURL error: ' . $curlError);
                            }
                            $result = false;
                        }

                        $results[$meta['result_index']] = [
                            'result'   => $result === false ? false : strval($result),
                            'curlinfo' => is_array($curlinfo) ? $curlinfo : [],
                        ];

                        unset($activeHandles[$handleId]);
                    }

                    curl_multi_remove_handle($multiHandle, $doneHandle);
                    curl_close($doneHandle);
                }

                if (!empty($activeHandles) && intval($running ?? 0) > 0) {
                    $select = curl_multi_select($multiHandle, 1.0);
                    if ($select === -1) {
                        usleep(10000);
                    }
                }
            }
        } finally {
            foreach ($activeHandles as $meta) {
                $handle = $meta['handle'];
                curl_multi_remove_handle($multiHandle, $handle);
                curl_close($handle);
            }
            curl_multi_close($multiHandle);
        }

        return $results;
    }

    /**
     * send file to URL with optional params using curl
     * @param string $url url to post file to
     * @param string $from_file file to send
     * @param array|null $params optional, post params
     * @param array|null $headers optional, additional headers
     * @return bool|string content received. FALSE if error
     */
    public static function sendFileToUrl(string $url, string $from_file, ?array $params = null, ?array $headers = null): bool|string {
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
     * @param array $curlinfo
     * @param int|null $timeout_seconds optional timeout in seconds. If null - default used.
     * @return false|array json data received. FALSE if error
     */
    public static function postJson(string $url, array $json, array $headers = [], string $to_file = '', array &$curlinfo = array(), ?int $timeout_seconds = null): false|array {
        $jsonstr = json_encode($json);

        $headers = array_merge(array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonstr),
        ), $headers);

        $result = self::loadUrl($url, $jsonstr, $headers, $to_file, $curlinfo, true, $timeout_seconds);
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
    public static function getJson(string $url, string $to_file = '', ?array $headers = null, array &$curlinfo = array(), ?int $timeout_seconds = null): false|array {
        $headers2 = array(
            'Accept: application/json',
        );
        if (is_array($headers)) {
            $headers2 = array_merge($headers2, $headers);
        }

        $result = self::loadUrl($url, null, $headers2, $to_file, $curlinfo, true, $timeout_seconds);
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
     * @return array json or empty array
     */
    public static function getPostedJson(): array {
        $raw    = file_get_contents("php://input");
        $result = json_decode($raw, true);
        if (!is_array($result)) {
            $result = [];
        }
        return $result;
    }

    /**
     * read posted json and add keys to $_REQUEST
     * @return array json or empty array
     */
    public static function parsePostedJson(): array {
        $json = self::getPostedJson();
        foreach ($json as $key => $value) {
            $_REQUEST[$key] = $value;
        }
        logger("TRACE", "REQUEST with Posted Json:", $_REQUEST);
        return $json;
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
     * prefix string with https:// if not already
     * @param string $str url like "google.com" or "https://google.com"
     * @return string url with https:// prefix
     */
    public static function str2url(string $str): string {
        if (!preg_match('!^\w+://!', $str)) {
            $str = "https://" . $str;
        }
        return $str;
    }

    /**
     * Normalize a user-provided URL for safe href output.
     * Empty result means the value should be rendered as plain text.
     */
    public static function safeUrl(string $str): string {
        $url = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $str) ?? '');
        if ($url === '' || str_starts_with($url, '//')) {
            return '';
        }

        if (!preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
            $url = self::str2url($url);
        }

        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return '';
        }

        if (($scheme === 'http' || $scheme === 'https') && !parse_url($url, PHP_URL_HOST)) {
            return '';
        }

        return $url;
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

    /**
     * get client IP address
     * @return string IP address from HTTP_X_FORWARDED_FOR or REMOTE_ADDR
     */
    public static function getIP(): string {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // This can be spoofed, but our production server are always behind our balancer
            $ips = explode(",", $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip  = trim(end($ips));
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '';
        }

        return $ip;
    }

    /**
     * initialize Sentry error logging if $sentryEndpoint is not empty
     * REQUIRES: composer require sentry/sdk
     * @param string $sentryEndpoint
     * @param string $release
     * @param string $environment
     * @return void
     */
    public static function initSentry(string $sentryEndpoint, string $release, string $environment): void {
        if (empty($sentryEndpoint)) {
            return;
        }

        try {
            \Sentry\init([
                'dsn'                => $sentryEndpoint,
                'release'            => $release,
                'environment'        => $environment,
                'traces_sample_rate' => 1,
                'error_types'        => E_ALL & ~E_NOTICE,
            ]);
            $real_ip = self::getIP();
            self::setSentryTag("real-ip", $real_ip);
            \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($real_ip): void {
                $scope->setUser([
                    'ip_address' => $real_ip
                ]);
            });
        } catch (Exception) {
            // do nothing with this exception. it's here to make sure we don't die on failed sentry log.
        }
    }

    /**
     * sets sentry user
     * @param string $email
     * @param string $id user id
     */
    public static function setSentryScope(string $email, string $id): void {
        try {
            \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($email, $id): void {
                $user = [
                    'id' => $id,
                ];
                if (!empty($email)) {
                    $user['email'] = $email;
                }
                $real_ip = self::getIP();
                if ($real_ip) {
                    $user['ip_address'] = $real_ip;
                }
                $scope->setUser($user);
                //$scope->setLevel(\Sentry\Severity::warning());
                //$scope->setExtra('character_name', 'Mighty Fighter');
            });
        } catch (Exception $e) {
            logger("WARN", "setSentryScope", $e->getMessage());
        }
    }

    public static function setSentryTag($tag, $value): void {
        if ((string)$value === '') {
            return; #do not add empty value tags, Sentry will complain about it
        }
        \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($tag, $value): void {
            $scope->setTag($tag, (string)$value);
        });
    }

    /**
     * Helper to send logs/events to Sentry based on the log type.
     * @param string $logType
     * @param bool $isExplicitLogType
     * @param string $message
     * @param array $extraParams
     * @return void
     */
    public static function sendToSentry(string $logType, bool $isExplicitLogType, string $message, array $extraParams): void {
        // Map fw log types to Sentry severities
        // (You can adjust these to your preference.)
        $levelMap = [
            'FATAL'  => Sentry\Severity::fatal(),
            'ERROR'  => Sentry\Severity::error(),
            'WARN'   => Sentry\Severity::warning(),
            'INFO'   => Sentry\Severity::info(),
            'DEBUG'  => Sentry\Severity::debug(),
            'NOTICE' => Sentry\Severity::info(),
            'TRACE'  => Sentry\Severity::debug(),
            'ALL'    => Sentry\Severity::debug(),
        ];

        // Default to "info" if not found
        $severity = $levelMap[$logType] ?? Sentry\Severity::info();

        // Decide if we want to capture a full separate Sentry event
        // for this logType or just add a breadcrumb
        $shouldCaptureAsEvent = (
            in_array($logType, ['FATAL', 'ERROR', 'WARN', 'INFO'], true)
            || ($logType === 'DEBUG' && $isExplicitLogType)
        );

        // If it’s an actual Throwable at the front, send as exception
        if ($logType === 'FATAL' && isset($extraParams[0]) && $extraParams[0] instanceof Throwable) {
            Sentry\withScope(function (Sentry\State\Scope $scope) use ($extraParams) {
                $scope->setExtra('extra', $extraParams);
                Sentry\captureException($extraParams[0]);
            });
        } elseif ($shouldCaptureAsEvent) {
            Sentry\withScope(function (Sentry\State\Scope $scope) use ($message, $severity, $extraParams) {
                $scope->setExtra('extra', $extraParams);
                Sentry\captureMessage($message, $severity);
            });
        }

        // Add a breadcrumb if level <= DEBUG
        // That means “TRACE” or “DEBUG” become breadcrumbs
        if (fw::$LOG_LEVELS[$logType] <= fw::$LOG_LEVELS['DEBUG']) {
            Sentry\addBreadcrumb(new Sentry\Breadcrumb(
                Sentry\Breadcrumb::LEVEL_INFO,  // or map it more precisely
                Sentry\Breadcrumb::TYPE_DEFAULT,
                'log',
                $message
            ));
        }
    }
}
