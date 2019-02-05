<?php
/*
 Base Fw Controller class for standard module with list/form screens

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2019 Oleg Savchuk www.osalabs.com
*/

class FwDynamicController extends FwController {
    const access_level = 100; #by default Admin Controllers allowed only for Admins
    protected $model_related;

    public function __construct() {
        parent::__construct();

        //uncomment in the interited controller
        //$this->base_url='/Admin/DemosDynamic'; #base url must be defined for loadControllerConfig
        //$this->loadControllerConfig();
        //$this->model_related = fw::model('DemoDicts');
    }

    public function IndexAction() {
        #get filters from the search form
        $f = $this->initFilter();

        $this->setListSorting();
        $this->setListSearch();
        $this->setListSearchStatus();
        // set here non-standard search
        if ($f["field"] > '') {
            $this->list_where .= " and field=".dbq($f["field"]);
        }

        $this->getListRows();
        //add/modify rows from db if necessary
        /*
        foreach ($this->list_rows as $k => $row) {
            $this->list_rows[$k]['field'] = 'value';
        }
        */
        $ps=array(
            'list_rows'     => $this->list_rows,
            'count'         => $this->list_count,
            'pager'         => $this->list_pager,
            'f'             => $this->list_filter,
            'related_id'    => $this->related_id,
            'return_url'    => $this->return_url,
        );

        #optional userlists support
        $ps["select_userlists"] = fw::model('UserLists')->listSelectByEntity($this->list_view);
        $ps["mylists"] = fw::model('UserLists')->listForItem($this->list_view, 0);
        $ps["list_view"] = $this->list_view;

        if ($this->is_dynamic_index){
            #customizable headers
            $this->setViewList($ps, reqh("search"));
        }

        return $ps;
    }

    public function ShowAction($form_id) {
        $id = $form_id+0;
        $item = $this->model->one($id);
        if (!$item) throw new ApplicationException("Not Found", 404);

        $ps = array(
            'id'    => $id,
            'i'     => $item,
            'add_users_id_name'  => fw::model('Users')->getFullName($item['add_users_id']),
            'upd_users_id_name'  => fw::model('Users')->getFullName($item['upd_users_id']),
            'return_url'        => $this->return_url,
            'related_id'        => $this->related_id,
        );

        return $ps;
    }

    public function ShowFormAction($form_id) {
        $id = $form_id+0;

        if ($this->fw->isGetRequest()){
            if ($id>0){
                $item = $this->model->one($id);
            }else{
                #defaults
                $item=$this->form_new_defaults;
            }
        }else{
            $itemdb = $id ? $this->model->one($id) : array();
            $item = array_merge($itemdb, reqh('item'));
        }

        $ps = array(
            'id'    => $id,
            'i'     => $item,
            'add_users_id_name'  => fw::model('Users')->getFullName($item['add_users_id']),
            'upd_users_id_name'  => fw::model('Users')->getFullName($item['upd_users_id']),
            'return_url'        => $this->return_url,
            'related_id'        => $this->related_id,
        );
        if ($this->fw->GLOBAL['ERR']) logger($this->fw->GLOBAL['ERR']);

        return $ps;
    }

    public function SaveAction($form_id) {
        $id = $form_id+0;
        $item = reqh('item');

        $success = true;
        $is_new  = ($id==0);
        $location = '';

        try{
            $this->Validate($id, $item);

            $itemdb=$this->getSaveFields($id, $item);

            $id = $this->modelAddOrUpdate($id, $itemdb);

            $location = $this->getReturnLocation($id);

        }catch( ApplicationException $ex ){
            $success=false;
            $this->setFormError($ex->getMessage());
        }

        return $this->afterSave($success, $location, $id, $is_new);
    }

    public function Validate($id, $item) {
        $result= $this->validateRequired($item, $this->required_fields);

/*
        if ($result){
            if ($this->model->isExists( $item['iname'], $id ) ){
                $this->setError('iname', 'EXISTS');
            }
        }
*/
        $this->validateCheckResult();
    }

    public function ShowDeleteAction($id){
        $id+=0;
        $ps = array(
            'i' => $this->model->one($id),
            'return_url'        => $this->return_url,
            'related_id'        => $this->related_id,
            'base_url'          => $this->fw->config->ROOT_URL.$this->base_url, #override default template url, remove if you created custom /showdelete templates
        );

        $this->fw->parser('/common/form/showdelete', $ps);
        //return $ps; #use this instead of parser if you created custom /showdelete templates
    }

    public function DeleteAction($id){
        $id+=0;
        $this->model->delete($id);

        $this->fw->flash("onedelete", 1);
        fw::redirect($this->getReturnLocation());
    }

    public function SaveMultiAction(){
        $acb = req('cb');
        if (!is_array($acb)) $acb=array();
        $is_delete = reqs('delete')>'';

        $ctr=0;
        foreach ($acb as $id => $value) {
            if ($is_delete){
                $this->model->delete($id);
                $ctr+=1;
            }
        }

        $this->fw->flash("multidelete", $ctr);
        fw::redirect($this->getReturnLocation());
    }

}//end of class

?>