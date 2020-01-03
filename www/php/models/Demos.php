<?php
/*
Demos model class
*/

class Demos extends FwModel {
    /** @return Demos */
    public static function i() {
        return parent::i();
    }

    public function __construct() {
        parent::__construct();

        $this->table_name = 'demos';
    }

    public function isExists($email, $not_id=NULL) {
        return parent::isExists($email, 'email', $not_id);
    }

    public function listSelectOptionsParent(){
        return $this->db->arr("SELECT id, iname FROM {$this->table_name} WHERE parent_id=0 and status<>127 ORDER BY iname");
    }

}

?>