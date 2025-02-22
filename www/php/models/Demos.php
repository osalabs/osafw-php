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
        return $this->isExistsByField($uniq_key, 'email', $not_id);
    }

    public function listSelectOptionsParent(): array {
        return $this->db->arr($this->table_name, array(
            'parent_id' => 0,
            'status'    => $this->db->opNOT(self::STATUS_DELETED),
        ), 'iname', null, 'id, iname');
    }

}
