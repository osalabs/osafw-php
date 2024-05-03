<?php
/*
Demo Dictionary Admin Controller

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class AdminDemoDictsController extends FwAdminController {
    public string $base_url = '/Admin/DemoDicts';
    public string $required_fields = 'iname';
    public string $save_fields = 'iname idesc status';
    public string $save_fields_checkboxes = '';
    public string $model_name = 'DemoDicts';
    /*REMOVE OR OVERRIDE*/
    public string $search_fields = 'iname idesc';
    public string $list_sortdef = 'iname asc';   //default sorting - req param name, asc|desc direction
    public array $list_sortmap = array(//sorting map: req param name => sql field name(s) asc|desc direction
                                       'id'       => 'id',
                                       'iname'    => 'iname',
                                       'add_time' => 'add_time',
                                       'status'   => 'status',
    );

    public function __construct() {
        parent::__construct();

        //optionally init controller
    }

    //override due to custom search filter on status
    public function setListSearch(): void {
        parent::setListSearch();

        if (isset($this->list_filter['status'])) {
            $this->list_where .= ' and status=' . dbqi($this->list_filter['status']);
        }
    }

    //override if necessary: IndexAction, ShowAction, ShowFormAction, Validate, DeleteAction, Export, SaveMultiAction
    //or override just: setListSearch, set_list_rows, getSaveFields

}//end of class
