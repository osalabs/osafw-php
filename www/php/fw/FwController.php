<?php
/*
 Base Fw Controller class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2015 Oleg Savchuk www.osalabs.com
*/

abstract class FwController {
    //overridable
    const route_default_action = ''; #index, show
    public $model_name; //default model name for the controller
    public $table_name; #$model_name is required in child class

    public $list_sortdef = 'id asc';    //default sorting - req param name, asc|desc direction
    public $list_sortmap = array(       //sorting map: req param name => sql field name(s) asc|desc direction
                        'id'            => 'id',
                        'iname'         => 'iname',
                        'add_time'      => 'add_time',
                        );
    public $search_fields = 'iname idesc';  //fields to search via $s$list_filter['s'], ! - means exact match, not "like"
                                            //format: 'field1 field2,!field3 field4' => field1 LIKE '%$s%' or (field2 LIKE '%$s%' and field3='$s') or field4 LIKE '%$s%'

    //not overridable
    public $fw;  //current app/framework object
    public $model; //default model for the controller
    public $list_orderby;               // orderby for the list screen
    public $list_filter;                // filter values for the list screen
    public $list_where;                 // where to use in list sql
    public $list_count;                 // count of list rows returned from db
    public $list_rows;                  // list rows returned from db (array of hashes)
    public $list_pager;                 // pager for the list from FormUtils::get_pager

    public function __construct() {
        $this->fw = fw::i();

        if ($this->model_name){
            $this->model = fw::model($this->model_name);
        }
    }

    ############### helpers - shortcuts from fw
    public function route_redirect($action, $controller=NULL, $args=NULL) {
        $this->fw->route_redirect($action, $controller, $args);
    }

    //add fields name to form error hash
    public function ferr($field_name, $error_type=true) {
        $this->fw->G['ERR'][$field_name]=$error_type;
    }

    #get filter saved in session
    #if request param 'dofilter' passed - session filters cleaned
    #get filter values from request and overwrite saved in session
    #save back to session and return
    public function get_filter(){
        global $CONFIG;

        #each filter remembered in session linking to controller.action
        $session_key = '_filter_'.$this->fw->G['controller.action'];
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
        if ( !preg_match("/^\d+$/", $f['pagesize']) ) $f['pagesize']=$CONFIG['MAX_PAGE_ITEMS'];

        #save in session for later use
        $_SESSION[ $session_key ] = $f;

        $this->list_filter = $f;
        return $f;
    }

    /**
     * set list sorting fields - list_orderby and list_orderdir according to $this->list_filter filter
     * @param array $f array of filter params from $this->get_filter, should contain sortby, sortdir
     */
    public function set_list_sorting() {

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
    public function set_list_search() {
        $s = trim($this->list_filter['s']);
        if ( strlen($s) && $this->search_fields){
            $like_quoted=dbq('%'.$s.'%');
            $exact_quoted=dbq($s);

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
    }

    /**
     * perform 2 queries to get list of rows
     * @return int $this->list_count count of rows obtained from db
     * @return array of arrays $this->list_rows list of rows
     * @return string $this->list_pager pager from FormUtils::get_pager
     */
    public function get_list_rows() {
        $this->list_count = db_value("select count(*) from $this->table_name where " . $this->list_where);
        if ($this->list_count){
            $offset = $this->list_filter['pagenum']*$this->list_filter['pagesize'];
            $limit  = $this->list_filter['pagesize'];

            $sql = "SELECT * FROM $this->table_name WHERE $this->list_where ORDER BY $this->list_orderby LIMIT $offset, $limit";
            $this->list_rows = db_array($sql);
            $this->list_pager = FormUtils::get_pager($this->list_count, $this->list_filter['pagenum'], $this->list_filter['pagesize']);
        }else{
            $this->list_rows = array();
            $this->list_pager = array();
        }
    }


    /**
     * validate required fields are non-empty and set global ERR[field] and ERR[REQ] values in case of errors
     * also set global ERR[REQUIRED]=true in case of validation error
     * @param  array $item    fields/values
     * @param  array or space-separated string $afields field names required to be non-empty (trim used)
     * @return boolean        true if all required field names non-empty
     */
    public function validate_required($item, $afields) {
        $result=true;

        if (!is_array($item)) $item=array();
        if (!is_array($afields)){
            $afields = Utils::qw($afields);
        }

        foreach ($afields as $fld) {
            if (!array_key_exists($fld, $item) || !strlen(trim($item[$fld])) ){
                $result=false;
                $this->ferr($fld);
            }
        }
        if (!$result) $this->ferr('REQUIRED', true);

        return $result;
    }

    //check validation result
    //optional $result param - to use from external validation check
    //throw ValidationException exception if global ERR non-empty
    //also set global ERR[INVALID] if ERR non-empty, but ERR[REQUIRED] not true
    public function validate_check_result($result=true) {
        if ($this->fw->G['ERR']['REQUIRED']){
            $result=false;
        }
        if ( count($this->fw->G['ERR']) && !$this->fw->G['ERR']['REQUIRED'] ){
            $this->ferr('INVALID', true);
            $result=false;
        }
        if (!$result) throw new ValidationException('');
    }

    public function set_form_error($err_msg){
        $this->fw->G['err_msg']=$err_msg;
    }

    /**
     * add or update records in db ($this->model)
     * @param  int $id          id of the record
     * @param  array $fields    hash of field/values
     * @return int              new autoincrement id (if added) or old id (if update). Also set fw->flash
     */
    public function model_add_or_update($id, $fields){
        if ($id>0){
            $this->model->update($id, $fields);
            $this->fw->flash("record_updated", 1);
        }else{
            $id=$this->model->add($fields);
            $this->fw->flash("record_added", 1);
        }
        return $id;
    }

    ######################### Default Actions

    public function IndexAction() {
        rw("in Base Fw Controller IndexAction");
        #fw->parser();
    }

}//end of class

?>