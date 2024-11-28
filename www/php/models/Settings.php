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

    public static function read($icode) {
        return Settings::i()->getValue($icode);
    }

    public static function readi($icode) {
        return intval(Settings::i()->getValue($icode));
    }

    public static function readd($icode) {
        return Utils::f2date(Settings::i()->getValue($icode));
    }

    public static function write($icode, $value) {
        return Settings::i()->setValue($icode, $value);
    }

    public function __construct() {
        parent::__construct();

        $this->table_name = 'settings';
    }

    public function oneByIcode($icode, $is_force = false): array {
        $cache_key = $this->cache_prefix . $icode;
        if (!$is_force) {
            $row = $this->fw->cache->getRequestValue($cache_key);
        }
        if ($is_force || is_null($row)) {
            $row = $this->db->row($this->table_name, array('icode' => $icode));
            $this->fw->cache->setRequestValue($cache_key, $row);
        }
        return $row;
    }

    public function getValue($icode) {
        return $this->oneByIcode($icode)['ivalue'];
    }

    public function setValue($icode, $ivalue) {
        $item   = $this->oneByIcode($icode);
        $fields = array(
            'ivalue' => $ivalue
        );
        if ($item) {
            #exists - update
            $this->update($item['id'], $fields);

        } else {
            $fields['icode']        = $icode;
            $fields['is_user_edit'] = 0; #all auto-added settings is not user-editable by default
            $this->add($fields);
        }
    }

    public function isExists($icode, $not_id = NULL): bool {
        return parent::isExists($icode, 'icode', $not_id);
    }

}
