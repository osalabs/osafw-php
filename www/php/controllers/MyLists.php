<?php

class MyListsController extends FwAdminController {
    const access_level = 0; #logged only
    const route_default_action = '';
    public $base_url = '/My/Lists';
    public $required_fields = 'entity iname';
    public $save_fields = 'entity iname idesc status';
    public $save_fields_checkboxes = '';
    public $model_name = 'UserLists';
    /*REMOVE OR OVERRIDE*/
    public $search_fields = 'iname idesc';
    public $list_sortdef = 'iname asc';   //default sorting - req param name, asc|desc direction
    public $list_sortmap = array(                   //sorting map: req param name => sql field name(s) asc|desc direction
                        'id'            => 'id',
                        'iname'         => 'iname',
                        'add_time'      => 'add_time',
                        'status'        => 'status',
                        );

    public function __construct() {
        parent::__construct();

        //optionally init controller
        $this->form_new_defaults["entity"] = $this->related_id;
    }

    public function initFilter(){
        parent::initFilter();
        if (!array_key_exists('entity', $this->list_filter)){
            $this->list_filter["entity"] = $this->related_id;
        }
        return $this->list_filter;
    }

    public function setListSearch(){
        $this->list_where = " status<>127 and add_users_id = ".dbqi(Utils::me()); #only logged user lists

        parent::setListSearch();

        if ($this->list_filter["entity"]){
            $this->list_where .= " and entity=".dbq($this->list_filter["entity"]);
        }
    }

    public function modelAddOrUpdate($id, $itemdb){
        $is_new  = ($id==0);

        $id = parent::modelAddOrUpdate($id, $itemdb);

        if ($is_new && array_key_exists('item_id', $itemdb)){
            #item_id could contain comma-separated ids
            $hids = Utils::commastr2hash(item["item_id"]);

            if ($hids){
                #if item id passed - link item with the created list
                foreach ($hids as $sitem_id => $value) {
                    $item_id = $sitem_id+0;
                    if ($item_id) $this->model->addItems($id, $item_id);
                }
            }
        }
    }

    public function ToggleListAction($user_lists_id){
        $user_lists_id+=0;

        $item_id = reqi("item_id");
        $ps = array(
            "_json"     => true,
            "success"   => true
        );

        try {
            $user_lists = $this->model->one($user_lists_id);
            if (!$item_id || !$user_lists || $user_lists["add_users_id"] <> Utils::me()) throw new ApplicationException("Wrong Request");

            $res = $this->model->toggleItemList($user_lists_id, $item_id);
            $ps["iname"] = $user_lists["iname"];
            $ps["action"] = ($res ? 'added' : 'removed');

        } catch (Exception $ex) {
            $ps["success"] = false;
            $ps["err_msg"] = $ex->getMessage();
        }

        return $ps;
    }

}//end of class

?>