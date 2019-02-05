<?php
/*
 DemoDynamic Admin Controller class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2019 Oleg Savchuk www.osalabs.com
*/

class AdminDemosDynamicController extends FwDynamicController {
    const access_level = 80;
    const route_default_action = '';

    public function __construct() {
        parent::__construct();

        $this->base_url = '/Admin/DemosDynamic';
        $this->loadControllerConfig();
        $this->model_related = fw::model('DemoDicts');
    }

    //override if necessary: IndexAction, ShowAction, ShowFormAction, Validate, DeleteAction, Export, SaveMultiAction
    //or override just: setListSearch, getListRows, getSaveFields

}//end of class

?>