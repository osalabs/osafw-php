<?php
/*
Demos model class
*/

class Demos extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name = 'demos';
    }

    public function isExists($email, $not_id=NULL) {
        return parent::isExists($email, 'email', $not_id);
    }

    public function getSelectOptionsParent($sel_id){
        $rows = $this->db->arr("select id, iname from {$this->table_name} where parent_id=0 and status<>127 order by iname");
        return FormUtils::selectOptions( $rows, $sel_id );
    }

}

?>