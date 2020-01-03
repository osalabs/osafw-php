<?php
/*
DemoDicts model class
*/

class DemoDicts extends FwModel {
    /** @return DemoDicts */
    public static function i() {
        return parent::i();
    }

    public function __construct() {
        parent::__construct();

        $this->table_name = 'demo_dicts';
    }

}

?>