<?php
/*
AttCategories model class
*/

class AttCategories extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name = 'att_categories';
    }

    public function one_by_icode($icode){
        return db_row($this->table_name, array('icode'=>$icode));
    }
}

?>