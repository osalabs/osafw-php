<?php

class MyListsController extends FwAdminController {
    const int    access_level         = 0; #logged only
    const string route_default_action = '';

    public FwModel|UserLists $model;
    public string $model_name = 'UserLists';

    public string $base_url = '/My/Lists';
    public string $required_fields = 'entity iname';
    public string $save_fields = 'entity iname idesc status';
    public string $save_fields_checkboxes = '';
    /*REMOVE OR OVERRIDE*/
    public string $search_fields = 'iname idesc';
    public string $list_sortdef = 'iname asc';   //default sorting - req param name, asc|desc direction
    public array $list_sortmap = array(                   //sorting map: req param name => sql field name(s) asc|desc direction
                                                          'id'       => 'id',
                                                          'iname'    => 'iname',
                                                          'add_time' => 'add_time',
                                                          'status'   => 'status',
    );

    public function __construct() {
        parent::__construct();

        //optionally init controller
        $this->form_new_defaults["entity"] = $this->related_id;
    }

    public function initFilter(string $session_key = ''): array {
        $result = parent::initFilter();
        if (!array_key_exists('entity', $this->list_filter)) {
            $this->list_filter["entity"] = $this->related_id;
        }
        return $this->list_filter;
    }

    public function setListSearch(): void {
        $this->list_where = " status<>127 and add_users_id = " . dbqi($this->fw->userId()); #only logged user lists

        parent::setListSearch();

        if ($this->list_filter["entity"]) {
            $this->list_where .= " and entity=" . dbq($this->list_filter["entity"]);
        }
    }

    public function modelAddOrUpdate(int $id, array $fields): int {
        $is_new = ($id == 0);

        $id = parent::modelAddOrUpdate($id, $fields);

        if ($is_new && array_key_exists('item_id', $fields)) {
            #item_id could contain comma-separated ids
            $hids = Utils::commastr2hash($fields["item_id"]);

            if ($hids) {
                #if item id passed - link item with the created list
                foreach ($hids as $sitem_id => $value) {
                    $item_id = $sitem_id + 0;
                    if ($item_id) {
                        $this->model->addItems($id, $item_id);
                    }
                }
            }
        }
        return $id;
    }

    public function ToggleListAction($user_lists_id) {
        $user_lists_id += 0;

        $item_id = reqi("item_id");
        $ps      = array(
            "_json" => true,
        );

        try {
            $user_lists = $this->model->one($user_lists_id);
            if (!$item_id || !$user_lists || $user_lists["add_users_id"] <> $this->fw->userId()) {
                throw new ApplicationException("Wrong Request");
            }

            $res          = $this->model->toggleItemList($user_lists_id, $item_id);
            $ps["iname"]  = $user_lists["iname"];
            $ps["action"] = ($res ? 'added' : 'removed');

        } catch (Exception $ex) {
            $ps['error'] = ['message' => $ex->getMessage()];
        }

        return $ps;
    }

}//end of class

?>
