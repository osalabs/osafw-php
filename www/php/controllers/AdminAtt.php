<?php

class AdminAttController extends FwAdminController {
    const route_default_action = '';
    public $base_url='/Admin/Att';
    public $required_fields = 'iname';
    public $save_fields = 'att_categories_id iname status';
    public $model_name = 'Att';

    /*REMOVE OR OVERRIDE*/
    public $search_fields = 'iname idesc';
    public $list_sortdef = 'iname asc';   //default sorting - req param name, asc|desc direction
    public $list_sortmap = array(                   //sorting map: req param name => sql field name(s) asc|desc direction
                        'id'            => 'id',
                        'iname'         => 'iname',
                        'add_time'      => 'add_time',
                        'fsize'         => 'fsize',
                        'ext'           => 'ext',
                        'category'      => 'att_categories_id',
                        );

    public function __construct() {
        parent::__construct();
    }

    public function IndexAction() {
        #get filters from the search form
        $f = $this->get_filter();

        $this->set_list_sorting();

        $this->list_where = ' status=0 ';
        $this->set_list_search();

        //other filters add to $this->list_where here
        if ($this->list_filter['att_categories_id']>0){
            $this->list_where .= '  and att_categories_id='. dbqi($att_categories_id);
        }

        $this->get_list_rows();
        //add/modify rows from db
        $AttCat = $this->fw->model('AttCategories');
        foreach ($this->list_rows as $k => $row) {
            $this->list_rows[$k]['field'] = 'value';
            $this->list_rows[$k]['cat'] = $AttCat->one($row['att_categories_id']);
            $this->list_rows[$k]['url_direct'] = $this->model->get_url_direct($row);
            $this->list_rows[$k]['url_s'] = $this->model->get_url_direct($row,'s');
            $this->list_rows[$k]['fsize_human'] = Utils::bytes2str($row['fsize']);
        }
        $ps=array(
            'list_rows'     => $this->list_rows,
            'count'         => $this->list_count,
            'pager'         => $this->list_pager,
            'f'             => $this->list_filter,

            'select_att_categories_ids' => $AttCat->get_select_options($this->list_filter['att_categories_id'])
        );
        return $ps;
    }

    public function ShowFormAction($form_id) {
        $id = $form_id+0;
        $dict_link_multi=array();

        if ($this->fw->route['method']=='GET' ){
            if ($id>0){
                $item = $this->model->one($id);
            }else{
                #defaults
                $item=array(
                );
            }
        }else{
            $itemdb = $id ? $this->model->one($id) : array();
            $item = req('item');
            if (!is_array($item)) $item=array();
            $item = array_merge($itemdb, $item);
        }

        $ps = array(
            'id'    => $id,
            'i'     => $item,
            'add_user_id_name'  => fw::model('Users')->full_name($item['add_user_id']),
            'upd_user_id_name'  => fw::model('Users')->full_name($item['upd_user_id']),

            'att_categories_id' => Utils::bytes2str($item['fsize']),
            'url'               => $this->model->get_url($id),
            'url_m'             => ($item['is_image'] ? $this->model->get_url($id, 'm') : ''),

            'select_options_att_categories_id'      => fw::model('AttCategories')->get_select_options($item['att_categories_id']),
        );

        return $ps;
    }

    public function SaveAction($form_id) {
        $id = $form_id+0;
        $item = req('item');
        if (!is_array($item)) $item=array();
        $files = UploadUtils::get_posted_files('file1');

        try{
            $this->Validate($id, $item, $files);
            #load old record if necessary
            #$item_old = $this->model->one($id);

            $itemdb = FormUtils::form2dbhash($item, $this->save_fields);
            if (!strlen($itemdb["iname"])) $itemdb["iname"] = 'new file upload';

            $is_add = ($id==0);
            $id = $this->model_add_or_update($id, $itemdb);

            #Proceed upload
            if (count($files)) $this->model->upload($id, $files[0], $is_add);

            logger($this->fw->get_response_expected_format());
            if ($this->fw->get_response_expected_format()=='json'){
                $item = $this->model->one($id);
                return array(
                    'success'   => true,
                    'id'        => $id,
                    'item'      => $item,
                    'url'       => $this->model->get_url_direct($item),
                );
            }else{
                fw::redirect($this->base_url.'/'.$id.'/edit');
            }

        }catch( ApplicationException $ex ){
            logger($this->fw->get_response_expected_format());
            logger($ex->getMessage());
            if ($this->fw->get_response_expected_format()=='json'){
                return array(
                    'success'   => false,
                    'err_msg'   => $ex->getMessage(),
                    'id'        => $id,
                );
            }else{
                $this->set_form_error($ex->getMessage());
                $this->route_redirect("ShowForm");
            }
        }
    }

    public function Validate($id, $item, $files=array()) {
        #only require file during first upload
        #only require iname during update
        $result= true;
        $item_old = array();
        if ($id>0){
            $item_old = $this->model->one($id);
            $result= $result && $this->validate_required($item, $this->required_fields);
        }else{
            if (!count($files) || !$files[0]['size']){
                $result = false;
                $this->ferr('file1', 'NOFILE');
            }
        }

        $this->validate_check_result($result);
    }

    public function SelectAction(){
        $category_icode = reqs("category");
        $att_categories_id = reqi("att_categories_id");
        $AttCat = $this->fw->model('AttCategories');

        if ($category_icode>''){
            $att_cat = $AttCat->one_by_icode($category_icode);
            if (count($att_cat)){
                $att_categories_id = $att_cat['id'];
            }
        }

        $rows = $this->model->ilist_by_category($att_categories_id);
        foreach ($rows as $key => $row) {
            $row['direct_url'] = $this->model->get_url_direct($row);
        }

        $ps=array(
            'att_dr' => $rows,
            'select_att_categories_id' => $AttCat->get_select_options($att_categories_id),
        );
        return $ps;
    }
}//end of class

?>