<?php
/*
DemoDicts model class
*/

class DemoDicts extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name = 'demo_dicts';
    }

}
