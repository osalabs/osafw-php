<?php
/*
Demos model class
*/

class Demos extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name = 'demos';
    }

    public function isExists($uniq_key, $not_id = NULL): bool {
        return parent::isExists($uniq_key, 'email', $not_id);
    }

    public function listSelectOptionsParent(): array {
        return $this->db->arrp("SELECT id, iname FROM {$this->table_name} WHERE parent_id=0 and status<>127 ORDER BY iname");
    }

}
