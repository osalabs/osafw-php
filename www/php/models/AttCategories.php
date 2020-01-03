<?php
/*
AttCategories model class
*/

class AttCategories extends FwModel {
    /** @return AttCategories */
    public static function i() {
        return parent::i();
    }

    public function __construct() {
        parent::__construct();

        $this->table_name = 'att_categories';
    }

    public function oneByIcode($icode){
        return $this->db->row($this->table_name, array('icode'=>$icode));
    }
}

?>