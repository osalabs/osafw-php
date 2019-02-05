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

    //override if necessary: IndexAction, ShowAction, ShowFormAction, Validate, DeleteAction, Export, SaveMultiAction
    //or override just: setListSearch, getListRows, getSaveFields

}//end of class

?>