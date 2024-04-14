<?php
/*
Base Fw Controller class

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
 */

abstract class FwController {
    //overridable
    const access_level = null; //access level for the controller. $CONFIG['ACCESS_LEVELS'] overrides this.
    //Default=null - use config. If not set in config - all users can access this controller (even not logged)
    const route_default_action = ''; //empty, "index", "show" - default action for controller, dispatcher will use it if no requested action found
    //if no default action set - this is special case action - this mean action should be got form REST's 'id'

    public $base_url;                   //base url for the controller
    public $model_name; //default model name for the controller

    public $list_sortdef = 'id asc'; //default sorting - req param name, asc|desc direction
    public $list_sortmap = array( //sorting map: req param name => sql field name(s) asc|desc direction
                                  'id'       => 'id',
                                  'iname'    => 'iname',
                                  'add_time' => 'add_time',
    );
    public $search_fields = 'iname idesc'; //space-separated, fields to search via $list_filter['s'], ! - means exact match, not "like"
    //format: 'field1 field2,!field3 field4' => field1 LIKE '%$s%' or (field2 LIKE '%$s%' and field3='$s') or field4 LIKE '%$s%'

    public $form_new_defaults = array(); //defaults for the fields in new form
    public $required_fields = '';           //optional, default required fields, space-separated
    public $save_fields; //fields to save from the form to db, space-separated
    public $save_fields_checkboxes; //checkboxes fields to save from the form to db, qw string: "field|def_value field2|def_value2" or "field field2" (def_value=1 in this case)
    public $save_fields_nullable;           //nullable fields that should be set to null in db if form submit as ''

    //not overridable
    public $fw; //current app/framework object
    public $model; //default model for the controller
    protected $config = array();        // controller config, loaded from template dir/config.json
    public $list_view; // table or view name to selecte from for the list screen
    public $list_orderby; // orderby for the list screen
    public $list_filter; // filter values for the list screen
    public $list_where = ' 1=1 '; // where to use in list sql, default all (see setListSearch() )
    public $list_count; // count of list rows returned from db
    public $list_rows; // list rows returned from db (array of hashes)
    public $list_pager; // pager for the list from FormUtils::getPager
    public $return_url; // url to return after SaveAction successfully completed, passed via request
    public $related_id; // related id, passed via request. Controller should limit view to items related to this id
    public $related_field_name; // if set and $related_id passed - list will be filtered on this field

    #support of dynamic controller and customizable view list
    protected $is_dynamic_index = false;    // true if controller has dynamic IndexAction, then define below:
    protected $view_list_defaults = '';       // qw list of default columns
    protected $view_list_map = array();  // list of all available columns fieldname|visiblename
    protected $view_list_custom = '';       // array or qw list of custom-formatted fields for the list_table

    protected $is_dynamic_show = false;    // true if controller has dynamic ShowAction, requires "show_fields" to be defined in config.json
    protected $is_dynamic_showform = false;    // true if controller has dynamic ShowFormAction, requires "showform_fields" to be defined in config.json

    protected $db;

    public function __construct() {
        $this->fw = fw::i();
        $this->db = $this->fw->db;

        $this->return_url = reqs('return_url');
        $this->related_id = reqs('related_id');

        if ($this->model_name) {
            $this->model = fw::model($this->model_name);
        }
    }

    public function loadControllerConfig($config_filename = 'config.json') {
        $conf_file0 = strtolower($this->base_url) . '/' . $config_filename;
        $conf_file  = $this->fw->config->SITE_TEMPLATES . $conf_file0;
        if (!file_exists($conf_file)) {
            throw new ApplicationException("Controller Config file not found in templates: $conf_file");
        }

        $this->config = json_decode(file_get_contents($conf_file), true);
        if (is_null($this->config) || !$this->config) {
            throw new ApplicationException("Controller Config is invalid, check json in templates: $conf_file0");
        }
        #logger("loaded config:", $this->config);

        #fill up controller params
        $this->model = fw::model($this->config["model"]);

        $this->required_fields = $this->config["required_fields"] ?? '';

        #save_fields could be defined as qw string or array - check and convert
        if (is_array($this->config["save_fields"])) {
            $this->save_fields = Utils::qwRevert($this->config["save_fields"]); #not optimal, but simplest for now
        } else {
            $this->save_fields = $this->config["save_fields"] ?? '';
        }

        #save_fields_checkboxes could be defined as qw string - check and convert
        if (is_array($this->config["save_fields_checkboxes"])) {
            $this->save_fields_checkboxes = Utils::qhRevert($this->config["save_fields_checkboxes"]); #not optimal, but simplest for now
        } else {
            $this->save_fields_checkboxes = $this->config["save_fields_checkboxes"] ?? '';
        }

        #save_fields_nullable could be defined as qw string - check and convert
        if (is_array($this->config["save_fields_nullable"])) {
            $this->save_fields_nullable = Utils::qwRevert($this->config["save_fields_nullable"]); #not optimal, but simplest for now
        } else {
            $this->save_fields_nullable = $this->config["save_fields_nullable"] ?? '';
        }

        $this->search_fields      = $this->config["search_fields"] ?? '';
        $this->list_sortdef       = $this->config["list_sortdef"] ?? '';
        $this->list_sortmap       = $this->config["list_sortmap"] ?? '';
        $this->related_field_name = $this->config["related_field_name"] ?? '';
        $this->list_view          = $this->config["list_view"] ?? '';
        $this->is_dynamic_index   = $this->config["is_dynamic_index"] ?? false;
        if ($this->is_dynamic_index) {
            #Whoah! list view is dynamic
            $this->view_list_defaults = $this->config["view_list_defaults"] ?? '';

            #save_fields_nullable could be defined as qw string - check and convert
            if (is_array($this->config["view_list_map"])) {
                $this->view_list_map = $this->config["view_list_map"]; #not optimal, but simplest for now
            } else {
                $this->view_list_map = Utils::qh($this->config["view_list_map"] ?? '');
            }

            $this->view_list_custom = $this->config["view_list_custom"] ?? '';

            $this->list_sortmap = $this->getViewListSortmap(); #just add all fields from view_list_map
            if (!$this->search_fields) {
                #just search in all visible fields if no specific fields defined
                $this->search_fields = $this->getViewListUserFields();
            }
        }

        $this->is_dynamic_show     = $this->config["is_dynamic_show"] ?? false;
        $this->is_dynamic_showform = $this->config["is_dynamic_showform"] ?? false;
    }

    ############### helpers - shortcuts from fw
    public function checkXSS() {
        $this->fw->checkXSS();
    }

    public function routeRedirect($action, $controller = null, $args = null) {
        $this->fw->routeRedirect($action, $controller, $args);
    }

    //add fields name to form error hash
    public function setError($field_name, $error_type = true) {
        $this->fw->GLOBAL['ERR'][$field_name] = $error_type;
    }

    #get filter saved in session
    #if request param 'dofilter' passed - session filters cleaned
    #get filter values from request and overwrite saved in session
    #save back to session and return
    public function initFilter() {
        #each filter remembered in session linking to controller.action
        $session_key = '_filter_' . $this->fw->GLOBAL['controller.action'];
        $sfilter     = $_SESSION[$session_key] ?? [];

        $f = req('f');
        if (!is_array($f)) {
            $f = array();
        }

        #if not forced filter
        if (!reqs('dofilter')) {
            $f = array_merge($sfilter, $f);
        }

        #paging
        if (!preg_match("/^\d+$/", $f['pagenum'] ?? '')) {
            $f['pagenum'] = 0;
        }

        if (!preg_match("/^\d+$/", $f['pagesize'] ?? '')) {
            $f['pagesize'] = $this->fw->config->MAX_PAGE_ITEMS;
        }

        #@session_start();
        #save in session for later use
        $_SESSION[$session_key] = $f;
        #@session_write_close();

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
        if (empty($this->list_filter['sortby'])) {
            $this->list_filter['sortby'] = $sortdef_field;
        }

        $sortdir = $this->list_filter['sortdir'] ?? '';
        if ($sortdir != 'desc' && $sortdir != 'asc') {
            $this->list_filter['sortdir'] = $sortdef_dir;
        }

        $orderby = trim($this->list_sortmap[$this->list_filter['sortby']]);
        if (!$orderby) {
            throw new Exception('No orderby defined for [' . $this->list_filter['sortby'] . ']');
        }

        if ($this->list_filter['sortdir'] == 'desc') {
            #if sortdir is desc, i.e. opposite to default - invert order for orderby fields
            #go thru each order field
            $aorderby = explode(',', $orderby);
            foreach ($aorderby as $k => $field_dir) {
                $arr   = preg_split('/\s+/', trim($field_dir));
                $field = $arr[0];
                $order = $arr[1] ?? '';
                if ($order == 'desc') {
                    $order = 'asc';
                } else {
                    $order = 'desc';
                }
                $aorderby[$k] = "$field $order";
            }
            $orderby = implode(', ', $aorderby);
        }

        $this->list_orderby = $orderby;
    }

    /**
     * add to $this->list_where search conditions from $this->list_filter['s'] and based on fields in $this->search_fields
     */
    public function setListSearch() {
        #$this->list_where =' 1=1 '; #override initial in child if necessary

        $s = trim($this->list_filter['s'] ?? '');
        if (strlen($s) && $this->search_fields) {
            $like_quoted_both = $this->db->quote('%' . $s . '%');
            $like_quoted      = $this->db->quote($s . '%');
            $exact_quoted     = $this->db->quote($s);

            $afields = Utils::qw($this->search_fields);
            foreach ($afields as $key => $fieldsand) {
                $afieldsand = explode(',', $fieldsand);

                foreach ($afieldsand as $key2 => $fand) {
                    if (preg_match("/^\!/", $fand)) {
                        $fand              = preg_replace("/^\!/", "", $fand);
                        $afieldsand[$key2] = $fand . " = " . $exact_quoted;
                    } elseif (preg_match("/^\*/", $fand)) {
                        $fand              = preg_replace("/^\*/", "", $fand);
                        $afieldsand[$key2] = $fand . " LIKE " . $like_quoted_both;
                    } else {
                        $afieldsand[$key2] = $fand . " LIKE " . $like_quoted;
                    }
                }
                $afields[$key] = implode(' and ', $afieldsand);
            }

            $this->list_where .= ' and (' . implode(' or ', $afields) . ')';
        }

        if (isset($this->list_filter["userlist"])) {
            $this->list_where .= " and id IN (SELECT ti.item_id FROM " . UserLists::i()->table_items . " ti WHERE ti.user_lists_id=" . dbqi($this->list_filter["userlist"]) . " and ti.add_users_id=" . $this->fw->userId() . " ) ";
        }

        #if related id and field name set - filter on it
        if ($this->related_id > '' && $this->related_field_name) {
            $this->list_where .= ' and ' . $this->db->quote_ident($this->related_field_name) . '=' . $this->db->quote($this->related_id);
        }

        $this->setListSearchAdvanced();
    }

    /**
     * set list_where based on search[] filter
     */
    public function setListSearchAdvanced() {
        $hsearch = reqh("search");
        foreach ($hsearch as $fieldname => $value) {
            if ($value > '' && (!$this->is_dynamic_index || array_key_exists($fieldname, $this->view_list_map))) {
                $this->list_where .= " and " . $this->db->qid($fieldname) . " LIKE " . dbq("%" . $value . "%");
            }
        }
    }

    /**
     * set list_where filter based on status filter:
     * - if status not set - filter our deleted (i.e. show all)
     * - if status set - filter by status, but if status=127 (deleted) only allow to see deleted by admins
     */
    public function setListSearchStatus() {
        if ($this->model && strlen($this->model->field_status)) {
            if (isset($this->list_filter['status'])) {
                $status = intval($this->list_filter['status']);
                #if want to see trashed and not admin - just show active
                if ($status == 127 && !Users::i()->isAccessLevel(Users::ACL_ADMIN)) {
                    $status = 0;
                }
                $this->list_where .= " and " . $this->db->quote_ident($this->model->field_status) . "=" . $this->db->quote($status);
            } else {
                $this->list_where .= " and " . $this->db->quote_ident($this->model->field_status) . "<>127"; #by default - show all non-deleted
            }
        }
    }

    /**
     * perform 2 queries to get list of rows
     * @return int $this->list_count count of rows obtained from db
     * @return array of arrays $this->list_rows list of rows
     * @return string $this->list_pager pager from FormUtils::getPager
     */
    public function getListRows() {
        $this->list_count = $this->db->valuep("SELECT count(*) FROM {$this->list_view} WHERE " . $this->list_where);
        if ($this->list_count) {
            $offset = $this->list_filter['pagenum'] * $this->list_filter['pagesize'];
            $limit  = $this->list_filter['pagesize'];

            $sql              = "SELECT * FROM {$this->list_view} WHERE {$this->list_where} ORDER BY {$this->list_orderby} LIMIT {$offset}, {$limit}";
            $this->list_rows  = $this->db->arrp($sql);
            $this->list_pager = FormUtils::getPager($this->list_count, $this->list_filter['pagenum'], $this->list_filter['pagesize']);
        } else {
            $this->list_rows  = array();
            $this->list_pager = array();
        }

        #if related_id defined - add it to each row
        if ($this->related_id > '') {
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
     * @param integer $id item id, could be 0 for new item
     * @param array $item fields from the form
     */
    public function getSaveFields($id, $item) {
        #load old record if necessary
        #$item_old = $this->model->one($id);

        $itemdb = FormUtils::filter($item, $this->save_fields);
        FormUtils::filterCheckboxes($itemdb, $item, $this->save_fields_checkboxes);
        FormUtils::filterNullable($itemdb, $this->save_fields_nullable);

        return $itemdb;
    }

    /**
     * validate required fields are non-empty and set global ERR[field] and ERR[REQ] values in case of errors
     * also set global ERR[REQUIRED]=true in case of validation error
     * @param array $item fields/values
     * @param array or space-separated string $afields field names required to be non-empty (trim used)
     * @return boolean        true if all required field names non-empty
     */
    public function validateRequired($item, $afields) {
        $result = true;

        if (!is_array($item)) {
            $item = array();
        }

        if (!is_array($afields)) {
            $afields = Utils::qw($afields);
        }

        foreach ($afields as $fld) {
            if ($fld > '' && (!array_key_exists($fld, $item) || !strlen(trim($item[$fld])))) {
                $result = false;
                $this->setError($fld);
            }
        }
        if (!$result) {
            $this->setError('REQUIRED', true);
        }

        return $result;
    }

    //check validation result
    //optional $result param - to use from external validation check
    //throw ValidationException exception if global ERR non-empty
    //also set global ERR[INVALID] if ERR non-empty, but ERR[REQUIRED] not true
    public function validateCheckResult($result = true) {
        if (isset($this->fw->GLOBAL['ERR']['REQUIRED'])) {
            logger('validateCheckResult required:', $this->fw->GLOBAL['ERR']);
            $result = false;
        }

        if (is_array($this->fw->GLOBAL['ERR']) && !empty($this->fw->GLOBAL['ERR']) && !$this->fw->GLOBAL['ERR']['REQUIRED']) {
            logger('validateCheckResult invalid:', $this->fw->GLOBAL['ERR']);
            $this->setError('INVALID', true);
            $result = false;
        }
        if (!$result) {
            throw new ValidationException('');
        }
    }

    public function setFormError($err_msg): void {
        $this->fw->GLOBAL['err_msg'] = $err_msg;
    }

    /**
     * add or update records in db ($this->model)
     * @param int $id id of the record
     * @param array $fields hash of field/values
     * @return int              new autoincrement id (if added) or old id (if update). Also set fw->flash
     */
    public function modelAddOrUpdate($id, $fields) {
        if ($id > 0) {
            $this->model->update($id, $fields);
            $this->fw->flash("record_updated", 1);
        } else {
            $id = $this->model->add($fields);
            $this->fw->flash("record_added", 1);
        }
        return $id;
    }

    /**
     * return URL for location after successful Save action
     * basic rule: after save we return to edit form screen. Or, if return_url set, to the return_url
     *
     * @param integer $id new or updated form id
     * @param string $explicit - 'index', 'form' - explicit return to Index, ShowForm without auto-detection and skipping return_url
     * @return string     url
     */
    public function getReturnLocation($id = null, $explicit = '') {
        $result = '';

        #if no id passed - basically return to list url, if passed - return to edit url
        if (is_null($id) || $explicit == 'index') {
            $base_url = $this->base_url;
        } else {
            $base_url = $this->base_url . '/' . $id . '/edit';
        }
        #logger('getReturnLocation:', $id, ', ex=' . $explicit);
        #logger($base_url);

        if ($this->return_url && !$explicit) {
            if ($this->fw->isJsonExpected()) {
                //if json - it's usually autosave - don't redirect back to return url yet
                $result = $base_url . '?return_url=' . Utils::urlescape($this->return_url) . ($this->related_id ? '&related_id=' . $this->related_id : '');
            } else {
                $result = $this->return_url;
            }
        } else {
            $result = $base_url . (strlen($this->related_id) ? '?related_id=' . $this->related_id : '');
        }
        #logger('result=', $result);

        return $result;
    }

    /**
     * standard processing after SaveAction()
     * usage: return $this->afterSave($success, $location, $id, $is_new);
     * @param boolean $success save success or not
     * @param string $location client redirect to this location
     * @param integer $id old or new id
     * @param boolean $is_new new id or not
     * @return ps array of json response or none (will be redirected to new location or ShowForm)
     */
    public function afterSave($success = true, $location = '', $id = 0, $is_new = false) {
        if ($this->fw->isJsonExpected()) {
            return array('_json' => array(
                'success'  => $success,
                'err_msg'  => $this->fw->GLOBAL['err_msg'],
                'location' => $location,
                'id'       => $id,
                'is_new'   => $is_new,
                #TODO - add ERR field errors here
            ));
        } else {
            #if save success - return redirect
            #if save failed - return back to add/edit form
            if ($success) {
                fw::redirect($location);
            } else {
                $this->routeRedirect("ShowForm");
            }
        }
    }


    ######################### dynamic controller support

    /**
     * as arraylist of hashtables {field_name=>, field_name_visible=> [, is_checked=>true]} in right order
     * @param string $fields qw-string, if fields defined - show fields only
     * @param boolean $is_all if is_all true - then show all fields (not only from fields param)
     * @return array           array of hashtables
     */
    public function getViewListArr($fields = '', $is_all = false) {
        $result = array();

        #if fields defined - first show these fields, then the rest
        $fields_added = array();
        if ($fields > '') {
            foreach (Utils::qw($fields) as $key => $fieldname) {
                $result[]                 = array(
                    'field_name'         => $fieldname,
                    'field_name_visible' => $this->view_list_map[$fieldname],
                    'is_checked'         => true,
                );
                $fields_added[$fieldname] = true;
            }
        }

        if ($is_all) {
            #rest/all fields
            foreach ($this->view_list_map as $key => $value) {
                if (array_key_exists($key, $fields_added)) {
                    continue;
                }
                $result[] = array(
                    'field_name'         => $key,
                    'field_name_visible' => $this->view_list_map[$key],
                );
            }
        }

        return $result;
    }

    public function getViewListSortmap($value = '') {
        $result = array();
        foreach ($this->view_list_map as $fieldname => $value) {
            $result[$fieldname] = $fieldname;
        }
        return $result;
    }

    public function getViewListUserFields($value = '') {
        $item = UserViews::i()->oneByIcode($this->base_url); #base_url is screen identifier
        return $item['fields'] > '' ? $item['fields'] : $this->view_list_defaults;
    }

    /**
     * add to $ps:
     * headers
     * headers_search
     * depends on $ps["list_rows"]
     * usage:
     * $this->setViewList($ps, reqh("search"))
     *
     * @param [type] $ps      [description]
     * @param [type] $hsearch [description]
     */
    public function setViewList(&$ps, $hsearch) {
        $fields = $this->getViewListUserFields();

        $headers = $this->getViewListArr($fields);
        #add search from user's submit
        foreach ($headers as $key => $header) {
            $headers[$key]["search_value"] = $hsearch[$header["field_name"]] ?? '';
        }

        $ps["headers"]        = $headers;
        $ps["headers_search"] = $headers;

        $hcustom = Utils::qh($this->view_list_custom);

        #dynamic cols
        $fields_qw = Utils::qw($fields);
        foreach ($ps["list_rows"] as $key => $row) {
            $cols = array();
            foreach ($fields_qw as $fieldname) {
                $cols[] = array(
                    'row'        => $row,
                    'field_name' => $fieldname,
                    'data'       => $row[$fieldname],
                    'is_custom'  => array_key_exists($fieldname, $hcustom),
                );
            }
            $ps["list_rows"][$key]['cols'] = $cols;
        }
    }

    ######################### Default Actions

    public function IndexAction() {
        rw("in Base Fw Controller IndexAction");
        #fw->parser();
    }
} //end of class
