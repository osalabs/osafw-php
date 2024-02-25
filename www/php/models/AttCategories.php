<?php
/*
AttCategories model class
*/

class AttCategories extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name = 'att_categories';
    }

    public function oneByIcode(string $icode): array {
        return $this->db->row($this->table_name, array('icode' => $icode));
    }
}
