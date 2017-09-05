<?php
/*
 Base Fw Controller class for standard module with list/form screens

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2017 Oleg Savchuk www.osalabs.com
*/

class FwAdminController extends FwController {
    const access_level = 100; #by default Admin Controllers allowed only for Admins
    const route_default_action = '';
    public $base_url = '/Admin/FwAdmin';
    public $required_fields = 'iname';
    public $save_fields = 'iname status';
    public $save_fields_checkboxes = '';
    #public $model_name = 'DemoDicts'; #set in child class!
    /*REMOVE OR OVERRIDE
    public $search_fields = 'iname idesc';
    public $list_sortdef = 'iname asc';   //default sorting - req param name, asc|desc direction
    public $list_sortmap = array(                   //sorting map: req param name => sql field name(s) asc|desc direction
                        'id'            => 'id',
                        'iname'         => 'iname',
                        'add_time'      => 'add_time',
                        );
    */

    public function __construct() {
        parent::__construct();

        //optionally init controller
        $this->list_view = $this->model->table_name;
    }

    public function IndexAction() {
        #get filters from the search form
        $f = $this->get_filter();

        $this->set_list_sorting();
        $this->set_list_search();
        //other filters add to $this->list_where here

        $this->get_list_rows();
        //add/modify rows from db
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
        );

        return $ps;
    }

    public function ShowAction($form_id) {
        $id = $form_id+0;
        $item = $this->model->one($id);
        if (!$item) throw new ApplicationException("Not Found", 404);

        $ps = array(
            'id'    => $id,
            'i'     => $item,
            'add_users_id_name'  => fw::model('Users')->full_name($item['add_users_id']),
            'upd_users_id_name'  => fw::model('Users')->full_name($item['upd_users_id']),
            'return_url'        => $this->return_url,
            'related_id'        => $this->related_id,
        );

        return $ps;
    }

    public function ShowFormAction($form_id) {
        $id = $form_id+0;

        if ($this->fw->route['method']=='GET' ){
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
            'add_users_id_name'  => fw::model('Users')->full_name($item['add_users_id']),
            'upd_users_id_name'  => fw::model('Users')->full_name($item['upd_users_id']),
            'return_url'        => $this->return_url,
            'related_id'        => $this->related_id,
        );

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

            $itemdb=$this->set_save_itemdb($id, $item);

            $id = $this->model_add_or_update($id, $itemdb);

            $location = $this->get_return_location($id);

        }catch( ApplicationException $ex ){
            $success=false;
            $this->set_form_error($ex->getMessage());
        }

        if ($this->fw->is_json_expected()){
            return array('_json'=>array(
                'id'        => $id,
                'is_new'    => $is_new,
                'location'  => $location,
                'success'   => $success,
                'err_msg'   => $this->fw->GLOBAL['err_msg'],
                #TODO - add ERR field errors here
            ));
        }else{
            #if save success - return redirect
            #if save failed - return back to add/edit form
            if ($success){
                fw::redirect($location);
            }else{
                $this->route_redirect("ShowForm");
            }
        }
    }

    public function Validate($id, $item) {
        $result= $this->validate_required($item, $this->required_fields);

/*
        if ($result){
            if ($this->model->is_exists( $item['iname'], $id ) ){
                $this->ferr('iname', 'EXISTS');
            }
        }
*/
        $this->validate_check_result();
    }

    public function ShowDeleteAction($id){
        $id+=0;
        $ps = array(
            'i' => $this->model->one($id),
            'return_url'        => $this->return_url,
            'related_id'        => $this->related_id,
        );

        return $ps;
    }

    public function DeleteAction($id){
        $id+=0;
        $this->model->delete($id);

        $this->fw->flash("onedelete", 1);
        fw::redirect($this->get_return_location());
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
        fw::redirect($this->get_return_location());
    }

}//end of class

?>