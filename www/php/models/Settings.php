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
        return fw::model('Settings')->getValue($icode);
    }
    public static function readi($icode){
        return (fw::model('Settings')->getValue($icode))+0;
    }
    public static function readd($icode){
        return Utils::f2date(fw::model('Settings')->getValue($icode));
    }
    public static function write($icode, $value){
        return fw::model('Settings')->setValue($icode, $value);
    }

    public function __construct() {
        parent::__construct();

        $this->table_name = 'settings';
    }

    public function oneByIcode($icode, $is_force=false) {
        $cache_key = $this->CACHE_PREFIX.$this->table_name.'*'.$icode;
        if (!$is_force) {
            $row = FwCache::getValue($cache_key);
        }
        if ($is_force || is_null($row)){
            $row = $this->db->row($this->table_name, array('icode'=>$icode));
            FwCache::setValue($cache_key, $row);
        }
        return $row;
    }

    public function getValue($icode){
        return $this->oneByIcode($icode)['ivalue'];
    }
    public function setValue($icode, $ivalue){
        $item = $this->oneByIcode($icode);
        $fields=array(
            'ivalue'    => $ivalue
        );
        if ($item){
            #exists - update
            $this->update($item['id'], $fields);

        }else{
            $fields['icode'] = $icode;
            $fields['is_user_edit'] = 0; #all auto-added settings is not user-editable by default
            $this->add($fields);
        }
    }

    public function isExists($icode, $not_id=NULL) {
        return parent::isExists($icode, 'icode', $not_id);
    }

}

?>