<?php
/*
Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2015 Oleg Savchuk www.osalabs.com
*/

class FormUtils {
  #simple email check
  public static function is_email($email) {
    return preg_match("/[^@]+\@[^@]+/", $email);
  }

  ########### usually used before calling get_sqlupdate_set and get_sqlinsert_set
  # exmple: $IFORM=form2dbhash($FORM, 'fname lname address');
  # from form hash $FORM
  # fields $names(string) ...
  # if is_exists (default true) - only values actually exists in input hash returned
  public static function form2dbhash($FORM, $names, $is_exists=true){
    $result=array();
    if ( is_array($FORM) ){
        $anames=Utils::qw($names);

        #copy fields
        foreach ($anames as $name){
          if (!$is_exists || array_key_exists($name, $FORM)) $result[$name]=$FORM[$name];
        }
    }

    return $result;
  }

  #similar to form2dbhash, but for checkboxes (as unchecked checkboxes doesn't passed from form)
  #RETURN: by ref itemdb - add fields with default_value or form value
  public static function form2dbhash_checkboxes(&$itemdb, $FORM, $names, $default_value="0"){
      if (is_array($FORM)){
        $anames=Utils::qw($names);
        foreach ($anames as $key => $fld) {
            if (array_key_exists($fld, $FORM)){
                $itemdb[$fld] = $FORM[$fld];
            }else{
                $itemdb[$fld] = $default_value;
            }
        }
      }
  }

  #RETURN: array of pages for pagination
  public static function get_pager($count, $pagenum, $pagesize=NULL){
    global $CONFIG;
    if (is_null($pagesize)) $pagesize = $CONFIG['MAX_PAGE_ITEMS'];

    $pager = array();
    if ($count>$pagesize){
      $page_count = ceil($count/$pagesize);
      for ($i=0; $i < $page_count; $i++) {
        $pager[]=array(
          'pagenum'       => $i,
          'pagenum_show'  => $i+1,
          'is_cur_page'   => ($pagenum==$i) ? 1 : 0,
        );
      }
    }

    return $pager;
  }

  #select options for rows returned from db.array('select id, iname from ...')
  public static function select_options_db($rows, $isel=NULL){
    return self::select_options_al($rows, $isel);
  }

  # arr is array of Hashes with "id" and "iname" keys
  # "id" key is optional, if not present - iname will be used for values too
  # isel may contain multiple comma-separated values
  public static function select_options_al($rows, $isel=NULL){
    $result = '';
    if (is_null($isel)) $isel='';

    $asel = explode(',', $isel);
    #trim all elements, so it would be simplier to compare
    foreach ($asel as $k => $v) {
      $asel[$k] = trim($v);
    }

    foreach ($rows as $k => $row) {
      $text = $row['iname'];
      if ( array_key_exists('id', $row) ){
        $val = $row['id'];
      }else{
        $val = $row['iname'];
      }

      $result .="<option value=\"$val\"";
      if ( array_search( trim($val), $asel)!==FALSE ){
        $result .=' selected ';
      }
      $result .=">$text</option>\n";
    }

    return $result;
  }


  /*#*********************************************************************
  #Function: get_combo_select_sql
  #Purpose : create HTML for select based on SQL data
  #Params  : sql, selected value
  #Returns : HTML string
  #Comment : SQL should return 2 fields: id - option value, iname - option desc
  #*********************************************************************
  */
  public static function get_combo_select_sql($sql, $sel_value){

   $rows=db_array($sql);

   $result='';
   foreach ($rows as $k => $row) {
      $value=$row['id'];
      $desc=$row['iname'];
      if (!$value && !$desc){continue;}
      if ($value==$sel_value){
         $result.="<option value=\"$value\" selected>$desc\n";
      }
      else{
         $result.="<option value=\"$value\">$desc\n";
      }
   }
   return $result;
  }

  # RETURN: true or false depending if $value is date and if it's date - add to $item 3 key/values for day/mon/year
  public static function combo4date($value, &$item, $field_prefix){
    $t = strtotime($value);
    if ($t===FALSE){
      return FALSE;
    }else{
      $dt = getdate($t);
      $item[$field_prefix.'_day'] = $dt['mday'];
      $item[$field_prefix.'_mon'] = $dt['mon'];
      $item[$field_prefix.'_year'] = $dt['year'];
      return TRUE;
    }
  }


  #join ids from form to comma-separated string
  #sample:
  # many <input name="dict_link_multi[<~id>]"...>
  # itemdb("dict_link_multi") = FormUtils.multi2ids(fw.FORM("dict_link_multi"))
  public static function multi2ids($hitems) {
    if (!is_array($hitems) || !count($hitems)) return '';

    return implode( ',', array_keys($hitems) );
  }

  # from string of ids: "1,2,3,4" to hash (id => 1)
  public static function ids2multi($str){
    $result=array();
    $arr = explode(',', $str);
    foreach ($arr as $key => $value) {
        $result[$value] = 1;
    }
    return $result;
  }

  public static function col2comma_str($acol) {
    return implode( ',', $acol);
  }

}

?>