<?php
/*
DemosDemoDicts model class
*/

class DemosDemoDicts extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name               = 'demos_demo_dicts';
        $this->junction_model_main      = Demos::i();
        $this->junction_field_main_id   = "demos_id";
        $this->junction_model_linked    = DemoDicts::i();
        $this->junction_field_linked_id = "demo_dicts_id";
    }
}
