<?php
/*
 Base Fw Controller class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2017 Oleg Savchuk www.osalabs.com
*/

abstract class FwController {
    //overridable
    const access_level = null;          //access level for the controller. $CONFIG['ACCESS_LEVELS'] overrides this.
                                        //Default=null - use config. If not set in config - all users can access this controller (even not logged)
    const route_default_action = '';    //empty, "index", "show" - default action for controller, dispatcher will use it if no requested action found
                                        //if no default action set - this is special case action - this mean action should be got form REST's 'id'
    public $model_name; //default model name for the controller

    public $list_sortdef = 'id asc';    //default sorting - req param name, asc|desc direction
    public $list_sortmap = array(       //sorting map: req param name => sql field name(s) asc|desc direction
                        'id'            => 'id',
                        'iname'         => 'iname',
                        'add_time'      => 'add_time',
                        );
    public $search_fields = 'iname idesc';  //fields to search via $s$list_filter['s'], ! - means exact match, not "like"
                                            //format: 'field1 field2,!field3 field4' => field1 LIKE '%$s%' or (field2 LIKE '%$s%' and field3='$s') or field4 LIKE '%$s%'

    public $form_new_defaults=array();      //defaults for the fields in new form
    public $save_fields;                    //fields to save from the form to db, space-separated
    public $save_fields_checkboxes;         //checkboxes fields to save from the form to db, qw string: "field|def_value field2|def_value2" or "field field2" (def_value=1 in this case)

    //not overridable
    public $fw;  //current app/framework object
    public $model; //default model for the controller
    public $list_view;                  // table or view name to selecte from for the list screen
    public $list_orderby;               // orderby for the list screen
    public $list_filter;                // filter values for the list screen
    public $list_where=' status<>127 '; // where sql to use in list sql, by default - return all non-deleted(status=127) records
    public $list_count;                 // count of list rows returned from db
    public $list_rows;                  // list rows returned from db (array of hashes)
    public $list_pager;                 // pager for the list from FormUtils::getPager
    public $return_url;                 // url to return after SaveAction successfully completed, passed via request
    public $related_id;                 // related id, passed via request. Controller should limit view to items related to this id
    public $related_field_name;         // if set and $related_id passed - list will be filtered on this field

    protected $db;

    public function __construct() {
        $this->fw = fw::i();
        $this->db = $this->fw->db;

        $this->return_url=reqs('return_url');
        $this->related_id=reqs('related_id');

        if ($this->model_name){
            $this->model = fw::model($this->model_name);
        }
    }

    ############### helpers - shortcuts from fw
    public function routeRedirect($action, $controller=NULL, $args=NULL) {
        $this->fw->routeRedirect($action, $controller, $args);
    }

    //add fields name to form error hash
    public function setError($field_name, $error_type=true) {
        $this->fw->GLOBAL['ERR'][$field_name]=$error_type;
    }

    #get filter saved in session
    #if request param 'dofilter' passed - session filters cleaned
    #get filter values from request and overwrite saved in session
    #save back to session and return
    public function initFilter(){
        #each filter remembered in session linking to controller.action
        $session_key = '_filter_'.$this->fw->GLOBAL['controller.action'];
        $sfilter = $_SESSION[ $session_key ];
        if (!is_array($sfilter)) $sfilter=array();

        $f = req('f');
        if (!is_array($f)) $f=array();

        #if not forced filter
        if ( !reqs('dofilter') ){
            $f = array_merge($sfilter, $f);
        }

        #paging
        if ( !preg_match("/^\d+$/", $f['pagenum']) ) $f['pagenum']=0;
        if ( !preg_match("/^\d+$/", $f['pagesize']) ) $f['pagesize']=$this->fw->config->MAX_PAGE_ITEMS;

        #save in session for later use
        $_SESSION[ $session_key ] = $f;

        $this->list_filter = $f;
        return $f;
    }

    /**
     * set list sorting fields - list_orderby and list_orderdir according to $this->list_filter filter
     * @param array $f array of filter params from $this->initFilter, should contain sortby, sortdir
     */
    public function setListSorting() {

        #default sorting
        list($sortdef_field, $sortdef_dir) = Utils::qw($this->list_sortdef);
        if ( $this->list_filter['sortby'] == '') $this->list_filter['sortby'] = $sortdef_field;
        if ( $this->list_filter['sortdir']!='desc' && $this->list_filter['sortdir']!='asc') $this->list_filter['sortdir'] = $sortdef_dir;

        $orderby = trim($this->list_sortmap[ $this->list_filter['sortby'] ]);
        if (!$orderby) throw new Exception('No orderby defined for ['. $this->list_filter['sortby'] .']');

        if ($this->list_filter['sortdir']=='desc'){
            #if sortdir is desc, i.e. opposite to default - invert order for orderby fields
            #go thru each order field
            $aorderby = explode(',', $orderby);
            foreach ($aorderby as $k => $field_dir) {
                list($field, $order) = preg_split('/\s+/', trim($field_dir));
                if ($order=='desc'){
                    $order='asc';
                }else{
                    $order='desc';
                }
                $aorderby[$k]="$field $order";
            }
            $orderby = implode(', ', $aorderby);
        }

        $this->list_orderby=$orderby;
    }

    /**
     * add to $this->list_where search conditions from $this->list_filter['s'] and based on fields in $this->search_fields
     */
    public function setListSearch() {
        #$this->list_where =' 1=1 '; #override initial in child if necessary

        $s = trim($this->list_filter['s']);
        if ( strlen($s) && $this->search_fields){
            $like_quoted=$this->db->quote('%'.$s.'%');
            $exact_quoted=$this->db->quote($s);

            $afields = Utils::qw($this->search_fields);
            foreach ($afields as $key => $fieldsand) {
                $afieldsand = explode(',', $fieldsand);

                foreach ($afieldsand as $key2 => $fand) {
                    if (preg_match("/^\!/", $fand)){
                        $fand=preg_replace("/^\!/", "", $fand);
                        $afieldsand[$key2]= $fand." = ".$exact_quoted;
                    }else{
                        $afieldsand[$key2]= $fand." LIKE ".$like_quoted;
                    }
                }
                $afields[$key] = implode(' and ', $afieldsand);
            }

            $this->list_where .= ' and ('.implode(' or ', $afields).')';
        }

        #if related id and field name set - filter on it
        if ($this->related_id>'' && $this->related_field_name){
            $this->list_where .= ' and '.$this->db->quote_ident($this->related_field_name).'='.$this->db->quote($this->related_id);
        }
    }

    /**
     * perform 2 queries to get list of rows
     * @return int $this->list_count count of rows obtained from db
     * @return array of arrays $this->list_rows list of rows
     * @return string $this->list_pager pager from FormUtils::getPager
     */
    public function getListRows() {
        $this->list_count = $this->db->value("select count(*) from {$this->list_view} where " . $this->list_where);
        if ($this->list_count){
            $offset = $this->list_filter['pagenum']*$this->list_filter['pagesize'];
            $limit  = $this->list_filter['pagesize'];

            $sql = "SELECT * FROM {$this->list_view} WHERE {$this->list_where} ORDER BY {$this->list_orderby} LIMIT {$offset}, {$limit}";
            $this->list_rows = $this->db->arr($sql);
            $this->list_pager = FormUtils::getPager($this->list_count, $this->list_filter['pagenum'], $this->list_filter['pagesize']);
        }else{
            $this->list_rows = array();
            $this->list_pager = array();
        }

        #if related_id defined - add it to each row
        if ($this->related_id>''){
            Utils::arrayInject($this->list_rows, array('related_id' => $this->related_id));
        }

        //add/modify rows from db - use in override child class
        /*
        foreach ($this->list_rows as $k => $row) {
            $this->list_rows[$k]['field'] = 'value';
        }
        */
    }

    /**
     * prepare and return itemdb for save to db
     * called from SaveAction()
     * using save_fields and save_fields_checkboxes
     * override in child class if more modifications is necessary
     *
     * @param integer $id   item id, could be 0 for new item
     * @param array   $item fields from the form
     */
    public function getSaveFields($id, $item){
        #load old record if necessary
        #$item_old = $this->model->one($id);

        $itemdb = FormUtils::filter($item, $this->save_fields);
        FormUtils::filterCheckboxes($itemdb, $item, $this->save_fields_checkboxes);

        return $itemdb;
    }

    /**
     * validate required fields are non-empty and set global ERR[field] and ERR[REQ] values in case of errors
     * also set global ERR[REQUIRED]=true in case of validation error
     * @param  array $item    fields/values
     * @param  array or space-separated string $afields field names required to be non-empty (trim used)
     * @return boolean        true if all required field names non-empty
     */
    public function validateRequired($item, $afields) {
        $result=true;

        if (!is_array($item)) $item=array();
        if (!is_array($afields)){
            $afields = Utils::qw($afields);
        }

        foreach ($afields as $fld) {
            if ($fld>'' && (!array_key_exists($fld, $item) || !strlen(trim($item[$fld])) ) ) {
                $result=false;
                $this->setError($fld);
            }
        }
        if (!$result) $this->setError('REQUIRED', true);

        return $result;
    }

    //check validation result
    //optional $result param - to use from external validation check
    //throw ValidationException exception if global ERR non-empty
    //also set global ERR[INVALID] if ERR non-empty, but ERR[REQUIRED] not true
    public function validateCheckResult($result=true) {
        if ($this->fw->GLOBAL['ERR']['REQUIRED']){
            $result=false;
        }
        if ( is_array($this->fw->GLOBAL['ERR']) && !$this->fw->GLOBAL['ERR']['REQUIRED'] ){
            $this->setError('INVALID', true);
            $result=false;
        }
        if (!$result) throw new ValidationException('');
    }

    public function setFormError($err_msg){
        $this->fw->GLOBAL['err_msg']=$err_msg;
    }

    /**
     * add or update records in db ($this->model)
     * @param  int $id          id of the record
     * @param  array $fields    hash of field/values
     * @return int              new autoincrement id (if added) or old id (if update). Also set fw->flash
     */
    public function modelAddOrUpdate($id, $fields){
        if ($id>0){
            $this->model->update($id, $fields);
            $this->fw->flash("record_updated", 1);
        }else{
            $id=$this->model->add($fields);
            $this->fw->flash("record_added", 1);
        }
        return $id;
    }

    /**
     * return URL for location after successful Save action
     * basic rule: after save we return to edit form screen. Or, if return_url set, to the return_url
     *
     * @param  integer $id new or updated form id
     * @param  string $explicit - 'index', 'form' - explicit return to Index, ShowForm without auto-detection and skipping return_url
     * @return string     url
     */
    public function getReturnLocation($id=null, $explicit=''){
        $result='';

        #if no id passed - basically return to list url, if passed - return to edit url
        if (is_null($id) || $explicit=='index'){
            $base_url = $this->base_url;
        }else{
            $base_url = $this->base_url.'/'.$id.'/edit';
        }

        if ($this->return_url && !$explicit){
            if ($this->fw->isJsonExpected()){
                //if json - it's usually autosave - don't redirect back to return url yet
                $result = $base_url.'?return_url='.Utils::urlescape($this->return_url).($this->related_id?'&related_id='.$this->related_id:'');
            }else{
                $result = $this->return_url;
            }
        }else{
            $result = $base_url.($this->related_id?'?related_id='.$this->related_id:'');
        }

        return $result;
    }

    /**
     * standard processing after SaveAction()
     * usage: return $this->afterSave($success, $location, $id, $is_new);
     * @param  boolean $success  save success or not
     * @param  string  $location client redirect to this location
     * @param  integer $id       old or new id
     * @param  boolean $is_new   new id or not
     * @return ps array of json response or none (will be redirected to new location or ShowForm)
     */
    public function afterSave($success=true, $location='', $id=0, $is_new=false){
        if ($this->fw->isJsonExpected()){
            return array('_json'=>array(
                'success'   => $success,
                'err_msg'   => $this->fw->GLOBAL['err_msg'],
                'location'  => $location,
                'id'        => $id,
                'is_new'    => $is_new,
                #TODO - add ERR field errors here
            ));
        }else{
            #if save success - return redirect
            #if save failed - return back to add/edit form
            if ($success){
                fw::redirect($location);
            }else{
                $this->routeRedirect("ShowForm");
            }
        }
    }

    ######################### Default Actions

    public function IndexAction() {
        rw("in Base Fw Controller IndexAction");
        #fw->parser();
    }

}//end of class

?>
