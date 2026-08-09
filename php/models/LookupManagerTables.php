<?php
/*
LookupManagerTables model class
*/

class LookupManagerTables extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name  = 'lookup_manager_tables';
        $this->field_icode = 'tname';
    }

}
