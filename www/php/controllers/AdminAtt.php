<?php

class AdminAttController extends FwAdminController {
    const access_level = 80;
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
        $f = $this->initFilter();

        $this->setListSorting();

        $this->list_where = ' status=0 ';
        $this->setListSearch();

        //other filters add to $this->list_where here
        if ($this->list_filter['att_categories_id']>0){
            $this->list_where .= '  and att_categories_id='. dbqi($att_categories_id);
        }

        $this->getListRows();
        //add/modify rows from db
        $AttCat = $this->fw->model('AttCategories');
        foreach ($this->list_rows as $k => $row) {
            $this->list_rows[$k]['field'] = 'value';
            $this->list_rows[$k]['cat'] = $AttCat->one($row['att_categories_id']);
            $this->list_rows[$k]['url_direct'] = $this->model->getUrlDirect($row);
            $this->list_rows[$k]['url_s'] = $this->model->getUrlDirect($row,'s');
            $this->list_rows[$k]['fsize_human'] = Utils::bytes2str($row['fsize']);
        }
        $ps=array(
            'list_rows'     => $this->list_rows,
            'count'         => $this->list_count,
            'pager'         => $this->list_pager,
            'f'             => $this->list_filter,

            'select_att_categories_ids' => $AttCat->listSelectOptions()
        );
        return $ps;
    }

    public function ShowFormAction($form_id) {
        $id = $form_id+0;
        $dict_link_multi=array();

        if ($this->fw->isGetRequest()){
            if ($id>0){
                $item = $this->model->one($id);
            }else{
                #defaults
                $item=array(
                );
            }
        }else{
            $itemdb = $id ? $this->model->one($id) : array();
            $item = reqh('item');
            $item = array_merge($itemdb, $item);
        }

        $ps = array(
            'id'    => $id,
            'i'     => $item,
            'add_users_id_name'  => fw::model('Users')->getFullName($item['add_users_id']),
            'upd_users_id_name'  => fw::model('Users')->getFullName($item['upd_users_id']),

            'fsize_human'       => Utils::bytes2str($item['fsize']),
            'url'               => $this->model->getUrl($id),
            'url_m'             => ($item['is_image'] ? $this->model->getUrl($id, 'm') : ''),

            'select_options_att_categories_id'      => fw::model('AttCategories')->listSelectOptions($item['att_categories_id']),
        );

        return $ps;
    }

    public function SaveAction($form_id) {
        $id = $form_id+0;
        $item = reqh('item');
        $files = UploadUtils::getPostedFiles('file1');

        try{
            $this->Validate($id, $item, $files);
            #load old record if necessary
            #$item_old = $this->model->one($id);

            $itemdb = FormUtils::filter($item, $this->save_fields);
            if (!strlen($itemdb["iname"])) $itemdb["iname"] = 'new file upload';
            if (!$id) $itemdb['status']=1; #under upload
            if (!$itemdb['att_categories_id']) $itemdb['att_categories_id']=1; #default cat - general


            $is_add = ($id==0);
            $id = $this->modelAddOrUpdate($id, $itemdb);

            #Proceed upload
            if (count($files)) $this->model->upload($id, $files[0], $is_add);

            if ($this->fw->isJsonExpected()){
                $item = $this->model->one($id);
                return array('_json' => array(
                        'success'   => true,
                        'id'        => $id,
                        'item'      => $item,
                        'url'       => $this->model->getUrlDirect($item),
                ));
            }else{
                fw::redirect($this->base_url.'/'.$id.'/edit');
            }

        }catch( ApplicationException $ex ){
            if ($this->fw->isJsonExpected()){
                return array('_json' => array(
                    'success'   => false,
                    'err_msg'   => $ex->getMessage(),
                    'id'        => $id,
                ));
            }else{
                $this->setFormError($ex->getMessage());
                $this->routeRedirect("ShowForm");
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
            $result= $result && $this->validateRequired($item, $this->required_fields);
        }else{
            if (!count($files) || !$files[0]['size']){
                $result = false;
                $this->setError('file1', 'NOFILE');
            }
        }

        $this->validateCheckResult($result);
    }

    public function SelectAction(){
        $category_icode = reqs("category");
        $att_categories_id = reqi("att_categories_id");
        $AttCat = $this->fw->model('AttCategories');

        if ($category_icode>''){
            $att_cat = $AttCat->oneByIcode($category_icode);
            if (count($att_cat)){
                $att_categories_id = $att_cat['id'];
            }
        }

        $rows = $this->model->ilistByCategory($att_categories_id);
        foreach ($rows as $key => $row) {
            $row['direct_url'] = $this->model->getUrlDirect($row);
        }

        $ps=array(
            'att_dr' => $rows,
            'select_att_categories_id' => $AttCat->listSelectOptions(),
            'att_categories_id' => $att_categories_id,
        );
        return $ps;
    }
}//end of class

?>