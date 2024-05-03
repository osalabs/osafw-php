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

    public function prepapreSubtable(array $list_rows, int $related_id, array $def = null): void {
        $model_name        = $def != null ? (string)$def["model"] : get_class($this);
        $select_demo_dicts = DemoDicts::i()->listSelectOptions();
        foreach ($list_rows as $row) {
            $row["model"] = $model_name;
            //if row_id starts with "new-" - set flag is_new
            $row["is_new"]            = str_starts_with($row["id"], "new-");
            $row["select_demo_dicts"] = $select_demo_dicts;
        }
    }

    public function prepareSubtableAddNew(array &$list_rows, int $related_id, array $def = null): void {
        $id          = "new-" . time(); // new item not in db yet - mark it with sequental id starting with "new-"
        $item        = array(
            "id" => $id,
        );
        $list_rows[] = $item;
    }
}
