<?php

class AdminSettingsController extends FwAdminController {
    const route_default_action = '';
    public $base_url='/Admin/Settings';
    public $required_fields = 'ivalue';
    public $save_fields = 'ivalue';
    public $model_name = 'Settings';

    public $search_fields = 'icode iname ivalue';
    public $list_sortdef = 'iname asc';   //default sorting - req param name, asc|desc direction
    public $list_sortmap = array(                   //sorting map: req param name => sql field name(s) asc|desc direction
                        'id'            => 'id',
                        'iname'         => 'iname',
                        'upd_time'      => 'upd_time',
                        );

    public function __construct() {
        parent::__construct();

    }

    public function IndexAction() {
        #get filters from the search form
        $f = $this->get_filter();

        $this->set_list_sorting();

        $this->list_where = ' 1=1 ';
        $this->set_list_search();

        //other filters add to $this->list_where here
        //if search - no category
        if ($f['s']==''){
            $this->list_where .= ' and icat='.dbq($f['icat']);
        }

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
        );

        return $ps;
    }

    public function SaveAction($form_id) {
        $id = $form_id+0;
        $item = req('item');

        try{
            $this->Validate($id, $item);
            #load old record if necessary
            #$item_old = $this->model->one($id);

            $itemdb = FormUtils::form2dbhash($item, $this->save_fields);
            #TODO - checkboxes support
            #FormUtils::form2dbhash_checkboxes($itemdb, $item, 'is_checkbox');

            $id = $this->model->update($id, $itemdb);

            #TODO cleanup any caches that depends on settings
            #FwCache::remove("XXX");

            fw::redirect($this->base_url.'/'.$id.'/edit');

        }catch( ApplicationException $ex ){
            $this->set_form_error($ex->getMessage());
            $this->route_redirect("ShowForm");
        }
    }

    public function Validate($id, $item) {
        $result= $this->validate_required($item, $this->required_fields);

        if ($id==0) throw new ApplicationException("Wrong Settings ID");

        $this->validate_check_result();
    }

}//end of class

?>