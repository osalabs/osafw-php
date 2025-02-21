<?php
/*
DemosItems model class
*/

class DemosItems extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name = 'demos_items';

        $this->junction_model_main    = Demos::i();
        $this->junction_field_main_id = "demos_id";
    }

    public function prepareSubtable(array &$list_rows, int $related_id, array $def = null): void {
        parent::prepareSubtable($list_rows, $related_id, $def);

        // add select options
        $select_demo_dicts = DemoDicts::i()->listSelectOptions();
        foreach ($list_rows as &$row) {
            $row["select_demo_dicts"] = $select_demo_dicts;
        }
        unset($row);
    }
}
