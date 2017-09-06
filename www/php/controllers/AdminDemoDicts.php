<?php

class AdminDemoDictsController extends FwAdminController {
    const route_default_action = '';
    public $base_url = '/Admin/DemoDicts';
    public $required_fields = 'iname';
    public $save_fields = 'iname idesc status';
    public $save_fields_checkboxes = '';
    public $model_name = 'DemoDicts';
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
    }

    //override due to custom search filter on status
    public function setListSearch() {
        parent::setListSearch();

        if ($this->list_filter['status']>''){
            $this->list_where .= ' and status='.dbqi($this->list_filter['status']);
        }
    }

    //override if necessary: IndexAction, ShowAction, ShowFormAction, Validate, DeleteAction, Export, SaveMultiAction
    //or override just: setListSearch, set_list_rows, getSaveFields

}//end of class

?>