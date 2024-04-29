<?php
/*
 Demo Dynamic Admin controller

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class AdminDemosDynamicController extends FwDynamicController {
    const int access_level = Users::ACL_MANAGER;

    public string $base_url = '/Admin/DemosDynamic';

    public function __construct() {
        parent::__construct();

        $this->loadControllerConfig();
        $this->model_related = DemoDicts::i();
    }

    //override if necessary: IndexAction, ShowAction, ShowFormAction, Validate, DeleteAction, Export, SaveMultiAction
    //or override just: setListSearch, getListRows, getSaveFields

}//end of class
