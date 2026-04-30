<?php
/*
FwControllers model class
*/

class FwControllers extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name = 'fwcontrollers';
    }

    public function listGrouped(): array {
        $p   = [
            'status_deleted' => self::STATUS_DELETED,
            'access_level'   => $this->fw->userAccessLevel()
        ];
        $sql = "SELECT * FROM {$this->qTable()} 
                WHERE status<>:status_deleted
                  and access_level<=:access_level
                ORDER BY igroup, iname";
        return $this->db->arrp($sql, $p);
    }
}
