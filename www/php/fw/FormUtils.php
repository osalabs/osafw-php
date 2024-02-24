<?php
/*
Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class FormUtils {
    #simple email check
    public static function isEmail($email) {
        return preg_match("/[^@]+\@[^@]+/", $email);
    }

    #validate phones in forms:
    # (xxx) xxx-xxxx
    # xxx xxx xx xx
    # xxx-xxx-xx-xx
    # xxxxxxxxxx
    public static function isPhone($phone) {
        return preg_match("/^\(?\d{3}\)?[\- ]?\d{3}[\- ]?\d{2}[\- ]?\d{2}$/", $phone);
    }

    #very simple date validation
    public static function isDate($str) {
        $result = true;
        try {
            $date = new DateTime($str);
        } catch (Exception $e) {
            $result = false;
        }
        return $result;
    }

    #very simple float number validation
    public function isFloat($str) {
        return preg_match("'/^-?[0-9]+(\.[0-9]+)?$/'", $str);
    }

    /**
     * filter posted $form extracting only specific field $names
     * usually used before calling get_sqlupdate_set and get_sqlinsert_set
     * $itemdb=FormUtils::filter($_POST, 'fname lname address');
     *
     * @param array $form array of fields from posted form (usually $_POST)
     * @param string $names space separated field names
     * @param boolean $is_exists (default true) only values actually exists in input hash returned
     * @return array              filtered fields, if value was array - converted to comma-separated string (for select multiple)
     */
    public static function filter($form, $names, $is_exists = true) {
        $result = array();
        if (is_array($form)) {
            $anames = Utils::qw($names);

            #copy fields
            foreach ($anames as $name) {
                if (!$is_exists || array_key_exists($name, $form)) {
                    $v = $form[$name];
                    #if form contains array - convert to comma-separated string (it's from select multiple)
                    if (is_array($v))
                        $v = implode(',', $v);
                    $result[$name] = $v;
                }
            }
        }

        return $result;
    }

    #similar to filter(), but for checkboxes (as unchecked checkboxes doesn't passed from form)
    #RETURN: by ref itemdb - add fields with default_value or form value
    public static function filterCheckboxes(&$itemdb, $form, $names, $default_value = "0") {
        if (is_array($form)) {
            $anames = Utils::qh($names, '0'); #$dval will be 0 by default
            foreach ($anames as $fld => $dval) {
                if (array_key_exists($fld, $form)) {
                    $itemdb[$fld] = $form[$fld];
                } else {
                    $itemdb[$fld] = $default_value === '0' ? $dval : $default_value;
                }
            }
        }
    }

    # fore each name in $name - check if value is empty '' and make it null
    # TODO: remove nullable processing and rely on DB lib instead (as DB knows field types)
    public static function filterNullable(&$itemdb, $names) {
        $anames = Utils::qw($names);
        foreach ($anames as $key => $fld) {
            if (array_key_exists($fld, $itemdb) && ($itemdb[$fld] === '' || $itemdb[$fld] == '0')) {
                $itemdb[$fld] = null;
            }
        }
    }

    #RETURN: array of pages for pagination
    public static function getPager($count, $pagenum, $pagesize = NULL) {
        if (is_null($pagesize))
            $pagesize = fw::i()->config->MAX_PAGE_ITEMS;

        $PAD_PAGES = 5; #show up to this number of pages before/after current page

        $pager = array();
        if ($count > $pagesize) {
            $page_count = ceil($count / $pagesize);

            $from_page = $pagenum - $PAD_PAGES;
            if ($from_page < 0)
                $from_page = 0;

            $to_page = $pagenum + $PAD_PAGES;
            if ($to_page > $page_count - 1)
                $to_page = $page_count - 1;

            for ($i = $from_page; $i <= $to_page; $i++) {
                $pg = array(
                    'pagenum'      => $i,
                    'pagenum_show' => $i + 1,
                    'is_cur_page'  => ($pagenum == $i) ? true : false,
                );
                if ($i == $from_page) {
                    if ($pagenum > $PAD_PAGES)
                        $pg['is_show_first'] = true;
                    if ($pagenum > 0) {
                        $pg['is_show_prev'] = true;
                        $pg['pagenum_prev'] = $pagenum - 1;
                    }
                } elseif ($i == $to_page) {
                    if ($pagenum < $page_count - 1) {
                        $pg['is_show_next'] = true;
                        $pg['pagenum_next'] = $pagenum + 1;
                    }
                }
                $pager[] = $pg;
            }
        }

        return $pager;
    }

    /**
     * return <option>... html for $rows with selected $selected_id
     * @param array $rows array of assoc arrays with "id" and "iname" keys, for ex returned from db.array('select id, iname from ...')
     * @param string $selected_id selected id, may contain multiple comma-separated values
     * @return string              html: <option value="id1">iname1</option>...
     *
     * "id" key is optional, if not present - iname will be used for values too
     */
    public static function selectOptions($rows, $selected_id = NULL) {
        $result = '';
        if (is_null($selected_id))
            $selected_id = '';

        $asel = explode(',', $selected_id);
        #trim all elements, so it would be simplier to compare
        foreach ($asel as $k => $v) {
            $asel[$k] = trim($v);
        }

        foreach ($rows as $k => $row) {
            $text = $row['iname'];
            if (array_key_exists('id', $row)) {
                $val = $row['id'];
            } else {
                $val = $row['iname'];
            }

            $result .= "<option value=\"$val\"";
            if (array_search(trim($val), $asel) !== FALSE) {
                $result .= ' selected ';
            }
            $result .= ">$text</option>\n";
        }

        return $result;
    }

    public static function selectTplOptions($tpl_path, $sel_id, $is_multi = false) {
        $result = array();
        if (!$sel_id)
            $sel_id = '';

        $lines = file(fw::i()->config->SITE_TEMPLATES . $tpl_path);
        foreach ($lines as $line) {
            if (strlen($line) < 2)
                continue;

            list($value, $desc) = explode('|', $line, 2);
            #$desc = preg_replace("/`(.+?)`/", "", $desc);
            parse_lang($desc); #from ParsePage

            $result[] = array(
                'id'    => $value,
                'iname' => $desc,
            );
        }

        return $result;
    }

    #return date for combo date selection or null if wrong date
    #sample:
    # <select name="item[fdate_combo_day]">
    # <select name="item[fdate_combo_mon]">
    # <select name="item[fdate_combo_year]">
    # $itemdb["fdate_combo"] = FormUtils::dateForCombo($item, "fdate_combo")
    public function dateForCombo($item, $field_prefix) {
        $result = null;
        $day    = intval($item[$field_prefix . "_day"]);
        $mon    = intval($item[$field_prefix . "_mon"]);
        $year   = intval($item[$field_prefix . "_year"]);

        if ($day > 0 && $mon > 0 && $year > 0) {
            $result = strtotime("$year-$mon-$day");
            if ($result === FALSE)
                $result = null;
        }

        return $result;
    }

    # RETURN: true or false depending if $value is date and if it's date - add to $item 3 key/values for day/mon/year
    public static function comboForDate($value, &$item, $field_prefix) {
        $t = strtotime($value);
        if ($t === FALSE) {
            return FALSE;
        } else {
            $dt                            = getdate($t);
            $item[$field_prefix . '_day']  = $dt['mday'];
            $item[$field_prefix . '_mon']  = $dt['mon'];
            $item[$field_prefix . '_year'] = $dt['year'];
            return TRUE;
        }
    }


    #join ids from form to comma-separated string
    #sample:
    # many <input name="dict_link_multi[<~id>]"...>
    # itemdb("dict_link_multi") = FormUtils.multi2ids(fw.FORM("dict_link_multi"))
    public static function multi2ids($hitems) {
        if (!is_array($hitems) || !count($hitems))
            return '';

        return implode(',', array_keys($hitems));
    }

    #similar to multi2ids, but uses array_values instead array_keys
    public static function multiv2ids($hitems) {
        if (!is_array($hitems) || !count($hitems))
            return '';

        return implode(',', array_values($hitems));
    }

    # from string of ids: "1,2,3,4" to hash (id => 1)
    public static function ids2multi($str) {
        $result = array();
        $arr    = explode(',', $str);
        foreach ($arr as $key => $value) {
            $result[$value] = 1;
        }
        return $result;
    }

    public static function col2comma_str($acol) {
        return implode(',', $acol);
    }

}
