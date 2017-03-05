<?php
/*
Demos model class
*/

class Demos extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name = 'demos';
    }

    public function is_exists($email, $not_id=NULL) {
        return parent::is_exists($email, 'email', $not_id);
    }

    public function get_select_options_parent($sel_id){
        $rows = db_array("select id, iname from {$this->table_name} where parent_id=0 and status<>127 order by iname");
        return FormUtils::select_options_db( $rows, $item['parent_id'] );
    }

}

?>