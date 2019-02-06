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
            #added/updated should be filled before dynamic fields
            'add_users_id_name'  => fw::model('Users')->getFullName($item['add_users_id']),
            'upd_users_id_name'  => fw::model('Users')->getFullName($item['upd_users_id']),
            'return_url'        => $this->return_url,
            'related_id'        => $this->related_id,
        );

        #dynamic fields
        if ($this->is_dynamic_show) $ps["fields"] = $this->prepareShowFields($item, $ps);

        #optional userlists support
        $ps["list_view"] = $this->list_view ? $this->model->table_name : $this->list_view;
        $ps["mylists"] = fw::model('UserLists')->listForItem($ps["list_view"], $id);

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

    ###################### support for autocomlete related items
    public function AutocompleteAction(){
        if ($this->model_related) throw new ApplicationException('No model_related defined');
        $items = $this->model_related->getAutocompleteList(reqs("q"));

        return array('_json' => $items);
    }

    ###################### HELPERS for dynamic fields

    /**
     * prepare data for fields repeat in ShowAction based on config.json show_fields parameter
     * @param  array $item one item
     * @param  array $ps   for parsepage
     * @return array       array of hashtables to build fields in templates
     */
    public function prepareShowFields($item, $ps){
        $id = $item['id']+0;

        $fields = $this->config["show_fields"];
        foreach ($fields as &$def) {
            $def['i'] = $item;
            $dtype = $def["type"];
            $field = $def["field"];

            if ($dtype == "row" || $dtype == "row_end" || $dtype == "col" || $dtype == "col_end"){
                #structural tags
                $def["is_structure"] = true;

            }elseif ($dtype == "multi"){
                #complex field
                $def["multi_datarow"] = fw::model($def["lookup_model"])->getMultiList($item[$field], $def["lookup_params"]);

            }elseif ($dtype == "att"){
                $def["att"] = fw::model('Att')->one($item[$field]);

            }elseif ($dtype == "att_links"){
                $def["att_links"] = fw::model('Att')->getAllLinked($this->model->table_name, $id);
                logger($def["att_links"]);

            }else{
                #single values
                #lookups
                if (array_key_exists('lookup_table', $def)){
                    #lookup by table
                    $lookup_key = $def["lookup_key"];
                    if (!$lookup_key) $lookup_key = "id";

                    $lookup_field = $def["lookup_field"];
                    if (!$lookup_field) $lookup_field = "iname";

                    $def["lookup_row"] = $this->db->row($def["lookup_table"], array($lookup_key => $item[$field]) );
                    $def["value"] = $def["lookup_row"][$lookup_field];

                }elseif(array_key_exists('lookup_model', $def)){
                    #lookup by model

                    $def["lookup_row"] = fw::model($def["lookup_model"])->one($item[$field]);

                    $lookup_field = $def["lookup_field"];
                    if (!$lookup_field) $lookup_field = "iname";

                    $def["value"] = $def["lookup_row"][$lookup_field];

                }elseif(array_key_exists('lookup_tpl', $def)){
                    $def["value"] = get_selvalue($def["lookup_tpl"], $item[$field]);
                }else{
                    $def["value"] = $item[$field];
                }
            }

            #convertors
            if (array_key_exists('conv', $def)){
                if ($def["conv"] == "time_from_seconds"){
                    $def["value"] = DateUtils::int2timestr($def["value"]);
                }
            }
        }
        unset($def);

        return $fields;
    }

}//end of class

?>