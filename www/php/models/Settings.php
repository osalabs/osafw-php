<?php
/*
Settings model class
*/

class Settings extends FwModel {

    /* static convenience functions, for easier use as:
    $var = Settings::read('icode');
    $int = Settings::readi('icode');
    $date = Settings::readd('icode');

    Settings::write('icode', $var);
    */

    public static function read($icode){
        return fw::i()->get_value($icode);
    }
    public static function readi($icode){
        return Utils::f2int(fw::i()->get_value($icode));
    }
    public static function readd($icode){
        return Utils::f2date(fw::i()->get_value($icode));
    }
    public static function write($icode, $value){
        return Utils::f2date(fw::i()->set_value($icode, $value));
    }

    public function __construct() {
        parent::__construct();

        $this->table_name = 'settings';
    }

    public function one_by_icode($icode, $is_force=false) {
        $cache_key = $this->CACHE_PREFIX.$this->table_name.'*'.$icode;
        if (!$is_force) {
            $row = FwCache::get_value($cache_key);
        }
        if ($is_force || is_null($row)){
            $row = db_row($this->table_name, array('icode'=>$icode));
            FwCache::set_value($cache_key, $row);
        }
        return $row;
    }

    public function get_value($icode){
        return one_by_icode($icode)['ivalue'];
    }
    public function set_value($icode, $ivalue){
        $item = one_by_icode($icode);
        $fields=array(
            'ivalue'    => $ivalue
        );
        if (count($item)){
            #exists - update
            $this->update($item['id'], $fields);

        }else{
            $fields['icode'] = $icode;
            $fields['is_user_edit'] = 0; #all auto-added settings is not user-editable by default
            $this->add($fields);
        }
    }

    public function is_exists($icode, $not_id=NULL) {
        return parent::is_exists($icode, 'icode', $not_id);
    }

}

?>