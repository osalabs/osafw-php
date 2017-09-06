<?php

class Categories extends FwModel {
    public function __construct() {
        parent::__construct();

        $this->table_name = 'categories';
    }

    public function ilist($parent_id=NULL) {
        $where ='';
        if (!is_null($parent_id)) $where .='and parent_id='.$this->db->quote($parent_id);
        return $this->db->arr("select * from ".$this->table_name." where status=0 $where order by parent_id, prio desc, iname");
    }

    public function getSelectOptions($sel_id, $parent_id=NULL) {
        return FormUtils::selectOptions($this->ilist($parent_id), $sel_id);
    }

}

?>