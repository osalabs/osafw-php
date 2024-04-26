<?php
/*
Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class FormUtils {
    public const int MAX_PAGE_ITEMS = 25; //default max number of items on list screen

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
     * @param array|string $names_str_or_arr space separated field names
     * @param boolean $is_exists (default true) only values actually exists in input hash returned
     * @return array              filtered fields, if value was array - converted to comma-separated string (for select multiple)
     */
    public static function filter(array $form, array|string $names_str_or_arr, bool $is_exists = true): array {
        $result = array();
        if (is_array($form)) {
            $anames = Utils::qw($names_str_or_arr);

            #copy fields
            foreach ($anames as $name) {
                if (!$is_exists || array_key_exists($name, $form)) {
                    $v = $form[$name];
                    #if form contains array - convert to comma-separated string (it's from select multiple)
                    if (is_array($v)) {
                        $v = implode(',', $v);
                    }
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
        if (is_null($pagesize)) {
            $pagesize = self::MAX_PAGE_ITEMS;
        }

        $PAD_PAGES = 5; #show up to this number of pages before/after current page

        $pager = array();
        if ($count > $pagesize) {
            $page_count = ceil($count / $pagesize);

            $from_page = $pagenum - $PAD_PAGES;
            if ($from_page < 0) {
                $from_page = 0;
            }

            $to_page = $pagenum + $PAD_PAGES;
            if ($to_page > $page_count - 1) {
                $to_page = $page_count - 1;
            }

            for ($i = $from_page; $i <= $to_page; $i++) {
                $pg = array(
                    'pagenum'      => $i,
                    'pagenum_show' => $i + 1,
                    'is_cur_page'  => ($pagenum == $i) ? true : false,
                );
                if ($i == $from_page) {
                    if ($pagenum > $PAD_PAGES) {
                        $pg['is_show_first'] = true;
                    }
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
        if (is_null($selected_id)) {
            $selected_id = '';
        }

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
        if (!$sel_id) {
            $sel_id = '';
        }

        $lines = file(fw::i()->config->SITE_TEMPLATES . $tpl_path);
        foreach ($lines as $line) {
            if (strlen($line) < 2) {
                continue;
            }

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
            if ($result === FALSE) {
                $result = null;
            }
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
        if (!is_array($hitems) || !count($hitems)) {
            return '';
        }

        return implode(',', array_keys($hitems));
    }

    #similar to multi2ids, but uses array_values instead array_keys
    public static function multiv2ids($hitems) {
        if (!is_array($hitems) || !count($hitems)) {
            return '';
        }

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

    /// ****** helpers to detect changes

    /**
     * leave in only those item keys, which are absent/different from itemold
     * @param array $item
     * @param array $itemold
     * @return array
     */
    public static function changesOnly(array $item, array $itemold) {
        $result = array();

        foreach ($item as $key => $vnew) {
            $vold = $itemold[$key] ?? null;

            // If both are dates, compare only the date part.
            $dtNew = DateUtils::f2date($vnew);
            $dtOld = DateUtils::f2date($vold);
            if ($dtNew && $dtOld) {
                if ($dtNew->format('Y-m-d') != $dtOld->format('Y-m-d')) {
                    $result[$key] = $vnew;
                }
            } else {
                // Handle non-date values and the case where one value is a date and the other is not.
                if (!array_key_exists($key, $itemold) || strval($vnew) != strval($vold)) {
                    $result[$key] = $vnew;
                }
            }
        }

        return $result;
    }

    /**
     * return true if any of passed fields changed
     * @param array $item1
     * @param array $item2
     * @param string $fields qw-list of fields
     * @return bool false if no changes in passed fields or fields are empty
     */
    public static function isChanged(array $item1, array $item2, string $fields): bool {
        $result  = false;
        $afields = Utils::qw($fields);
        foreach ($afields as $fld) {
            if (array_key_exists($fld, $item1) && array_key_exists($fld, $item2) && strval($item1[$fld]) != strval($item2[$fld])) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * check if 2 dates (without time) changed
     * @param $date1
     * @param $date2
     * @return bool
     */
    public static function isChangedDate($date1, $date2): bool {
        $dt1 = DateUtils::f2date($date1);
        $dt2 = DateUtils::f2date($date2);

        if ($dt1 != null || $dt2 != null) {
            if ($dt1 != null && $dt2 != null) {
                // both set - compare dates
                if (DateUtils::Date2SQL($dt1) != DateUtils::Date2SQL($dt2)) {
                    return true;
                }
            } else // one set, one no - changed
            {
                return true;
            }
        }

        return false;
    }

    /**
     * return sql for order by clause for the passed form name (sortby) and direction (sortdir) using defined mapping (sortmap)
     * @param string $sortby form_name field to sort by
     * @param string $sortdir desc|[asc]
     * @param array $sortmap mapping form_name => field_name
     * @return string sql order by clause
     * @throws Exception
     */
    public static function sqlOrderBy(string $sortby, string $sortdir, array $sortmap): string {
        $orderby = ($sortmap[$sortby] ?? '');
        if (!$orderby) {
            throw new Exception("No orderby defined for [$sortby], define in list_sortmap");
        }

        $aorderby = explode(',', $orderby);
        if ($sortdir == "desc") {
            // if sortdir is desc, i.e. opposite to default - invert order for orderby fields
            // go thru each order field
            foreach ($aorderby as $i => $orderby) {
                list($field, $order) = Utils::split2('/\s+/', $orderby);
                if ($order == "desc") {
                    $order = "asc";
                } else {
                    $order = "desc";
                }
                $aorderby[$i] = dbqid($field) . " " . $order;
            }
        } else {
            // quote
            foreach ($aorderby as $i => $orderby) {
                list($field, $order) = Utils::split2('/\s+/', $orderby);
                $aorderby[$i] = dbqid($field) . " " . $order;
            }
        }
        return implode(", ", $aorderby);
    }
}
