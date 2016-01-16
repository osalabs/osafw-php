<?php

class AdminDemosController extends FwAdminController {
    const route_default_action = '';
    public $base_url='/Admin/Demos';
    public $required_fields = 'iname email';
    public $save_fields = 'parent_id demo_dicts_id iname idesc email fint ffloat fcombo fradio fyesno fdate_pop fdatetime dict_link_multi is_checkbox att_id status';
    public $model_name = 'Demos';
    public $model_related;

    /*REMOVE OR OVERRIDE*/
    public $search_fields = 'iname idesc';
    public $list_sortdef = 'iname asc';   //default sorting - req param name, asc|desc direction
    public $list_sortmap = array(                   //sorting map: req param name => sql field name(s) asc|desc direction
                        'id'            => 'id',
                        'iname'         => 'iname',
                        'add_time'      => 'add_time',
                        'demo_dicts_id' => 'demo_dicts_id',
                        'email'         => 'email',
                        );

    public function __construct() {
        parent::__construct();

        $this->model_related = fw::model('DemoDicts');
    }

    public function IndexAction() {
        $ps = parent::IndexAction();

        #add/modify rows from db
        foreach ($ps['list_rows'] as $k => $row) {
            $ps['list_rows'][$k]['demo_dicts'] = $this->model_related->one( $row['demo_dicts_id'] );
        }

        return $ps;
    }

    public function ShowFormAction($form_id) {
        $id = $form_id+0;
        $dict_link_multi=array();

        if ($this->fw->route['method']=='GET' ){
            if ($id>0){
                $item = $this->model->one($id);
                $item["ftime_str"] = DateUtils::int2timestr( $item["ftime"] );
                $dict_link_multi = FormUtils::ids2multi($item['dict_link_multi']);
            }else{
                #defaults
                $item=array(
                    'fint'=>0,
                    'ffloat'=>0,
                );
            }
        }else{
            $itemdb = $id ? $this->model->one($id) : array();
            $item = array_merge($itemdb, req('item'));
            $dict_link_multi = req('dict_link_multi');
        }

        $ps = array(
            'id'    => $id,
            'i'     => $item,
            'add_user_id_name'  => fw::model('Users')->full_name($item['add_user_id']),
            'upd_user_id_name'  => fw::model('Users')->full_name($item['upd_user_id']),

            #read dropdowns lists from db
            'select_options_parent_id'      => FormUtils::select_options_db( db_array("select id, iname from $this->table_name where parent_id=0 and status=0 order by iname"), $item['parent_id'] ),
            'select_options_demo_dicts_id'  => $this->model_related->get_select_options( $item['demo_dicts_id'] ),
            'dict_link_auto_id_iname'       => $item['dict_link_auto_id'] ? $this->model_related->iname( $item['dict_link_auto_id'] ) : $item['dict_link_auto_id_iname'],
            'multi_datarow'                 => $this->model_related->get_multi_list( $dict_link_multi ),
            'att_id_url_s'                  => $this->fw->model('Att')->get_url_direct($item['att_id'],'s'),
        );
        #combo date
        #TODO FormUtils::combo4date( $item['fdate_combo'], $ps, 'fdate_combo');

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
            FormUtils::form2dbhash_checkboxes($itemdb, $item, 'is_checkbox');

            $itemdb['dict_link_auto_id'] = $this->model_related->add_or_update_quick( $item['dict_link_auto_id_iname'] );
            $itemdb['dict_link_multi'] = FormUtils::multi2ids( req('dict_link_multi') );
            #TODO $itemdb['fdate_combo'] = FormUtils::date4combo($item, 'fdate_combo');
            $itemdb['ftime'] = DateUtils::timestr2int( $item['ftime_str'] ); #ftime - convert from HH:MM to int (0-24h in seconds)

            $id = $this->model_add_or_update($id, $itemdb);

            fw::redirect($this->base_url.'/'.$id.'/edit');

        }catch( ApplicationException $ex ){
            $this->set_form_error($ex->getMessage());
            $this->route_redirect("ShowForm");
        }
    }

    public function Validate($id, $item) {
        $result= $this->validate_required($item, $this->required_fields);

        //result here used only to disable further validation if required fields validation failed
        if ($result){
            if ($this->model->is_exists( $item['email'], $id ) ){
                $this->ferr('email', 'EXISTS');
            }

            if (!FormUtils::is_email( $item['email'] ) ){
                $this->ferr('email', 'WRONG');
            }
        }

        $this->validate_check_result();
    }

    public function AjaxAutocompleteAction(){
        $query = reqs('q');

        $ps=$this->model_related->get_autocomplete_items($query);
        return $ps;
    }

}//end of class

?>